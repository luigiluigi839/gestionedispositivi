<?php
session_start();

// Includi il file di connessione al database
require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;

// MODIFICATO: Controllo sul permesso specifico della dashboard
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
$marche_modelli = [];
$operatori = [];
$stati_globali = [];
$gradi_finali = [];

try {
    // Le query per popolare i filtri rimangono invariate...
    $sql_marche_modelli = "SELECT DISTINCT ma.Nome AS Marca, mo.Nome AS Modello FROM Dispositivi d JOIN Marche ma ON d.MarcaID = ma.ID JOIN Modelli mo ON d.ModelloID = mo.ID ORDER BY Marca, Modello";
    $stmt = $pdo->query($sql_marche_modelli);
    $marche_modelli = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql_operatori = "SELECT DISTINCT u.ID, u.Nome, u.Cognome FROM Ricondizionamenti r JOIN Utenti u ON r.Operatore_ID = u.ID ORDER BY u.Cognome, u.Nome";
    $stmt = $pdo->query($sql_operatori);
    $operatori = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stati_globali = $pdo->query("SELECT DISTINCT Stato_Globale FROM Ricondizionamenti ORDER BY Stato_Globale")->fetchAll(PDO::FETCH_COLUMN);

    $sql_gradi = "SELECT ID, Nome FROM Stati WHERE Nome LIKE 'Ricondizionato Grado%' OR Nome IN ('Demolito', 'Da Cannibalizzare') ORDER BY Nome";
    $gradi_finali = $pdo->query($sql_gradi)->fetchAll(PDO::FETCH_ASSOC);

    $sql = "SELECT r.ID, r.Dispositivo_Seriale, r.Data_Inizio, r.Data_Fine, r.Stato_Globale, u.Nome AS OperatoreNome, u.Cognome AS OperatoreCognome, d.Seriale AS SerialeFisico, ma.Nome AS Marca, mo.Nome AS Modello, s.Nome AS GradoFinaleNome FROM Ricondizionamenti r LEFT JOIN Utenti u ON r.Operatore_ID = u.ID LEFT JOIN Dispositivi d ON r.Dispositivo_Seriale = d.Seriale_Inrete LEFT JOIN Marche ma ON d.MarcaID = ma.ID LEFT JOIN Modelli mo ON d.ModelloID = mo.ID LEFT JOIN Ricondizionamenti_Dettagli rd ON r.ID = rd.Ricondizionamento_ID LEFT JOIN Stati s ON rd.Grado_Finale = s.ID ORDER BY r.Data_Inizio DESC";
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
    if (!$dateString || $dateString === '0000-00-00 00:00:00') return 'N/D';
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
    <style>
        .status-badge { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; color: white; text-transform: uppercase; }
        .status-in-corso { background-color: #ffc107; color: #333; }
        .status-completato { background-color: #28a745; }
        .status-non-iniziato { background-color: #6c757d; }
        .status-demolito { background-color: #dc3545; }
        .filter-group { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-group > div { flex: 1; min-width: 150px; }
    </style>
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
            <select id="marca-filter" class="filter-select">
                <option value="">Tutte le Marche</option>
                <?php $unique_marche = array_unique(array_column($marche_modelli, 'Marca')); foreach ($unique_marche as $marca): ?>
                    <option value="<?= htmlspecialchars($marca) ?>"><?= htmlspecialchars($marca) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="modello-filter">Modello:</label>
            <select id="modello-filter" class="filter-select">
                <option value="">Tutti i Modelli</option>
            </select>
        </div>
        <div>
            <label for="operatore-filter">Operatore:</label>
            <select id="operatore-filter" class="filter-select">
                <option value="">Tutti gli Operatori</option>
                <?php foreach ($operatori as $op): ?>
                    <option value="<?= htmlspecialchars($op['Nome'] . ' ' . $op['Cognome']) ?>"><?= htmlspecialchars($op['Nome'] . ' ' . $op['Cognome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="stato-filter">Stato Intervento:</label>
            <select id="stato-filter" class="filter-select">
                <option value="">Tutti gli Stati</option>
                <?php foreach ($stati_globali as $stato_g): ?>
                    <option value="<?= htmlspecialchars($stato_g) ?>"><?= htmlspecialchars($stato_g) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="grado-filter">Grado Finale:</label>
            <select id="grado-filter" class="filter-select">
                <option value="">Tutti i Gradi Finali</option>
                <?php foreach ($gradi_finali as $grado): ?>
                    <option value="<?= htmlspecialchars($grado['Nome']) ?>"><?= htmlspecialchars($grado['Nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if (!empty($ricondizionamenti)): ?>
    <div class="scroll-table-container">
        <table id="ricondizionamenti-table">
            <thead>
                <tr>
                    <th>Seriale Inrete</th><th>Seriale Fisico</th><th>Marca</th><th>Modello</th><th>Data Inizio</th>
                    <th>Operatore</th><th>Stato Globale</th><th>Grado Finale</th><th>Data Fine</th><th>Azioni</th>
                </tr>
            </thead>
            <tbody id="table-body">
                <?php foreach ($ricondizionamenti as $r): ?>
                    <tr 
                        data-marca="<?= htmlspecialchars($r['Marca']) ?>"
                        data-modello="<?= htmlspecialchars($r['Modello']) ?>"
                        data-operatore="<?= htmlspecialchars($r['OperatoreNome'] . ' ' . $r['OperatoreCognome']) ?>"
                        data-stato="<?= htmlspecialchars($r['Stato_Globale']) ?>"
                        data-grado="<?= htmlspecialchars($r['GradoFinaleNome']) ?>"
                    >
                        <td><?= formatSeriale($r['Dispositivo_Seriale']) ?></td>
                        <td><?= htmlspecialchars($r['SerialeFisico'] ?? 'N/D') ?></td>
                        <td><?= htmlspecialchars($r['Marca'] ?? 'N/D') ?></td>
                        <td><?= htmlspecialchars($r['Modello'] ?? 'N/D') ?></td>
                        <td><?= formatDate($r['Data_Inizio']) ?></td>
                        <td><?= htmlspecialchars($r['OperatoreNome'] . ' ' . $r['OperatoreCognome']) ?></td>
                        <td><span class='status-badge status-<?= strtolower(str_replace(' ', '-', $r['Stato_Globale'])) ?>'><?= htmlspecialchars($r['Stato_Globale']) ?></span></td>
                        <td><?= htmlspecialchars($r['GradoFinaleNome'] ?? 'N/D') ?></td>
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
// Il codice Javascript per i filtri rimane invariato
document.addEventListener("DOMContentLoaded", function() {
    const marcheModelliData = <?= json_encode($marche_modelli) ?>;
    const marcaFilter = document.getElementById('marca-filter');
    const modelloFilter = document.getElementById('modello-filter');

    function updateModelloOptions() {
        const selectedMarca = marcaFilter.value;
        const currentModelloValue = modelloFilter.value;
        modelloFilter.innerHTML = '<option value="">Tutti i Modelli</option>';

        let modelliDaMostrare;
        if (selectedMarca) {
            modelliDaMostrare = [...new Set(marcheModelliData
                .filter(item => item.Marca === selectedMarca)
                .map(item => item.Modello)
            )].sort();
        } else {
            modelliDaMostrare = [...new Set(marcheModelliData.map(item => item.Modello))].sort();
        }
        
        modelliDaMostrare.forEach(modello => {
            const option = new Option(modello, modello);
            modelloFilter.add(option);
        });
        
        modelloFilter.value = currentModelloValue;
    }

    const searchInput = document.getElementById('search-input');
    const operatoreFilter = document.getElementById('operatore-filter');
    const statoFilter = document.getElementById('stato-filter');
    const gradoFilter = document.getElementById('grado-filter');
    const tableBody = document.getElementById('table-body');
    const noResults = document.getElementById('no-results');
    
    function applyFilters() {
        const searchText = searchInput.value.toLowerCase();
        const selectedMarca = marcaFilter.value;
        const selectedModello = modelloFilter.value;
        const selectedOperatore = operatoreFilter.value;
        const selectedStato = statoFilter.value;
        const selectedGrado = gradoFilter.value;
        let resultsFound = false;

        Array.from(tableBody.rows).forEach(row => {
            const marca = row.dataset.marca;
            const modello = row.dataset.modello;
            const operatore = row.dataset.operatore;
            const stato = row.dataset.stato;
            const grado = row.dataset.grado;
            const rowText = row.textContent.toLowerCase();
            
            const textMatch = rowText.includes(searchText);
            const marcaMatch = (selectedMarca === "" || marca === selectedMarca);
            const modelloMatch = (selectedModello === "" || modello === selectedModello);
            const operatoreMatch = (selectedOperatore === "" || operatore === selectedOperatore);
            const statoMatch = (selectedStato === "" || stato === selectedStato);
            const gradoMatch = (selectedGrado === "" || grado === selectedGrado);

            if (textMatch && marcaMatch && modelloMatch && operatoreMatch && statoMatch && gradoMatch) {
                row.style.display = '';
                resultsFound = true;
            } else {
                row.style.display = 'none';
            }
        });

        noResults.style.display = resultsFound ? 'none' : 'block';
    }

    searchInput.addEventListener('keyup', applyFilters);
    marcaFilter.addEventListener('change', () => {
        updateModelloOptions();
        applyFilters();
    });
    modelloFilter.addEventListener('change', applyFilters);
    operatoreFilter.addEventListener('change', applyFilters);
    statoFilter.addEventListener('change', applyFilters);
    gradoFilter.addEventListener('change', applyFilters);

    updateModelloOptions();
});
</script>

<?php require_once '../PHP/footer.php'; ?>

</body>
</html>