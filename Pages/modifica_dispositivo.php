<?php
session_start();
require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;

// Controllo sul permesso specifico di modifica
if (!isset($_SESSION['user_id']) || (!in_array('modifica_gestione_dispositivi', $user_permessi) && !$is_superuser)) {
    header('Location: ../Pages/dashboard.php?error=' . urlencode('Accesso non autorizzato.'));
    exit();
}

$dispositivo_serial_inrete = $_GET['id'] ?? null;
if (!$dispositivo_serial_inrete) { die("ID dispositivo non specificato."); }

$message = '';
$status = '';
// Inizializzazione array
$dispositivo = null; $ubicazioni = []; $stati = []; $proprieta = []; $utenti = []; $marche = []; $modelli = []; $scadenze_esistenti = [];

try {
    // Recupera tutti i dati necessari per popolare il form
    $stmt_dispositivo = $pdo->prepare("SELECT * FROM Dispositivi WHERE Seriale_inrete = :id");
    $stmt_dispositivo->execute([':id' => $dispositivo_serial_inrete]);
    $dispositivo = $stmt_dispositivo->fetch(PDO::FETCH_ASSOC);
    if (!$dispositivo) { die("Dispositivo non trovato."); }

    // Dati per i dropdown
    $ubicazioni = $pdo->query("SELECT ID, Nome FROM Ubicazioni ORDER BY Nome")->fetchAll(PDO::FETCH_ASSOC);
    $stati = $pdo->query("SELECT ID, Nome FROM Stati ORDER BY Nome")->fetchAll(PDO::FETCH_ASSOC);
    $proprieta = $pdo->query("SELECT ID, Nome FROM Proprieta ORDER BY Nome")->fetchAll(PDO::FETCH_ASSOC);
    $utenti = $pdo->query("SELECT ID, Nome, Cognome FROM Utenti ORDER BY Cognome, Nome")->fetchAll(PDO::FETCH_ASSOC);
    $marche = $pdo->query("SELECT ID, Nome FROM Marche ORDER BY Nome")->fetchAll(PDO::FETCH_ASSOC);
    
    // Dati per la ricerca JS dei modelli
    $modelli = $pdo->query("SELECT mo.ID, mo.Nome, mo.Codice_Modello, mo.MarcaID, t.Nome AS TipologiaNome 
                            FROM Modelli mo 
                            LEFT JOIN Tipologie t ON mo.Tipologia = t.ID 
                            ORDER BY mo.Nome")->fetchAll(PDO::FETCH_ASSOC);

    // Recupera il testo da visualizzare per il modello attualmente selezionato
    $modello_display_text = '';
    if (!empty($dispositivo['ModelloID'])) {
        foreach ($modelli as $m) {
            if ($m['ID'] == $dispositivo['ModelloID']) {
                $modello_display_text = $m['Codice_Modello'] ? "{$m['Codice_Modello']} - {$m['Nome']}" : $m['Nome'];
                break;
            }
        }
    }

} catch (PDOException $e) { die("Errore nel recupero dei dati: " . $e->getMessage()); }

// Gestione del salvataggio del form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        
        header('Location: gestione_dispositivi.php?success=' . urlencode('Dispositivo aggiornato con successo!'));
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
    <link rel="stylesheet" href="../CSS/_search.css">
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="form-container">
    <h2>Modifica Dispositivo (Seriale Inrete: <?= htmlspecialchars($dispositivo['Seriale_Inrete']) ?>)</h2>
    
    <?php if ($message): ?>
        <p class="message <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    
    <form action="modifica_dispositivo.php?id=<?= htmlspecialchars($dispositivo_serial_inrete) ?>" method="POST">
        <div class="card">
            <h3>Informazioni Principali</h3>
            <div class="form-group"><label>Codice Articolo</label><input type="text" name="codice_articolo" required value="<?= htmlspecialchars($dispositivo['Codice_Articolo'] ?? '') ?>"></div>
            <div class="form-group"><label>Marca</label><select id="marca_id" name="marca_id" required>
                <option value="">Seleziona...</option>
                <?php foreach ($marche as $marca): ?><option value="<?= $marca['ID'] ?>" <?= ($dispositivo['MarcaID'] == $marca['ID']) ? 'selected' : '' ?>><?= htmlspecialchars($marca['Nome']) ?></option><?php endforeach; ?>
            </select></div>
            <div class="form-group">
                <label for="search_modello">Modello</label>
                <input type="text" id="search_modello" placeholder="Cerca per codice o nome..." required autocomplete="off" value="<?= htmlspecialchars($modello_display_text) ?>">
                <input type="hidden" id="modello_id" name="modello_id" value="<?= htmlspecialchars($dispositivo['ModelloID'] ?? '') ?>">
                <div id="modello_search_results" class="search-results-list hidden"></div>
            </div>
            <div class="form-group"><label>Numero di Serie</label><input type="text" name="seriale" required value="<?= htmlspecialchars($dispositivo['Seriale'] ?? '') ?>"></div>
        </div>
        
        <div class="card">
            <h3>Dati Amministrativi e Magazzino</h3>
            <div class="form-group"><label>Ubicazione</label><select name="ubicazione" required><?php foreach ($ubicazioni as $u): ?><option value="<?= $u['ID'] ?>" <?= ($dispositivo['Ubicazione'] == $u['ID']) ? 'selected' : '' ?>><?= htmlspecialchars($u['Nome']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Stato</label><select name="stato" required><?php foreach ($stati as $s): ?><option value="<?= $s['ID'] ?>" <?= ($dispositivo['Stato'] == $s['ID']) ? 'selected' : '' ?>><?= htmlspecialchars($s['Nome']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Data Ordine</label><input type="date" name="data_ordine" value="<?= htmlspecialchars($dispositivo['Data_Ordine'] ?? '') ?>"></div>
            <div class="form-group"><label>Proprietà</label><select name="proprieta_id"><option value="">Nessuna</option><?php foreach ($proprieta as $p): ?><option value="<?= $p['ID'] ?>" <?= ($dispositivo['Proprieta'] == $p['ID']) ? 'selected' : '' ?>><?= htmlspecialchars($p['Nome']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Prenotato da</label><select name="prenotato_da"><option value="">Nessuno</option><?php foreach ($utenti as $u): ?><option value="<?= $u['ID'] ?>" <?= ($dispositivo['Prenotato_Da'] == $u['ID']) ? 'selected' : '' ?>><?= htmlspecialchars($u['Nome'].' '.$u['Cognome']) ?></option><?php endforeach; ?></select></div>
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
        
        <button type="submit">Aggiorna Dispositivo</button>
    </form>
    
    <a href="gestione_dispositivi.php" class="back-link">Torna alla gestione dispositivi</a>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modelliData = <?= json_encode($modelli) ?>;
        const marcaSelect = document.getElementById('marca_id');
        const searchModelloInput = document.getElementById('search_modello');
        const hiddenModelloInput = document.getElementById('modello_id');
        const resultsContainer = document.getElementById('modello_search_results');
        
        const datiTecniciContainer = document.getElementById('dati-tecnici-container');
        const cccContainer = document.getElementById('ccc-container');

        function toggleModelloSearch() {
            if (marcaSelect.value) {
                searchModelloInput.disabled = false;
                searchModelloInput.placeholder = 'Cerca per codice o nome...';
            } else {
                searchModelloInput.disabled = true;
                searchModelloInput.placeholder = 'Seleziona prima una marca';
            }
        }
        
        marcaSelect.addEventListener('change', function() {
            searchModelloInput.value = '';
            hiddenModelloInput.value = '';
            resultsContainer.classList.add('hidden');
            toggleModelloSearch();
            checkFormVisibility();
        });

        searchModelloInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const selectedMarcaId = marcaSelect.value;
            resultsContainer.innerHTML = '';
            hiddenModelloInput.value = '';

            if (query.length < 1 || !selectedMarcaId) {
                resultsContainer.classList.add('hidden');
                return;
            }

            const filteredModelli = modelliData.filter(modello => {
                const matchesMarca = modello.MarcaID == selectedMarcaId;
                const matchesQuery = (modello.Nome.toLowerCase().includes(query)) ||
                                     (modello.Codice_Modello && modello.Codice_Modello.toLowerCase().includes(query));
                return matchesMarca && matchesQuery;
            });
            
            if (filteredModelli.length > 0) {
                filteredModelli.slice(0, 10).forEach(modello => {
                    const item = document.createElement('div');
                    item.className = 'search-result-item';
                    const displayText = modello.Codice_Modello ? `${modello.Codice_Modello} - ${modello.Nome}` : modello.Nome;
                    item.textContent = displayText;
                    item.addEventListener('click', () => {
                        searchModelloInput.value = displayText;
                        hiddenModelloInput.value = modello.ID;
                        resultsContainer.classList.add('hidden');
                        checkFormVisibility();
                    });
                    resultsContainer.appendChild(item);
                });
                resultsContainer.classList.remove('hidden');
            } else {
                resultsContainer.innerHTML = '<div class="search-result-item no-results">Nessun modello trovato</div>';
                resultsContainer.classList.remove('hidden');
            }
        });
        
        function checkFormVisibility() {
            const selectedModelloId = hiddenModelloInput.value;
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
        
        document.addEventListener('click', function(e) {
            if (!searchModelloInput.contains(e.target) && !resultsContainer.contains(e.target)) {
                resultsContainer.classList.add('hidden');
            }
        });

        // Inizializza stato al caricamento
        toggleModelloSearch();
        checkFormVisibility();
    });
</script>

<?php require_once '../PHP/footer.php'; ?>

</body>
</html>

