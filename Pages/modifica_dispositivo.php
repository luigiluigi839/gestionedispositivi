<?php
session_start();
require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;

// MODIFICATO: Controllo sul permesso specifico di modifica
if (!isset($_SESSION['user_id']) || (!in_array('modifica_gestione_dispositivi', $user_permessi) && !$is_superuser)) {
    header('Location: ../Pages/dashboard.php?error=' . urlencode('Accesso non autorizzato.'));
    exit();
}

// Gestione messaggi di sessione per i feedback
$message = $_SESSION['message'] ?? '';
$status = $_SESSION['status'] ?? '';
unset($_SESSION['message'], $_SESSION['status']);

$dispositivo_serial_inrete = $_GET['id'] ?? null;
if (!$dispositivo_serial_inrete) { die("ID dispositivo non specificato."); }

// Inizializzazione array
$dispositivo = null; $ubicazioni = []; $stati = []; $proprieta = []; $utenti = []; $marche = []; $modelli = []; $scadenze_esistenti = [];

try {
    // Recupera tutti i dati necessari per popolare il form
    $stmt_dispositivo = $pdo->prepare("SELECT * FROM Dispositivi WHERE Seriale_inrete = :id");
    $stmt_dispositivo->execute([':id' => $dispositivo_serial_inrete]);
    $dispositivo = $stmt_dispositivo->fetch(PDO::FETCH_ASSOC);
    if (!$dispositivo) { die("Dispositivo non trovato."); }

    $ubicazioni = $pdo->query("SELECT ID, Nome FROM Ubicazioni ORDER BY Nome")->fetchAll(PDO::FETCH_ASSOC);
    $stati = $pdo->query("SELECT ID, Nome FROM Stati ORDER BY Nome")->fetchAll(PDO::FETCH_ASSOC);
    $proprieta = $pdo->query("SELECT ID, Nome FROM Proprieta ORDER BY Nome")->fetchAll(PDO::FETCH_ASSOC);
    $utenti = $pdo->query("SELECT ID, Nome, Cognome FROM Utenti ORDER BY Cognome, Nome")->fetchAll(PDO::FETCH_ASSOC);
    $marche = $pdo->query("SELECT ID, Nome FROM Marche ORDER BY Nome")->fetchAll(PDO::FETCH_ASSOC);
    
    $modelli = $pdo->query("SELECT mo.ID, mo.Nome, mo.MarcaID, t.Nome AS TipologiaNome 
                            FROM Modelli mo 
                            LEFT JOIN Tipologie t ON mo.Tipologia = t.ID 
                            ORDER BY mo.Nome")->fetchAll(PDO::FETCH_ASSOC);
    
    // MODIFICATO: Recupera le scadenze solo se si ha il permesso di vederle
    if (in_array('dashboard_reminder', $user_permessi) || $is_superuser) {
        $stmt_scadenze = $pdo->prepare("SELECT * FROM Scadenze_Reminder WHERE Dispositivo_Seriale = :id ORDER BY Data_Scadenza DESC");
        $stmt_scadenze->execute([':id' => $dispositivo_serial_inrete]);
        $scadenze_esistenti = $stmt_scadenze->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) { die("Errore nel recupero dei dati: " . $e->getMessage()); }

// Gestione del salvataggio del form principale (invariata)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'salva_dispositivo') {
    try {
        $sql = "UPDATE Dispositivi SET
                    Codice_Articolo = :codice_articolo, MarcaID = :marca_id, ModelloID = :modello_id,
                    Seriale = :seriale, Ubicazione = :ubicazione, Stato = :stato, Pin = :pin,
                    Data_Ordine = :data_ordine, Note = :note, Proprieta = :proprieta_id,
                    `B/N` = :bn, Colore = :colore, `C.C.C.` = :ccc,
                    Prenotato_Da = :prenotato_da, Data_Prenotazione = :data_prenotazione,
                    Utente_Ultima_Mod = :utente_ultima_mod, Data_Ultima_Mod = :data_ultima_mod
                WHERE Seriale_Inrete = :seriale_inrete";
        $stmt = $pdo->prepare($sql);
        $params = [
            ':codice_articolo' => $_POST['codice_articolo'], ':marca_id' => $_POST['marca_id'], ':modello_id' => $_POST['modello_id'],
            ':seriale' => $_POST['seriale'], ':ubicazione' => $_POST['ubicazione'], ':stato' => $_POST['stato'], ':pin' => $_POST['pin'],
            ':data_ordine' => empty(trim($_POST['data_ordine'])) ? null : trim($_POST['data_ordine']),
            ':note' => trim($_POST['note']), ':proprieta_id' => empty($_POST['proprieta_id']) ? null : $_POST['proprieta_id'],
            ':bn' => empty(trim($_POST['bn'])) ? null : trim($_POST['bn']),
            ':colore' => empty(trim($_POST['colore'])) ? null : trim($_POST['colore']),
            ':ccc' => empty(trim($_POST['ccc'])) ? null : trim($_POST['ccc']),
            ':prenotato_da' => empty($_POST['prenotato_da']) ? null : $_POST['prenotato_da'],
            ':data_prenotazione' => empty(trim($_POST['data_prenotazione'])) ? null : trim($_POST['data_prenotazione']),
            ':utente_ultima_mod' => $_SESSION['user_id'], ':data_ultima_mod' => date('Y-m-d'),
            ':seriale_inrete' => $dispositivo_serial_inrete
        ];
        $stmt->execute($params);
        
        $_SESSION['message'] = 'Dispositivo aggiornato con successo!';
        $_SESSION['status'] = 'success';
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit();
    } catch (PDOException $e) {
        $message = "Errore nell'aggiornamento: " . $e->getMessage();
        $status = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Modifica Dispositivo</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_forms.css">
    <link rel="stylesheet" href="../CSS/_cards.css">
    <link rel="stylesheet" href="../CSS/_tables.css">
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="form-container">
    <h2>Modifica Dispositivo</h2>
    
    <?php if ($message): ?>
        <p class="message <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    
    <form action="modifica_dispositivo.php?id=<?= htmlspecialchars($dispositivo_serial_inrete) ?>" method="POST">
        <input type="hidden" name="action" value="salva_dispositivo">
        <input type="hidden" name="dispositivo_serial_inrete" value="<?= htmlspecialchars($dispositivo_serial_inrete) ?>">

        <div class="card">
            <h3>Informazioni Principali</h3>
            <div class="form-group"><label>Codice Articolo</label><input type="text" name="codice_articolo" required value="<?= htmlspecialchars($dispositivo['Codice_Articolo'] ?? '') ?>"></div>
            <div class="form-group"><label>Marca</label><select name="marca_id" id="marca_id" required>
                <option value="">Seleziona...</option>
                <?php foreach ($marche as $marca): ?><option value="<?= $marca['ID'] ?>" <?= ($dispositivo['MarcaID'] == $marca['ID']) ? 'selected' : '' ?>><?= htmlspecialchars($marca['Nome']) ?></option><?php endforeach; ?>
            </select></div>
            <div class="form-group"><label>Modello</label><select name="modello_id" id="modello_id" required><option value="">Seleziona prima una marca</option></select></div>
            <div class="form-group"><label>Numero di Serie</label><input type="text" name="seriale" required value="<?= htmlspecialchars($dispositivo['Seriale'] ?? '') ?>"></div>
        </div>
        
        <div class="card">
            <h3>Dati Amministrativi e Magazzino</h3>
            <div class="form-group"><label>Data Ordine</label><input type="date" name="data_ordine" value="<?= htmlspecialchars($dispositivo['Data_Ordine'] ?? '') ?>"></div>
            <div class="form-group"><label>Proprietà</label><select name="proprieta_id"><option value="">Nessuna</option><?php foreach ($proprieta as $p): ?><option value="<?= $p['ID'] ?>" <?= ($dispositivo['Proprieta'] == $p['ID']) ? 'selected' : '' ?>><?= htmlspecialchars($p['Nome']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Ubicazione</label><select name="ubicazione" required><option value="">Seleziona...</option><?php foreach ($ubicazioni as $u): ?><option value="<?= $u['ID'] ?>" <?= ($dispositivo['Ubicazione'] == $u['ID']) ? 'selected' : '' ?>><?= htmlspecialchars($u['Nome']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Stato</label><select name="stato" required><?php foreach ($stati as $s): ?><option value="<?= $s['ID'] ?>" <?= ($dispositivo['Stato'] == $s['ID']) ? 'selected' : '' ?>><?= htmlspecialchars($s['Nome']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Prenotato da</label><select name="prenotato_da"><option value="">Nessun utente</option><?php foreach ($utenti as $u): ?><option value="<?= $u['ID'] ?>" <?= ($dispositivo['Prenotato_Da'] == $u['ID']) ? 'selected' : '' ?>><?= htmlspecialchars($u['Nome'].' '.$u['Cognome']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Data Prenotazione</label><input type="date" name="data_prenotazione" value="<?= htmlspecialchars($dispositivo['Data_Prenotazione'] ?? '') ?>"></div>
        </div>
        
        <div class="card" id="dati-tecnici-container" style="display:none;">
            <h3>Dati Tecnici</h3>
            <div class="form-group"><label>Pin</label><input type="text" name="pin" value="<?= htmlspecialchars($dispositivo['Pin'] ?? '') ?>"></div>
            <div class="form-group"><label>B/N</label><input type="text" name="bn" value="<?= htmlspecialchars($dispositivo['B/N'] ?? '') ?>"></div>
            <div class="form-group"><label>Colore</label><input type="text" name="colore" value="<?= htmlspecialchars($dispositivo['Colore'] ?? '') ?>"></div>
            <div class="form-group" id="ccc-container" style="display:none;"><label>C.C.C.</label><input type="text" name="ccc" value="<?= htmlspecialchars($dispositivo['C.C.C.'] ?? '') ?>"></div>
        </div>

        <div class="card">
            <h3>Note</h3>
            <div class="form-group"><textarea name="note" rows="4"><?= htmlspecialchars($dispositivo['Note'] ?? '') ?></textarea></div>
        </div>
        
        <button type="submit">Salva Modifiche Dispositivo</button>
    </form>
    
    <hr style="margin: 30px 0;">

    <?php if (in_array('dashboard_reminder', $user_permessi) || $is_superuser): ?>
    <div class="card">
        <h3>Scadenze / Reminder Associati</h3>
        <?php if (!empty($scadenze_esistenti)): ?>
            <table class="spostamenti-table">
                <thead><tr><th>Tipo</th><th>Data Scadenza</th><th>Stato</th><th>Note</th><th>Azioni</th></tr></thead>
                <tbody>
                <?php foreach($scadenze_esistenti as $scadenza): ?>
                    <tr>
                        <td><?= htmlspecialchars($scadenza['Tipo_Scadenza']) ?></td>
                        <td><?= date('d/m/Y', strtotime($scadenza['Data_Scadenza'])) ?></td>
                        <td><?= htmlspecialchars($scadenza['Stato']) ?></td>
                        <td><?= htmlspecialchars($scadenza['Note']) ?></td>
                        <td>
                            </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <hr>
        <?php else: ?>
            <p>Nessuna scadenza presente per questo dispositivo.</p>
        <?php endif; ?>

        <?php if (in_array('inserisci_reminder', $user_permessi) || $is_superuser): ?>
            <h4 style="margin-top: 20px;">Aggiungi Nuova Scadenza</h4>
            <form action="../PHP/salva_scadenza.php" method="POST">
                <input type="hidden" name="dispositivo_seriale" value="<?= htmlspecialchars($dispositivo_serial_inrete) ?>">
                <div class="form-group"><label>Tipo Scadenza (es. Garanzia)</label><input type="text" name="tipo_scadenza" required></div>
                <div class="form-group"><label>Data Scadenza</label><input type="date" name="data_scadenza" required></div>
                <div class="form-group"><label>Note</label><textarea name="note" rows="2"></textarea></div>
                <button type="submit">Aggiungi Scadenza</button>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <a href="gestione_dispositivi.php" class="back-link">Torna alla gestione dispositivi</a>
</div>

<script>
// Il codice Javascript rimane invariato
document.addEventListener('DOMContentLoaded', function() {
    const modelliData = <?= json_encode($modelli) ?>;
    const dispositivoData = <?= json_encode($dispositivo) ?>;
    const marcaSelect = document.getElementById('marca_id');
    const modelloSelect = document.getElementById('modello_id');
    const datiTecniciContainer = document.getElementById('dati-tecnici-container');
    const cccContainer = document.getElementById('ccc-container');

    function updateModelli() {
        const selectedMarcaId = marcaSelect.value;
        modelloSelect.innerHTML = '<option value="">Seleziona un modello</option>';
        if (selectedMarcaId) {
            const filteredModelli = modelliData.filter(m => m.MarcaID == selectedMarcaId);
            filteredModelli.forEach(modello => {
                const option = document.createElement('option');
                option.value = modello.ID;
                option.textContent = modello.Nome;
                if (modello.ID == dispositivoData.ModelloID) {
                    option.selected = true;
                }
                modelloSelect.appendChild(option);
            });
        }
        checkFormVisibility();
    }

    function checkFormVisibility() {
        const selectedModelloId = modelloSelect.value;
        if (!selectedModelloId) {
            datiTecniciContainer.style.display = 'none';
            cccContainer.style.display = 'none';
            return;
        }

        const modello = modelliData.find(m => m.ID == selectedModelloId);
        if (modello && modello.TipologiaNome) {
            const tipologia = modello.TipologiaNome;
            if (tipologia.includes('Stampante')) {
                datiTecniciContainer.style.display = 'block';
                if (tipologia.includes('Production')) {
                    cccContainer.style.display = 'block';
                } else {
                    cccContainer.style.display = 'none';
                }
            } else {
                datiTecniciContainer.style.display = 'none';
                cccContainer.style.display = 'none';
            }
        } else {
            datiTecniciContainer.style.display = 'none';
            cccContainer.style.display = 'none';
        }
    }

    marcaSelect.addEventListener('change', updateModelli);
    modelloSelect.addEventListener('change', checkFormVisibility);

    updateModelli();
});
</script>
</body>
</html>