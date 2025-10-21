<?php
session_start();
require_once '../PHP/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.html');
    exit();
}

$permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$user_name = $_SESSION['user_name'] ?? 'Ospite';

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Gestione Dispositivi</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_dashboard.css">
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="dashboard-container">
    <h2>Benvenuto, <?= htmlspecialchars($user_name) ?>! 👋</h2>
    <p>Seleziona una funzionalità qui sotto.</p>

    <div class="dashboard-grid">
        
        <?php if (in_array('dashboard_dispositivi', $permessi) || $is_superuser): ?>
        <a href="gestione_dispositivi.php" class="dashboard-tile">
            <span class="icon">💻</span>
            <h3>Gestione Dispositivi</h3>
            <p>Visualizza e gestisci l'anagrafica dei dispositivi.</p>
        </a>
        <?php endif; ?>

        <?php if (in_array('dashboard_ricondizionamenti', $permessi) || $is_superuser): ?>
        <a href="gestione_ricondizionamenti.php" class="dashboard-tile">
            <span class="icon">🛠️</span>
            <h3>Ricondizionamenti</h3>
            <p>Gestisci gli interventi di ricondizionamento.</p>
        </a>
        <?php endif; ?>

        <?php if (in_array('dashboard_bundle', $permessi) || $is_superuser): ?>
        <a href="gestione_bundle.php" class="dashboard-tile">
            <span class="icon">🔗</span>
            <h3>Gestione Bundle</h3>
            <p>Associa e gestisci i bundle di dispositivi.</p>
        </a>
        <?php endif; ?>
        
        <?php if (in_array('dashboard_reminder', $permessi) || $is_superuser): ?>
        <a href="gestione_reminder.php" class="dashboard-tile">
            <span class="icon">📅</span>
            <h3>Gestione Reminder</h3>
            <p>Visualizza e gestisci tutte le scadenze.</p>
        </a>
        <?php endif; ?>
        
        <?php if (in_array('assegnazione_cliente', $permessi) || $is_superuser): ?>
        <a href="assegnazione_cliente.php" class="dashboard-tile">
            <span class="icon">✍️</span>
            <h3>Assegna Dispositivo</h3>
            <p>Associa un dispositivo o un bundle a un cliente.</p>
        </a>
        <?php endif; ?>

        <?php if (in_array('ritiro_dispositivi', $permessi) || $is_superuser): ?>
        <a href="rientro_dispositivi.php" class="dashboard-tile">
            <span class="icon">↩️</span>
            <h3>Rientro Dispositivi</h3>
            <p>Gestisci il rientro dei dispositivi da un cliente.</p>
        </a>
        <?php endif; ?>

        <?php if (in_array('dashboard_gestione_spostamenti', $permessi) || $is_superuser): ?>
        <a href="dashboard_gestione_spostamenti.php" class="dashboard-tile">
            <span class="icon">📖</span>
            <h3>Storico Spostamenti</h3>
            <p>Visualizza e correggi lo storico delle installazioni.</p>
        </a>
        <?php endif; ?>

        <?php if (in_array('vista_dispositivi_commerciali', $permessi) || $is_superuser): ?>
        <a href="disponibili_commerciali.php" class="dashboard-tile">
            <span class="icon">📦</span>
            <h3>Dispositivi Disponibili</h3>
            <p>Visualizza i dispositivi per i commerciali.</p>
        </a>
        <?php endif; ?>
        
        <?php if (in_array('vista_reminder_commerciali', $permessi) || $is_superuser): ?>
        <a href="reminder_commerciali.php" class="dashboard-tile">
            <span class="icon">🔔</span>
            <h3>I Miei Reminder</h3>
            <p>Visualizza solo le scadenze da te create.</p>
        </a>
        <?php endif; ?>

        <?php if (in_array('dashboard_gestione_utenti', $permessi) || $is_superuser): ?>
        <a href="gestione_utenti.php" class="dashboard-tile">
            <span class="icon">👥</span>
            <h3>Gestione Utenti</h3>
            <p>Aggiungi, modifica o elimina utenti e permessi.</p>
        </a>
        <?php endif; ?>
        
        <?php if (in_array('gestione_aziende', $permessi) || $is_superuser): ?>
        <a href="gestione_aziende.php" class="dashboard-tile">
            <span class="icon">🏢</span>
            <h3>Anagrafica Aziende</h3>
            <p>Visualizza i dati delle aziende clienti.</p>
        </a>
        <?php endif; ?>
        
        <?php if (in_array('stampa_etichette', $permessi) || $is_superuser): ?>
        <a href="stampa_etichette.php" class="dashboard-tile">
            <span class="icon">🖨️</span>
            <h3>Stampa Etichette</h3>
            <p>Genera PDF con codici a barre per i dispositivi.</p>
        </a>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../PHP/footer.php'; ?>

</body>
</html>