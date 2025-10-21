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

// Validazione dell'input
$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$new_status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_SPECIAL_CHARS);

$allowed_statuses = ['Completata', 'Annullata'];
if (!$id || !$new_status || !in_array($new_status, $allowed_statuses)) {
    header('Location: ../Pages/gestione_reminder.php?error=' . urlencode('Azione non valida o ID mancante.'));
    exit();
}

// --- NUOVA LOGICA DI REDIRECT ---
// Determina la pagina di ritorno in base ai permessi dell'utente.
if (in_array('vista_reminder_commerciali', $user_permessi)) {
    $redirect_page = 'reminder_commerciali.php';
} else {
    $redirect_page = 'gestione_reminder.php';
}
// --- FINE NUOVA LOGICA ---

try {
    $sql = "UPDATE Scadenze_Reminder SET Stato = :status WHERE ID = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':status' => $new_status,
        ':id' => $id
    ]);

    if ($stmt->rowCount() > 0) {
        header("Location: ../Pages/$redirect_page?success=" . urlencode('Stato del reminder aggiornato con successo.'));
    } else {
        header("Location: ../Pages/$redirect_page?error=" . urlencode('Nessun reminder trovato o stato già aggiornato.'));
    }
    exit();

} catch (PDOException $e) {
    header("Location: ../Pages/$redirect_page?error=" . urlencode('Errore database: ' . $e->getMessage()));
    exit();
}
?>