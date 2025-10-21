<?php
session_start();
require_once '../PHP/db_connect.php';

// Controlla se l'utente è loggato
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.html');
    exit();
}

// Utilizza il permesso 'vista_dispositivi_commerciali'
$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
if (!in_array('vista_dispositivi_commerciali', $user_permessi) && !$is_superuser) {
    header('Location: dashboard.php?error=' . urlencode('Accesso non autorizzato.'));
    exit();
}

$id_utente_loggato = $_SESSION['user_id'];
$dispositivi = [];
$message = '';
$status = '';

// Definisce gli ID degli stati considerati "disponibili" per i commerciali
$stati_disponibili_ids = [1, 2, 3, 4, 5]; // Esempio: Nuovo, Ricondizionato Grado 1, 2, 3, Da Verificare

try {
    $sql = "SELECT 
                d.Seriale_Inrete,
                d.Seriale,
                ma.Nome AS MarcaNome, 
                mo.Nome AS ModelloNome, 
                t.Nome as TipologiaNome, -- Usiamo la Tipologia per determinare se è accessorio
                s.Nome AS StatoNome,
                d.`B/N`,
                d.Colore,
                d.`C.C.C.`,
                (SELECT MAX(r.Data_Fine) 
                 FROM Ricondizionamenti r 
                 WHERE r.Dispositivo_Seriale = d.Seriale_Inrete AND r.Stato_Globale = 'COMPLETATO'
                ) AS UltimoRicondizionamento
            FROM Dispositivi AS d
            LEFT JOIN Marche AS ma ON d.MarcaID = ma.ID
            LEFT JOIN Modelli AS mo ON d.ModelloID = mo.ID
            LEFT JOIN Tipologie AS t ON mo.Tipologia = t.ID
            LEFT JOIN Stati AS s ON d.Stato = s.ID
            WHERE
                d.Seriale_Inrete NOT IN (SELECT Dispositivo FROM Spostamenti WHERE Data_Ritiro IS NULL)
                AND (
                    (d.Prenotato_Da = :id_utente_loggato)
                    OR
                    (d.Prenotato_Da IS NULL AND d.Stato IN (". implode(',', $stati_disponibili_ids) ."))
                )
            ORDER BY d.Prenotato_Da DESC, ma.Nome, mo.Nome";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_utente_loggato' => $id_utente_loggato]);
    $dispositivi = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = "Errore nel recupero dei dati: " . $e->getMessage();
    $status = 'error';
}

