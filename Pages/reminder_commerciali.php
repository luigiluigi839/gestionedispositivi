<?php
session_start();
require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$current_user_id = $_SESSION['user_id'] ?? null;

// Controllo sul nuovo permesso specifico per questa vista
if (!isset($current_user_id) || (!in_array('vista_reminder_commerciali', $user_permessi) && !$is_superuser)) {
    header('Location: ../Pages/dashboard.php?error=Accesso non autorizzato');
    exit();
}

$message = '';
$status = '';
if (isset($_GET['success'])) { $message = $_GET['success']; $status = 'success'; }
if (isset($_GET['error'])) { $message = $_GET['error']; $status = 'error'; }

$reminders = [];
try {
    // MODIFICATO: La query ora seleziona anche il campo Azienda
    $sql = "SELECT 
                sr.ID, sr.Tipo_Scadenza, sr.Data_Scadenza, sr.Stato, sr.Is_Privato,
                sr.Utente_Creazione_ID, sr.Azienda, sr.Note, sr.Email_Notifica,
                d.Seriale_Inrete
            FROM Scadenze_Reminder sr
            LEFT JOIN Dispositivi d ON sr.Dispositivo_Seriale = d.Seriale_Inrete
            WHERE sr.Utente_Creazione_ID = :current_user_id
            ORDER BY CASE sr.Stato WHEN 'Attivo' THEN 1 ELSE 2 END, sr.Data_Scadenza ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':current_user_id' => $current_user_id]);
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = "Errore nel recupero dei reminder: " . $e->getMessage();
    $status = 'error';
}

