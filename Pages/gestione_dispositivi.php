<?php
session_start();
require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;

// MODIFICATO: Controllo per visualizzare la pagina master della dashboard
if (!isset($_SESSION['user_id']) || (!in_array('dashboard_dispositivi', $user_permessi) && !$is_superuser)) {
    header('Location: ../Pages/dashboard.php?error=Accesso non autorizzato');
    exit();
}

$message = '';
$status = '';
$dispositivi = [];

if (isset($_GET['success'])) { $message = $_GET['success']; $status = 'success'; } 
elseif (isset($_GET['error'])) { $message = $_GET['error']; $status = 'error'; }

try {
    $sql = "SELECT 
                d.*, 
                ma.Nome AS MarcaNome, 
                mo.Nome AS ModelloNome, 
                t.Nome AS TipologiaNome,
                ub.Nome AS UbicazioneNome,
                s.Nome AS StatoNome,
                p.Nome AS ProprietaNome,
                CONCAT(u.Nome, ' ', u.Cognome) AS UtenteAssegnato,
                ut.Nome AS UtenteModNome,
                ut.Cognome AS UtenteModCognome
            FROM Dispositivi AS d
            LEFT JOIN Marche AS ma ON d.MarcaID = ma.ID
            LEFT JOIN Modelli AS mo ON d.ModelloID = mo.ID
            LEFT JOIN Tipologie AS t ON mo.Tipologia = t.ID
            LEFT JOIN Ubicazioni AS ub ON d.Ubicazione = ub.ID
            LEFT JOIN Stati AS s ON d.Stato = s.ID
            LEFT JOIN Proprieta AS p ON d.Proprieta = p.ID
            LEFT JOIN Utenti AS u ON d.Prenotato_Da = u.ID
            LEFT JOIN Utenti AS ut ON d.Utente_Ultima_Mod = ut.ID
            ORDER BY ma.Nome, mo.Nome";
            
    $stmt = $pdo->query($sql);
    $dispositivi = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = "Errore nel recupero dei dati: " . $e->getMessage();
    $status = 'error';
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Dispositivi</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_tables.css">
    <link rel="stylesheet" href="../CSS/_forms.css">
    <style>
        .action-group-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;}
        .action-group-top > div { display: flex; gap: 10px; }
        .add-button, .export-button { margin-bottom: 0; }
        .reset-btn { padding: 8px 12px; font-size: 1.2em; line-height: 1; cursor: pointer; }
    </style>
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="table-container">
    <h2>Gestione Dispositivi</h2>
    <input type="text" id="searchInput" class="search-box" placeholder="Cerca in tutta la tabella...">
    
    <?php if ($message): ?><p class="message <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($message) ?></p><?php endif; ?>

    <?php if (!empty($dispositivi)): ?>
    <div class="action-group-top">
        <div>
            <?php if (in_array('modifica_gestione_dispositivi', $user_permessi) || $is_superuser): ?>
                <a href="aggiungi_dispositivo.php" class="add-button">Aggiungi Dispositivo</a>
            <?php endif; ?>
            
            <?php if (in_array('export_dati', $user_permessi) || $is_superuser): ?>
                <button id="exportCsvButton" class="add-button export-button">Esporta CSV</button>
            <?php endif; ?>
        </div>
    </div>
    <div class="scroll-table-container">
        <table>
            <thead>
                <tr>
                    <th data-sortable>Seriale Inrete</th>
                    <th data-sortable>Marca</th>
                    <th data-sortable>Modello</th>
                    <th data-sortable>Tipologia</th>
                    <th data-sortable>Numero di Serie</th>
                    <th data-sortable>Ubicazione</th>
                    <th data-sortable>Utente Assegnato</th>
                    <th>Azioni</th>
                </tr>
                <tr class="filter-row">
                    <td></td>
                    <td><select id="marca-filter" class="filter-select"></select></td>
                    <td><select id="modello-filter" class="filter-select"></select></td>
                    <td><select id="tipologia-filter" class="filter-select"></select></td>
                    <td></td>
                    <td><select id="ubicazione-filter" class="filter-select"></select></td>
                    <td><select id="utente-filter" class="filter-select"></select></td>
                    <td><button id="resetFiltersBtn" title="Resetta filtri" class="reset-btn">🔄</button></td>
                </tr>
            </thead>
            <tbody id="dispositivoTableBody">
                <?php foreach ($dispositivi as $dispositivo): ?>
                    <tr>
                        <td><?= htmlspecialchars(str_pad($dispositivo['Seriale_Inrete'], 10, '0', STR_PAD_LEFT)) ?></td>
                        <td><?= htmlspecialchars($dispositivo['MarcaNome']) ?></td>
                        <td><?= htmlspecialchars($dispositivo['ModelloNome']) ?></td>
                        <td><?= htmlspecialchars($dispositivo['TipologiaNome'] ?? 'N/D') ?></td>
                        <td><?= htmlspecialchars($dispositivo['Seriale']) ?></td>
                        <td><?= htmlspecialchars($dispositivo['UbicazioneNome']) ?></td>
                        <td><?= htmlspecialchars($dispositivo['UtenteAssegnato'] ?? '') ?></td>
                        <td class="action-buttons">
                            <?php if (in_array('visualizza_gestione_dispositivi', $user_permessi) || $is_superuser): ?>
                                <a href="visualizza_dispositivo.php?id=<?= $dispositivo['Seriale_Inrete'] ?>" class="btn btn-visualizza">👁️</a>
                            <?php endif; ?>
                            
                            <?php if (in_array('modifica_gestione_dispositivi', $user_permessi) || $is_superuser): ?>
                                <a href="modifica_dispositivo.php?id=<?= htmlspecialchars($dispositivo['Seriale_Inrete']) ?>" class="btn btn-modifica">✏️</a>
                            <?php endif; ?>
                            
                            <?php if (in_array('elimina_gestione_dispositivi', $user_permessi) || $is_superuser): ?>
                                <a href="../PHP/elimina_dispositivo.php?id=<?= htmlspecialchars($dispositivo['Seriale_Inrete']) ?>"
                                   class="btn btn-elimina"
                                   onclick="return confirm('ATTENZIONE: Stai per eliminare il dispositivo. Vuoi continuare?');">
                                   🗑️
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p class="warning">Nessun dispositivo trovato.</p>
    <?php endif; ?>
