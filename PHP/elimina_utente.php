<?php
session_start();
require_once 'db_connect.php';
$user_permessi = $_SESSION['permessi'] ?? [];

if (!isset($_SESSION['user_id']) || (!in_array('gestione_utenti', $user_permessi) && !($_SESSION['is_superuser'] ?? false))) {
    header('Location: ../index.html');
    exit();
}

// Verifica che l'ID dell'utente da eliminare sia passato nell'URL
if (!isset($_GET['id'])) {
    header('Location: ../Pages/gestione_utenti.php?error=' . urlencode('ID utente non specificato.'));
    exit();
}

$id_da_eliminare = $_GET['id'];
$id_utente_loggato = $_SESSION['user_id'];

// 1. Impedisci all'utente di eliminare se stesso
if ($id_da_eliminare == $id_utente_loggato) {
    header('Location: ../Pages/gestione_utenti.php?error=' . urlencode('Non puoi eliminare il tuo stesso account.'));
    exit();
}

try {
    // Recupera lo stato di superuser dell'utente da eliminare
    $sql = "SELECT is_Superuser FROM Utenti WHERE ID = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id_da_eliminare]);
    $utente = $stmt->fetch();

    if (!$utente) {
        header('Location: ../Pages/gestione_utenti.php?error=' . urlencode('Utente non trovato.'));
        exit();
    }
    
    // 2. Controlla se è l'ultimo superuser, solo se l'utente da eliminare è un superuser
    if ($utente['is_Superuser']) {
        $sql_count = "SELECT COUNT(*) FROM Utenti WHERE is_Superuser = 1";
        $stmt_count = $pdo->query($sql_count);
        $count = $stmt_count->fetchColumn();

        if ($count <= 1) {
            header('Location: ../Pages/gestione_utenti.php?error=' . urlencode('Non puoi eliminare l\'ultimo utente superuser.'));
            exit();
        }
    }

    // 3. Esegui la query di eliminazione
    $sql_delete = "DELETE FROM Utenti WHERE ID = :id";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->execute([':id' => $id_da_eliminare]);

    // Reindirizza con un messaggio di successo
    header('Location: ../Pages/gestione_utenti.php?success=' . urlencode('Utente eliminato con successo.'));
    exit();

} catch (PDOException $e) {
    header('Location: ../Pages/gestione_utenti.php?error=' . urlencode('Errore durante l\'eliminazione dell\'utente: ' . $e->getMessage()));
    exit();
}