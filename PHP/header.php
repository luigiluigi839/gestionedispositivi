<?php
// File: PHP/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_permessi = $_SESSION['permessi'] ?? [];
$permessi_raggruppati = $_SESSION['permessi_raggruppati'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$user_name = $_SESSION['user_name'] ?? 'Ospite';
$current_user_id = $_SESSION['user_id'] ?? null;

// Elenco centralizzato delle voci di menu principali basate sui GRUPPI di permessi
$menu_groups = [
    'Gestione Dispositivi' => ['link' => 'gestione_dispositivi.php', 'permesso_gruppo' => 'dashboard_dispositivi'],
    'Ricondizionamenti' => ['link' => 'gestione_ricondizionamenti.php', 'permesso_gruppo' => 'dashboard_ricondizionamenti'],
    'Bundle' => ['link' => 'gestione_bundle.php', 'permesso_gruppo' => 'dashboard_bundle'],
    'Reminder' => ['link' => 'gestione_reminder.php', 'permesso_gruppo' => 'dashboard_reminder'],
    'Gestione Utenti' => ['link' => 'gestione_utenti.php', 'permesso_gruppo' => 'dashboard_gestione_utenti'],
];

?>
<header class="main-header">
    <div class="logo">
        <a href="dashboard.php">Gestione Dispositivi</a>
    </div>

    <button class="mobile-nav-toggle" aria-controls="main-nav" aria-expanded="false">
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
    </button>
    
    <nav class="main-nav" id="main-nav">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            
            <?php foreach ($menu_groups as $label => $item): ?>
                <?php if (in_array($item['permesso_gruppo'], $user_permessi) || $is_superuser): ?>
                    <li><a href="<?= htmlspecialchars($item['link']) ?>"><?= htmlspecialchars($label) ?></a></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="user-menu">
        <div class="user-menu-toggle" id="user-menu-toggle">
            <span>Ciao, <?= htmlspecialchars($user_name) ?></span>
            <span class="arrow">▼</span>
        </div>
        <div class="user-menu-dropdown hidden" id="user-menu-dropdown">
            <a href="modifica_utente.php?id=<?= $current_user_id ?>">Modifica Profilo</a>
            <a href="../PHP/logout.php" class="logout-link">Logout</a>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestione menu mobile
    const mobileNavToggle = document.querySelector('.mobile-nav-toggle');
    const mainNav = document.getElementById('main-nav');
    if (mobileNavToggle && mainNav) {
        mobileNavToggle.addEventListener('click', function() {
            mainNav.classList.toggle('is-active');
            this.classList.toggle('is-active');
        });
    }

    // Gestione menu utente dropdown
    const userMenuToggle = document.getElementById('user-menu-toggle');
    const userMenuDropdown = document.getElementById('user-menu-dropdown');
    if (userMenuToggle && userMenuDropdown) {
        userMenuToggle.addEventListener('click', function() {
            userMenuDropdown.classList.toggle('hidden');
        });

        // Chiudi il menu se si clicca fuori
        document.addEventListener('click', function(e) {
            if (!userMenuToggle.contains(e.target) && !userMenuDropdown.contains(e.target)) {
                userMenuDropdown.classList.add('hidden');
            }
        });
    }
});
</script>