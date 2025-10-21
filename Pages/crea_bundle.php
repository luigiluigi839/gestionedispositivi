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

try {
    // MODIFICATO: La query ora recupera tutti i dispositivi disponibili, senza separarli in anticipo.
    // Vengono esclusi solo quelli già presenti in un qualsiasi bundle.
    $sql = "SELECT d.Seriale_Inrete, d.Seriale, ma.Nome AS Marca, mo.Nome AS Modello, t.Nome AS TipologiaNome
            FROM Dispositivi d
            JOIN Modelli mo ON d.ModelloID = mo.ID
            JOIN Marche ma ON d.MarcaID = ma.ID
            JOIN Tipologie t ON mo.Tipologia = t.ID
            WHERE 
                d.Seriale_Inrete NOT IN (SELECT Accessorio_Seriale FROM Bundle_Dispositivi)
                AND
                d.Seriale_Inrete NOT IN (SELECT CorpoMacchina_Seriale FROM Bundle_Dispositivi)";
    
    $stmt = $pdo->query($sql);
    $all_devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = "Errore DB: " . $e->getMessage();
    $status = 'error';
    $all_devices = [];
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
            <label for="search-main-device">1. Seleziona il Dispositivo o Accessorio Principale</label>
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
    const allDevices = <?= json_encode(array_values($all_devices)) ?>;

    const searchMain = document.getElementById('search-main-device');
    const hiddenMain = document.getElementById('main-device-serial');
    const resultsMain = document.getElementById('results-main-device');
    const selectedMainDiv = document.getElementById('selected-main-device');
    
    const searchAccessorio = document.getElementById('search-accessorio');
    const resultsAccessorio = document.getElementById('results-accessorio');
    const accessoryListDiv = document.getElementById('accessory-list');
    
    const submitButton = document.getElementById('submit-bundle');
    const form = document.getElementById('bundle-form');

    function handleSearch(input, data, resultsContainer, onSelect, excludeSerials = []) {
        input.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            resultsContainer.innerHTML = '';
            if (query.length < 2) {
                resultsContainer.classList.add('hidden');
                return;
            }
            
            const excludeSet = new Set(excludeSerials);
            const filtered = data.filter(d => 
                !excludeSet.has(String(d.Seriale_Inrete)) && (
                    (String(d.Seriale_Inrete).toLowerCase().includes(query)) ||
                    (d.Seriale || '').toLowerCase().includes(query) ||
                    (d.Marca || '').toLowerCase().includes(query) ||
                    (d.Modello || '').toLowerCase().includes(query)
                )
            );
            
            if (filtered.length > 0) {
                filtered.slice(0, 10).forEach(device => {
                    const item = document.createElement('div');
                    item.className = 'search-result-item';
                    item.textContent = `${device.Marca} ${device.Modello} (S/N: ${device.Seriale})`;
                    item.addEventListener('click', () => onSelect(device));
                    resultsContainer.appendChild(item);
                });
            } else {
                resultsContainer.innerHTML = '<div class="search-result-item no-results">Nessun risultato</div>';
            }
            resultsContainer.classList.remove('hidden');
        });
    }

    // Ricerca per Dispositivo Principale
    searchMain.addEventListener('input', () => {
        const query = searchMain.value.toLowerCase().trim();
        resultsMain.innerHTML = '';
        if (query.length < 2) {
            resultsMain.classList.add('hidden');
            return;
        }
        handleSearch(searchMain, allDevices, resultsMain, selectMainDevice);
    });

    function selectMainDevice(device) {
        const displayText = `<span>${device.Marca} ${device.Modello} (S/N: ${device.Seriale})</span> <span class="cancel-selection" title="Annulla selezione">✖</span>`;
        hiddenMain.value = device.Seriale_Inrete;
        selectedMainDiv.innerHTML = displayText;
        selectedMainDiv.style.display = 'flex';
        resultsMain.classList.add('hidden');
        searchMain.style.display = 'none'; // Nasconde la barra di ricerca
        checkFormValidity();
    }

    // Annullamento della selezione del dispositivo principale
    selectedMainDiv.addEventListener('click', function(e) {
        if (e.target.classList.contains('cancel-selection')) {
            hiddenMain.value = '';
            selectedMainDiv.style.display = 'none';
            searchMain.value = '';
            searchMain.style.display = 'block';
            searchMain.focus();
            checkFormValidity();
        }
    });

    // Ricerca per Accessori
    searchAccessorio.addEventListener('input', () => {
        const addedSerials = Array.from(accessoryListDiv.querySelectorAll('input[name="accessori[]"]')).map(input => input.value);
        const mainDeviceSerial = hiddenMain.value;
        const excludeList = mainDeviceSerial ? [...addedSerials, mainDeviceSerial] : addedSerials;
        handleSearch(searchAccessorio, allDevices, resultsAccessorio, addAccessoryToList, excludeList);
    });

    function addAccessoryToList(device) {
        const listItem = document.createElement('div');
        listItem.className = 'accessory-list-item';
        const text = document.createElement('span');
        text.textContent = `${device.Marca} ${device.Modello} (S/N: ${device.Seriale})`;
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
        hiddenInput.value = device.Seriale_Inrete;
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