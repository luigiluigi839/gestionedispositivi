<?php
session_start();

// Includi il file di connessione al database
require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;

// Controllo sul permesso specifico della dashboard
if (!isset($_SESSION['user_id']) || (!in_array('dashboard_ricondizionamenti', $user_permessi) && !$is_superuser)) {
    header('Location: ../Pages/dashboard.php?error=Accesso non autorizzato');
    exit();
}

// Recupera i messaggi di stato dall'URL
$message = '';
$status = '';
if (isset($_GET['success'])) { $message = $_GET['success']; $status = 'success'; }
if (isset($_GET['error'])) { $message = $_GET['error']; $status = 'error'; }

$ricondizionamenti = [];
// Rimossa la query per popolare i filtri qui, verrà fatto dinamicamente da JS

try {
    // La query principale per i dati rimane invariata
    $sql = "SELECT
                r.ID, r.Dispositivo_Seriale, r.Data_Inizio, r.Data_Fine, r.Stato_Globale,
                u.Nome AS OperatoreNome, u.Cognome AS OperatoreCognome,
                d.Seriale AS SerialeFisico, ma.Nome AS Marca, mo.Nome AS Modello,
                s.Nome AS GradoFinaleNome
            FROM Ricondizionamenti r
            LEFT JOIN Utenti u ON r.Operatore_ID = u.ID
            LEFT JOIN Dispositivi d ON r.Dispositivo_Seriale = d.Seriale_Inrete
            LEFT JOIN Marche ma ON d.MarcaID = ma.ID
            LEFT JOIN Modelli mo ON d.ModelloID = mo.ID
            LEFT JOIN Ricondizionamenti_Dettagli rd ON r.ID = rd.Ricondizionamento_ID
            LEFT JOIN Stati s ON rd.Grado_Finale = s.ID
            ORDER BY r.Data_Inizio DESC";
    $stmt = $pdo->query($sql);
    $ricondizionamenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = "Errore nel recupero dei dati: " . $e->getMessage();
    $status = 'error';
}

function formatSeriale($seriale) {
    return str_pad((string)$seriale, 10, '0', STR_PAD_LEFT);
}

