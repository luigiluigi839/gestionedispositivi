<?php
// File: Pages/modifica_bundle.php
session_start();
require_once '../PHP/db_connect.php';

// Sicurezza e Permessi (invariato)
$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
if (!isset($_SESSION['user_id']) || (!in_array('modifica_bundle', $user_permessi) && !$is_superuser)) {
    header('Location: ../Pages/dashboard.php?error=Accesso non autorizzato');
    exit();
}

$corpo_macchina_seriale = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$corpo_macchina_seriale) {
    die('ID del corpo macchina del bundle non specificato.');
}

$message = $_SESSION['bundle_message'] ?? null;
$status = $_SESSION['bundle_status'] ?? null;
unset($_SESSION['bundle_message'], $_SESSION['bundle_status']);

try {
    // MODIFICATO: Aggiunto d.Ubicazione alla query
    $sql_corpo = "SELECT d.Seriale_Inrete, d.Seriale, d.Ubicazione, ma.Nome AS Marca, mo.Nome AS Modello, t.Nome AS TipologiaNome 
                  FROM Dispositivi d
                  JOIN Marche ma ON d.MarcaID = ma.ID
                  JOIN Modelli mo ON d.ModelloID = mo.ID
                  JOIN Tipologie t ON mo.Tipologia = t.ID
                  WHERE d.Seriale_Inrete = :id";
    $stmt_corpo = $pdo->prepare($sql_corpo);
    $stmt_corpo->execute([':id' => $corpo_macchina_seriale]);
    $corpo_macchina = $stmt_corpo->fetch(PDO::FETCH_ASSOC);

    if (!$corpo_macchina) {
        die('Corpo macchina del bundle non trovato.');
    }

    // Query accessori attuali (invariata)
    $sql_accessori_attuali = "SELECT d.Seriale_Inrete, d.Seriale, ma.Nome AS Marca, mo.Nome AS Modello, t.Nome AS TipologiaNome 
                              FROM Bundle_Dispositivi b
                              JOIN Dispositivi d ON b.Accessorio_Seriale = d.Seriale_Inrete
                              JOIN Marche ma ON d.MarcaID = ma.ID
                              JOIN Modelli mo ON d.ModelloID = mo.ID
                              JOIN Tipologie t ON mo.Tipologia = t.ID
                              WHERE b.CorpoMacchina_Seriale = :id";
    $stmt_attuali = $pdo->prepare($sql_accessori_attuali);
    $stmt_attuali->execute([':id' => $corpo_macchina_seriale]);
    $accessori_attuali = $stmt_attuali->fetchAll(PDO::FETCH_ASSOC);

    // Query accessori disponibili (invariata)
    $sql_disponibili = "SELECT d.Seriale_Inrete, d.Seriale, ma.Nome AS Marca, mo.Nome AS Modello, t.Nome as TipologiaNome
                        FROM Dispositivi d
                        JOIN Modelli mo ON d.ModelloID = mo.ID
                        JOIN Marche ma ON d.MarcaID = ma.ID
                        JOIN Tipologie t ON mo.Tipologia = t.ID
                        WHERE d.Ubicazione != 9
                          AND t.Nome LIKE 'Accessorio%'
                          AND d.Seriale_Inrete NOT IN (SELECT Accessorio_Seriale FROM Bundle_Dispositivi)";
    $stmt_disponibili = $pdo->query($sql_disponibili);
    $accessori_disponibili = $stmt_disponibili->fetchAll(PDO::FETCH_ASSOC);
    
    $accessori_ricercabili = array_merge($accessori_disponibili, $accessori_attuali);
    $accessori_ricercabili = array_values(array_unique($accessori_ricercabili, SORT_REGULAR));

} catch (PDOException $e) {
    $message = "Errore DB: " . $e->getMessage();
    $status = 'error';
    $corpo_macchina = $corpo_macchina ?? [];
    $accessori_attuali = $accessori_attuali ?? [];
    $accessori_ricercabili = [];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Modifica Bundle</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_forms.css">
    <link rel="stylesheet" href="../CSS/_search.css">
    <style>
        .readonly-info { background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .accessory-list-item { display: flex; justify-content: space-between; align-items: center; padding: 8px; border-bottom: 1px solid #ddd; }
        .remove-btn { color: #dc3545; cursor: pointer; font-weight: bold; }
        .accessory-list { border: 1px solid #ddd; border-radius: 5px; margin-top: 10px; min-height: 50px; }
    </style>
</head>
<body>
<?php require_once '../PHP/header.php'; ?>
<div class="form-container">
    <h2>Modifica Bundle</h2>
    <?php if ($message): ?><p class="message <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($message) ?></p><?php endif; ?>

    <div class="readonly-info">
        <h4>Dispositivo Principale</h4>
        <p><?= htmlspecialchars(($corpo_macchina['Marca'] ?? '') . ' ' . ($corpo_macchina['Modello'] ?? '') . ' (S/N: ' . ($corpo_macchina['Seriale'] ?? '') . ')') ?></p>
    </div>

    <form action="../PHP/salva_modifica_bundle.php" method="POST" id="bundle-form">
        <input type="hidden" name="corpo_macchina_seriale" value="<?= htmlspecialchars($corpo_macchina_seriale) ?>">
        
        <div class="form-group">
            <label>Accessori nel Bundle</label>
            <div id="accessory-list" class="accessory-list">
                <?php foreach ($accessori_attuali as $acc): ?>
                    <div class="accessory-list-item" id="item-<?= $acc['Seriale_Inrete'] ?>">
                        <span><?= htmlspecialchars($acc['Marca'] . ' ' . $acc['Modello'] . ' (S/N: ' . $acc['Seriale'] . ')') ?></span>
                        <span class="remove-btn">✖ Rimuovi</span>
                        <input type="hidden" name="accessori[]" value="<?= $acc['Seriale_Inrete'] ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group">
            <label for="search-accessorio">Aggiungi un nuovo accessorio</label>
            <input type="text" id="search-accessorio" placeholder="Cerca per seriale, marca o modello..." autocomplete="off">
            <div id="results-accessorio" class="search-results-list hidden"></div>
        </div>

        <button type="submit">Salva Modifiche Bundle</button>
    </form>
    <a href="gestione_bundle.php" class="back-link">Torna alla Gestione Bundle</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const accessoriRicercabili = <?= json_encode($accessori_ricercabili) ?>;
    const mainDeviceData = <?= json_encode($corpo_macchina) ?>;

    const searchAccessorio = document.getElementById('search-accessorio');
    const resultsAccessorio = document.getElementById('results-accessorio');
    const accessoryListDiv = document.getElementById('accessory-list');
    const form = document.getElementById('bundle-form');

    // MODIFICATO: Event listener sul submit del form per il controllo finale
    form.addEventListener('submit', function(event) {
        if (mainDeviceData && mainDeviceData.Ubicazione == 9) {
            // Messaggio aggiornato per riflettere la nuova logica
            const message = "ATTENZIONE: Questo bundle è installato presso un cliente.\n\n" +
                          "1. I NUOVI accessori aggiunti verranno installati automaticamente presso lo stesso cliente con la data di oggi.\n\n" +
                          "2. Gli accessori RIMOSSI verranno scollegati dal bundle, ma rimarranno installati presso il cliente (NON verranno fatti rientrare).\n\n" +
                          "Vuoi procedere?";
            
            if (!confirm(message)) {
                event.preventDefault(); // Annulla l'invio del form se l'utente clicca "Annulla"
            }
        }
    });

    // La logica di ricerca e aggiunta con controllo marche/tipologia rimane invariata
    searchAccessorio.addEventListener('input', function() {
        // ... (codice ricerca invariato) ...
        const query = this.value.toLowerCase().trim();
        resultsAccessorio.innerHTML = '';
        if (query.length < 2) { resultsAccessorio.classList.add('hidden'); return; }
        const addedSerials = new Set(Array.from(accessoryListDiv.querySelectorAll('input[name="accessori[]"]')).map(input => input.value));
        const filtered = accessoriRicercabili.filter(d => {
            const serialeInreteStr = String(d.Seriale_Inrete);
            if (addedSerials.has(serialeInreteStr)) return false;
            const textMatch = (serialeInreteStr.toLowerCase().includes(query) || (d.Seriale && d.Seriale.toLowerCase().includes(query)) || (d.Marca && d.Marca.toLowerCase().includes(query)) || (d.Modello && d.Modello.toLowerCase().includes(query)));
            return textMatch;
        });
        filtered.slice(0, 10).forEach(device => {
            const item = document.createElement('div');
            item.className = 'search-result-item';
            item.textContent = `${device.Marca} ${device.Modello} (S/N: ${device.Seriale})`;
            item.addEventListener('click', () => addAccessoryToList(device));
            resultsAccessorio.appendChild(item);
        });
        resultsAccessorio.classList.remove('hidden');
    });

    function getDeviceFamily(tipologiaNome) {
        // ... (codice invariato) ...
        if (!tipologiaNome) return 'Sconosciuta';
        if (tipologiaNome.toLowerCase().includes('production')) return 'Production';
        if (tipologiaNome.toLowerCase().includes('office')) return 'Office';
        return 'Altro';
    }

    function addAccessoryToList(accessoryDevice) {
        // ... (logica di controllo marche/tipologia invariata) ...
        const mainBrand = mainDeviceData.Marca;
        const accessoryBrand = accessoryDevice.Marca;
        const mainFamily = getDeviceFamily(mainDeviceData.TipologiaNome);
        const accessoryFamily = getDeviceFamily(accessoryDevice.TipologiaNome);
        let warnings = [];
        if (mainBrand !== accessoryBrand) { warnings.push("le marche sono diverse ('" + mainBrand + "' vs '" + accessoryBrand + "')"); }
        if (mainFamily !== 'Sconosciuta' && accessoryFamily !== 'Sconosciuta' && mainFamily !== accessoryFamily) { warnings.push("stai mischiando dispositivi di tipo '" + mainFamily + "' e '" + accessoryFamily + "'"); }
        if (warnings.length > 0) {
            const message = "ATTENZIONE: " + warnings.join(" e ") + ".\n\nVuoi continuare comunque?";
            if (!confirm(message)) {
                searchAccessorio.value = '';
                resultsAccessorio.classList.add('hidden');
                return;
            }
        }
        
        // ... (logica di aggiunta elemento DOM invariata) ...
        const listItem = document.createElement('div');
        listItem.className = 'accessory-list-item';
        listItem.id = `item-${accessoryDevice.Seriale_Inrete}`;
        const text = document.createElement('span');
        text.textContent = `${accessoryDevice.Marca} ${accessoryDevice.Modello} (S/N: ${accessoryDevice.Seriale})`;
        const removeBtn = document.createElement('span');
        removeBtn.className = 'remove-btn';
        removeBtn.textContent = '✖ Rimuovi';
        removeBtn.addEventListener('click', () => { listItem.remove(); searchAccessorio.dispatchEvent(new Event('input')); });
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
    }

    accessoryListDiv.addEventListener('click', function(e) {
        // ... (logica rimozione invariata) ...
        if (e.target.classList.contains('remove-btn')) {
            e.target.closest('.accessory-list-item').remove();
            searchAccessorio.dispatchEvent(new Event('input'));
        }
    });

    document.addEventListener('click', e => {
        // ... (logica chiusura dropdown invariata) ...
        if (!searchAccessorio.contains(e.target) && !resultsAccessorio.contains(e.target)) {
            resultsAccessorio.classList.add('hidden');
        }
    });
});
</script>
</body>
</html>