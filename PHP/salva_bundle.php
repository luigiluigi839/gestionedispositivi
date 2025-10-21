<?php
// File: PHP/salva_bundle.php
session_start();
require_once 'db_connect.php';

// Sicurezza e Permessi
$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$user_id = $_SESSION['user_id'] ?? null;

// MODIFICATO: Controllo sul permesso specifico 'modifica_bundle'
if (!isset($user_id) || (!in_array('modifica_bundle', $user_permessi) && !$is_superuser)) {
    header('HTTP/1.1 403 Forbidden');
    exit('Accesso negato.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../Pages/crea_bundle.php');
    exit();
}

$corpo_macchina_seriale = $_POST['corpo_macchina_seriale'] ?? null;
$accessori = $_POST['accessori'] ?? [];

if (empty($corpo_macchina_seriale) || empty($accessori)) {
    $_SESSION['bundle_message'] = 'Errore: Devi selezionare un dispositivo principale e almeno un accessorio.';
    $_SESSION['bundle_status'] = 'error';
    header('Location: ../Pages/crea_bundle.php');
    exit();
}

try {
    $pdo->beginTransaction();

    $sql = "INSERT INTO Bundle_Dispositivi (CorpoMacchina_Seriale, Accessorio_Seriale, Utente_Creazione_ID) VALUES (:corpo_macchina, :accessorio, :utente_id)";
    $stmt = $pdo->prepare($sql);

    foreach ($accessori as $accessorio_seriale) {
        $stmt->execute([
            ':corpo_macchina' => $corpo_macchina_seriale,
            ':accessorio' => $accessorio_seriale,
            ':utente_id' => $user_id
        ]);
    }

    $pdo->commit();

    $_SESSION['bundle_message'] = 'Bundle creato con successo!';
    $_SESSION['bundle_status'] = 'success';

} catch (PDOException $e) {
    $pdo->rollBack();
    // Se l'errore è di chiave duplicata (UNIQUE KEY)
    if ($e->getCode() == 23000) {
        $_SESSION['bundle_message'] = 'Errore: Uno dei dispositivi selezionati è già parte di un altro bundle.';
    } else {
        $_SESSION['bundle_message'] = 'Errore database: ' . $e->getMessage();
    }
    $_SESSION['bundle_status'] = 'error';
}

// Reindirizza alla pagina di gestione bundle per vedere il risultato
header('Location: ../Pages/gestione_bundle.php');
exit();
?>