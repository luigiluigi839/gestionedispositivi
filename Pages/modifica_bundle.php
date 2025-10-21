<?php
// File: Pages/modifica_bundle.php
session_start();
require_once '../PHP/db_connect.php';

// Sicurezza e Permessi
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
    // 1. Recupera i dettagli del corpo macchina
    $sql_corpo = "SELECT d.Seriale_Inrete, d.Seriale, ma.Nome AS Marca, mo.Nome AS Modello FROM Dispositivi d
                  JOIN Marche ma ON d.MarcaID = ma.ID
                  JOIN Modelli mo ON d.ModelloID = mo.ID
                  WHERE d.Seriale_Inrete = :id";
    $stmt_corpo = $pdo->prepare($sql_corpo);
    $stmt_corpo->execute([':id' => $corpo_macchina_seriale]);
    $corpo_macchina = $stmt_corpo->fetch(PDO::FETCH_ASSOC);

    if (!$corpo_macchina) {
        die('Corpo macchina del bundle non trovato.');
    }

    // 2. Recupera gli accessori GIA' presenti nel bundle
    $sql_accessori_attuali = "SELECT d.Seriale_Inrete, d.Seriale, ma.Nome AS Marca, mo.Nome AS Modello FROM Bundle_Dispositivi b
                              JOIN Dispositivi d ON b.Accessorio_Seriale = d.Seriale_Inrete
                              JOIN Marche ma ON d.MarcaID = ma.ID
                              JOIN Modelli mo ON d.ModelloID = mo.ID
                              WHERE b.CorpoMacchina_Seriale = :id";
    $stmt_attuali = $pdo->prepare($sql_accessori_attuali);
    $stmt_attuali->execute([':id' => $corpo_macchina_seriale]);
    $accessori_attuali = $stmt_attuali->fetchAll(PDO::FETCH_ASSOC);

    // 3. Recupera gli accessori DISPONIBILI (quelli non in nessun bundle)
    $sql_disponibili = "SELECT d.Seriale_Inrete, d.Seriale, ma.Nome AS Marca, mo.Nome AS Modello
                        FROM Dispositivi d
                        JOIN Modelli mo ON d.ModelloID = mo.ID
                        JOIN Marche ma ON d.MarcaID = ma.ID
                        JOIN Tipologie t ON mo.Tipologia = t.ID
                        WHERE t.Nome LIKE 'Accessorio%'
                          AND d.Seriale_Inrete NOT IN (SELECT Accessorio_Seriale FROM Bundle_Dispositivi)";
    $stmt_disponibili = $pdo->query($sql_disponibili);
    $accessori_disponibili = $stmt_disponibili->fetchAll(PDO::FETCH_ASSOC);

    // --- MODIFICA CHIAVE ---
    // Unisci gli accessori disponibili con quelli già nel bundle per creare un'unica lista ricercabile
    $accessori_ricercabili = array_merge($accessori_disponibili, $accessori_attuali);

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
            <input type="text" id="search-accessorio" placeholder="Cerca per Seriale Inrete, seriale, marca o modello..." autocomplete="off">
            <div id="results-accessorio" class="search-results-list hidden"></div>
        </div>

        <button type="submit">Salva Modifiche Bundle</button>
    </form>
    <a href="gestione_bundle.php" class="back-link">Torna alla Gestione Bundle</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // MODIFICATO: Ora usa la lista unificata per la ricerca
    const accessoriRicercabili = <?= json_encode($accessori_ricercabili) ?>;
    const searchAccessorio = document.getElementById('search-accessorio');
    const resultsAccessorio = document.getElementById('results-accessorio');
    const accessoryListDiv = document.getElementById('accessory-list');
    const form = document.getElementById('bundle-form');

    searchAccessorio.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        resultsAccessorio.innerHTML = '';

        if (query.length < 2) {
            resultsAccessorio.classList.add('hidden');
            return;
        }

        const addedSerials = new Set(
            Array.from(accessoryListDiv.querySelectorAll('input[name="accessori[]"]'))
                 .map(input => input.value)
        );

        const filtered = accessoriRicercabili.filter(d => {
            const serialeInreteStr = String(d.Seriale_Inrete);
            
            if (addedSerials.has(serialeInreteStr)) {
                return false;
            }

            const textMatch = (serialeInreteStr.toLowerCase().includes(query) ||
                               (d.Seriale && d.Seriale.toLowerCase().includes(query)) || 
                               (d.Marca && d.Marca.toLowerCase().includes(query)) || 
                               (d.Modello && d.Modello.toLowerCase().includes(query)));
            
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

    function addAccessoryToList(device) {
        const listItem = document.createElement('div');
        listItem.className = 'accessory-list-item';
        listItem.id = `item-${device.Seriale_Inrete}`;
        
        const text = document.createElement('span');
        text.textContent = `${device.Marca} ${device.Modello} (S/N: ${device.Seriale})`;
        
        const removeBtn = document.createElement('span');
        removeBtn.className = 'remove-btn';
        removeBtn.textContent = '✖ Rimuovi';
        removeBtn.addEventListener('click', () => {
            listItem.remove();
            searchAccessorio.dispatchEvent(new Event('input'));
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
    }

    accessoryListDiv.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-btn')) {
            e.target.closest('.accessory-list-item').remove();
            searchAccessorio.dispatchEvent(new Event('input'));
        }
    });
});
</script>
</body>
</html>