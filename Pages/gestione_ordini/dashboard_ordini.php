<?php
session_start();
require_once '../../PHP/db_connect.php'; // Attenzione al percorso relativo

$user_id = $_SESSION['user_id'] ?? null;
$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;

// Permesso base per visualizzare la pagina
if (!$user_id || (!in_array('dashboard_ordini', $user_permessi) && !$is_superuser)) {
    header('Location: ../dashboard.php?error=Accesso non autorizzato');
    exit();
}

// Determina le condizioni della query SQL in base ai permessi
$where_conditions = [];
$params = [];
$show_inserito_da_column = false; // Colonna visibile solo a certi ruoli

if ($is_superuser) {
    // Superuser vede tutto
    $show_inserito_da_column = true;
} elseif (in_array('ordini_gestione_amm', $user_permessi)) {
    // Amministrazione: Stati da 3 a 13
    $where_conditions[] = "o.StatoOrdine_ID BETWEEN 3 AND 13";
    $show_inserito_da_column = true;
} elseif (in_array('ordini_gestione_tec', $user_permessi)) {
    // Tecnica: Stati da 8 a 12 (In Preparazione -> Fatturato)
    $where_conditions[] = "o.StatoOrdine_ID BETWEEN 8 AND 12";
    $show_inserito_da_column = true;
} elseif (in_array('ordini_gestione_comm', $user_permessi)) {
    // Commerciale: Solo i propri ordini
    $where_conditions[] = "o.Agente_ID = :user_id";
    $params[':user_id'] = $user_id;
    // La colonna 'Inserito Da' non serve, vede solo i suoi
} else {
    // Utente base con solo 'dashboard_ordini': vede solo i propri ordini come agente
    $where_conditions[] = "o.Agente_ID = :user_id";
    $params[':user_id'] = $user_id;
}

$sql = "SELECT
            o.ID, o.NumeroOrdine, o.DataInserimento, o.Azienda_RagioneSociale,
            so.NomeStato AS StatoNome, so.ID as StatoID,
            ag.Nome AS AgenteNome, ag.Cognome AS AgenteCognome, ag.ID as AgenteID,
            prop.Nome AS ProprietarioNome, prop.ID as ProprietarioID
        FROM Ordini o
        JOIN StatiOrdine so ON o.StatoOrdine_ID = so.ID
        LEFT JOIN Utenti ag ON o.Agente_ID = ag.ID
        LEFT JOIN Proprieta prop ON o.InserimentoContoDi_ID = prop.ID";

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(' AND ', $where_conditions);
}
$sql .= " ORDER BY o.DataInserimento DESC";

$ordini = [];
$message = $_SESSION['order_message'] ?? '';
$status = $_SESSION['order_status'] ?? '';
unset($_SESSION['order_message'], $_SESSION['order_status']);

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ordini = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Errore nel recupero degli ordini: " . $e->getMessage();
    $status = 'error';
}

// Prepara dati per filtri (verranno popolati da JS)
$stati_options = [];
$agenti_options = [];
$proprietari_options = [];

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Ordini</title>
    <link rel="stylesheet" href="../../CSS/_base.css">    <link rel="stylesheet" href="../../CSS/_tables.css">  <link rel="stylesheet" href="../../CSS/_forms.css">   <style>
        .filter-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            font-size: 0.9em;
        }
        .date-filter input {
            width: calc(50% - 5px); /* Divide lo spazio per i due input data */
        }
        #resetFiltersBtn {
             padding: 8px 15px;
             cursor: pointer;
             align-self: end; /* Allinea il pulsante in basso */
        }
    </style>
</head>
<body>

<?php require_once '../../PHP/header.php'; // Attenzione al percorso ?>

