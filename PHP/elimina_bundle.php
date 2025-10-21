<?php
// File: PHP/elimina_bundle.php
session_start();
require_once 'db_connect.php';

// Sicurezza e Permessi
$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;

// MODIFICATO: Controllo sul permesso specifico 'elimina_bundle'
if (!isset($_SESSION['user_id']) || (!in_array('elimina_bundle', $user_permessi) && !$is_superuser)) {
    // Imposta un messaggio di errore e reindirizza se non autorizzato
    $_SESSION['bundle_message'] = 'Accesso non autorizzato.';
    $_SESSION['bundle_status'] = 'error';
    header('Location: ../Pages/gestione_bundle.php');
    exit();
}

// Recupera l'ID del corpo macchina del bundle da eliminare
$corpo_macchina_seriale = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

if ($corpo_macchina_seriale) {
    try {
        // La query elimina tutte le righe che hanno questo corpo macchina,
        // sciogliendo di fatto il bundle.
        $sql = "DELETE FROM Bundle_Dispositivi WHERE CorpoMacchina_Seriale = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $corpo_macchina_seriale]);

        // Controlla se qualche riga è stata effettivamente eliminata
        if ($stmt->rowCount() > 0) {
            $_SESSION['bundle_message'] = 'Bundle eliminato con successo. I dispositivi sono ora disponibili singolarmente.';
            $_SESSION['bundle_status'] = 'success';
        } else {
            $_SESSION['bundle_message'] = 'Nessun bundle trovato da eliminare con l\'ID specificato.';
            $_SESSION['bundle_status'] = 'warning';
        }

    } catch (PDOException $e) {
        $_SESSION['bundle_message'] = 'Errore durante l\'eliminazione del bundle: ' . $e->getMessage();
        $_SESSION['bundle_status'] = 'error';
    }
} else {
    $_SESSION['bundle_message'] = 'ID del bundle non valido o non fornito.';
    $_SESSION['bundle_status'] = 'error';
}

// Reindirizza sempre alla pagina di gestione dei bundle
header('Location: ../Pages/gestione_bundle.php');
exit();
?>