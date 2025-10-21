<?php
// File: Pages/gestione_bundle.php
session_start();
require_once '../PHP/db_connect.php';

// Sicurezza e Permessi
$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;

// MODIFICATO: Controllo sul permesso specifico della dashboard bundle
if (!isset($_SESSION['user_id']) || (!in_array('dashboard_bundle', $user_permessi) && !$is_superuser)) {
    header('Location: ../Pages/dashboard.php?error=Accesso non autorizzato');
    exit();
}

$message = $_SESSION['bundle_message'] ?? null;
$status = $_SESSION['bundle_status'] ?? null;
unset($_SESSION['bundle_message'], $_SESSION['bundle_status']);

$bundles = [];

try {
    // 1. Recupera tutti i corpi macchina che sono in un bundle
    $sql_corpi_macchina = "SELECT DISTINCT b.CorpoMacchina_Seriale, 
                                d.Seriale, 
                                ma.Nome AS Marca, 
                                mo.Nome AS Modello,
                                (SELECT s.Azienda FROM Spostamenti s WHERE s.Dispositivo = b.CorpoMacchina_Seriale AND s.Data_Ritiro IS NULL ORDER BY s.Data_Install DESC LIMIT 1) AS ClienteAssegnato
                          FROM Bundle_Dispositivi b
                          JOIN Dispositivi d ON b.CorpoMacchina_Seriale = d.Seriale_Inrete
                          JOIN Marche ma ON d.MarcaID = ma.ID
                          JOIN Modelli mo ON d.ModelloID = mo.ID";
    $stmt_corpi = $pdo->query($sql_corpi_macchina);
    $corpi_macchina_list = $stmt_corpi->fetchAll(PDO::FETCH_ASSOC);

    // 2. Per ogni corpo macchina, recupera i suoi accessori
    $sql_accessori = "SELECT b.Accessorio_Seriale, 
                             d.Seriale, 
                             ma.Nome AS Marca, 
                             mo.Nome AS Modello 
                      FROM Bundle_Dispositivi b
                      JOIN Dispositivi d ON b.Accessorio_Seriale = d.Seriale_Inrete
                      JOIN Marche ma ON d.MarcaID = ma.ID
                      JOIN Modelli mo ON d.ModelloID = mo.ID
                      WHERE b.CorpoMacchina_Seriale = :corpo_macchina_seriale";
    $stmt_accessori = $pdo->prepare($sql_accessori);

    foreach ($corpi_macchina_list as $corpo) {
        $stmt_accessori->execute([':corpo_macchina_seriale' => $corpo['CorpoMacchina_Seriale']]);
        $accessori_list = $stmt_accessori->fetchAll(PDO::FETCH_ASSOC);
        
        $bundles[] = [
            'corpo_macchina' => $corpo,
            'accessori' => $accessori_list
        ];
    }

} catch (PDOException $e) {
    $message = "Errore DB: " . $e->getMessage();
    $status = 'error';
}

