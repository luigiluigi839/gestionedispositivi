<?php
session_start();
require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$current_user_id = $_SESSION['user_id'] ?? null;

if (
    !isset($current_user_id) ||
    (
        !in_array('dashboard_reminder', $user_permessi) &&
        !in_array('vista_reminder_commerciali', $user_permessi) &&
        !$is_superuser
    )
) {
    header('Location: ../Pages/dashboard.php?error=Accesso non autorizzato');
    exit();
}

$reminder_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$reminder = null;
$message = '';
$status = '';

if ($reminder_id) {
    try {
        $sql = "SELECT 
                    sr.*, 
                    d.Seriale_Inrete, ma.Nome AS MarcaNome, mo.Nome AS ModelloNome,
                    CONCAT(u.Nome, ' ', u.Cognome) AS UtenteCreazioneNome
                FROM Scadenze_Reminder sr
                LEFT JOIN Dispositivi d ON sr.Dispositivo_Seriale = d.Seriale_Inrete
                LEFT JOIN Marche ma ON d.MarcaID = ma.ID
                LEFT JOIN Modelli mo ON d.ModelloID = mo.ID
                LEFT JOIN Utenti u ON sr.Utente_Creazione_ID = u.ID
                WHERE sr.ID = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $reminder_id]);
        $reminder = $stmt->fetch(PDO::FETCH_ASSOC);

        // Controllo privacy
        if (!$is_superuser && $reminder && $reminder['Is_Privato'] && $reminder['Utente_Creazione_ID'] != $current_user_id) {
            $reminder = null; // Nascondi il reminder se privato e non dell'utente
            $message = "Accesso non autorizzato a questo reminder.";
            $status = 'error';
        } elseif (!$reminder) {
            $message = "Reminder non trovato.";
            $status = 'error';
        }

    } catch (PDOException $e) {
        $message = "Errore nel recupero dei dati: " . $e->getMessage();
        $status = 'error';
    }
} else {
    $message = "ID reminder non specificato.";
    $status = 'error';
}

function formatDate($dateString) {
    return $dateString ? date('d/m/Y', strtotime($dateString)) : 'N/D';
}
function formatSeriale($seriale) {
    return $seriale ? str_pad((string)$seriale, 10, '0', STR_PAD_LEFT) : 'Generico';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Dettagli Reminder</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_cards.css">
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="card-container">
    <h2>Dettagli Reminder</h2>

    <?php if ($message): ?>
        <p class="message <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if ($reminder): ?>
        <div class="card">
            <h3>Informazioni Principali</h3>
            <p><strong>Tipo Scadenza:</strong> <?= htmlspecialchars($reminder['Tipo_Scadenza']) ?> <?php if ($reminder['Is_Privato']): ?><span title="Questo reminder è privato">🔒</span><?php endif; ?></p>
            <p><strong>Data Scadenza:</strong> <?= formatDate($reminder['Data_Scadenza']) ?></p>
            <p><strong>Stato:</strong> <?= htmlspecialchars($reminder['Stato']) ?></p>
            <p><strong>Creato Da:</strong> <?= htmlspecialchars($reminder['UtenteCreazioneNome'] ?? 'N/D') ?></p>
        </div>

        <div class="card">
            <h3>Dettagli Associazione</h3>
            <p><strong>Azienda Associata:</strong> <?= htmlspecialchars($reminder['Azienda'] ?? 'Nessuna') ?></p>
            <p><strong>Dispositivo Associato:</strong> <?= htmlspecialchars(formatSeriale($reminder['Seriale_Inrete'])) ?></p>
            <?php if($reminder['Seriale_Inrete']): ?>
                <p><strong>Marca/Modello:</strong> <?= htmlspecialchars(($reminder['MarcaNome'] ?? '') . ' / ' . ($reminder['ModelloNome'] ?? '')) ?></p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Destinatari e Note</h3>
            <p><strong>Destinatari Notifica:</strong> <?= htmlspecialchars($reminder['Email_Notifica'] ?? 'Nessuno') ?></p>
            <p><strong>Note:</strong></p>
            <p><?= nl2br(htmlspecialchars($reminder['Note'] ?? 'Nessuna nota.')) ?></p>
        </div>

        <div class="button-group">
            <a href="gestione_reminder.php" class="back-button">Torna alla Lista</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>