<?php
session_start();
require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$id_utente_loggato = $_SESSION['user_id'] ?? null;

// Controllo sul permesso specifico 'assegnazione_cliente'
if (!isset($id_utente_loggato) || (!in_array('assegnazione_cliente', $user_permessi) && !$is_superuser)) {
    header('Location: ../Pages/dashboard.php?error=Accesso non autorizzato');
    exit();
}

$message = '';
$status = '';

// Gestione dell'invio del form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seriale_dispositivo = $_POST['seriale_dispositivo'] ?? ''; // Seriale Inrete del corpo macchina
    $ragione_sociale_azienda_dest = $_POST['ragione_sociale_azienda_dest'] ?? null;
    $data_installazione = $_POST['data_installazione'] ?? date('Y-m-d');
    $nolo_cash = $_POST['nolo_cash'] ?? null;
    $assistenza_id = $_POST['assistenza'] ?? null;
    $note = $_POST['note'] ?? null;

    $tipo_scadenza = trim($_POST['tipo_scadenza'] ?? '');
    $data_scadenza = $_POST['data_scadenza'] ?? null;
    $note_scadenza = trim($_POST['note_scadenza'] ?? '');
    
    $id_ubicazione_cliente = 9; // Ubicazione "Installato Presso Cliente"

    if (empty($seriale_dispositivo) || empty($ragione_sociale_azienda_dest)) {
        $_SESSION['message'] = 'Selezionare un dispositivo e un\'azienda.';
        $_SESSION['status'] = 'error';
    } else {
        $pdo->beginTransaction();
        try {
            $assistenza_nome = null;
            if ($assistenza_id) {
                $stmt_assistenza_nome = $pdo->prepare("SELECT Nome FROM Proprieta WHERE ID = :id");
                $stmt_assistenza_nome->execute([':id' => $assistenza_id]);
                $assistenza_nome = $stmt_assistenza_nome->fetchColumn();
            }

            // Inserimento dello spostamento
            $sql_insert_spostamento = "INSERT INTO Spostamenti (Dispositivo, Data_Install, Azienda, Nolo_Cash, Assistenza, Note, Utente_Ultima_Mod, Data_Ultima_Mod) VALUES (:dispositivo, :data_install, :azienda, :nolo_cash, :assistenza, :note, :utente_mod, NOW())";
            $stmt_insert_spostamento = $pdo->prepare($sql_insert_spostamento);
            
            $params = [
                ':data_install' => $data_installazione,
                ':azienda' => $ragione_sociale_azienda_dest,
                ':nolo_cash' => $nolo_cash,
                ':assistenza' => $assistenza_nome,
                ':note' => $note,
                ':utente_mod' => $id_utente_loggato
            ];

            $stmt_insert_spostamento->execute(array_merge($params, [':dispositivo' => $seriale_dispositivo]));
            
            // Aggiornamento ubicazione dispositivo principale
            $sql_update_ubicazione = "UPDATE Dispositivi SET Ubicazione = :id_ubicazione, Utente_Ultima_Mod = :utente_mod, Data_Ultima_Mod = NOW() WHERE Seriale_Inrete = :seriale_dispositivo";
            $stmt_update_ubicazione = $pdo->prepare($sql_update_ubicazione);
            $stmt_update_ubicazione->execute([
                ':id_ubicazione' => $id_ubicazione_cliente,
                ':utente_mod' => $id_utente_loggato,
                ':seriale_dispositivo' => $seriale_dispositivo
            ]);
            
            $sql_accessori = "SELECT Accessorio_Seriale FROM Bundle_Dispositivi WHERE CorpoMacchina_Seriale = :corpo_macchina";
            $stmt_accessori = $pdo->prepare($sql_accessori);
            $stmt_accessori->execute([':corpo_macchina' => $seriale_dispositivo]);
            $accessori = $stmt_accessori->fetchAll(PDO::FETCH_COLUMN);
            
            $accessori_count = 0;
            if ($accessori) {
                foreach ($accessori as $accessorio_seriale) {
                    
                    // MODIFICATO: La nota dell'accessorio ora include il seriale del dispositivo principale
                    $note_accessorio = trim($note . " (Installato come accessorio del bundle " . $seriale_dispositivo . ")");

                    $stmt_insert_spostamento->execute(array_merge($params, [
                        ':dispositivo' => $accessorio_seriale,
                        ':note' => $note_accessorio
                    ]));

                    // Aggiorna anche l'ubicazione di ogni accessorio
                    $stmt_update_ubicazione->execute([
                        ':id_ubicazione' => $id_ubicazione_cliente,
                        ':utente_mod' => $id_utente_loggato,
                        ':seriale_dispositivo' => $accessorio_seriale
                    ]);

                    $accessori_count++;
                }
            }

            $reminder_creato = false;
            if ((in_array('inserisci_reminder', $user_permessi) || $is_superuser) && !empty($tipo_scadenza) && !empty($data_scadenza)) {
                $sql_scadenza = "INSERT INTO Scadenze_Reminder (Dispositivo_Seriale, Data_Scadenza, Tipo_Scadenza, Note, Utente_Creazione_ID)
                                 VALUES (:dispositivo, :data_scadenza, :tipo, :note, :utente)";
                $stmt_scadenza = $pdo->prepare($sql_scadenza);
                $stmt_scadenza->execute([
                    ':dispositivo' => $seriale_dispositivo,
                    ':data_scadenza' => $data_scadenza,
                    ':tipo' => $tipo_scadenza,
                    ':note' => $note_scadenza,
                    ':utente' => $id_utente_loggato
                ]);
                $reminder_creato = true;
            }

            $pdo->commit();
            
            $success_message = '';
            if ($accessori_count > 0) {
                $success_message = "Bundle assegnato con successo! Registrato spostamento e aggiornata ubicazione per il corpo macchina e $accessori_count accessorio/i.";
            } else {
                $success_message = 'Dispositivo assegnato, spostamento registrato e ubicazione aggiornata con successo!';
            }
            if ($reminder_creato) {
                $success_message .= ' È stato creato anche un reminder di scadenza.';
            }
            $_SESSION['message'] = $success_message;
            $_SESSION['status'] = 'success';

        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['message'] = 'Errore durante l\'assegnazione: ' . $e->getMessage();
            $_SESSION['status'] = 'error';
        }
    }
    header('Location: assegnazione_cliente.php');
    exit();
}