function formatSeriale($seriale) {
    return str_pad((string)$seriale, 10, '0', STR_PAD_LEFT);
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Bundle</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_tables.css">
    <style>
        .bundle-row { border-left: 5px solid #007bff; cursor: pointer; user-select: none; }
        .accessory-row { background-color: #f8f9fa; }
        .accessory-row.hidden { display: none; }
        .toggle-icon { display: inline-block; margin-right: 10px; transition: transform 0.2s ease-in-out; font-size: 0.8em; }
        .bundle-row.expanded .toggle-icon { transform: rotate(90deg); }
    </style>
</head>
<body>
<?php require_once '../PHP/header.php'; ?>
<div class="table-container">
    <h2>Gestione Bundle</h2>
    <?php if ($message): ?><p class="message <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($message) ?></p><?php endif; ?>
    
    <?php if (in_array('modifica_bundle', $user_permessi) || $is_superuser): ?>
        <a href="crea_bundle.php" class="add-button">Crea Nuovo Bundle</a>
    <?php endif; ?>

    <input type="text" id="searchInput" class="search-box" placeholder="Cerca per seriale, marca, modello o cliente...">

    <div class="scroll-table-container">
        <table>
            <thead>
                <tr>
                    <th>Dispositivo Principale / Accessorio</th>
                    <th>Marca</th>
                    <th>Modello</th>
                    <th>Seriale Fisico</th>
                    <th>Cliente Attuale</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody id="bundleTableBody">
                <?php if (empty($bundles)): ?>
                    <tr><td colspan="6" style="text-align:center;">Nessun bundle creato.</td></tr>
                <?php else: ?>
                    <?php foreach ($bundles as $bundle): 
                        $bundleId = htmlspecialchars($bundle['corpo_macchina']['CorpoMacchina_Seriale']);
                    ?>
                        <tr class="bundle-row" data-bundle-id="<?= $bundleId ?>">
                            <td><strong><span class="toggle-icon">►</span><?= formatSeriale($bundle['corpo_macchina']['CorpoMacchina_Seriale']) ?> - Corpo Macchina</strong></td>
                            <td><strong><?= htmlspecialchars($bundle['corpo_macchina']['Marca']) ?></strong></td>
                            <td><strong><?= htmlspecialchars($bundle['corpo_macchina']['Modello']) ?></strong></td>
                            <td><strong><?= htmlspecialchars($bundle['corpo_macchina']['Seriale']) ?></strong></td>
                            <td><?= htmlspecialchars($bundle['corpo_macchina']['ClienteAssegnato'] ?? 'Libero') ?></td>
                            <td class="action-buttons">
                                <?php if (in_array('modifica_bundle', $user_permessi) || $is_superuser): ?>
                                    <a href="modifica_bundle.php?id=<?= $bundleId ?>" class="btn btn-modifica" title="Modifica">✏️</a>
                                <?php endif; ?>
                                <?php if (in_array('elimina_bundle', $user_permessi) || $is_superuser): ?>
                                    <a href="../PHP/elimina_bundle.php?id=<?= $bundleId ?>" class="btn btn-elimina" onclick="return confirm('Sei sicuro di voler eliminare questo bundle? Gli accessori torneranno disponibili.');" title="Elimina">🗑️</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php foreach ($bundle['accessori'] as $accessorio): ?>
                            <tr class="accessory-row hidden" data-bundle-id="<?= $bundleId ?>">
                                <td><?= formatSeriale($accessorio['Accessorio_Seriale']) ?> - <?= htmlspecialchars($accessorio['Modello']) ?></td>
                                <td><?= htmlspecialchars($accessorio['Marca']) ?></td>
                                <td><?= htmlspecialchars($accessorio['Modello']) ?></td>
                                <td><?= htmlspecialchars($accessorio['Seriale']) ?></td>
                                <td>-</td>
                                <td>-</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.getElementById('bundleTableBody');

    tableBody.addEventListener('click', function(e) {
        const bundleRow = e.target.closest('.bundle-row');
        if (!bundleRow) return;

        // Non attivare il toggle se si clicca su un pulsante di azione
        if (e.target.closest('.action-buttons')) {
            return;
        }

        const bundleId = bundleRow.dataset.bundleId;
        if (!bundleId) return;

        bundleRow.classList.toggle('expanded');
        const accessoryRows = tableBody.querySelectorAll(`.accessory-row[data-bundle-id="${bundleId}"]`);
        accessoryRows.forEach(row => {
            row.classList.toggle('hidden');
        });
    });

    document.getElementById('searchInput').addEventListener('keyup', function() {
        let filter = this.value.toUpperCase();
        let bundleRows = tableBody.getElementsByClassName('bundle-row');

        for (let i = 0; i < bundleRows.length; i++) {
            let mainRow = bundleRows[i];
            let bundleId = mainRow.dataset.bundleId;
            let accessoryRows = tableBody.querySelectorAll(`.accessory-row[data-bundle-id="${bundleId}"]`);
            
            let searchableText = (mainRow.textContent || mainRow.innerText);
            accessoryRows.forEach(accRow => {
                searchableText += ' ' + (accRow.textContent || accRow.innerText);
            });

            let display = 'none';
            if (searchableText.toUpperCase().indexOf(filter) > -1) {
                display = '';
            }
            
            mainRow.style.display = display;
            
            let isExpanded = mainRow.classList.contains('expanded');
            accessoryRows.forEach(accRow => {
                if (isExpanded && display === '') {
                    accRow.style.display = '';
                } else {
                    accRow.style.display = 'none';
                }
            });
        }
    });
});
</script>

</body>
</html>