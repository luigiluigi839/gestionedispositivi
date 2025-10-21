<?php
session_start();

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;

if (!isset($_SESSION['user_id']) || (!in_array('gestione_aziende', $user_permessi) && !$is_superuser)) {
    header('Location: ../index.html');
    exit();
}

$aziende = $_SESSION['aziende_data'] ?? [];

$message = $_SESSION['azienda_error'] ?? '';
$status = $message ? 'error' : '';
unset($_SESSION['azienda_error']);

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Aziende</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_tables.css">
    <link rel="stylesheet" href="../CSS/_forms.css">
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="table-container">
    <h2>Gestione Aziende</h2>
    <input type="text" id="searchInput" class="search-box" placeholder="Cerca un'azienda...">

    <?php if ($message): ?>
        <p class="message <?= htmlspecialchars($status) ?>">
            <span class="icon">&#10008;</span>
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($aziende)): ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th data-sortable="text">Ragione Sociale</th>
                        <th data-sortable="text">Indirizzo</th>
                        <th data-sortable="text">CAP</th>
                        <th data-sortable="text">Località</th>
                        <th data-sortable="text">Provincia</th>
                        <th data-sortable="text">Nazione</th>
                        <th data-sortable="text">Regione</th>
                        <th data-sortable="text">Telefono</th>
                        <th data-sortable="text">Mail</th>
                        <th data-sortable="text">Codice Fiscale</th>
                        <th data-sortable="text">Partita IVA</th>
                    </tr>
                </thead>
                <tbody id="aziendaTableBody">
                    </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="warning">Nessuna azienda trovata.</p>
    <?php endif; ?>
</div>

<script>
    const aziendeData = <?= json_encode($aziende) ?>;
    let currentData = [...aziendeData];

    const columnKeys = [
        'RagioneSociale', 'Indirizzo', 'CAP', 'Localita', 'Provincia',
        'Nazione', 'Regione', 'Telefono', 'Mail', 'CodiceFiscale', 'PartitaIva'
    ];

    function renderTable(data) {
        const tableBody = document.getElementById('aziendaTableBody');
        tableBody.innerHTML = '';

        if (data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="11" class="text-center">Nessuna azienda trovata.</td></tr>';
            return;
        }

        data.forEach(azienda => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${azienda.RagioneSociale ?? ''}</td>
                <td>${azienda.Indirizzo ?? ''}</td>
                <td>${azienda.CAP ?? ''}</td>
                <td>${azienda.Localita ?? ''}</td>
                <td>${azienda.Provincia ?? ''}</td>
                <td>${azienda.Nazione ?? ''}</td>
                <td>${azienda.Regione ?? ''}</td>
                <td>${azienda.Telefono ?? ''}</td>
                <td>${azienda.Mail ?? ''}</td>
                <td>${azienda.CodiceFiscale ?? ''}</td>
                <td>${azienda.PartitaIva ?? ''}</td>
            `;
            tableBody.appendChild(row);
        });
    }

    document.querySelectorAll('th[data-sortable]').forEach(headerCell => {
        headerCell.addEventListener('click', () => {
            const tableElement = headerCell.closest('table');
            const headerCells = tableElement.querySelectorAll('th[data-sortable]'); // Seleziona solo le colonne ordinabili
            const columnIndex = Array.from(headerCells).indexOf(headerCell);
            const sortDirection = headerCell.classList.contains('sort-asc') ? 'desc' : 'asc';
            const dataType = headerCell.dataset.sortable;
            
            const sortKey = columnKeys[columnIndex];

            headerCells.forEach(cell => cell.classList.remove('sort-asc', 'sort-desc'));
            
            const sortedData = [...currentData].sort((a, b) => {
                const aValue = a[sortKey];
                const bValue = b[sortKey];

                if (dataType === 'number') {
                    const numA = parseFloat(aValue) || 0;
                    const numB = parseFloat(bValue) || 0;
                    return sortDirection === 'asc' ? numA - numB : numB - numA;
                } else {
                    const stringA = (aValue || '').toString().toLowerCase();
                    const stringB = (bValue || '').toString().toLowerCase();
                    if (stringA < stringB) return sortDirection === 'asc' ? -1 : 1;
                    if (stringA > stringB) return sortDirection === 'asc' ? 1 : -1;
                    return 0;
                }
            });

            headerCell.classList.add(sortDirection === 'asc' ? 'sort-asc' : 'sort-desc');
            currentData = sortedData;
            renderTable(currentData);
        });
    });

    document.getElementById('searchInput').addEventListener('keyup', function() {
        const filter = this.value.toUpperCase();
        
        const filteredData = aziendeData.filter(azienda => {
            const ragioneSociale = (azienda.RagioneSociale || '').toUpperCase();
            const codiceFiscale = (azienda.CodiceFiscale || '').toUpperCase();
            const partitaIva = (azienda.PartitaIva || '').toUpperCase();
            
            return ragioneSociale.includes(filter) || 
                   codiceFiscale.includes(filter) || 
                   partitaIva.includes(filter);
        });
        
        currentData = filteredData;
        renderTable(currentData);
    });

    renderTable(currentData);
</script>

<?php require_once '../PHP/footer.php'; ?>

</body>
</html>