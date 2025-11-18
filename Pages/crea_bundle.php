<?php
// File: Pages/crea_bundle.php
session_start();
require_once '../PHP/db_connect.php';

// Sicurezza e Permessi
$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
if (!isset($_SESSION['user_id']) || (!in_array('modifica_bundle', $user_permessi) && !$is_superuser)) {
    header('Location: ../Pages/dashboard.php?error=Accesso non autorizzato');
    exit();
}

$message = $_SESSION['bundle_message'] ?? null;
$status = $_SESSION['bundle_status'] ?? null;
unset($_SESSION['bundle_message'], $_SESSION['bundle_status']);

$main_devices_available = [];
$accessories_available = [];

try {
    // MODIFICATO: Aggiunto t.Nome AS TipologiaNome a entrambe le query

    // 1. Recupera TUTTI i dispositivi disponibili (corpi macchina E accessori) per il campo principale.
    $sql_main = "SELECT d.Seriale_Inrete, d.Seriale, ma.Nome AS Marca, mo.Nome AS Modello, t.Nome AS TipologiaNome
                 FROM Dispositivi d
                 JOIN Modelli mo ON d.ModelloID = mo.ID
                 JOIN Marche ma ON d.MarcaID = ma.ID
                 JOIN Tipologie t ON mo.Tipologia = t.ID
                 WHERE
                     d.Ubicazione != 9
                     AND d.Seriale_Inrete NOT IN (SELECT Accessorio_Seriale FROM Bundle_Dispositivi)
                     AND d.Seriale_Inrete NOT IN (SELECT CorpoMacchina_Seriale FROM Bundle_Dispositivi)";
    $stmt_main = $pdo->query($sql_main);
    $main_devices_available = $stmt_main->fetchAll(PDO::FETCH_ASSOC);

    // 2. Recupera SOLO gli ACCESSORI disponibili per il campo di aggiunta accessori.
    $sql_accessories = "SELECT d.Seriale_Inrete, d.Seriale, ma.Nome AS Marca, mo.Nome AS Modello, t.Nome AS TipologiaNome
                        FROM Dispositivi d
                        JOIN Modelli mo ON d.ModelloID = mo.ID
                        JOIN Marche ma ON d.MarcaID = ma.ID
                        JOIN Tipologie t ON mo.Tipologia = t.ID
                        WHERE
                            d.Ubicazione != 9
                            AND t.Nome LIKE 'Accessorio%'
                            AND d.Seriale_Inrete NOT IN (SELECT Accessorio_Seriale FROM Bundle_Dispositivi)
                            AND d.Seriale_Inrete NOT IN (SELECT CorpoMacchina_Seriale FROM Bundle_Dispositivi)";
    $stmt_accessories = $pdo->query($sql_accessories);
    $accessories_available = $stmt_accessories->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    $message = "Errore DB: " . $e->getMessage();
    $status = 'error';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Crea Bundle Dispositivi</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_forms.css">
    <link rel="stylesheet" href="../CSS/_search.css">
    <style>
        .selected-item { background-color: #e9ecef; padding: 10px; border-radius: 5px; margin-bottom: 10px; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
        .cancel-selection { color: #dc3545; cursor: pointer; font-weight: bold; padding: 5px; }
        .accessory-list { border: 1px solid #ddd; border-radius: 5px; margin-top: 10px; min-height: 50px; }
        .accessory-list-item { display: flex; justify-content: space-between; align-items: center; padding: 8px; border-bottom: 1px solid #eee; }
        .accessory-list-item:last-child { border-bottom: none; }
        .remove-btn { color: #dc3545; cursor: pointer; font-weight: bold; padding: 5px; }
        .form-hint { font-size: 0.9em; color: #6c757d; margin-top: -15px; margin-bottom: 15px; }
    </style>
</head>
<body>
<?php require_once '../PHP/header.php'; ?>
<div class="form-container">
    <h2>Crea Nuovo Bundle</h2>
    <?php if ($message): ?><p class="message <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($message) ?></p><?php endif; ?>

    <form action="../PHP/salva_bundle.php" method="POST" id="bundle-form">
        <div class="form-group">
            <label for="search-main-device">1. Seleziona il Dispositivo Principale</label>
            <input type="text" id="search-main-device" placeholder="Cerca per seriale, marca o modello..." autocomplete="off">
            <input type="hidden" name="corpo_macchina_seriale" id="main-device-serial">
            <div id="results-main-device" class="search-results-list hidden"></div>
            <div id="selected-main-device" class="selected-item" style="display:none;"></div>
        </div>

        <div class="form-group">
            <label for="search-accessorio">2. Aggiungi Accessori al Bundle</label>
            <p class="form-hint">Cerca e seleziona un accessorio. Verrà aggiunto alla lista qui sotto.</p>
            <input type="text" id="search-accessorio" placeholder="Cerca un accessorio da aggiungere..." autocomplete="off">
            <div id="results-accessorio" class="search-results-list hidden"></div>
            <div id="accessory-list" class="accessory-list"></div>
        </div>

        <button type="submit" id="submit-bundle" disabled>Crea Bundle</button>
    </form>
    
    <a href="gestione_bundle.php" class="back-link">Torna alla Gestione Bundle</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mainDevices = <?= json_encode(array_values($main_devices_available)) ?>;
    const accessories = <?= json_encode(array_values($accessories_available)) ?>;

    // NUOVO: Variabile per memorizzare i dati del dispositivo principale selezionato
    let selectedMainDeviceObject = null;

    const searchMain = document.getElementById('search-main-device');
    const hiddenMain = document.getElementById('main-device-serial');
    const resultsMain = document.getElementById('results-main-device');
    const selectedMainDiv = document.getElementById('selected-main-device');
    
    const searchAccessorio = document.getElementById('search-accessorio');
    const resultsAccessorio = document.getElementById('results-accessorio');
    const accessoryListDiv = document.getElementById('accessory-list');
    
    const submitButton = document.getElementById('submit-bundle');
    const form = document.getElementById('bundle-form');

    function performSearch(query, data, excludeSerials = []) {
        const excludeSet = new Set(excludeSerials);
        return data.filter(d => 
            !excludeSet.has(String(d.Seriale_Inrete)) && (
                (String(d.Seriale_Inrete).toLowerCase().includes(query)) ||
                (d.Seriale || '').toLowerCase().includes(query) ||
                (d.Marca || '').toLowerCase().includes(query) ||
                (d.Modello || '').toLowerCase().includes(query)
            )
        );
    }

    searchMain.addEventListener('input', () => {
        const query = searchMain.value.toLowerCase().trim();
        resultsMain.innerHTML = '';
        if (query.length < 2) {
            resultsMain.classList.add('hidden');
            return;
        }
        const filtered = performSearch(query, mainDevices);
        
        if (filtered.length > 0) {
            filtered.slice(0, 10).forEach(device => {
                const item = document.createElement('div');
                item.className = 'search-result-item';
                item.textContent = `${device.Marca} ${device.Modello} (S/N: ${device.Seriale})`;
                item.addEventListener('click', () => selectMainDevice(device));
                resultsMain.appendChild(item);
            });
        } else {
            resultsMain.innerHTML = '<div class="search-result-item no-results">Nessun risultato</div>';
        }
        resultsMain.classList.remove('hidden');
    });

    function selectMainDevice(device) {
        // NUOVO: Salva l'oggetto completo del dispositivo principale
        selectedMainDeviceObject = device;

        const displayText = `<span>${device.Marca} ${device.Modello} (S/N: ${device.Seriale})</span> <span class="cancel-selection" title="Annulla selezione">✖</span>`;
        hiddenMain.value = device.Seriale_Inrete;
        selectedMainDiv.innerHTML = displayText;
        selectedMainDiv.style.display = 'flex';
        resultsMain.classList.add('hidden');
        searchMain.style.display = 'none';
        checkFormValidity();
    }

    selectedMainDiv.addEventListener('click', function(e) {
        if (e.target.classList.contains('cancel-selection')) {
            // NUOVO: Resetta l'oggetto del dispositivo principale
            selectedMainDeviceObject = null;
            
            hiddenMain.value = '';
            selectedMainDiv.style.display = 'none';
            searchMain.value = '';
            searchMain.style.display = 'block';
            searchMain.focus();
            checkFormValidity();
        }
    });

    searchAccessorio.addEventListener('input', () => {
        const query = searchAccessorio.value.toLowerCase().trim();
        resultsAccessorio.innerHTML = '';
        if (query.length < 2) {
            resultsAccessorio.classList.add('hidden');
            return;
        }
        
        const addedSerials = Array.from(accessoryListDiv.querySelectorAll('input[name="accessori[]"]')).map(input => input.value);
        const mainDeviceSerial = hiddenMain.value;
        const excludeList = mainDeviceSerial ? [...addedSerials, mainDeviceSerial] : addedSerials;
        
        const filtered = performSearch(query, accessories, excludeList);

        if (filtered.length > 0) {
            filtered.slice(0, 10).forEach(device => {
                const item = document.createElement('div');
                item.className = 'search-result-item';
                item.textContent = `${device.Marca} ${device.Modello} (S/N: ${device.Seriale})`;
                item.addEventListener('click', () => addAccessoryToList(device));
                resultsAccessorio.appendChild(item);
            });
        } else {
            resultsAccessorio.innerHTML = '<div class="search-result-item no-results">Nessun risultato</div>';
        }
        resultsAccessorio.classList.remove('hidden');
    });
    
    // NUOVO: Funzione per estrarre la "famiglia" del dispositivo (Office/Production)
    function getDeviceFamily(tipologiaNome) {
        if (!tipologiaNome) return 'Sconosciuta';
        if (tipologiaNome.toLowerCase().includes('production')) return 'Production';
        if (tipologiaNome.toLowerCase().includes('office')) return 'Office';
        return 'Altro';
    }

    // MODIFICATO: Logica di aggiunta accessorio con controllo e alert
    function addAccessoryToList(accessoryDevice) {
        if (!selectedMainDeviceObject) {
            alert("Errore: Seleziona prima un dispositivo principale.");
            return;
        }

        const mainBrand = selectedMainDeviceObject.Marca;
        const accessoryBrand = accessoryDevice.Marca;
        
        const mainFamily = getDeviceFamily(selectedMainDeviceObject.TipologiaNome);
        const accessoryFamily = getDeviceFamily(accessoryDevice.TipologiaNome);
        
        let warnings = [];
        if (mainBrand !== accessoryBrand) {
            warnings.push("le marche sono diverse ('" + mainBrand + "' vs '" + accessoryBrand + "')");
        }
        if (mainFamily !== 'Sconosciuta' && accessoryFamily !== 'Sconosciuta' && mainFamily !== accessoryFamily) {
            warnings.push("stai mischiando dispositivi di tipo '" + mainFamily + "' e '" + accessoryFamily + "'");
        }
        
        if (warnings.length > 0) {
            const message = "ATTENZIONE: " + warnings.join(" e ") + ".\n\nVuoi continuare comunque?";
            if (!confirm(message)) {
                searchAccessorio.value = '';
                resultsAccessorio.classList.add('hidden');
                return; // Interrompe l'aggiunta se l'utente annulla
            }
        }

        // Se l'utente conferma o non ci sono avvisi, procede con l'aggiunta
        const listItem = document.createElement('div');
        listItem.className = 'accessory-list-item';
        const text = document.createElement('span');
        text.textContent = `${accessoryDevice.Marca} ${accessoryDevice.Modello} (S/N: ${accessoryDevice.Seriale})`;
        const removeBtn = document.createElement('span');
        removeBtn.className = 'remove-btn';
        removeBtn.textContent = '✖ Rimuovi';
        removeBtn.addEventListener('click', () => {
            listItem.remove();
            checkFormValidity();
        });
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'accessori[]';
        hiddenInput.value = accessoryDevice.Seriale_Inrete;
        listItem.appendChild(text);
        listItem.appendChild(removeBtn);
        listItem.appendChild(hiddenInput);
        accessoryListDiv.appendChild(listItem);
        searchAccessorio.value = '';
        resultsAccessorio.classList.add('hidden');
        checkFormValidity();
    }

    function checkFormValidity() {
        const hasMainDevice = hiddenMain.value !== '';
        const hasAccessories = accessoryListDiv.children.length > 0;
        submitButton.disabled = !(hasMainDevice && hasAccessories);
    }

    document.addEventListener('click', e => {
        if (!searchMain.contains(e.target) && !resultsMain.contains(e.target)) {
            resultsMain.classList.add('hidden');
        }
        if (!searchAccessorio.contains(e.target) && !resultsAccessorio.contains(e.target)) {
            resultsAccessorio.classList.add('hidden');
        }
    });
});
</script>
</body>
</html>