function formatSeriale($seriale) {
    return str_pad((string)$seriale, 10, '0', STR_PAD_LEFT);
}
function formatDateOrNull($dateString) {
    if (!$dateString) return 'N/D';
    try {
        return date('d/m/Y', strtotime($dateString));
    } catch (Exception $e) {
        return 'N/D';
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Dispositivi Disponibili</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_tables.css">
    <style>
        .reset-btn { padding: 8px 12px; font-size: 1.2em; line-height: 1; cursor: pointer; }
    </style>
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="table-container">
    <h2>Dispositivi Disponibili</h2>
    <p>Lista dei dispositivi a te assegnati e di quelli liberi in magazzino.</p>
    
    <input type="text" id="searchInput" class="search-box" placeholder="Cerca per seriale, marca, modello...">
    
    <?php if ($message): ?><p class="message <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($message) ?></p><?php endif; ?>

    <div class="scroll-table-container">
        <table>
            <thead>
                <tr>
                    <th>Seriale Inrete</th>
                    <th>Seriale Fisico</th>
                    <th>Marca</th>
                    <th>Modello</th>
                    <th>Tipo</th>
                    <th>Stato</th>
                    <th>Contatore B/N</th>
                    <th>Contatore Colore</th>
                    <th>Contatore C.C.C.</th>
                    <th>Ultimo Ricond.</th>
                </tr>
                <tr class="filter-row">
                    <td></td>
                    <td></td>
                    <td><select id="marca-filter" class="filter-select"></select></td>
                    <td><select id="modello-filter" class="filter-select"></select></td>
                    <td><select id="tipo-filter" class="filter-select"></select></td>
                    <td><select id="stato-filter" class="filter-select"></select></td>
                    <td colspan="3"></td>
                    <td><button id="resetFiltersBtn" title="Resetta filtri" class="reset-btn">🔄</button></td>
                </tr>
            </thead>
            <tbody id="dispositiviTableBody">
                <?php if (!empty($dispositivi)): ?>
                    <?php foreach ($dispositivi as $d): ?>
                        <tr>
                            <td><?= htmlspecialchars(formatSeriale($d['Seriale_Inrete'])) ?></td>
                            <td><?= htmlspecialchars($d['Seriale']) ?></td>
                            <td><?= htmlspecialchars($d['MarcaNome']) ?></td>
                            <td><?= htmlspecialchars($d['ModelloNome']) ?></td>
                            <td><?= (isset($d['TipologiaNome']) && strpos($d['TipologiaNome'], 'Accessorio') !== false) ? 'Accessorio' : 'Corpo Macchina' ?></td>
                            <td><?= htmlspecialchars($d['StatoNome']) ?></td>
                            <td><?= htmlspecialchars($d['B/N'] ?? 'N/D') ?></td>
                            <td><?= htmlspecialchars($d['Colore'] ?? 'N/D') ?></td>
                            <td><?= htmlspecialchars($d['C.C.C.'] ?? 'N/D') ?></td>
                            <td><?= formatDateOrNull($d['UltimoRicondizionamento']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="10" style="text-align: center;">Nessun dispositivo disponibile.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const originalData = <?= json_encode($dispositivi) ?>.map(item => {
        item.Tipo = (item.TipologiaNome && item.TipologiaNome.includes('Accessorio')) ? 'Accessorio' : 'Corpo Macchina';
        return item;
    });

    const tableBody = document.getElementById('dispositiviTableBody');
    const rows = Array.from(tableBody.querySelectorAll('tr'));

    const searchInput = document.getElementById('searchInput');
    const marcaFilter = document.getElementById('marca-filter');
    const modelloFilter = document.getElementById('modello-filter');
    const tipoFilter = document.getElementById('tipo-filter');
    const statoFilter = document.getElementById('stato-filter');
    const resetBtn = document.getElementById('resetFiltersBtn');

    const allFilters = [marcaFilter, modelloFilter, tipoFilter, statoFilter];

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
            marca: marcaFilter.value,
            modello: modelloFilter.value,
            tipo: tipoFilter.value,
            stato: statoFilter.value,
            search: searchInput.value.toUpperCase()
        };

        rows.forEach((row, index) => {
            const rowData = originalData[index];
            if (!rowData) return;

            const matchesDropdowns = 
                (!filters.marca || rowData.MarcaNome === filters.marca) &&
                (!filters.modello || rowData.ModelloNome === filters.modello) &&
                (!filters.tipo || rowData.Tipo === filters.tipo) &&
                (!filters.stato || rowData.StatoNome === filters.stato);

            const matchesSearch = !filters.search || (row.textContent || row.innerText).toUpperCase().includes(filters.search);

            row.style.display = (matchesDropdowns && matchesSearch) ? '' : 'none';
        });

        let tempFilter;

        tempFilter = originalData.filter(i => (!filters.modello || i.ModelloNome === filters.modello) && (!filters.tipo || i.Tipo === filters.tipo) && (!filters.stato || i.StatoNome === filters.stato));
        populateSelect(marcaFilter, getUniqueValues(tempFilter, 'MarcaNome'), filters.marca);

        tempFilter = originalData.filter(i => (!filters.marca || i.MarcaNome === filters.marca) && (!filters.tipo || i.Tipo === filters.tipo) && (!filters.stato || i.StatoNome === filters.stato));
        populateSelect(modelloFilter, getUniqueValues(tempFilter, 'ModelloNome'), filters.modello);

        tempFilter = originalData.filter(i => (!filters.marca || i.MarcaNome === filters.marca) && (!filters.modello || i.ModelloNome === filters.modello) && (!filters.stato || i.StatoNome === filters.stato));
        populateSelect(tipoFilter, getUniqueValues(tempFilter, 'Tipo'), filters.tipo);

        tempFilter = originalData.filter(i => (!filters.marca || i.MarcaNome === filters.marca) && (!filters.modello || i.ModelloNome === filters.modello) && (!filters.tipo || i.Tipo === filters.tipo));
        populateSelect(statoFilter, getUniqueValues(tempFilter, 'StatoNome'), filters.stato);
    }

    allFilters.forEach(select => select.addEventListener('change', updateUI));
    searchInput.addEventListener('input', updateUI);
    resetBtn.addEventListener('click', () => {
        searchInput.value = '';
        allFilters.forEach(select => select.value = '');
        updateUI();
    });
    
    updateUI();
});
</script>

<?php require_once '../PHP/footer.php'; ?>

</body>
</html>