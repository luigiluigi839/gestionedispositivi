<?php
session_start();
require_once 'db_connect.php';

// Sicurezza e Permessi
$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;

if (!isset($_SESSION['user_id']) || (!in_array('modifica_reminder', $user_permessi) && !$is_superuser)) {
    header('Location: ../Pages/dashboard.php?error=' . urlencode('Accesso non autorizzato.'));
    exit();
}

$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$new_date_str = filter_input(INPUT_GET, 'new_date', FILTER_SANITIZE_SPECIAL_CHARS);

function parseAndFormatDate($dateStr) {
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $dateStr, $matches)) {
        return DateTime::createFromFormat('d-m-Y', $dateStr);
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateStr, $matches)) {
        return DateTime::createFromFormat('Y-m-d', $dateStr);
    }
    return false;
}

$dateObject = parseAndFormatDate($new_date_str);

// --- NUOVA LOGICA DI REDIRECT INTELLIGENTE ---
$redirect_page = 'dashboard.php'; // Fallback di base
if (in_array('vista_reminder_commerciali', $user_permessi) || $is_superuser) {
    $redirect_page = 'reminder_commerciali.php';
} elseif (in_array('dashboard_reminder', $user_permessi)) {
    $redirect_page = 'gestione_reminder.php';
}
// --- FINE NUOVA LOGICA ---

if (!$id || $dateObject === false) {
    header("Location: ../Pages/$redirect_page?error=" . urlencode('Azione non valida o formato data non corretto.'));
    exit();
}

$db_formatted_date = $dateObject->format('Y-m-d');

try {
    $sql = "UPDATE Scadenze_Reminder SET Data_Scadenza = :new_date WHERE ID = :id AND Stato = 'Attivo'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':new_date' => $db_formatted_date,
        ':id' => $id
    ]);

    if ($stmt->rowCount() > 0) {
        header("Location: ../Pages/$redirect_page?success=" . urlencode('Reminder posposto con successo.'));
    } else {
        header("Location: ../Pages/$redirect_page?error=" . urlencode('Nessun reminder attivo trovato da posporre.'));
    }
    exit();

} catch (PDOException $e) {
    header("Location: ../Pages/$redirect_page?error=" . urlencode('Errore database: ' . $e->getMessage()));
    exit();
}
?>