</div>

<script>
// Il codice Javascript per i filtri rimane invariato
document.addEventListener('DOMContentLoaded', function() {
    const originalData = <?= json_encode($dispositivi) ?>;
    const tableBody = document.getElementById('dispositivoTableBody');
    const rows = Array.from(tableBody.querySelectorAll('tr'));
    
    const searchInput = document.getElementById('searchInput');
    const marcaSelect = document.getElementById('marca-filter');
    const modelloSelect = document.getElementById('modello-filter');
    const tipologiaSelect = document.getElementById('tipologia-filter');
    const ubicazioneSelect = document.getElementById('ubicazione-filter');
    const utenteSelect = document.getElementById('utente-filter');
    const resetBtn = document.getElementById('resetFiltersBtn');
    const headers = document.querySelectorAll('th[data-sortable]');
    const exportCsvButton = document.getElementById('exportCsvButton');

    const allFilters = [marcaSelect, modelloSelect, tipologiaSelect, ubicazioneSelect, utenteSelect];

    if (exportCsvButton) {
        exportCsvButton.addEventListener('click', exportTableToCSV);
    }

    function exportTableToCSV() {
        const filename = 'dispositivi_filtrati_' + new Date().toISOString().slice(0, 10) + '.csv';
        let csvContent = "data:text/csv;charset=utf-8,\uFEFF";
        
        const headers = ["Seriale_Inrete", "Marca", "Modello", "Tipologia", "Seriale", "Ubicazione", "UtenteAssegnato"];
        csvContent += headers.map(h => `"${h}"`).join(';') + "\r\n";
        
        rows.forEach((row, index) => {
            if (row.style.display !== 'none') {
                const rowData = originalData[index];
                const csvRow = [
                    String(rowData.Seriale_Inrete).padStart(10, '0'),
                    rowData.MarcaNome || '',
                    rowData.ModelloNome || '',
                    rowData.TipologiaNome || '',
                    rowData.Seriale || '',
                    rowData.UbicazioneNome || '',
                    rowData.UtenteAssegnato || ''
                ].map(value => `"${String(value).replace(/"/g, '""')}"`).join(';');
                csvContent += csvRow + "\r\n";
            }
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function getUniqueValues(data, key) {
        return [...new Set(data.map(item => item[key]).filter(Boolean))].sort();
    }

    function populateSelect(selectElement, options, selectedValue) {
        const currentVal = selectedValue;
        selectElement.innerHTML = `<option value="">Tutti</option>`;
        options.forEach(value => {
            const option = new Option(value, value);
            if (value === currentVal) { option.selected = true; }
            selectElement.add(option);
        });
    }

    function updateUI() {
        const filters = {
            marca: marcaSelect.value,
            modello: modelloSelect.value,
            tipologia: tipologiaSelect.value,
            ubicazione: ubicazioneSelect.value,
            utente: utenteSelect.value
        };

        const searchText = searchInput.value.toUpperCase();
        rows.forEach((row, index) => {
            const rowData = originalData[index];
            if (!rowData) return;

            const matchesDropdowns = 
                (!filters.marca || rowData.MarcaNome === filters.marca) &&
                (!filters.modello || rowData.ModelloNome === filters.modello) &&
                (!filters.tipologia || rowData.TipologiaNome === filters.tipologia) &&
                (!filters.ubicazione || rowData.UbicazioneNome === filters.ubicazione) &&
                (!filters.utente || rowData.UtenteAssegnato === filters.utente);

            const textMatch = !searchText || (row.textContent || row.innerText).toUpperCase().includes(searchText);
            row.style.display = (matchesDropdowns && textMatch) ? '' : 'none';
        });

        let tempFilter;
        tempFilter = originalData.filter(i => (!filters.modello || i.ModelloNome === filters.modello) && (!filters.tipologia || i.TipologiaNome === filters.tipologia) && (!filters.ubicazione || i.UbicazioneNome === filters.ubicazione) && (!filters.utente || i.UtenteAssegnato === filters.utente));
        populateSelect(marcaSelect, getUniqueValues(tempFilter, 'MarcaNome'), filters.marca);
        
        tempFilter = originalData.filter(i => (!filters.marca || i.MarcaNome === filters.marca) && (!filters.modello || i.ModelloNome === filters.modello) && (!filters.ubicazione || i.UbicazioneNome === filters.ubicazione) && (!filters.utente || i.UtenteAssegnato === filters.utente));
        populateSelect(tipologiaSelect, getUniqueValues(tempFilter, 'TipologiaNome'), filters.tipologia);

        tempFilter = originalData.filter(i => (!filters.marca || i.MarcaNome === filters.marca) && (!filters.modello || i.ModelloNome === filters.modello) && (!filters.tipologia || i.TipologiaNome === filters.tipologia) && (!filters.utente || i.UtenteAssegnato === filters.utente));
        populateSelect(ubicazioneSelect, getUniqueValues(tempFilter, 'UbicazioneNome'), filters.ubicazione);

        tempFilter = originalData.filter(i => (!filters.marca || i.MarcaNome === filters.marca) && (!filters.modello || i.ModelloNome === filters.modello) && (!filters.tipologia || i.TipologiaNome === filters.tipologia) && (!filters.ubicazione || i.UbicazioneNome === filters.ubicazione));
        populateSelect(utenteSelect, getUniqueValues(tempFilter, 'UtenteAssegnato'), filters.utente);

        tempFilter = originalData.filter(i => (!filters.marca || i.MarcaNome === filters.marca) && (!filters.tipologia || i.TipologiaNome === filters.tipologia) && (!filters.ubicazione || i.UbicazioneNome === filters.ubicazione) && (!filters.utente || i.UtenteAssegnato === filters.utente));
        populateSelect(modelloSelect, getUniqueValues(tempFilter, 'ModelloNome'), filters.modello);
    }
    
    allFilters.forEach(select => select.addEventListener('change', updateUI));
    searchInput.addEventListener('input', updateUI);
    resetBtn.addEventListener('click', () => {
        searchInput.value = '';
        allFilters.forEach(select => select.value = '');
        updateUI();
    });
    
    headers.forEach(headerCell => {
        headerCell.addEventListener('click', () => {
            const tbody = tableBody;
            const columnIndex = Array.from(headerCell.parentNode.children).indexOf(headerCell);
            const currentDirection = headerCell.classList.contains('sort-asc') ? 'asc' : (headerCell.classList.contains('sort-desc') ? 'desc' : null);
            const isDateColumn = headerCell.dataset.type === 'date';
            const newDirection = (currentDirection === 'asc') ? 'desc' : 'asc';

            headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
            headerCell.classList.add(newDirection === 'asc' ? 'sort-asc' : 'sort-desc');

            const rowsToSort = Array.from(tbody.querySelectorAll('tr'));
            rowsToSort.sort((a, b) => {
                const aVal = a.children[columnIndex].innerText.trim();
                const bVal = b.children[columnIndex].innerText.trim();
                if (isDateColumn) {
                    const dateA = aVal ? new Date(aVal.split('-').reverse().join('-')) : new Date(0);
                    const dateB = bVal ? new Date(bVal.split('-').reverse().join('-')) : new Date(0);
                    return newDirection === 'asc' ? dateA - dateB : dateB - dateA;
                }
                const comparison = aVal.localeCompare(bVal, undefined, { numeric: true, sensitivity: 'base' });
                return newDirection === 'asc' ? comparison : -comparison;
            });
            rowsToSort.forEach(row => tbody.appendChild(row));
        });
    });

    updateUI();
});
</script>

<?php require_once '../PHP/footer.php'; ?>

</body>
</html>