<?php
session_start();
require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$id_utente_loggato = $_SESSION['user_id'];

// Controllo dei permessi (visualizzazione o modifica)
if (!isset($id_utente_loggato) || (!in_array('visualizza_spostamenti', $user_permessi) && !in_array('modifica_spostamenti', $user_permessi) && !$is_superuser)) {
    header('Location: dashboard.php?error=Accesso non autorizzato');
    exit();
}

$aziende = $_SESSION['aziende_data'] ?? [];
$message = '';
$status = '';
if (isset($_SESSION['form_message'])) {
    $message = $_SESSION['form_message']['text'];
    $status = $_SESSION['form_message']['status'];
    unset($_SESSION['form_message']);
}

// Gestione POST per la modifica
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_spostamento'])) {
    if (!in_array('modifica_spostamenti', $user_permessi) && !$is_superuser) {
        $_SESSION['form_message'] = ['text' => 'Non hai i permessi per salvare le modifiche.', 'status' => 'error'];
        header('Location: modifica_spostamenti.php');
        exit();
    }

    $id_spostamento = $_POST['id_spostamento'];
    $azienda = trim($_POST['azienda']);
    $data_install = $_POST['data_install'];
    $data_ritiro = !empty($_POST['data_ritiro']) ? $_POST['data_ritiro'] : null;
    $nolo_cash = trim($_POST['nolo_cash']);
    $assistenza = trim($_POST['assistenza']);
    $note = trim($_POST['note']);
    $data_ultima_mod = date('Y-m-d');
    
    if ($data_ritiro && $data_ritiro < $data_install) {
        $message = "Errore: La data di ritiro non può essere precedente alla data di installazione.";
        $status = 'error';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt_orig = $pdo->prepare("SELECT Dispositivo, Azienda, Data_Install FROM Spostamenti WHERE ID = :id");
            $stmt_orig->execute([':id' => $id_spostamento]);
            $original_spostamento = $stmt_orig->fetch();
            $dispositivo_id_originale = $original_spostamento['Dispositivo'];

            $stmt_bundle = $pdo->prepare("SELECT CorpoMacchina_Seriale FROM Bundle_Dispositivi WHERE CorpoMacchina_Seriale = :id1 OR Accessorio_Seriale = :id2 LIMIT 1");
            $stmt_bundle->execute([':id1' => $dispositivo_id_originale, ':id2' => $dispositivo_id_originale]);
            $bundle_info = $stmt_bundle->fetch();

            $params = [
                ':azienda' => $azienda, ':data_install' => $data_install, ':data_ritiro' => $data_ritiro,
                ':nolo_cash' => $nolo_cash, ':assistenza' => $assistenza, ':note' => $note,
                ':utente_mod' => $id_utente_loggato, ':data_mod' => $data_ultima_mod
            ];

            if ($bundle_info) {
                $corpo_macchina_id = $bundle_info['CorpoMacchina_Seriale'];
                $stmt_all_bundle_devs = $pdo->prepare("SELECT CorpoMacchina_Seriale as seriale FROM Bundle_Dispositivi WHERE CorpoMacchina_Seriale = :id1 UNION SELECT Accessorio_Seriale as seriale FROM Bundle_Dispositivi WHERE CorpoMacchina_Seriale = :id2");
                $stmt_all_bundle_devs->execute([':id1' => $corpo_macchina_id, ':id2' => $corpo_macchina_id]);
                $bundle_device_ids = $stmt_all_bundle_devs->fetchAll(PDO::FETCH_COLUMN);

                $in_placeholders = [];
                foreach ($bundle_device_ids as $key => $id) {
                    $placeholder = ":in_id_$key";
                    $in_placeholders[] = $placeholder;
                    $params[$placeholder] = $id;
                }
                $in_clause = implode(',', $in_placeholders);
                
                $update_sql = "UPDATE Spostamenti SET 
                               Azienda = :azienda, Data_Install = :data_install, Data_Ritiro = :data_ritiro, 
                               Nolo_Cash = :nolo_cash, Assistenza = :assistenza, Note = :note,
                               Utente_Ultima_Mod = :utente_mod, Data_Ultima_Mod = :data_mod
                               WHERE Dispositivo IN ($in_clause) AND Azienda = :orig_azienda AND Data_Install = :orig_data_install";
                
                $params[':orig_azienda'] = $original_spostamento['Azienda'];
                $params[':orig_data_install'] = $original_spostamento['Data_Install'];

                $stmt = $pdo->prepare($update_sql);
                $stmt->execute($params);
                $success_message = "L'intero bundle è stato aggiornato con successo!";
            } else {
                $update_sql = "UPDATE Spostamenti SET 
                               Azienda = :azienda, Data_Install = :data_install, Data_Ritiro = :data_ritiro, 
                               Nolo_Cash = :nolo_cash, Assistenza = :assistenza, Note = :note,
                               Utente_Ultima_Mod = :utente_mod, Data_Ultima_Mod = :data_mod
                               WHERE ID = :id";
                $params[':id'] = $id_spostamento;
                $stmt = $pdo->prepare($update_sql);
                $stmt->execute($params);
                $success_message = 'Record aggiornato con successo!';
            }

            $pdo->commit();
            $_SESSION['form_message'] = ['text' => $success_message, 'status' => 'success'];
            header('Location: dashboard_gestione_spostamenti.php');
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Errore durante l'aggiornamento: " . $e->getMessage();
            $status = 'error';
        }
    }
}

