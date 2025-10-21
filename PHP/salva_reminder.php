<?php
session_start();
require_once 'db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$user_id = $_SESSION['user_id'] ?? null;

// Dati dal form
$reminder_id = !empty($_POST['id']) ? filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT) : null;
$tipo_scadenza = trim($_POST['tipo_scadenza'] ?? '');
$data_scadenza = $_POST['data_scadenza'] ?? null;
$azienda = !empty($_POST['azienda']) ? trim($_POST['azienda']) : null;
$dispositivo_seriale = !empty($_POST['dispositivo_seriale']) ? trim($_POST['dispositivo_seriale']) : null;
$note = trim($_POST['note'] ?? '');
$email_notifica_array = $_POST['email_notifica'] ?? [];
$email_notifica_string = implode(', ', $email_notifica_array);

// --- NUOVA LOGICA PER GESTIRE IL FLAG "PRIVATO" ---
$is_privato = 0; // Di default è pubblico
if (in_array('dashboard_reminder', $user_permessi) || $is_superuser) {
    // Solo gli admin/gestori possono scegliere se è privato o no
    $is_privato = isset($_POST['is_privato']) ? 1 : 0;
} else {
    // Per tutti gli altri utenti, il reminder è forzatamente privato
    $is_privato = 1;
}
// --- FINE NUOVA LOGICA ---

if (empty($tipo_scadenza) || empty($data_scadenza)) {
    $redirect_page = $reminder_id ? "modifica_reminder.php?id=$reminder_id" : "aggiungi_reminder.php";
    header("Location: ../Pages/$redirect_page?error=" . urlencode('Tipo e Data di scadenza sono obbligatori.'));
    exit();
}

// Logica per determinare la pagina di redirect
$redirect_page = 'dashboard.php';
if (in_array('vista_reminder_commerciali', $user_permessi) || $is_superuser) {
    $redirect_page = 'reminder_commerciali.php';
} elseif (in_array('dashboard_reminder', $user_permessi)) {
    $redirect_page = 'gestione_reminder.php';
}

if ($reminder_id) {
    // --- MODIFICA (UPDATE) ---
    if (!in_array('modifica_reminder', $user_permessi) && !$is_superuser) {
        header('Location: ../Pages/dashboard.php?error=Accesso non autorizzato');
        exit();
    }
    try {
        $stmt_check = $pdo->prepare("SELECT Utente_Creazione_ID FROM Scadenze_Reminder WHERE ID = :id");
        $stmt_check->execute([':id' => $reminder_id]);
        $owner_id = $stmt_check->fetchColumn();
        if (!$is_superuser && $owner_id != $user_id) {
            throw new Exception('Non hai i permessi per modificare questo reminder.');
        }

        $sql = "UPDATE Scadenze_Reminder SET 
                    Tipo_Scadenza = :tipo, Data_Scadenza = :data, Azienda = :azienda, 
                    Dispositivo_Seriale = :dispositivo, Note = :note, Email_Notifica = :email, 
                    Is_Privato = :is_privato, Stato = 'Attivo'
                WHERE ID = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':tipo' => $tipo_scadenza, ':data' => $data_scadenza, ':azienda' => $azienda,
            ':dispositivo' => $dispositivo_seriale, ':note' => $note,
            ':email' => !empty($email_notifica_string) ? $email_notifica_string : null,
            ':is_privato' => $is_privato, ':id' => $reminder_id
        ]);
        header("Location: ../Pages/$redirect_page?success=" . urlencode('Reminder aggiornato e riattivato con successo!'));
    } catch (Exception $e) {
        header("Location: ../Pages/modifica_reminder.php?id=$reminder_id&error=" . urlencode('Errore: ' . $e->getMessage()));
    }
} else {
    // --- INSERIMENTO (INSERT) ---
    if (!in_array('inserisci_reminder', $user_permessi) && !$is_superuser) {
        header('Location: ../Pages/dashboard.php?error=Accesso non autorizzato');
        exit();
    }
    try {
        $sql = "INSERT INTO Scadenze_Reminder (
                    Azienda, Dispositivo_Seriale, Data_Scadenza, Tipo_Scadenza, Note, Email_Notifica, Utente_Creazione_ID, Is_Privato, Stato
                ) VALUES (
                    :azienda, :dispositivo, :data_scadenza, :tipo, :note, :email, :utente, :is_privato, 'Attivo'
                )";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':azienda' => $azienda, ':dispositivo' => $dispositivo_seriale, ':data_scadenza' => $data_scadenza,
            ':tipo' => $tipo_scadenza, ':note' => $note,
            ':email' => !empty($email_notifica_string) ? $email_notifica_string : null,
            ':utente' => $user_id, ':is_privato' => $is_privato
        ]);
        header("Location: ../Pages/$redirect_page?success=" . urlencode('Reminder salvato con successo!'));
    } catch (PDOException $e) {
        header('Location: ../Pages/aggiungi_reminder.php?error=' . urlencode('Errore nel salvataggio: ' . $e->getMessage()));
    }
}
exit();
?>