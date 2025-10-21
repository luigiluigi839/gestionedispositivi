<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


session_start();
require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$id_utente_loggato = $_SESSION['user_id'] ?? null;

// MODIFICATO: Controllo sul permesso specifico 'ritiro_dispositivi'
if (!isset($id_utente_loggato) || (!in_array('ritiro_dispositivi', $user_permessi) && !$is_superuser)) {
    header('Location: ../Pages/dashboard.php?error=Accesso non autorizzato');
    exit();
}

// Inizializzazione delle variabili
$message_list = [];
if (isset($_SESSION['rientro_messages'])) {
    $message_list = $_SESSION['rientro_messages'];
    unset($_SESSION['rientro_messages']);
}

// Recupera dati per i menu a tendina
try {
    $ubicazioni = $pdo->query("SELECT ID, Nome FROM Ubicazioni ORDER BY Nome")->fetchAll(PDO::FETCH_ASSOC);
    $stati = $pdo->query("SELECT ID, Nome FROM Stati ORDER BY Nome")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Errore fatale: " . $e->getMessage());
}

// Elaborazione del form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seriali_input = trim($_POST['seriali_input'] ?? '');
    $data_ritiro = $_POST['data_ritiro'];
    $nuova_ubicazione_id = $_POST['ubicazione'];
    $nuovo_stato_id = $_POST['stato'];
    $data_ultima_mod = date('Y-m-d');

    $message_list_local = [];
    $error_report = [];
    unset($_SESSION['error_report']);

    $seriali_grezzi = preg_split('/[\s,]+/', $seriali_input, -1, PREG_SPLIT_NO_EMPTY);
    
    if (empty($seriali_grezzi)) {
        $message_list_local[] = ['status' => 'error', 'text' => 'Nessun seriale inserito.'];
    } else {
        $seriali_scansionati_inrete = [];
        $seriale_map = []; // Mappa da Seriale_Inrete a seriale grezzo/originale

        // 1. Pre-validazione: Converte tutti i seriali scansionati in Seriale_Inrete e verifica l'esistenza
        foreach ($seriali_grezzi as $seriale) {
            $stmt_find = $pdo->prepare("SELECT Seriale_Inrete FROM Dispositivi WHERE Seriale = ? OR Seriale_Inrete = ?");
            $stmt_find->execute([$seriale, $seriale]);
            $dispositivo = $stmt_find->fetch();
            if ($dispositivo) {
                $seriale_inrete = $dispositivo['Seriale_Inrete'];
                $seriali_scansionati_inrete[] = $seriale_inrete;
                $seriale_map[$seriale_inrete] = $seriale;
            } else {
                $error_report[] = ['seriale' => $seriale, 'motivo' => 'Dispositivo non trovato.'];
            }
        }
        $seriali_scansionati_inrete = array_unique($seriali_scansionati_inrete);

        // 2. Controllo Integrità Bundle
        $seriali_da_processare = [];
        $bundle_processati = []; // Tiene traccia dei corpi macchina già validati

        foreach ($seriali_scansionati_inrete as $seriale_inrete) {
            $stmt_bundle = $pdo->prepare("SELECT CorpoMacchina_Seriale FROM Bundle_Dispositivi WHERE CorpoMacchina_Seriale = :id1 OR Accessorio_Seriale = :id2 LIMIT 1");
            $stmt_bundle->execute([':id1' => $seriale_inrete, ':id2' => $seriale_inrete]);
            $bundle_info = $stmt_bundle->fetch();

            if (!$bundle_info) {
                // Non fa parte di nessun bundle, è valido per il processo
                $seriali_da_processare[] = $seriale_inrete;
            } else {
                $corpo_macchina_id = $bundle_info['CorpoMacchina_Seriale'];
                if (in_array($corpo_macchina_id, $bundle_processati)) {
                    continue; // Bundle già controllato
                }

                // Recupera tutti i componenti del bundle
                $stmt_componenti = $pdo->prepare("SELECT Accessorio_Seriale FROM Bundle_Dispositivi WHERE CorpoMacchina_Seriale = :id");
                $stmt_componenti->execute([':id' => $corpo_macchina_id]);
                $componenti_bundle = $stmt_componenti->fetchAll(PDO::FETCH_COLUMN);
                $componenti_bundle[] = (int)$corpo_macchina_id; // Aggiunge il corpo macchina alla lista

                // Verifica se tutti i componenti sono stati scansionati
                $componenti_mancanti = array_diff($componenti_bundle, $seriali_scansionati_inrete);

                if (empty($componenti_mancanti)) {
                    // Bundle completo, aggiungi tutti i suoi componenti alla lista da processare
                    foreach ($componenti_bundle as $comp) {
                        $seriali_da_processare[] = $comp;
                    }
                } else {
                    // Bundle incompleto, genera errore per tutti i componenti scansionati di questo bundle
                    $mancanti_str = implode(', ', $componenti_mancanti);
                    $componenti_scansionati_del_bundle = array_intersect($componenti_bundle, $seriali_scansionati_inrete);
                    foreach ($componenti_scansionati_del_bundle as $comp_scansionato) {
                        $error_report[] = [
                            'seriale' => $seriale_map[$comp_scansionato], 
                            'motivo' => "Rientro fallito: bundle incompleto. Componenti mancanti: $mancanti_str"
                        ];
                    }
                }
                $bundle_processati[] = $corpo_macchina_id;
            }
        }
        $seriali_da_processare = array_unique($seriali_da_processare);
        
        // 3. Processa solo i seriali validi (singoli o bundle completi)
        if (!empty($seriali_da_processare)) {
            $pdo->beginTransaction();
            try {
                $count_success = 0;
                foreach ($seriali_da_processare as $seriale_rientro) {
                    // Controlla se già rientrato (sicurezza aggiuntiva)
                    $stmt_check = $pdo->prepare("SELECT Data_Ritiro FROM Spostamenti WHERE Dispositivo = ? ORDER BY Data_Install DESC LIMIT 1");
                    $stmt_check->execute([$seriale_rientro]);
                    $ultimo_spostamento = $stmt_check->fetch();

                    if ($ultimo_spostamento && $ultimo_spostamento['Data_Ritiro'] !== null) {
                        $error_report[] = ['seriale' => $seriale_map[$seriale_rientro], 'motivo' => 'Dispositivo già rientrato. Nessuna modifica.'];
                        continue;
                    }
                    
                    // Aggiorna Dispositivo
                    $stmt_update_disp = $pdo->prepare("UPDATE Dispositivi SET Ubicazione = :ubicazione, Stato = :stato, Utente_Ultima_Mod = :utente, Data_Ultima_Mod = :data WHERE Seriale_Inrete = :seriale");
                    $stmt_update_disp->execute([':ubicazione' => $nuova_ubicazione_id, ':stato' => $nuovo_stato_id, ':utente' => $id_utente_loggato, ':data' => $data_ultima_mod, ':seriale' => $seriale_rientro]);

                    // Aggiorna Spostamento
                    if ($ultimo_spostamento) {
                        $stmt_update_spost = $pdo->prepare("UPDATE Spostamenti SET Data_Ritiro = :data_ritiro, Utente_Ultima_Mod = :utente, Data_Ultima_Mod = :data WHERE Dispositivo = :seriale AND Data_Ritiro IS NULL");
                        $stmt_update_spost->execute([':data_ritiro' => $data_ritiro, ':utente' => $id_utente_loggato, ':data' => $data_ultima_mod, ':seriale' => $seriale_rientro]);
                    }
                    $count_success++;
                }
                $pdo->commit();
                if ($count_success > 0) {
                     $message_list_local[] = ['status' => 'success', 'text' => "$count_success dispositivi/componenti sono rientrati con successo."];
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message_list_local[] = ['status' => 'error', 'text' => 'Errore DB durante il processamento. Nessuna modifica salvata. Dettagli: ' . $e->getMessage()];
            }
        }
        
        // 4. Gestione finale dei messaggi e del report
        if (!empty($error_report)) {
            $message_list_local[] = ['status' => 'warning', 'text' => count($error_report) . " seriali hanno generato errori o avvisi. Scarica il report."];
            $_SESSION['error_report'] = $error_report;
        }
    }
    
    $_SESSION['rientro_messages'] = $message_list_local;
    header("Location: rientro_dispositivi.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Rientro Dispositivi</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_forms.css">
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="main-content">
    <div class="form-container">
        <h2>Registra Rientro Dispositivi</h2>

        <?php if (!empty($message_list)): ?>
            <div class="messages-container">
                <?php foreach ($message_list as $msg): ?>
                    <p class="message <?= htmlspecialchars($msg['status']) ?>"><?= htmlspecialchars($msg['text']) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_report']) && !empty($_SESSION['error_report'])): ?>
            <div class="form-group">
                 <a href="../PHP/scarica_report_errori.php" class="btn-download-report">Scarica Report Errori/Avvisi (.csv)</a>
            </div>
        <?php endif; ?>

        <form action="rientro_dispositivi.php" method="POST">
            <div class="form-group">
                <label for="seriali_input">Seriali Dispositivi (fisico o interno)</label>
                <textarea id="seriali_input" name="seriali_input" rows="10" placeholder="Inserisci o scansiona qui i seriali..."></textarea>
            </div>
            
            <div class="form-group">
                <label for="data_ritiro">Data di Rientro</label>
                <input type="date" id="data_ritiro" name="data_ritiro" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
                <label for="ubicazione">Imposta Nuova Ubicazione</label>
                <select id="ubicazione" name="ubicazione" required>
                    <option value="">Seleziona un'ubicazione...</option>
                    <?php foreach ($ubicazioni as $ubicazione): ?>
                        <option value="<?= htmlspecialchars($ubicazione['ID']) ?>"><?= htmlspecialchars($ubicazione['Nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="stato">Imposta Nuovo Stato</label>
                <select id="stato" name="stato" required>
                    <option value="">Seleziona uno stato...</option>
                    <?php foreach ($stati as $stato): ?>
                        <option value="<?= htmlspecialchars($stato['ID']) ?>"><?= htmlspecialchars($stato['Nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="submit-button">Registra Rientro</button>
        </form>
    </div>
</div>

<?php require_once '../PHP/footer.php'; ?>

</body>
</html>