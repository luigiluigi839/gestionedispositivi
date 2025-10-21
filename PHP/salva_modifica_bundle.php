<?php
// File: PHP/salva_modifica_bundle.php
session_start();
require_once 'db_connect.php';

// Sicurezza e Permessi
$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$user_id = $_SESSION['user_id'] ?? null;

// MODIFICATO: Controllo sul permesso specifico 'modifica_bundle'
if (!isset($user_id) || (!in_array('modifica_bundle', $user_permessi) && !$is_superuser)) {
    // Imposta un messaggio di errore e reindirizza se non autorizzato
    $_SESSION['bundle_message'] = 'Accesso non autorizzato.';
    $_SESSION['bundle_status'] = 'error';
    header('Location: ../Pages/gestione_bundle.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../Pages/gestione_bundle.php');
    exit();
}

$corpo_macchina_seriale = $_POST['corpo_macchina_seriale'] ?? null;
$accessori = $_POST['accessori'] ?? []; // Potrebbe essere vuoto se si rimuovono tutti gli accessori

if (empty($corpo_macchina_seriale)) {
    $_SESSION['bundle_message'] = 'Errore: ID del dispositivo principale mancante.';
    $_SESSION['bundle_status'] = 'error';
    header('Location: ../Pages/gestione_bundle.php');
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Cancella tutte le vecchie associazioni per questo corpo macchina
    $sql_delete = "DELETE FROM Bundle_Dispositivi WHERE CorpoMacchina_Seriale = :corpo_macchina";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->execute([':corpo_macchina' => $corpo_macchina_seriale]);

    // 2. Se ci sono accessori da inserire, li aggiunge di nuovo
    if (!empty($accessori)) {
        $sql_insert = "INSERT INTO Bundle_Dispositivi (CorpoMacchina_Seriale, Accessorio_Seriale, Utente_Creazione_ID) VALUES (:corpo_macchina, :accessorio, :utente_id)";
        $stmt_insert = $pdo->prepare($sql_insert);

        foreach ($accessori as $accessorio_seriale) {
            $stmt_insert->execute([
                ':corpo_macchina' => $corpo_macchina_seriale,
                ':accessorio' => $accessorio_seriale,
                ':utente_id' => $user_id
            ]);
        }
    }
    
    $pdo->commit();

    // Se la lista accessori è vuota, il bundle è stato di fatto eliminato.
    if (empty($accessori)) {
        $_SESSION['bundle_message'] = 'Tutti gli accessori sono stati rimossi. Il bundle è stato eliminato.';
    } else {
        $_SESSION['bundle_message'] = 'Bundle aggiornato con successo!';
    }
    $_SESSION['bundle_status'] = 'success';
    
    // Reindirizza alla pagina di gestione generale
    header('Location: ../Pages/gestione_bundle.php');
    exit();

} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['bundle_message'] = 'Errore database durante la modifica: ' . $e->getMessage();
    $_SESSION['bundle_status'] = 'error';
    // Reindirizza di nuovo alla pagina di modifica in caso di errore
    header('Location: ../Pages/modifica_bundle.php?id=' . $corpo_macchina_seriale);
    exit();
}
?>