$id = $_GET['id'] ?? 0;
$spostamento = null;
$dispositivo_principale = null;
$bundle_devices = [];
$is_in_bundle = false;

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT s.*, CONCAT(u.Nome, ' ', u.Cognome) as UtenteModNome FROM Spostamenti s LEFT JOIN Utenti u ON s.Utente_Ultima_Mod = u.ID WHERE s.ID = :id");
        $stmt->execute([':id' => $id]);
        $spostamento = $stmt->fetch();

        if ($spostamento) {
            $stmt_dev = $pdo->prepare("SELECT d.*, ma.Nome as Marca, mo.Nome as Modello FROM Dispositivi d LEFT JOIN Marche ma ON d.MarcaID = ma.ID LEFT JOIN Modelli mo ON d.ModelloID = mo.ID WHERE d.Seriale_Inrete = :id");
            $stmt_dev->execute([':id' => $spostamento['Dispositivo']]);
            $dispositivo_principale = $stmt_dev->fetch();

            $stmt_bundle_check = $pdo->prepare("SELECT CorpoMacchina_Seriale FROM Bundle_Dispositivi WHERE CorpoMacchina_Seriale = :id1 OR Accessorio_Seriale = :id2 LIMIT 1");
            $stmt_bundle_check->execute([':id1' => $spostamento['Dispositivo'], ':id2' => $spostamento['Dispositivo']]);
            $bundle_info = $stmt_bundle_check->fetch();
            $is_in_bundle = (bool)$bundle_info;

            if ($is_in_bundle) {
                $corpo_macchina_id = $bundle_info['CorpoMacchina_Seriale'];
                $stmt_bundle_devs = $pdo->prepare("
                    SELECT d.Seriale, ma.Nome as Marca, mo.Nome as Modello, 'Dispositivo Principale' as Ruolo FROM Dispositivi d JOIN Marche ma ON d.MarcaID=ma.ID JOIN Modelli mo ON d.ModelloID=mo.ID WHERE d.Seriale_Inrete = :id1
                    UNION
                    SELECT d.Seriale, ma.Nome as Marca, mo.Nome as Modello, 'Accessorio' as Ruolo FROM Bundle_Dispositivi bd JOIN Dispositivi d ON bd.Accessorio_Seriale = d.Seriale_Inrete JOIN Marche ma ON d.MarcaID=ma.ID JOIN Modelli mo ON d.ModelloID=mo.ID WHERE bd.CorpoMacchina_Seriale = :id2");
                $stmt_bundle_devs->execute([':id1' => $corpo_macchina_id, ':id2' => $corpo_macchina_id]);
                $bundle_devices = $stmt_bundle_devs->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            $message = "Record non trovato.";
            $status = 'error';
        }
    } catch (PDOException $e) {
        $message = "Errore nel recupero dati: " . $e->getMessage();
        $status = 'error';
    }
} else {
    $message = "ID dello spostamento non specificato.";
    $status = 'error';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Modifica Spostamento</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_forms.css">
    <link rel="stylesheet" href="../CSS/_search.css">
    <link rel="stylesheet" href="../CSS/_cards.css">
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="form-container">
    <h2>Modifica Installazione #<?= htmlspecialchars($id) ?></h2>
    <?php if ($message): ?>
        <p class="message <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if ($spostamento): ?>
        <div class="card">
            <h3>Dettagli Dispositivo</h3>
            <?php if ($dispositivo_principale): ?>
                <p><strong>Seriale:</strong> <?= htmlspecialchars($dispositivo_principale['Seriale']) ?></p>
                <p><strong>Marca:</strong> <?= htmlspecialchars($dispositivo_principale['Marca']) ?></p>
                <p><strong>Modello:</strong> <?= htmlspecialchars($dispositivo_principale['Modello']) ?></p>
            <?php else: ?>
                <p>Dettagli non disponibili.</p>
            <?php endif; ?>
        </div>
        
        <?php if ($is_in_bundle && !empty($bundle_devices)): ?>
        <div class="card">
            <h3>Componenti del Bundle</h3>
            <ul style="list-style-type: none; padding: 0;">
                <?php foreach($bundle_devices as $dev): ?>
                    <li><strong><?= htmlspecialchars($dev['Ruolo']) ?>:</strong> <?= htmlspecialchars($dev['Marca'] . ' ' . $dev['Modello']) ?> (S/N: <?= htmlspecialchars($dev['Seriale']) ?>)</li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form id="modificaForm" action="modifica_spostamenti.php?id=<?= $id ?>" method="POST" data-is-bundle="<?= $is_in_bundle ? 'true' : 'false' ?>">
            <input type="hidden" name="id_spostamento" value="<?= $spostamento['ID'] ?>">
            <div class="form-group">
                <label for="searchAziendaInput">Azienda</label>
                <input type="text" id="searchAziendaInput" class="search-box" placeholder="Cerca per Ragione Sociale..." value="<?= htmlspecialchars($spostamento['Azienda']) ?>" required>
                <input type="hidden" name="azienda" id="ragione_sociale_azienda_dest" value="<?= htmlspecialchars($spostamento['Azienda']) ?>">
                <div id="searchAziendeResults" class="search-results-list hidden"></div>
            </div>
            <div class="form-group"><label for="data_install">Data Installazione</label><input type="date" id="data_install" name="data_install" value="<?= htmlspecialchars($spostamento['Data_Install']) ?>" required></div>
            <div class="form-group"><label for="data_ritiro">Data Ritiro</label><input type="date" id="data_ritiro" name="data_ritiro" value="<?= htmlspecialchars($spostamento['Data_Ritiro'] ?? '') ?>"></div>
            <div class="form-group"><label for="nolo_cash">Nolo/Cash</label><input type="text" id="nolo_cash" name="nolo_cash" value="<?= htmlspecialchars($spostamento['Nolo_Cash'] ?? '') ?>"></div>
            <div class="form-group"><label for="assistenza">Assistenza</label><input type="text" id="assistenza" name="assistenza" value="<?= htmlspecialchars($spostamento['Assistenza'] ?? '') ?>"></div>
            <div class="form-group"><label for="note">Note</label><textarea id="note" name="note" rows="3"><?= htmlspecialchars($spostamento['Note']) ?></textarea></div>
            <div class="form-group">
                <label for="utente_mod">Ultima Modifica Effettuata Da</label>
                <input type="text" id="utente_mod" name="utente_mod" value="<?= htmlspecialchars($spostamento['UtenteModNome'] ?? 'N/D') ?>" disabled style="background-color: #e9ecef;">
            </div>
            <button type="submit">Salva Modifiche</button>
        </form>
        <a href="dashboard_gestione_spostamenti.php" class="back-link">Torna alla lista</a>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const aziende = <?= json_encode($aziende) ?>;
    const searchAziendaInput = document.getElementById('searchAziendaInput');
    const hiddenAziendaInput = document.getElementById('ragione_sociale_azienda_dest');
    const searchAziendeResults = document.getElementById('searchAziendeResults');

    if (searchAziendaInput) {
        searchAziendaInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            searchAziendeResults.innerHTML = '';
            hiddenAziendaInput.value = this.value;
            searchAziendeResults.classList.remove('hidden');
            if (searchTerm.length > 1) {
                const filteredData = aziende.filter(item => (item['RagioneSociale'] && item['RagioneSociale'].toLowerCase().includes(searchTerm)));
                if (filteredData.length > 0) {
                    filteredData.slice(0, 5).forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'search-result-item';
                        div.textContent = item['RagioneSociale'];
                        div.setAttribute('data-value', item['RagioneSociale']);
                        searchAziendeResults.appendChild(div);
                    });
                } else {
                    searchAziendeResults.innerHTML = '<div class="search-result-item no-results">Nessun risultato trovato.</div>';
                }
            }
        });
        searchAziendeResults.addEventListener('click', function(event) {
            const selectedItem = event.target.closest('.search-result-item');
            if (selectedItem && selectedItem.getAttribute('data-value')) {
                const value = selectedItem.getAttribute('data-value');
                searchAziendaInput.value = value;
                hiddenAziendaInput.value = value;
                searchAziendeResults.innerHTML = '';
                searchAziendeResults.classList.add('hidden');
            }
        });
        document.addEventListener('click', e => {
            if (searchAziendaInput && !searchAziendaInput.contains(e.target) && !searchAziendeResults.contains(e.target)) {
                searchAziendeResults.classList.add('hidden');
            }
        });
    }

    const modificaForm = document.getElementById('modificaForm');
    if (modificaForm) {
        modificaForm.addEventListener('submit', function(event) {
            const dataInstall = document.getElementById('data_install').value;
            const dataRitiro = document.getElementById('data_ritiro').value;
            if (dataRitiro && dataInstall && dataRitiro < dataInstall) {
                event.preventDefault();
                alert('Errore: La data di ritiro non può essere precedente alla data di installazione.');
                return;
            }
            const isBundle = this.dataset.isBundle === 'true';
            if (isBundle) {
                const confirmation = confirm("ATTENZIONE: Stai modificando un record associato a un bundle. Tutte le voci relative a questo bundle per questa installazione verranno aggiornate. Continuare?");
                if (!confirmation) {
                    event.preventDefault();
                }
            }
        });
    }
});
</script>

</body>
</html>