function formatSeriale($seriale) {
    return $seriale ? str_pad((string)$seriale, 10, '0', STR_PAD_LEFT) : 'Generico';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>I Miei Reminder</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_tables.css">
    <style>
        .action-group-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;}
        .action-group-top > div { display: flex; gap: 10px; }
        .add-button, .export-button { margin-bottom: 0; }
        .reset-btn { padding: 8px 12px; font-size: 1.2em; line-height: 1; cursor: pointer; }
        .scaduto { background-color: #f8d7da !important; color: #721c24; }
        .scaduto:hover { background-color: #f5c6cb !important; }
        
        /* CORREZIONE STILI PULSANTI */
        .action-buttons .btn { color: white !important; } /* Assicura che l'icona sia sempre bianca */
        .btn-success { background-color: #28a745; }
        .btn-success:hover { background-color: #218838; }
        .btn-secondary { background-color: #6c757d; }
        .btn-secondary:hover { background-color: #5a6268; }
        .btn-warning { background-color: #ffc107; color: #212529 !important; } /* Icona scura per sfondo giallo */
        .btn-warning:hover { background-color: #e0a800; }

        .date-picker-container { display: flex; gap: 5px; align-items: center; }
        .date-picker-input { padding: 4px; border: 1px solid #ccc; border-radius: 4px; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; color: white; text-transform: uppercase; }
        .status-attivo { background-color: #007bff; }
        .status-completata { background-color: #28a745; }
        .status-annullata { background-color: #6c757d; }
    </style>
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="table-container">
    <h2>I Miei Reminder</h2>
    <p>Questa è la lista di tutti i reminder da te creati, sia pubblici che privati.</p>
    
    <?php if ($message): ?><p class="message <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($message) ?></p><?php endif; ?>

    <div class="action-group-top">
        <div>
            <?php if (in_array('inserisci_reminder', $user_permessi) || $is_superuser): ?>
                <a href="aggiungi_reminder.php" class="add-button">Aggiungi Reminder</a>
            <?php endif; ?>
             <?php if (in_array('export_dati', $user_permessi) || $is_superuser): ?>
                <button id="exportCsvButton" class="add-button export-button">Esporta CSV</button>
            <?php endif; ?>
        </div>
    </div>

    <input type="text" id="searchInput" class="search-box" placeholder="Cerca nei tuoi reminder...">

    <div class="scroll-table-container">
        <table>
            <thead>
                <tr>
                    <th data-sortable>Dispositivo</th>
                    <th data-sortable>Azienda</th> <th data-sortable>Tipo Scadenza</th>
                    <th data-sortable data-type="date">Data Scadenza</th>
                    <th data-sortable>Stato</th>
                    <th>Azioni</th>
                </tr>
                <tr class="filter-row">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td><select id="stato-filter" class="filter-select"></select></td>
                    <td><button id="resetFiltersBtn" title="Resetta filtri" class="reset-btn">🔄</button></td>
                </tr>
            </thead>
            <tbody id="reminderTableBody">
                <?php foreach ($reminders as $r): 
                    $isScaduto = strtotime($r['Data_Scadenza']) < time();
                ?>
                    <tr data-reminder-id="<?= $r['ID'] ?>" class="<?= ($isScaduto && $r['Stato'] === 'Attivo') ? 'scaduto' : '' ?>">
                        <td><?= htmlspecialchars(formatSeriale($r['Seriale_Inrete'])) ?></td>
                        <td><?= htmlspecialchars($r['Azienda'] ?? '-') ?></td> <td>
                            <?= htmlspecialchars($r['Tipo_Scadenza']) ?>
                            <?php if ($r['Is_Privato']): ?>
                                <span title="Questo reminder è privato">🔒</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y', strtotime($r['Data_Scadenza'])) ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower(htmlspecialchars($r['Stato'])) ?>">
                                <?= htmlspecialchars($r['Stato']) ?>
                            </span>
                        </td>
                        <td class="action-buttons" data-action-cell-id="<?= $r['ID'] ?>">
                            <a href="visualizza_reminder.php?id=<?= $r['ID'] ?>" class="btn btn-visualizza" title="Dettagli">👁️</a>
                            <?php if (in_array('modifica_reminder', $user_permessi) || $is_superuser): ?>
                                 <a href="modifica_reminder.php?id=<?= $r['ID'] ?>" class="btn btn-modifica" title="Modifica">✏️</a>
                            <?php endif; ?>
                            <?php if ($r['Stato'] === 'Attivo' && (in_array('modifica_reminder', $user_permessi) || $is_superuser)): ?>
                                <a href="#" class="btn btn-warning btn-posponi" data-id="<?= $r['ID'] ?>" title="Posponi">⏰</a>
                                <a href="../PHP/aggiorna_stato_reminder.php?id=<?= $r['ID'] ?>&status=Completata" class="btn btn-success" onclick="return confirm('Marcare come COMPLETATO?');" title="Completa">✓</a>
                                <a href="../PHP/aggiorna_stato_reminder.php?id=<?= $r['ID'] ?>&status=Annullata" class="btn btn-secondary" onclick="return confirm('ANNULLARE questo reminder?');" title="Annulla">X</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const originalData = <?= json_encode($reminders) ?>;
    const tableBody = document.getElementById('reminderTableBody');
    const searchInput = document.getElementById('searchInput');
    const statoFilter = document.getElementById('stato-filter');
    const resetBtn = document.getElementById('resetFiltersBtn');
    const headers = document.querySelectorAll('th[data-sortable]');
    const exportCsvButton = document.getElementById('exportCsvButton');
    const allFilters = [statoFilter];

    const rowMap = new Map();
    tableBody.querySelectorAll('tr[data-reminder-id]').forEach(row => {
        rowMap.set(row.dataset.reminderId, row);
    });

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
            stato: statoFilter.value,
            search: searchInput.value.toUpperCase()
        };

        originalData.forEach(rowData => {
            const rowElement = rowMap.get(String(rowData.ID));
            if (!rowElement) return;

            const matchesDropdowns = (!filters.stato || rowData.Stato === filters.stato);
            
            const rowText = [
                formatSeriale(rowData.Seriale_Inrete),
                rowData.Azienda, // Aggiunto alla ricerca
                rowData.Tipo_Scadenza,
                rowData.Stato
            ].join(' ').toUpperCase();
            const matchesSearch = !filters.search || rowText.includes(filters.search);

            if (matchesDropdowns && matchesSearch) {
                rowElement.style.display = '';
            } else {
                rowElement.style.display = 'none';
            }
        });

        populateSelect(statoFilter, getUniqueValues(originalData, 'Stato'), filters.stato);
    }

    allFilters.forEach(select => select.addEventListener('change', updateUI));
    searchInput.addEventListener('input', updateUI);
    resetBtn.addEventListener('click', () => {
        searchInput.value = '';
        allFilters.forEach(select => select.value = '');
        updateUI();
    });
    
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
            const rowsToSort = Array.from(tbody.querySelectorAll('tr'));
            
            rowsToSort.sort((a, b) => {
                const aVal = a.children[columnIndex].innerText.trim();
                const bVal = b.children[columnIndex].innerText.trim();
                if (isDateColumn) {
                    const dateA = aVal ? new Date(aVal.split('/').reverse().join('-')) : new Date(0);
                    const dateB = bVal ? new Date(bVal.split('/').reverse().join('-')) : new Date(0);
                    return newDirection === 'asc' ? dateA - dateB : dateB - dateA;
                }
                const comparison = aVal.localeCompare(bVal, undefined, { numeric: true, sensitivity: 'base' });
                return newDirection === 'asc' ? comparison : -comparison;
            });
            rowsToSort.forEach(row => tbody.appendChild(row));
        });
    });

    tableBody.addEventListener('click', function(e) {
        if (!e.target.closest('.btn-posponi')) return;
        e.preventDefault();
        const button = e.target.closest('.btn-posponi');
        const reminderId = button.dataset.id;
        const actionCell = document.querySelector(`[data-action-cell-id="${reminderId}"]`);
        const originalActionContent = actionCell.innerHTML;
        const pickerContainer = document.createElement('div');
        pickerContainer.className = 'date-picker-container';
        const dateInput = document.createElement('input');
        dateInput.type = 'date';
        dateInput.className = 'date-picker-input';
        dateInput.value = new Date().toISOString().slice(0, 10);
        const confirmBtn = document.createElement('a');
        confirmBtn.href = '#';
        confirmBtn.className = 'btn btn-success';
        confirmBtn.innerHTML = '✓';
        confirmBtn.onclick = (ev) => {
            ev.preventDefault();
            const newDate = dateInput.value;
            if (newDate) {
                const [year, month, day] = newDate.split('-');
                const formattedDate = `${day}-${month}-${year}`;
                window.location.href = `../PHP/posponi_reminder.php?id=${reminderId}&new_date=${formattedDate}`;
            } else {
                alert("Per favore, seleziona una data.");
            }
        };
        const cancelBtn = document.createElement('a');
        cancelBtn.href = '#';
        cancelBtn.className = 'btn btn-secondary';
        cancelBtn.innerHTML = 'X';
        cancelBtn.onclick = (ev) => {
            ev.preventDefault();
            actionCell.innerHTML = originalActionContent;
        };
        pickerContainer.appendChild(dateInput);
        pickerContainer.appendChild(confirmBtn);
        pickerContainer.appendChild(cancelBtn);
        actionCell.innerHTML = '';
        actionCell.appendChild(pickerContainer);
        dateInput.focus();
    });

    if (exportCsvButton) {
        exportCsvButton.addEventListener('click', exportTableToCSV);
    }

    function exportTableToCSV() {
        const filename = 'miei_reminders_filtrati_' + new Date().toISOString().slice(0, 10) + '.csv';
        let csvContent = "data:text/csv;charset=utf-8,\uFEFF";
        const headers = ["Dispositivo", "Azienda", "Tipo Scadenza", "Data Scadenza", "Stato", "Privato", "Note", "Destinatari"];
        csvContent += headers.map(h => `"${h}"`).join(';') + "\r\n";
        originalData.forEach(rowData => {
            const rowElement = rowMap.get(String(rowData.ID));
            if (rowElement && rowElement.style.display !== 'none') {
                const csvRow = [
                    (rowData.Seriale_Inrete ? formatSeriale(rowData.Seriale_Inrete) : 'Generico'),
                    rowData.Azienda || '',
                    rowData.Tipo_Scadenza || '',
                    new Date(rowData.Data_Scadenza).toLocaleDateString('it-IT'),
                    rowData.Stato || '',
                    rowData.Is_Privato == '1' ? 'Sì' : 'No',
                    (rowData.Note || '').replace(/"/g, '""'),
                    rowData.Email_Notifica || ''
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
    
    function formatSeriale(seriale) {
        return seriale ? String(seriale).padStart(10, '0') : 'Generico';
    }
});
</script>

</body>
</html>