if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $status = $_SESSION['status'];
    unset($_SESSION['message'], $_SESSION['status']);
}

try {
    $sql_proprieta = "SELECT ID, Nome FROM Proprieta ORDER BY Nome";
    $stmt_proprieta = $pdo->query($sql_proprieta);
    $proprieta_opzioni = $stmt_proprieta->fetchAll(PDO::FETCH_ASSOC);

    $sql_dispositivi = "SELECT 
                            d.Seriale_Inrete, ma.Nome AS Marca, mo.Nome AS Modello, d.Seriale, d.Proprieta,
                            COALESCE(b1.CorpoMacchina_Seriale, b2.CorpoMacchina_Seriale) as bundle_parent_id
                        FROM Dispositivi d
                        LEFT JOIN Marche ma ON d.MarcaID = ma.ID
                        LEFT JOIN Modelli mo ON d.ModelloID = mo.ID
                        LEFT JOIN Tipologie t ON mo.Tipologia = t.ID
                        LEFT JOIN Bundle_Dispositivi b1 ON d.Seriale_Inrete = b1.CorpoMacchina_Seriale
                        LEFT JOIN Bundle_Dispositivi b2 ON d.Seriale_Inrete = b2.Accessorio_Seriale
                        WHERE 
                            d.Seriale_Inrete NOT IN (SELECT Dispositivo FROM Spostamenti WHERE Data_Ritiro IS NULL)
                        GROUP BY d.Seriale_Inrete
                        ORDER BY ma.Nome, mo.Nome";
    $stmt_dispositivi = $pdo->query($sql_dispositivi);
    $dispositivi = $stmt_dispositivi->fetchAll(PDO::FETCH_ASSOC);

    $aziende = $_SESSION['aziende_data'] ?? [];

} catch (PDOException $e) {
    $message = 'Errore nel caricamento dei dati: ' . $e->getMessage();
    $status = 'error';
    $dispositivi = [];
    $aziende = [];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Assegna Dispositivo</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_forms.css">
    <link rel="stylesheet" href="../CSS/_search.css">
    <link rel="stylesheet" href="../CSS/_cards.css">
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="form-container">
    <h2>Assegna Dispositivo a un Cliente</h2>
    
    <?php if ($message): ?>
        <p class="message <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form action="assegnazione_cliente.php" method="POST">
        <div class="form-group">
            <label for="searchDeviceInput">Cerca un Dispositivo</label>
            <input type="text" id="searchDeviceInput" class="search-box" placeholder="Cerca per marca, modello, seriale...">
            <input type="hidden" name="seriale_dispositivo" id="seriale_dispositivo" required>
            <div id="searchResults" class="search-results-list hidden"></div>
        </div>

        <div id="bundle-accessories-container" class="card" style="display: none; margin-top: -10px; margin-bottom: 20px; background-color: #f8f9fa;">
            <h4 style="margin-top:0;">Accessori Inclusi nel Bundle:</h4>
            <ul id="bundle-accessories-list" style="list-style-type: disc; padding-left: 20px; margin: 0;"></ul>
        </div>
        
        <div class="form-group">
            <label for="nolo_cash">Nolo/Cash</label>
            <input type="text" id="nolo_cash" name="nolo_cash">
        </div>

        <div class="form-group">
            <label for="assistenza">Assistenza</label>
            <select id="assistenza" name="assistenza">
                <option value="">Seleziona...</option>
                <?php foreach ($proprieta_opzioni as $opzione): ?>
                    <option value="<?= htmlspecialchars($opzione['ID']) ?>"><?= htmlspecialchars($opzione['Nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="searchAziendaInput">Cerca Azienda di Destinazione</label>
            <input type="text" id="searchAziendaInput" class="search-box" placeholder="Cerca per Ragione Sociale..." required>
            <input type="hidden" name="ragione_sociale_azienda_dest" id="ragione_sociale_azienda_dest">
            <div id="searchAziendeResults" class="search-results-list hidden"></div>
        </div>

        <div class="form-group">
            <label for="data_installazione">Data Installazione</label>
            <input type="date" id="data_installazione" name="data_installazione" value="<?= date('Y-m-d') ?>">
        </div>

        <div class="form-group">
            <label for="note">Note Spostamento</label>
            <textarea id="note" name="note" rows="3"></textarea>
        </div>

        <?php if (in_array('inserisci_reminder', $user_permessi) || $is_superuser): ?>
        <hr style="margin: 30px 0;">
        <div class="permessi-section">
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

        <button type="submit" class="submit-button">Assegna Dispositivo</button>
    </form>
</div>

<script>
    // Lo script JS rimane invariato
    document.addEventListener('DOMContentLoaded', function() {
        const dispositivi = <?= json_encode($dispositivi) ?>;
        const aziende = <?= json_encode($aziende) ?>;
        
        const searchInputDispositivo = document.getElementById('searchDeviceInput');
        const serialeDispositivoInput = document.getElementById('seriale_dispositivo');
        const searchResultsDispositivo = document.getElementById('searchResults');
        const assistenzaSelect = document.getElementById('assistenza');
        
        const searchInputAzienda = document.getElementById('searchAziendaInput');
        const ragioneSocialeAziendaInput = document.getElementById('ragione_sociale_azienda_dest');
        const searchResultsAzienda = document.getElementById('searchAziendeResults');
        
        const bundleContainer = document.getElementById('bundle-accessories-container');
        const bundleList = document.getElementById('bundle-accessories-list');

        searchInputDispositivo.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            searchResultsDispositivo.innerHTML = '';
            searchResultsDispositivo.classList.remove('hidden');
            
            if (searchTerm.length > 1) {
                const filteredDispositivi = dispositivi.filter(dispositivo => {
                    const serInrete = dispositivo.Seriale_Inrete ? String(dispositivo.Seriale_Inrete).toLowerCase() : '';
                    const marca = dispositivo.Marca ? dispositivo.Marca.toLowerCase() : '';
                    const modello = dispositivo.Modello ? dispositivo.Modello.toLowerCase() : '';
                    const seriale = dispositivo.Seriale ? dispositivo.Seriale.toLowerCase() : '';
                    return marca.includes(searchTerm) || modello.includes(searchTerm) || seriale.includes(searchTerm) || serInrete.includes(searchTerm);
                });

                if (filteredDispositivi.length > 0) {
                    filteredDispositivi.slice(0, 10).forEach(dispositivo => {
                        const div = document.createElement('div');
                        div.className = 'search-result-item';
                        div.textContent = `${dispositivo.Marca} ${dispositivo.Modello} - Seriale: ${dispositivo.Seriale}`;
                        div.setAttribute('data-id', dispositivo.Seriale_Inrete);
                        searchResultsDispositivo.appendChild(div);
                    });
                } else {
                    searchResultsDispositivo.innerHTML = '<div class="search-result-item no-results">Nessun dispositivo trovato.</div>';
                }
            }
        });

        searchResultsDispositivo.addEventListener('click', async function(event) {
            const selectedItem = event.target.closest('.search-result-item');
            if (!selectedItem || !selectedItem.getAttribute('data-id')) return;

            const seriale = selectedItem.getAttribute('data-id');
            const selectedDispositivo = dispositivi.find(d => String(d.Seriale_Inrete) === seriale);

            if (selectedDispositivo && selectedDispositivo.bundle_parent_id) {
                const corpoMacchinaId = selectedDispositivo.bundle_parent_id;
                
                try {
                    const response = await fetch(`../PHP/get_bundle_accessories.php?id=${corpoMacchinaId}`);
                    if (!response.ok) throw new Error(`Errore dal server: ${response.statusText}`);
                    const accessories = await response.json();
                    if (accessories.error) throw new Error(`Errore PHP: ${accessories.error}`);

                    bundleList.innerHTML = ''; 
                    if (accessories && accessories.length > 0) {
                        accessories.forEach(acc => {
                            const li = document.createElement('li');
                            li.textContent = `${acc.Marca} ${acc.Modello} (S/N: ${acc.Seriale})`;
                            bundleList.appendChild(li);
                        });
                    } else {
                        bundleList.innerHTML = '<li>Nessun accessorio aggiuntivo trovato.</li>';
                    }
                    bundleContainer.style.display = 'block';
                    
                    const corpoMacchina = dispositivi.find(d => String(d.Seriale_Inrete) === corpoMacchinaId);
                    if (corpoMacchina) {
                        selectDevice(corpoMacchina.Seriale_Inrete, `${corpoMacchina.Marca} ${corpoMacchina.Modello} - Seriale: ${corpoMacchina.Seriale}`, corpoMacchina);
                    } else {
                        const responseMain = await fetch(`../PHP/get_bundle_accessories.php?action=get_device_details&id=${corpoMacchinaId}`);
                        if (!responseMain.ok) throw new Error(`Errore server corpo macchina: ${responseMain.statusText}`);
                        const corpoMacchinaDetails = await responseMain.json();
                        if (corpoMacchinaDetails.error) throw new Error(`Errore PHP corpo macchina: ${corpoMacchinaDetails.error}`);
                        selectDevice(corpoMacchinaDetails.Seriale_Inrete, `${corpoMacchinaDetails.Marca} ${corpoMacchinaDetails.Modello} - Seriale: ${corpoMacchinaDetails.Seriale}`, corpoMacchinaDetails);
                    }

                } catch (error) {
                    console.error('Errore nel recuperare i dettagli del bundle:', error);
                    alert(`Impossibile recuperare i dettagli del bundle. Dettaglio: ${error.message}`);
                    resetDeviceSelection();
                }
            } else {
                bundleContainer.style.display = 'none';
                bundleList.innerHTML = '';
                selectDevice(seriale, selectedItem.textContent, selectedDispositivo);
            }
        });

        function selectDevice(seriale, text, dispositivo) {
            searchInputDispositivo.value = text;
            serialeDispositivoInput.value = seriale;
            searchResultsDispositivo.innerHTML = '';
            searchResultsDispositivo.classList.add('hidden');
            if (dispositivo) {
                assistenzaSelect.value = dispositivo.Proprieta || '';
            }
        }

        function resetDeviceSelection() {
            searchInputDispositivo.value = '';
            serialeDispositivoInput.value = '';
            searchResultsDispositivo.innerHTML = '';
            searchResultsDispositivo.classList.add('hidden');
            bundleContainer.style.display = 'none';
            bundleList.innerHTML = '';
        }
        
        function setupSearchAziende(inputElement, resultsElement, dataArray, dataField, hiddenInputElement) {
            inputElement.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                resultsElement.innerHTML = '';
                resultsElement.classList.remove('hidden');
                if (searchTerm.length > 1) {
                    const filteredData = dataArray.filter(item => (item[dataField] && item[dataField].toLowerCase().includes(searchTerm)));
                    if (filteredData.length > 0) {
                        filteredData.slice(0, 5).forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'search-result-item';
                            div.textContent = item[dataField];
                            div.setAttribute('data-value', item[dataField]);
                            resultsElement.appendChild(div);
                        });
                    } else {
                        resultsElement.innerHTML = '<div class="search-result-item no-results">Nessun risultato trovato.</div>';
                    }
                }
            });
            resultsElement.addEventListener('click', function(event) {
                const selectedItem = event.target.closest('.search-result-item');
                if (selectedItem && selectedItem.getAttribute('data-value')) {
                    const value = selectedItem.getAttribute('data-value');
                    inputElement.value = value;
                    hiddenInputElement.value = value;
                    resultsElement.innerHTML = '';
                    resultsElement.classList.add('hidden');
                }
            });
        }
        
        setupSearchAziende(searchInputAzienda, searchResultsAzienda, aziende, 'RagioneSociale', ragioneSocialeAziendaInput);

        document.addEventListener('click', e => {
            if (!searchInputDispositivo.contains(e.target) && !searchResultsDispositivo.contains(e.target)) {
                searchResultsDispositivo.classList.add('hidden');
            }
            if (!searchInputAzienda.contains(e.target) && !searchResultsAzienda.contains(e.target)) {
                searchResultsAzienda.classList.add('hidden');
            }
        });
    });
</script>

</body>
</html>