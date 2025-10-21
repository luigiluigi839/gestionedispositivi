<?php
session_start();
require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;

// Controllo sul permesso specifico di aggiunta dispositivo
if (!isset($_SESSION['user_id']) || (!in_array('aggiungi_gestione_dispositivi', $user_permessi) && !$is_superuser)) {
    header('Location: ../Pages/dashboard.php?error=Accesso non autorizzato');
    exit();
}

$message = '';
// Inizializza tutti gli array per i dati del form
$ubicazioni = [];
$stati = [];
$proprieta = [];
$marche = [];
$modelli = [];
$utenti = [];

// Recupera tutti i dati necessari per i menu a tendina
try {
    $stmt_stato_default = $pdo->query("SELECT ID FROM Stati WHERE Nome = 'Nuovo'");
    $stato_nuovo_id = $stmt_stato_default->fetchColumn();
    $ubicazioni = $pdo->query("SELECT ID, Nome FROM Ubicazioni ORDER BY Nome")->fetchAll(PDO::FETCH_ASSOC);
    $stati = $pdo->query("SELECT ID, Nome FROM Stati ORDER BY Nome")->fetchAll(PDO::FETCH_ASSOC);
    $proprieta = $pdo->query("SELECT ID, Nome FROM Proprieta ORDER BY Nome")->fetchAll(PDO::FETCH_ASSOC);
    $marche = $pdo->query("SELECT ID, Nome FROM Marche ORDER BY Nome")->fetchAll(PDO::FETCH_ASSOC);
    $modelli = $pdo->query("SELECT mo.ID, mo.Nome, mo.MarcaID, t.Nome AS TipologiaNome 
                            FROM Modelli mo 
                            LEFT JOIN Tipologie t ON mo.Tipologia = t.ID 
                            ORDER BY mo.Nome")->fetchAll(PDO::FETCH_ASSOC);
    $utenti = $pdo->query("SELECT ID, Nome, Cognome FROM Utenti ORDER BY Cognome, Nome")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Errore nel recupero dei dati per il form: " . $e->getMessage();
}

// Se il modulo è stato inviato, elabora i dati
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Campi dispositivo
    $codice_articolo = trim($_POST['codice_articolo'] ?? '');
    $marca_id = $_POST['marca_id'] ?? null;
    $modello_id = $_POST['modello_id'] ?? null;
    $seriale = trim($_POST['seriale'] ?? '');
    $ubicazione = $_POST['ubicazione'] ?? null;
    $stato = $_POST['stato'] ?? $stato_nuovo_id;
    $data_ordine = !empty(trim($_POST['data_ordine'] ?? '')) ? trim($_POST['data_ordine']) : null;
    $note = trim($_POST['note'] ?? '');
    $proprieta_id = !empty($_POST['proprieta_id']) ? trim($_POST['proprieta_id']) : null;
    $prenotato_da_id = !empty($_POST['prenotato_da']) ? trim($_POST['prenotato_da']) : null;
    $data_prenotazione = !empty(trim($_POST['data_prenotazione'] ?? '')) ? trim($_POST['data_prenotazione']) : null;
    $pin = !empty(trim($_POST['pin'] ?? '')) ? trim($_POST['pin']) : null;
    $bn = !empty(trim($_POST['bn'] ?? '')) ? trim($_POST['bn']) : null;
    $colore = !empty(trim($_POST['colore'] ?? '')) ? trim($_POST['colore']) : null;
    $ccc = !empty(trim($_POST['ccc'] ?? '')) ? trim($_POST['ccc']) : null;

    $tipo_scadenza = trim($_POST['tipo_scadenza'] ?? '');
    $data_scadenza = $_POST['data_scadenza'] ?? null;
    $note_scadenza = trim($_POST['note_scadenza'] ?? '');

    $utente_ultima_mod = $_SESSION['user_id'];
    $data_ultima_mod = date('Y-m-d');
    
    if (empty($codice_articolo) || empty($marca_id) || empty($modello_id) || empty($seriale) || empty($ubicazione) || empty($stato)) {
        $message = "Per favore, compila tutti i campi obbligatori.";
    } else {
        try {
            $pdo->beginTransaction();

            $sql = "INSERT INTO Dispositivi (Codice_Articolo, MarcaID, ModelloID, Seriale, Pin, Ubicazione, Stato, Data_Ordine, Note, Proprieta, Prenotato_Da, Data_Prenotazione, `B/N`, Colore, `C.C.C.`, Utente_Ultima_Mod, Data_Ultima_Mod)
                    VALUES (:codice_articolo, :marca_id, :modello_id, :seriale, :pin, :ubicazione, :stato, :data_ordine, :note, :proprieta_id, :prenotato_da, :data_prenotazione, :bn, :colore, :ccc, :utente_ultima_mod, :data_ultima_mod)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':codice_articolo' => $codice_articolo, ':marca_id' => $marca_id, ':modello_id' => $modello_id,
                ':seriale' => $seriale, ':pin' => $pin, ':ubicazione' => $ubicazione, ':stato' => $stato,
                ':data_ordine' => $data_ordine, ':note' => $note, ':proprieta_id' => $proprieta_id,
                ':prenotato_da' => $prenotato_da_id, ':data_prenotazione' => $data_prenotazione,
                ':bn' => $bn, ':colore' => $colore, ':ccc' => $ccc,
                ':utente_ultima_mod' => $utente_ultima_mod, ':data_ultima_mod' => $data_ultima_mod
            ]);
            
            $nuovo_dispositivo_id = $pdo->lastInsertId();
            $success_message = 'Dispositivo aggiunto con successo!';

            // MODIFICATO: Inserisce il reminder solo se i dati sono presenti E l'utente ha il permesso
            if ((in_array('inserisci_reminder', $user_permessi) || $is_superuser) && !empty($tipo_scadenza) && !empty($data_scadenza)) {
                $sql_scadenza = "INSERT INTO Scadenze_Reminder (Dispositivo_Seriale, Data_Scadenza, Tipo_Scadenza, Note, Utente_Creazione_ID)
                                 VALUES (:dispositivo, :data_scadenza, :tipo, :note, :utente)";
                $stmt_scadenza = $pdo->prepare($sql_scadenza);
                $stmt_scadenza->execute([
                    ':dispositivo' => $nuovo_dispositivo_id,
                    ':data_scadenza' => $data_scadenza,
                    ':tipo' => $tipo_scadenza,
                    ':note' => $note_scadenza,
                    ':utente' => $utente_ultima_mod
                ]);
                $success_message .= ' È stato creato anche un reminder di scadenza.';
            }

            $pdo->commit();
            
            header('Location: gestione_dispositivi.php?success=' . urlencode($success_message));
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000) {
                 $message = "Errore: Il numero di serie '" . htmlspecialchars($seriale) . "' esiste già nel database.";
            } else {
                 $message = "Errore nel salvataggio: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Aggiungi Dispositivo</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_forms.css">
    <link rel="stylesheet" href="../CSS/_cards.css">
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="form-container">
    <h2>Aggiungi Nuovo Dispositivo</h2>
    
    <?php if ($message): ?>
        <p class="message <?= strpos($message, 'Errore') !== false ? 'error' : 'success' ?>">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>
    
    <form action="aggiungi_dispositivo.php" method="POST">
        <div class="card">
            <h3>Informazioni Principali</h3>
            <div class="form-group">
                <label for="codice_articolo">Codice Articolo</label>
                <input type="text" id="codice_articolo" name="codice_articolo" required value="<?= htmlspecialchars($_POST['codice_articolo'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="marca_id">Marca</label>
                <select id="marca_id" name="marca_id" required>
                    <option value="">Seleziona una marca</option>
                    <?php foreach ($marche as $marca): ?>
                        <option value="<?= $marca['ID'] ?>" <?= (($_POST['marca_id'] ?? '') == $marca['ID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($marca['Nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="modello_id">Modello</label>
                <select id="modello_id" name="modello_id" required>
                    <option value="">Seleziona prima una marca</option>
                </select>
            </div>

            <div class="form-group">
                <label for="seriale">Numero di Serie</label>
                <input type="text" id="seriale" name="seriale" required value="<?= htmlspecialchars($_POST['seriale'] ?? '') ?>">
            </div>
        </div>

        <div class="card">
            <h3>Dati Amministrativi e Magazzino</h3>
            <div class="form-group">
                <label for="ubicazione">Ubicazione</label>
                <select id="ubicazione" name="ubicazione" required>
                    <option value="">Seleziona un'ubicazione</option>
                    <?php foreach ($ubicazioni as $ubicazione_option): ?>
                        <option value="<?= htmlspecialchars($ubicazione_option['ID']) ?>"
                            <?= (($_POST['ubicazione'] ?? '') == $ubicazione_option['ID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ubicazione_option['Nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="stato">Stato</label>
                <select id="stato" name="stato" required>
                    <?php foreach ($stati as $stato_option): ?>
                        <option value="<?= htmlspecialchars($stato_option['ID']) ?>"
                            <?= (($stato_option['ID'] == ($_POST['stato'] ?? $stato_nuovo_id))) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($stato_option['Nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="data_ordine">Data Ordine</label>
                <input type="date" id="data_ordine" name="data_ordine" value="<?= htmlspecialchars($_POST['data_ordine'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <label for="proprieta_id">Proprietà</label>
                <select id="proprieta_id" name="proprieta_id">
                    <option value="">Nessuna</option>
                    <?php foreach ($proprieta as $prop_option): ?>
                        <option value="<?= htmlspecialchars($prop_option['ID']) ?>"
                            <?= (($_POST['proprieta_id'] ?? '') == $prop_option['ID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($prop_option['Nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="prenotato_da">Prenota per Utente (Commerciale)</label>
                <select id="prenotato_da" name="prenotato_da">
                    <option value="">Nessuno</option>
                    <?php foreach ($utenti as $utente): ?>
                        <option value="<?= htmlspecialchars($utente['ID']) ?>"
                            <?= (($_POST['prenotato_da'] ?? '') == $utente['ID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($utente['Cognome'] . ' ' . $utente['Nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="data_prenotazione">Data Prenotazione</label>
                <input type="date" id="data_prenotazione" name="data_prenotazione" value="<?= htmlspecialchars($_POST['data_prenotazione'] ?? '') ?>">
            </div>
        </div>
        
        <div class="card" id="dati-tecnici-container" style="display:none;">
            <h3>Dati Tecnici</h3>
             <div class="form-group">
                <label for="pin">Pin</label>
                <input type="text" id="pin" name="pin" value="<?= htmlspecialchars($_POST['pin'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="bn">B/N</label>
                <input type="text" id="bn" name="bn" value="<?= htmlspecialchars($_POST['bn'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="colore">Colore</label>
                <input type="text" id="colore" name="colore" value="<?= htmlspecialchars($_POST['colore'] ?? '') ?>">
            </div>
            <div class="form-group" id="ccc-container" style="display:none;">
                <label for="ccc">C.C.C.</label>
                <input type="text" id="ccc" name="ccc" value="<?= htmlspecialchars($_POST['ccc'] ?? '') ?>">
            </div>
        </div>

        <?php if (in_array('inserisci_reminder', $user_permessi) || $is_superuser): ?>
        <div class="card">
            <h3>Aggiungi Reminder Scadenza (Opzionale)</h3>
            <div class="form-group">
                <label for="tipo_scadenza">Tipo Scadenza (es. Garanzia, Noleggio)</label>
                <input type="text" id="tipo_scadenza" name="tipo_scadenza">
            </div>
            <div class="form-group">
                <label for="data_scadenza">Data Scadenza</label>
                <input type="date" id="data_scadenza" name="data_scadenza">
            </div>
            <div class="form-group">
                <label for="note_scadenza">Note Reminder</label>
                <textarea id="note_scadenza" name="note_scadenza" rows="2"></textarea>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <h3>Note</h3>
            <div class="form-group">
                <textarea id="note" name="note" rows="4"><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
            </div>
        </div>
        
        <button type="submit">Aggiungi Dispositivo</button>
    </form>
    
    <a href="gestione_dispositivi.php" class="back-link">Torna alla gestione dispositivi</a>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modelliData = <?= json_encode($modelli) ?>;
        const marcaSelect = document.getElementById('marca_id');
        const modelloSelect = document.getElementById('modello_id');
        const previousModelloId = '<?= htmlspecialchars($_POST['modello_id'] ?? '') ?>';
        const datiTecniciContainer = document.getElementById('dati-tecnici-container');
        const cccContainer = document.getElementById('ccc-container');

        function updateModelli() {
            const selectedMarcaId = marcaSelect.value;
            modelloSelect.innerHTML = '<option value="">Seleziona un modello</option>';

            if (selectedMarcaId) {
                const filteredModelli = modelliData.filter(modello => modello.MarcaID == selectedMarcaId);
                filteredModelli.forEach(modello => {
                    const option = document.createElement('option');
                    option.value = modello.ID;
                    option.textContent = modello.Nome;
                    if (modello.ID == previousModelloId) {
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

        if (marcaSelect.value) {
            updateModelli();
        }
    });
</script>

<?php require_once '../PHP/footer.php'; ?>

</body>
</html>