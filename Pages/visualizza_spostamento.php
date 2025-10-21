<?php
session_start();
require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$id_utente_loggato = $_SESSION['user_id'];

// Controllo permesso per visualizzare la pagina
if (!isset($id_utente_loggato) || (!in_array('visualizza_spostamenti', $user_permessi) && !$is_superuser)) {
    header('Location: dashboard.php?error=Accesso non autorizzato');
    exit();
}

$id = $_GET['id'] ?? 0;
$message = '';
$status = 'error';

// Inizializzazione delle variabili per i dati
$spostamento = null;
$dispositivo_principale = null;
$bundle_devices = [];
$is_in_bundle = false;

if ($id > 0) {
    try {
        // 1. Recupera i dati dello spostamento e dell'utente che ha fatto l'ultima modifica
        $stmt = $pdo->prepare("SELECT s.*, CONCAT(u.Nome, ' ', u.Cognome) as UtenteModNome FROM Spostamenti s LEFT JOIN Utenti u ON s.Utente_Ultima_Mod = u.ID WHERE s.ID = :id");
        $stmt->execute([':id' => $id]);
        $spostamento = $stmt->fetch();

        if ($spostamento) {
            // 2. Recupera i dettagli del dispositivo principale associato allo spostamento
            $stmt_dev = $pdo->prepare("SELECT d.*, ma.Nome as Marca, mo.Nome as Modello FROM Dispositivi d LEFT JOIN Marche ma ON d.MarcaID = ma.ID LEFT JOIN Modelli mo ON d.ModelloID = mo.ID WHERE d.Seriale_Inrete = :id");
            $stmt_dev->execute([':id' => $spostamento['Dispositivo']]);
            $dispositivo_principale = $stmt_dev->fetch();

            // 3. Controlla se il dispositivo fa parte di un bundle
            $stmt_bundle_check = $pdo->prepare("SELECT CorpoMacchina_Seriale FROM Bundle_Dispositivi WHERE CorpoMacchina_Seriale = :id1 OR Accessorio_Seriale = :id2 LIMIT 1");
            $stmt_bundle_check->execute([':id1' => $spostamento['Dispositivo'], ':id2' => $spostamento['Dispositivo']]);
            $bundle_info = $stmt_bundle_check->fetch();
            $is_in_bundle = (bool)$bundle_info;

            if ($is_in_bundle) {
                // 4. Se è in un bundle, recupera tutti i dispositivi che ne fanno parte
                $corpo_macchina_id = $bundle_info['CorpoMacchina_Seriale'];
                $stmt_bundle_devs = $pdo->prepare("
                    SELECT d.Seriale, ma.Nome as Marca, mo.Nome as Modello, 'Dispositivo Principale' as Ruolo FROM Dispositivi d JOIN Marche ma ON d.MarcaID=ma.ID JOIN Modelli mo ON d.ModelloID=mo.ID WHERE d.Seriale_Inrete = :id1
                    UNION
                    SELECT d.Seriale, ma.Nome as Marca, mo.Nome as Modello, 'Accessorio' as Ruolo FROM Bundle_Dispositivi bd JOIN Dispositivi d ON bd.Accessorio_Seriale = d.Seriale_Inrete JOIN Marche ma ON d.MarcaID=ma.ID JOIN Modelli mo ON d.ModelloID=mo.ID WHERE bd.CorpoMacchina_Seriale = :id2");
                $stmt_bundle_devs->execute([':id1' => $corpo_macchina_id, ':id2' => $corpo_macchina_id]);
                $bundle_devices = $stmt_bundle_devs->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            $message = "Record di spostamento non trovato.";
        }
    } catch (PDOException $e) {
        $message = "Errore nel recupero dei dati: " . $e->getMessage();
    }
} else {
    $message = "ID dello spostamento non specificato.";
}

function formatDate($date) {
    return $date ? date('d/m/Y', strtotime($date)) : 'N/D';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Dettaglio Spostamento</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_cards.css">
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="card-container">
    <h2>Dettaglio Installazione #<?= htmlspecialchars($id) ?></h2>

    <?php if ($message): ?>
        <p class="message <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if ($spostamento): ?>
        <div class="card">
            <h3>Dati Installazione</h3>
            <p><strong>Azienda:</strong> <?= htmlspecialchars($spostamento['Azienda']) ?></p>
            <p><strong>Data Installazione:</strong> <?= formatDate($spostamento['Data_Install']) ?></p>
            <p><strong>Data Ritiro:</strong> <?= formatDate($spostamento['Data_Ritiro']) ?></p>
            <p><strong>Nolo/Cash:</strong> <?= htmlspecialchars($spostamento['Nolo_Cash'] ?? '-') ?></p>
            <p><strong>Assistenza:</strong> <?= htmlspecialchars($spostamento['Assistenza'] ?? '-') ?></p>
        </div>

        <div class="card">
            <h3>Dettagli Dispositivo</h3>
            <?php if ($dispositivo_principale): ?>
                <p><strong>Seriale:</strong> <?= htmlspecialchars($dispositivo_principale['Seriale']) ?></p>
                <p><strong>Marca:</strong> <?= htmlspecialchars($dispositivo_principale['Marca']) ?></p>
                <p><strong>Modello:</strong> <?= htmlspecialchars($dispositivo_principale['Modello']) ?></p>
            <?php else: ?>
                <p>Informazioni sul dispositivo non disponibili.</p>
            <?php endif; ?>
        </div>
        
        <?php if ($is_in_bundle && !empty($bundle_devices)): ?>
        <div class="card">
            <h3>Componenti del Bundle Installato</h3>
            <ul style="list-style-type: none; padding: 0;">
                <?php foreach($bundle_devices as $dev): ?>
                    <li><strong><?= htmlspecialchars($dev['Ruolo']) ?>:</strong> <?= htmlspecialchars($dev['Marca'] . ' ' . $dev['Modello']) ?> (S/N: <?= htmlspecialchars($dev['Seriale']) ?>)</li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="card">
            <h3>Note e Log</h3>
            <p><strong>Note:</strong></p>
            <p><?= nl2br(htmlspecialchars($spostamento['Note'] ?? 'Nessuna nota.')) ?></p>
            <hr style="margin: 15px 0;">
            <p><strong>Ultima Modifica:</strong> <?= formatDate($spostamento['Data_Ultima_Mod']) ?> da <?= htmlspecialchars($spostamento['UtenteModNome'] ?? 'N/D') ?></p>
        </div>

        <div class="button-group">
            <a href="dashboard_gestione_spostamenti.php" class="back-button">Torna alla Lista</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>