<div class="table-container">
    <h2>Gestione Ordini</h2>

    <?php if ($message): ?>
        <p class="message <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if (in_array('ordini_inserisci', $user_permessi) || $is_superuser): ?>
        <a href="crea_ordine.php" class="add-button">Aggiungi Nuovo Ordine</a>
    <?php endif; ?>

    <input type="text" id="searchInput" class="search-box" placeholder="Cerca per Numero Ordine, Cliente, Agente...">

    <div class="filter-container">
        <div class="filter-group">
            <label for="stato-filter">Stato Ordine:</label>
            <select id="stato-filter" class="filter-select"><option value="">Tutti</option></select>
        </div>
         <?php if ($show_inserito_da_column): // Mostra filtro Agente solo se vede ordini di altri ?>
        <div class="filter-group">
            <label for="agente-filter">Agente:</label>
            <select id="agente-filter" class="filter-select"><option value="">Tutti</option></select>
        </div>
        <?php endif; ?>
        <div class="filter-group">
            <label for="proprietario-filter">Conto Di:</label>
            <select id="proprietario-filter" class="filter-select"><option value="">Tutti</option></select>
        </div>
        <div class="filter-group date-filter">
            <label>Data Inserimento (Da - A):</label>
            <div>
                <input type="date" id="date-from-filter">
                <input type="date" id="date-to-filter">
            </div>
        </div>
        <button id="resetFiltersBtn">Resetta Filtri</button>
    </div>

    <div class="scroll-table-container">
        <table>
            <thead>
                <tr>
                    <th data-sortable>Numero Ordine</th>
                    <th data-sortable data-type="date">Data Inserimento</th>
                    <th data-sortable>Cliente</th>
                    <th data-sortable>Stato</th>
                    <th data-sortable>Agente</th>
                    <th data-sortable>Conto Di</th>
                    <?php if ($show_inserito_da_column): ?>
                        <th data-sortable>Inserito Da</th>
                    <?php endif; ?>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody id="ordiniTableBody">
                <tr><td colspan="<?= $show_inserito_da_column ? 8 : 7 ?>" style="text-align: center;">Caricamento...</td></tr>
            </tbody>
        </table>
    </div>
     <p id="no-results" style="display: none; text-align: center; color: #dc3545; font-weight: bold;">Nessun ordine trovato con i filtri applicati.</p>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dati iniziali caricati da PHP
    const originalData = <?= json_encode($ordini) ?>;
    // Mappa per associare ID filtro a chiave dati
    const filterMap = {
        'stato-filter': 'StatoNome',
        'agente-filter': 'AgenteNomeCompleto', // Useremo nome completo per il filtro
        'proprietario-filter': 'ProprietarioNome'
    };
    const showInseritoDa = <?= $show_inserito_da_column ? 'true' : 'false' ?>;

    // Elementi DOM
    const tableBody = document.getElementById('ordiniTableBody');
    const searchInput = document.getElementById('searchInput');
    const statoFilter = document.getElementById('stato-filter');
    const agenteFilter = document.getElementById('agente-filter');
    const proprietarioFilter = document.getElementById('proprietario-filter');
    const dateFromFilter = document.getElementById('date-from-filter');
    const dateToFilter = document.getElementById('date-to-filter');
    const resetBtn = document.getElementById('resetFiltersBtn');
    const headers = document.querySelectorAll('th[data-sortable]');
    const noResultsP = document.getElementById('no-results');

    const allSelects = [statoFilter, agenteFilter, proprietarioFilter].filter(Boolean); // Filtra null se agenteFilter non esiste

    // Pre-processa i dati per JS (aggiunge nome completo agente)
    const processedData = originalData.map(item => ({
        ...item,
        AgenteNomeCompleto: `${item.AgenteNome || ''} ${item.AgenteCognome || ''}`.trim()
    }));

    // --- FUNZIONI HELPER ---
    function formatDate(dateString) {
        if (!dateString) return '';
        try {
            const date = new Date(dateString);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${day}/${month}/${year}`;
        } catch (e) {
            return '';
        }
    }

    function getUniqueValues(data, key) {
        return [...new Set(data.map(item => item[key]).filter(Boolean))].sort();
    }

    function populateSelect(selectElement, options, selectedValue) {
        if (!selectElement) return; // Salta se il select non esiste (es. agenteFilter)
        const currentVal = selectedValue;
        selectElement.innerHTML = `<option value="">Tutti</option>`;
        options.forEach(value => {
            const option = new Option(value, value);
            if (value === currentVal) option.selected = true;
            selectElement.add(option);
        });
    }

    // --- FUNZIONE RENDER TABELLA ---
    function renderTable(data) {
        tableBody.innerHTML = '';
        if (data.length === 0) {
            noResultsP.style.display = 'block';
            tableBody.innerHTML = `<tr><td colspan="${showInseritoDa ? 8 : 7}" style="text-align: center;">Nessun ordine trovato.</td></tr>`;
            return;
        }
        noResultsP.style.display = 'none';

        const canModify = <?= (in_array('ordini_modifica', $user_permessi) || $is_superuser) ? 'true' : 'false' ?>;
        const canDelete = <?= (in_array('ordini_elimina', $user_permessi) || $is_superuser) ? 'true' : 'false' ?>;

        data.forEach(order => {
            const row = tableBody.insertRow();
            row.insertCell().textContent = order.NumeroOrdine || '';
            row.insertCell().textContent = formatDate(order.DataInserimento);
            row.insertCell().textContent = order.Azienda_RagioneSociale || '';
            row.insertCell().textContent = order.StatoNome || '';
            row.insertCell().textContent = order.AgenteNomeCompleto || '';
            row.insertCell().textContent = order.ProprietarioNome || '';
            if (showInseritoDa) {
                // Mostra l'agente come "Inserito Da" per ora
                row.insertCell().textContent = order.AgenteNomeCompleto || '';
            }

            // Azioni
            const actionsCell = row.insertCell();
            actionsCell.classList.add('action-buttons');
            let actionsHTML = `<a href="visualizza_ordine.php?id=${order.ID}" class="btn btn-visualizza" title="Visualizza">👁️</a>`;
            if (canModify) {
                actionsHTML += ` <a href="modifica_ordine.php?id=${order.ID}" class="btn btn-modifica" title="Modifica">✏️</a>`;
            }
            if (canDelete) {
                actionsHTML += ` <a href="../../PHP/elimina_ordine.php?id=${order.ID}" class="btn btn-elimina" onclick="return confirm('Sei sicuro di voler eliminare l\'ordine ${order.NumeroOrdine}?');" title="Elimina">🗑️</a>`;
            }
            actionsCell.innerHTML = actionsHTML;
        });
    }

    // --- FUNZIONE FILTRI E POPOLAMENTO DINAMICO ---
    function applyFiltersAndPopulate() {
        const currentFilters = {
            search: searchInput.value.toLowerCase(),
            stato: statoFilter.value,
            agente: agenteFilter ? agenteFilter.value : '', // Controlla se il filtro agente esiste
            proprietario: proprietarioFilter.value,
            dateFrom: dateFromFilter.value,
            dateTo: dateToFilter.value,
        };

        // Filtra i dati
        const filteredData = processedData.filter(item => {
            // Filtro Search
            const searchMatch = !currentFilters.search ||
                (item.NumeroOrdine && item.NumeroOrdine.toLowerCase().includes(currentFilters.search)) ||
                (item.Azienda_RagioneSociale && item.Azienda_RagioneSociale.toLowerCase().includes(currentFilters.search)) ||
                (item.AgenteNomeCompleto && item.AgenteNomeCompleto.toLowerCase().includes(currentFilters.search));

            // Filtri Select
            const statoMatch = !currentFilters.stato || item.StatoNome === currentFilters.stato;
            const agenteMatch = !currentFilters.agente || item.AgenteNomeCompleto === currentFilters.agente;
            const proprietarioMatch = !currentFilters.proprietario || item.ProprietarioNome === currentFilters.proprietario;

            // Filtro Data
            let dateMatch = true;
            if (currentFilters.dateFrom || currentFilters.dateTo) {
                const itemDate = item.DataInserimento ? new Date(item.DataInserimento.split(' ')[0]) : null; // Considera solo la data
                if (!itemDate) {
                     dateMatch = false; // Se l'ordine non ha data, non matcha il range
                } else {
                    if (currentFilters.dateFrom) {
                        const fromDate = new Date(currentFilters.dateFrom);
                        if (itemDate < fromDate) dateMatch = false;
                    }
                    if (currentFilters.dateTo) {
                        const toDate = new Date(currentFilters.dateTo);
                        // Aggiunge un giorno alla data 'to' per includere tutto il giorno selezionato
                        toDate.setDate(toDate.getDate() + 1);
                        if (itemDate >= toDate) dateMatch = false;
                    }
                }
            }


            return searchMatch && statoMatch && agenteMatch && proprietarioMatch && dateMatch;
        });

        // Rirenderizza la tabella con i dati filtrati
        renderTable(filteredData);

        // Popola i select con opzioni basate sui dati FILTRATI
        allSelects.forEach(select => {
            const key = filterMap[select.id];
            // Per popolare, usa i dati filtrati escludendo il filtro del select corrente
             const tempFilters = { ...currentFilters };
             const currentSelectKey = select.id.split('-')[0]; // es. 'stato', 'agente'
             tempFilters[currentSelectKey] = ''; // Resetta filtro corrente per popolare

             const relevantDataForSelect = processedData.filter(item => {
                 const searchMatch = !tempFilters.search || (/*...*/); // Logica search
                 const statoMatch = !tempFilters.stato || item.StatoNome === tempFilters.stato;
                 const agenteMatch = !tempFilters.agente || item.AgenteNomeCompleto === tempFilters.agente;
                 const proprietarioMatch = !tempFilters.proprietario || item.ProprietarioNome === tempFilters.proprietario;
                 // (Ometti la logica data qui per semplicità, ma andrebbe inclusa se si vuole che le date influenzino i dropdown)
                 return searchMatch && statoMatch && agenteMatch && proprietarioMatch /* && dateMatch */;
             });


            const options = getUniqueValues(filteredData, key); // Usa filteredData per opzioni dipendenti
            populateSelect(select, options, currentFilters[select.id.split('-')[0]]);
        });
    }

    // --- FUNZIONE ORDINAMENTO ---
    function sortData(columnIndex, direction, isDate) {
        const key = Object.keys(processedData[0])[columnIndex]; // Trova la chiave dati corrispondente all'indice colonna (potrebbe essere fragile)

        // Mappa più robusta indice colonna -> chiave dati
        const columnKeyMap = [
            'NumeroOrdine', 'DataInserimento', 'Azienda_RagioneSociale', 'StatoNome',
            'AgenteNomeCompleto', 'ProprietarioNome'
        ];
        if (showInseritoDa) { columnKeyMap.push('AgenteNomeCompleto'); } // Aggiungi se presente

        const sortKey = columnKeyMap[columnIndex];
        if (!sortKey) return; // Colonna non ordinabile (es. Azioni)

        processedData.sort((a, b) => {
            let aVal = a[sortKey] || '';
            let bVal = b[sortKey] || '';

            if (isDate) {
                const dateA = aVal ? new Date(aVal.split(' ')[0]) : new Date(0);
                const dateB = bVal ? new Date(bVal.split(' ')[0]) : new Date(0);
                return direction === 'asc' ? dateA - dateB : dateB - dateA;
            } else {
                 // Converti in stringa per localeCompare
                 aVal = String(aVal);
                 bVal = String(bVal);
                 const comparison = aVal.localeCompare(bVal, undefined, { numeric: true, sensitivity: 'base' });
                 return direction === 'asc' ? comparison : -comparison;
            }
        });

        applyFiltersAndPopulate(); // Rirenderizza con i dati ordinati e filtri correnti
    }


    // --- EVENT LISTENERS ---
    searchInput.addEventListener('input', applyFiltersAndPopulate);
    allSelects.forEach(select => select.addEventListener('change', applyFiltersAndPopulate));
    dateFromFilter.addEventListener('change', applyFiltersAndPopulate);
    dateToFilter.addEventListener('change', applyFiltersAndPopulate);

    resetBtn.addEventListener('click', () => {
        searchInput.value = '';
        allSelects.forEach(select => select.value = '');
        dateFromFilter.value = '';
        dateToFilter.value = '';
        headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
        // Riordina per data inserimento default? O lascia l'ultimo ordinamento?
        // Per ora, riapplica solo i filtri resettati
        applyFiltersAndPopulate();
    });

    headers.forEach((headerCell, index) => {
        headerCell.addEventListener('click', () => {
            const currentDirection = headerCell.classList.contains('sort-asc') ? 'asc' : (headerCell.classList.contains('sort-desc') ? 'desc' : null);
            const newDirection = (currentDirection === 'asc') ? 'desc' : 'asc';
            const isDate = headerCell.dataset.type === 'date';

            headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
            headerCell.classList.add(newDirection === 'asc' ? 'sort-asc' : 'sort-desc');

            sortData(index, newDirection, isDate);
        });
    });

    // --- INIZIALIZZAZIONE ---
    applyFiltersAndPopulate(); // Popola tabella e filtri iniziali

});
</script>

<?php require_once '../../PHP/footer.php'; // Attenzione al percorso ?>

</body>
</html>