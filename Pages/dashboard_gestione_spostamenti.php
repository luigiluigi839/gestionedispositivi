<?php
// File: Pages/dashboard_gestione_spostamenti.php
session_start();
require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$id_utente_loggato = $_SESSION['user_id'];

// Controllo permesso per visualizzare la lista
if (!isset($id_utente_loggato) || (!in_array('dashboard_gestione_spostamenti', $user_permessi) && !$is_superuser)) {
    header('Location: dashboard.php?error=Accesso non autorizzato');
    exit();
}

$message = '';
$status = '';
if (isset($_SESSION['form_message'])) {
    $message = $_SESSION['form_message']['text'];
    $status = $_SESSION['form_message']['status'];
    unset($_SESSION['form_message']);
}

$grouped_spostamenti = [];

try {
    // 1. Recupera tutti gli spostamenti con informazioni complete sul bundle
    $query = "
        SELECT 
            s.ID, s.Dispositivo, d.Seriale, d.Seriale_Inrete, ma.Nome as Marca, mo.Nome as Modello, 
            s.Azienda, s.Data_Install, s.Data_Ritiro, s.Nolo_Cash, s.Assistenza,
            COALESCE(b1.CorpoMacchina_Seriale, b2.CorpoMacchina_Seriale) as bundle_parent_id,
            CASE WHEN b1.CorpoMacchina_Seriale IS NOT NULL THEN 1 ELSE 0 END as is_main_device,
            CASE WHEN (b1.CorpoMacchina_Seriale IS NOT NULL OR b2.Accessorio_Seriale IS NOT NULL) THEN 1 ELSE 0 END AS is_bundle_part
        FROM Spostamenti s
        LEFT JOIN Dispositivi d ON s.Dispositivo = d.Seriale_Inrete
        LEFT JOIN Modelli mo ON d.ModelloID = mo.ID
        LEFT JOIN Marche ma ON d.MarcaID = ma.ID
        LEFT JOIN Bundle_Dispositivi b1 ON d.Seriale_Inrete = b1.CorpoMacchina_Seriale
        LEFT JOIN Bundle_Dispositivi b2 ON d.Seriale_Inrete = b2.Accessorio_Seriale
        GROUP BY s.ID
        ORDER BY s.Data_Install DESC, s.Azienda, is_main_device DESC";
    $stmt = $pdo->query($query);
    $all_spostamenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Raggruppa gli spostamenti per installazione
    $installations = [];
    foreach ($all_spostamenti as $spostamento) {
        $key = $spostamento['Azienda'] . '|' . $spostamento['Data_Install'];
        if ($spostamento['bundle_parent_id']) {
            $key .= '|' . $spostamento['bundle_parent_id'];
        } else {
            // Per i dispositivi singoli, usiamo l'ID dello spostamento per garantire unicità
            $key .= '|single-' . $spostamento['ID'];
        }
        if (!isset($installations[$key])) {
            $installations[$key] = [];
        }
        $installations[$key][] = $spostamento;
    }

    // 3. Struttura i dati per la visualizzazione
    foreach ($installations as $group_key => $group) {
        $main_device = null;
        $accessories = [];
        // Cerca il dispositivo principale nel gruppo
        foreach ($group as $spostamento) {
            if ($spostamento['is_main_device'] || !$spostamento['bundle_parent_id']) {
                $main_device = $spostamento;
                break;
            }
        }
        // Se non trova un main device esplicito (es. solo accessori), prende il primo
        if (!$main_device) $main_device = $group[0];

        // Aggiunge gli altri come accessori
        foreach ($group as $spostamento) {
            if ($spostamento['ID'] !== $main_device['ID']) {
                $accessories[] = $spostamento;
            }
        }
        $grouped_spostamenti[$group_key] = ['main' => $main_device, 'accessori' => $accessories];
    }

} catch (PDOException $e) {
    $message = "Errore di connessione: " . $e->getMessage();
    $status = 'error';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Storico Spostamenti</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_tables.css">
    <link rel="stylesheet" href="../CSS/_forms.css">
    <style>
        .bundle-row { border-left: 5px solid #007bff; cursor: pointer; user-select: none; }
        .accessory-row { background-color: #f8f9fa; }
        .toggle-icon { display: inline-block; margin-right: 10px; transition: transform 0.2s ease-in-out; font-size: 0.8em; }
        .bundle-row.expanded .toggle-icon { transform: rotate(90deg); }
    </style>
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="table-container">
    <h2>Cronologia Installazioni/Spostamenti</h2>
    <input type="text" id="searchInput" class="search-box" placeholder="Cerca per Seriale, Seriale Inrete o Azienda...">

    <?php if ($message): ?>
        <p class="message <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    
    <div class="scroll-table-container">
        <table>
            <thead>
                <tr>
                    <th data-sortable>Seriale/Dispositivo</th><th data-sortable>Marca</th><th data-sortable>Modello</th> 
                    <th data-sortable>Azienda</th><th data-sortable data-type="date">Data Install.</th><th data-sortable data-type="date">Data Ritiro</th>
                    <th>Nolo/Cash</th><th>Assistenza</th><th>Azioni</th>
                </tr>
                <tr class="filter-row">
                     <td></td>
                     <td><select id="marca-filter" class="filter-select"><option value="">Tutte</option></select></td>
                     <td><select id="modello-filter" class="filter-select"><option value="">Tutti</option></select></td>
                     <td></td><td></td><td></td>
                     <td><select id="nolo-filter" class="filter-select"><option value="">Tutti</option></select></td>
                     <td><select id="assistenza-filter" class="filter-select"><option value="">Tutti</option></select></td>
                     <td><button id="resetFiltersBtn" title="Resetta filtri" class="reset-btn">🔄</button></td>
                </tr>
            </thead>
            <tbody id="tableBody">
                <?php if (!empty($grouped_spostamenti)): ?>
                    <?php foreach ($grouped_spostamenti as $group_key => $group): 
                        $main = $group['main'];
                        $is_bundle = !empty($group['accessori']);
                    ?>
                        <tr class="<?= $is_bundle ? 'bundle-row' : '' ?>" data-group-key="<?= htmlspecialchars($group_key) ?>">
                            <td><strong><?= $is_bundle ? '<span class="toggle-icon">►</span>' : '' ?><?= htmlspecialchars($main['Seriale'] ?? $main['Dispositivo'] ?? 'N/D') ?></strong></td>
                            <td><strong><?= htmlspecialchars($main['Marca'] ?? 'N/D') ?></strong></td>
                            <td><strong><?= htmlspecialchars($main['Modello'] ?? 'N/D') ?></strong></td>
                            <td><strong><?= htmlspecialchars($main['Azienda']) ?></strong></td>
                            <td><strong><?= date('d/m/Y', strtotime($main['Data_Install'])) ?></strong></td>
                            <td><strong><?= $main['Data_Ritiro'] ? date('d/m/Y', strtotime($main['Data_Ritiro'])) : '-' ?></strong></td>
                            <td><?= htmlspecialchars($main['Nolo_Cash'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($main['Assistenza'] ?? '-') ?></td>
                            <td class="action-buttons">
                                <a href="visualizza_spostamento.php?id=<?= $main['ID'] ?>" class="btn btn-visualizza" title="Visualizza">👁️</a>
                                <?php if (in_array('modifica_spostamenti', $user_permessi) || $is_superuser): ?>
                                    <a href="modifica_spostamenti.php?id=<?= $main['ID'] ?>" class="btn btn-modifica" title="Modifica">✏️</a>
                                <?php endif; ?>
                                <?php if (in_array('elimina_spostamenti', $user_permessi) || $is_superuser):
                                    $confirm_message = $is_bundle
                                        ? "ATTENZIONE: Stai eliminando lo spostamento di un bundle. Verranno eliminati anche gli spostamenti di tutti gli altri componenti per questa installazione. Continuare?"
                                        : "Sei sicuro di voler eliminare questo record di spostamento?";
                                ?>
                                    <a href="../PHP/elimina_spostamento.php?id=<?= $main['ID'] ?>" class="btn btn-elimina" onclick="return confirm('<?= addslashes($confirm_message) ?>');" title="Elimina">🗑️</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php foreach ($group['accessori'] as $accessory): ?>
                            <tr class="accessory-row" data-group-key="<?= htmlspecialchars($group_key) ?>" style="display: none;">
                                <td><?= htmlspecialchars($accessory['Seriale'] ?? $accessory['Dispositivo'] ?? 'N/D') ?></td>
                                <td><?= htmlspecialchars($accessory['Marca'] ?? 'N/D') ?></td>
                                <td><?= htmlspecialchars($accessory['Modello'] ?? 'N/D') ?></td>
                                <td></td><td></td><td></td><td></td><td></td><td></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9">Nessun record trovato.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const groupedData = <?= json_encode($grouped_spostamenti, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    const tableBody = document.getElementById('tableBody');
    
    const searchInput = document.getElementById('searchInput');
    const marcaFilter = document.getElementById('marca-filter');
    const modelloFilter = document.getElementById('modello-filter');
    const noloFilter = document.getElementById('nolo-filter');
    const assistenzaFilter = document.getElementById('assistenza-filter');
    const resetBtn = document.getElementById('resetFiltersBtn');
    const headers = document.querySelectorAll('th[data-sortable]');
    
    // Mappatura tra elementi select e chiavi dei dati
    const filterMap = {
        'marca-filter': 'Marca',
        'modello-filter': 'Modello',
        'nolo-filter': 'Nolo_Cash',
        'assistenza-filter': 'Assistenza'
    };
    const allSelects = [marcaFilter, modelloFilter, noloFilter, assistenzaFilter];

    function getUniqueValues(data, key) {
        let values = new Set();
        for (const groupKey in data) {
            const group = data[groupKey];
            if (group.main && group.main[key]) values.add(group.main[key]);
            group.accessori.forEach(acc => {
                if (acc && acc[key]) values.add(acc[key]);
            });
        }
        return [...values].sort();
    }
    
    function populateSelect(selectElement, options, selectedValue) {
        const currentVal = selectedValue;
        selectElement.innerHTML = `<option value="">${selectElement.id === 'marca-filter' ? 'Tutte' : 'Tutti'}</option>`;
        options.forEach(value => {
            const option = new Option(value, value);
            if (value === currentVal) {
                option.selected = true;
            }
            selectElement.add(option);
        });
    }

    function updateUI() {
        const currentFilters = {
            search: searchInput.value.toUpperCase(),
            'marca-filter': marcaFilter.value,
            'modello-filter': modelloFilter.value,
            'nolo-filter': noloFilter.value,
            'assistenza-filter': assistenzaFilter.value,
        };
        
        // Funzione helper per filtrare i dati basandosi su un set di filtri
        // Può escludere un filtro, utile per popolare le opzioni in modo contestuale
        const getFilteredData = (filters, excludeKey = null) => {
            let filteredGroups = {};
            for (const [groupKey, group] of Object.entries(groupedData)) {
                const allDevicesInGroup = [group.main, ...group.accessori];
                
                let groupMatches = false;
                for (const deviceData of allDevicesInGroup) {
                    if (!deviceData) continue;
                    
                    let deviceMatchesAllFilters = true;

                    // 1. Controllo ricerca globale
                    const rowText = [deviceData.Seriale, deviceData.Seriale_Inrete, deviceData.Azienda].join(' ').toUpperCase();
                    if (filters.search && !rowText.includes(filters.search)) {
                        deviceMatchesAllFilters = false;
                    }
                    
                    // 2. Controllo filtri select
                    for (const select of allSelects) {
                        if (select.id === excludeKey) continue; // Salta il filtro da escludere
                        
                        const filterValue = filters[select.id];
                        const dataKey = filterMap[select.id];
                        
                        if (filterValue && deviceData[dataKey] !== filterValue) {
                            deviceMatchesAllFilters = false;
                            break;
                        }
                    }
                    
                    if (deviceMatchesAllFilters) {
                        groupMatches = true;
                        break; 
                    }
                }
                
                if (groupMatches) {
                    filteredGroups[groupKey] = group;
                }
            }
            return filteredGroups;
        };
        
        // Popola ogni select in modo contestuale agli altri filtri
        allSelects.forEach(select => {
            const dataKey = filterMap[select.id];
            const dataForThisSelect = getFilteredData(currentFilters, select.id);
            const options = getUniqueValues(dataForThisSelect, dataKey);
            populateSelect(select, options, select.value);
        });
        
        // Determina la visibilità finale delle righe usando TUTTI i filtri
        const finalVisibleData = getFilteredData(currentFilters);
        const visibleGroupKeys = new Set(Object.keys(finalVisibleData));
        
        tableBody.querySelectorAll('tr[data-group-key]').forEach(row => {
            const groupKey = row.dataset.groupKey;
            const isVisible = visibleGroupKeys.has(groupKey);
            
            const parentRow = tableBody.querySelector(`tr.bundle-row[data-group-key="${groupKey.replace(/["'|]/g, '\\$&')}"]`);
            const isExpanded = parentRow && parentRow.classList.contains('expanded');

            if (row.classList.contains('bundle-row') || !row.classList.contains('accessory-row')) {
                 row.style.display = isVisible ? '' : 'none';
            } else if (row.classList.contains('accessory-row')) {
                 row.style.display = (isVisible && isExpanded) ? '' : 'none';
            }
        });
    }

    allSelects.forEach(select => select.addEventListener('change', updateUI));
    searchInput.addEventListener('input', updateUI);
    
    resetBtn.addEventListener('click', () => {
        searchInput.value = '';
        allSelects.forEach(select => select.value = '');
        tableBody.querySelectorAll('.bundle-row.expanded').forEach(row => row.classList.remove('expanded'));
        updateUI();
    });
    
    // Inizializzazione
    updateUI();

    headers.forEach(headerCell => {
        headerCell.addEventListener('click', () => {
            const tbody = tableBody;
            const columnIndex = Array.from(headerCell.parentNode.children).indexOf(headerCell);
            const currentDirection = headerCell.classList.contains('sort-asc') ? 'asc' : (headerCell.classList.contains('sort-desc') ? 'desc' : null);
            const isDateColumn = headerCell.dataset.type === 'date';
            const newDirection = (currentDirection === 'asc') ? 'desc' : 'asc';
            
            headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
            headerCell.classList.add(newDirection === 'asc' ? 'sort-asc' : 'sort-desc');
            
            const rowsToSort = Array.from(tbody.querySelectorAll('tr:not(.accessory-row)'));
            
            rowsToSort.sort((a, b) => {
                const aVal = a.children[columnIndex].innerText.trim();
                const bVal = b.children[columnIndex].innerText.trim();
                if (isDateColumn) {
                    const dateA = aVal && aVal !== '-' ? new Date(aVal.split('/').reverse().join('-')) : new Date(0);
                    const dateB = bVal && bVal !== '-' ? new Date(bVal.split('/').reverse().join('-')) : new Date(0);
                    return newDirection === 'asc' ? dateA - dateB : dateB - dateA;
                }
                const comparison = aVal.localeCompare(bVal, undefined, { numeric: true, sensitivity: 'base' });
                return newDirection === 'asc' ? comparison : -comparison;
            });
            
            rowsToSort.forEach(row => {
                tbody.appendChild(row);
                const groupKey = row.dataset.groupKey;
                if (groupKey) {
                    const accessoryRows = tableBody.querySelectorAll(`.accessory-row[data-group-key="${groupKey.replace(/["'|]/g, '\\$&')}"]`);
                    accessoryRows.forEach(accRow => tbody.appendChild(accRow));
                }
            });
        });
    });

    tableBody.addEventListener('click', function(e) {
        const bundleRow = e.target.closest('.bundle-row');
        if (bundleRow && !e.target.closest('.action-buttons')) {
            bundleRow.classList.toggle('expanded');
            updateUI(); // Ridisegna per mostrare/nascondere accessori in base allo stato expanded
        }
    });
});
</script>

</body>
</html>