function formatDate($dateString) {
    if (!$dateString || $dateString === '0000-00-00 00:00:00') return ''; // Ritorna stringa vuota per N/D
    try {
        $date = new DateTime($dateString);
        return $date->format('d/m/Y H:i');
    } catch (Exception $e) {
        return 'Data non valida';
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Ricondizionamenti</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_tables.css">
    
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="table-container">
    <h2>Gestione Ricondizionamenti</h2>

    <?php if ($message): ?><p class="message <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($message) ?></p><?php endif; ?>

    <?php if (in_array('inserisci_ricondizionamenti', $user_permessi) || $is_superuser): ?>
        <a href="nuovo_ricondizionamento.php" class="add-button">Avvia Nuovo Ricondizionamento</a>
    <?php endif; ?>

    <input type="text" id="search-input" class="search-box" placeholder="Cerca per Seriale, Marca, Modello o Operatore...">

    <div class="filter-group">
        <div>
            <label for="marca-filter">Marca:</label>
            <select id="marca-filter" class="filter-select"><option value="">Tutte</option></select>
        </div>
        <div>
            <label for="modello-filter">Modello:</label>
            <select id="modello-filter" class="filter-select"><option value="">Tutti</option></select>
        </div>
        <div>
            <label for="operatore-filter">Operatore:</label>
            <select id="operatore-filter" class="filter-select"><option value="">Tutti</option></select>
        </div>
        <div>
            <label for="stato-filter">Stato Intervento:</label>
            <select id="stato-filter" class="filter-select"><option value="">Tutti</option></select>
        </div>
        <div>
            <label for="grado-filter">Grado Finale:</label>
            <select id="grado-filter" class="filter-select"><option value="">Tutti</option></select>
        </div>
        <button id="reset-filters" style="align-self: flex-end;">Resetta Filtri</button>
    </div>

    <?php if (!empty($ricondizionamenti)): ?>
    <div class="scroll-table-container">
        <table id="ricondizionamenti-table">
            <thead>
                <tr>
                    <th data-sortable>Seriale Inrete</th>
                    <th data-sortable>Seriale Fisico</th>
                    <th data-sortable>Marca</th>
                    <th data-sortable>Modello</th>
                    <th data-sortable data-type="date">Data Inizio</th>
                    <th data-sortable>Operatore</th>
                    <th data-sortable>Stato Globale</th>
                    <th data-sortable>Grado Finale</th>
                    <th data-sortable data-type="date">Data Fine</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody id="table-body">
                <?php foreach ($ricondizionamenti as $r): ?>
                    <tr>
                        <td><?= formatSeriale($r['Dispositivo_Seriale']) ?></td>
                        <td><?= htmlspecialchars($r['SerialeFisico'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['Marca'] ?? '') ?></td>
                        <td><?= htmlspecialchars($r['Modello'] ?? '') ?></td>
                        <td><?= formatDate($r['Data_Inizio']) ?></td>
                        <td><?= htmlspecialchars(($r['OperatoreNome'] ?? '') . ' ' . ($r['OperatoreCognome'] ?? '')) ?></td>
                        <td>
                            <span class='status-badge status-<?= strtolower(str_replace([' ', '/'], '-', $r['Stato_Globale'])) ?>'>
                                <?= htmlspecialchars($r['Stato_Globale']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($r['GradoFinaleNome'] ?? '') ?></td>
                        <td><?= formatDate($r['Data_Fine']) ?></td>
                        <td class="action-buttons">
                            <?php if (in_array('visualizza_ricondizionamento', $user_permessi) || in_array('modifica_ricondizionamenti', $user_permessi) || $is_superuser): ?>
                                <a href="gestisci_ricondizionamento.php?id=<?= $r['ID'] ?>" class="btn btn-visualizza"><?= (in_array($r['Stato_Globale'], ['COMPLETATO', 'DEMOLITO']) ? '👁️' : '✏️') ?></a>
                            <?php endif; ?>

                            <?php if (in_array('elimina_ricondizionamenti', $user_permessi) || $is_superuser): ?>
                                <a href="../PHP/elimina_ricondizionamento.php?id=<?= $r['ID'] ?>" class="btn btn-elimina" onclick="return confirm('Sei sicuro di voler eliminare questo record di ricondizionamento? L\'azione è irreversibile.');">
                                    🗑️
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p id="no-results" style="display: none; text-align: center; color: #dc3545; font-weight: bold;">Nessun risultato trovato.</p>
    <?php else: ?>
        <p class="warning">Nessun ricondizionamento trovato nel database.</p>
    <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Memorizza i dati originali caricati da PHP
    const originalData = <?= json_encode($ricondizionamenti) ?>;

    // Riferimenti agli elementi DOM
    const searchInput = document.getElementById('search-input');
    const marcaFilter = document.getElementById('marca-filter');
    const modelloFilter = document.getElementById('modello-filter');
    const operatoreFilter = document.getElementById('operatore-filter');
    const statoFilter = document.getElementById('stato-filter');
    const gradoFilter = document.getElementById('grado-filter');
    const tableBody = document.getElementById('table-body');
    const noResults = document.getElementById('no-results');
    const resetBtn = document.getElementById('reset-filters');
    const headers = document.querySelectorAll('th[data-sortable]');

    // Mappa per associare gli ID dei select ai nomi delle proprietà nei dati
    const filterMap = {
        'marca-filter': 'Marca',
        'modello-filter': 'Modello',
        'operatore-filter': 'OperatoreCompleto', // Useremo un campo combinato
        'stato-filter': 'Stato_Globale',
        'grado-filter': 'GradoFinaleNome'
    };
    const allSelects = [marcaFilter, modelloFilter, operatoreFilter, statoFilter, gradoFilter];

    // Pre-processa i dati originali per aggiungere 'OperatoreCompleto'
    const processedData = originalData.map(item => ({
        ...item,
        OperatoreCompleto: `${item.OperatoreNome || ''} ${item.OperatoreCognome || ''}`.trim()
    }));

    // Funzione helper per ottenere valori unici da un array di oggetti
    function getUniqueValues(data, key) {
        return [...new Set(data.map(item => item[key]).filter(Boolean))].sort();
    }

    // Funzione per popolare un <select> con opzioni
    function populateSelect(selectElement, options, selectedValue) {
        const currentVal = selectedValue; // Memorizza il valore selezionato
        const defaultTextMap = {
            'marca-filter': 'Tutte le Marche',
            'modello-filter': 'Tutti i Modelli',
            'operatore-filter': 'Tutti gli Operatori',
            'stato-filter': 'Tutti gli Stati',
            'grado-filter': 'Tutti i Gradi'
        };
        selectElement.innerHTML = `<option value="">${defaultTextMap[selectElement.id] || 'Tutti'}</option>`; // Opzione default
        options.forEach(value => {
            const option = new Option(value, value);
            // Ripristina la selezione precedente se il valore è ancora tra le opzioni disponibili
            if (value === currentVal) {
                option.selected = true;
            }
            selectElement.add(option);
        });
    }

    // Funzione principale per applicare i filtri e aggiornare la UI
    function applyFiltersAndPopulate() {
        // Leggi i valori correnti di tutti i filtri
        const currentFilters = {
            search: searchInput.value.toLowerCase(),
            marca: marcaFilter.value,
            modello: modelloFilter.value,
            operatore: operatoreFilter.value,
            stato: statoFilter.value,
            grado: gradoFilter.value
        };

        // Filtra i dati processati in base ai valori correnti
        const filteredData = processedData.filter(item => {
            const searchTextMatch = !currentFilters.search ||
                (item.Dispositivo_Seriale && String(item.Dispositivo_Seriale).toLowerCase().includes(currentFilters.search)) ||
                (item.SerialeFisico && item.SerialeFisico.toLowerCase().includes(currentFilters.search)) ||
                (item.Marca && item.Marca.toLowerCase().includes(currentFilters.search)) ||
                (item.Modello && item.Modello.toLowerCase().includes(currentFilters.search)) ||
                (item.OperatoreCompleto && item.OperatoreCompleto.toLowerCase().includes(currentFilters.search));

            const marcaMatch = !currentFilters.marca || item.Marca === currentFilters.marca;
            const modelloMatch = !currentFilters.modello || item.Modello === currentFilters.modello;
            const operatoreMatch = !currentFilters.operatore || item.OperatoreCompleto === currentFilters.operatore;
            const statoMatch = !currentFilters.stato || item.Stato_Globale === currentFilters.stato;
            const gradoMatch = !currentFilters.grado || item.GradoFinaleNome === currentFilters.grado;

            return searchTextMatch && marcaMatch && modelloMatch && operatoreMatch && statoMatch && gradoMatch;
        });

        // Mostra/Nascondi le righe della tabella
        const visibleIds = new Set(filteredData.map(item => item.ID));
        Array.from(tableBody.rows).forEach((row, index) => {
            // Assumendo che l'ordine delle righe corrisponda a processedData (potrebbe essere necessario un ID sulla riga)
            const rowData = processedData[index]; // Potrebbe non essere corretto se la tabella è stata ordinata
            // Alternativa: usare un data-id sulla riga
            // const rowId = parseInt(row.dataset.id); // Aggiungere data-id="< ?= $r['ID'] ? >" alla <tr> in PHP
             if (rowData && visibleIds.has(rowData.ID)) {
                 row.style.display = '';
             } else {
                 row.style.display = 'none';
             }
        });

        // Aggiorna le opzioni dei select in base ai dati filtrati
        allSelects.forEach(select => {
            const key = filterMap[select.id];
            // Per popolare, usa i dati filtrati ESCLUDENDO il filtro corrente
            const tempFilters = { ...currentFilters };
            delete tempFilters[select.id.split('-')[0]]; // Rimuove il filtro corrente per il popolamento

            const relevantDataForSelect = processedData.filter(item => {
                 const searchTextMatch = !tempFilters.search ||
                     (item.Dispositivo_Seriale && String(item.Dispositivo_Seriale).toLowerCase().includes(tempFilters.search)) ||
                     (item.SerialeFisico && item.SerialeFisico.toLowerCase().includes(tempFilters.search)) ||
                     (item.Marca && item.Marca.toLowerCase().includes(tempFilters.search)) ||
                     (item.Modello && item.Modello.toLowerCase().includes(tempFilters.search)) ||
                     (item.OperatoreCompleto && item.OperatoreCompleto.toLowerCase().includes(tempFilters.search));

                 const marcaMatch = !tempFilters.marca || item.Marca === tempFilters.marca;
                 const modelloMatch = !tempFilters.modello || item.Modello === tempFilters.modello;
                 const operatoreMatch = !tempFilters.operatore || item.OperatoreCompleto === tempFilters.operatore;
                 const statoMatch = !tempFilters.stato || item.Stato_Globale === tempFilters.stato;
                 const gradoMatch = !tempFilters.grado || item.GradoFinaleNome === tempFilters.grado;
                 return searchTextMatch && marcaMatch && modelloMatch && operatoreMatch && statoMatch && gradoMatch;
            });


            const options = getUniqueValues(relevantDataForSelect, key);
            populateSelect(select, options, currentFilters[select.id.split('-')[0]]);
        });

        // Mostra/Nascondi messaggio "Nessun risultato"
        noResults.style.display = filteredData.length === 0 ? 'block' : 'none';
    }

    // --- LOGICA DI ORDINAMENTO ---
   // --- LOGICA DI ORDINAMENTO ---
    headers.forEach(headerCell => {
        headerCell.addEventListener('click', () => {
            const tbody = tableBody;
            const columnIndex = Array.from(headerCell.parentNode.children).indexOf(headerCell);
            const currentDirection = headerCell.classList.contains('sort-asc') ? 'asc' : (headerCell.classList.contains('sort-desc') ? 'desc' : null);
            const isDateColumn = headerCell.dataset.type === 'date';
            const newDirection = (currentDirection === 'asc') ? 'desc' : 'asc';

            // Resetta classi di ordinamento su tutte le intestazioni
            headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
            // Applica la classe alla colonna cliccata
            headerCell.classList.add(newDirection === 'asc' ? 'sort-asc' : 'sort-desc');

            // --- RIMUOVI QUESTE RIGHE ---
            // headers.forEach(h => h.style.setProperty('--sort-icon', '" \\2195"')); // Freccia default
            // headerCell.style.setProperty('--sort-icon', newDirection === 'asc' ? '" \\2191"' : '" \\2193"'); // Freccia specifica
            // --- FINE RIGHE DA RIMUOVERE ---


            // Ordina le righe
            const rowsToSort = Array.from(tbody.querySelectorAll('tr'));

            rowsToSort.sort((a, b) => {
                const aVal = a.children[columnIndex].innerText.trim();
                const bVal = b.children[columnIndex].innerText.trim();

                if (isDateColumn) {
                    const dateA = aVal ? new Date(aVal.split(' ')[0].split('/').reverse().join('-')) : new Date(0);
                    const dateB = bVal ? new Date(bVal.split(' ')[0].split('/').reverse().join('-')) : new Date(0);
                    return newDirection === 'asc' ? dateA - dateB : dateB - dateA;
                }

                const comparison = aVal.localeCompare(bVal, undefined, { numeric: true, sensitivity: 'base' });
                return newDirection === 'asc' ? comparison : -comparison;
            });

            // Riaggiungi le righe ordinate al tbody
            rowsToSort.forEach(row => tbody.appendChild(row));
        });
    });
    
    // Aggiungi event listener ai filtri e alla ricerca
    searchInput.addEventListener('keyup', applyFiltersAndPopulate);
    allSelects.forEach(select => select.addEventListener('change', applyFiltersAndPopulate));
    resetBtn.addEventListener('click', () => {
        searchInput.value = '';
        allSelects.forEach(select => select.value = '');
        headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc')); // Resetta ordinamento visivo
        // Potresti voler ripristinare l'ordine originale qui se necessario
        applyFiltersAndPopulate();
    });

    // Esegui la funzione una volta al caricamento per popolare i select iniziali
    applyFiltersAndPopulate();
});
</script>

<?php require_once '../PHP/footer.php'; ?>

</body>
</html>