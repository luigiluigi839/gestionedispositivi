<?php
// File: Pages/dashboard_gestione_spostamenti.php
session_start();
// Il db_connect non serve più qui per i dati, ma può servire per l'header.
require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$id_utente_loggato = $_SESSION['user_id'];

// Controllo permesso per visualizzare la pagina
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

// Passiamo i permessi al frontend tramite attributi data-* per un accesso sicuro
$can_edit = (in_array('modifica_spostamenti', $user_permessi) || $is_superuser) ? '1' : '0';
$can_delete = (in_array('elimina_spostamenti', $user_permessi) || $is_superuser) ? '1' : '0';

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

<!-- Usiamo data-* per passare i permessi al JavaScript in modo sicuro -->
<div class="table-container" data-can-edit="<?= $can_edit ?>" data-can-delete="<?= $can_delete ?>">
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
            <!-- Il corpo della tabella sarà popolato dinamicamente da JavaScript -->
            <tbody id="tableBody">
                <tr>
                    <td colspan="9" style="text-align: center;">Caricamento dati in corso...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Includiamo il file JavaScript esterno che contiene tutta la logica -->
<script src="../JS/gestione_spostamenti.js"></script>

</body>
</html>

