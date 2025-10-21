<?php
// File: PHP/salva_scadenza.php
session_start();
require_once 'db_connect.php';

// Sicurezza e Permessi
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    exit('Accesso non autorizzato.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../Pages/gestione_dispositivi.php');
    exit();
}

$dispositivo_seriale = $_POST['dispositivo_seriale'] ?? null;
$tipo_scadenza = trim($_POST['tipo_scadenza'] ?? '');
$data_scadenza = $_POST['data_scadenza'] ?? null;
$note = trim($_POST['note'] ?? '');

// Validazione
if (empty($dispositivo_seriale) || empty($tipo_scadenza) || empty($data_scadenza)) {
    $_SESSION['message'] = 'Errore: Tutti i campi per la scadenza sono obbligatori.';
    $_SESSION['status'] = 'error';
    header('Location: ../Pages/modifica_dispositivo.php?id=' . $dispositivo_seriale);
    exit();
}

try {
    $sql = "INSERT INTO Scadenze_Reminder (Dispositivo_Seriale, Data_Scadenza, Tipo_Scadenza, Note, Utente_Creazione_ID)
            VALUES (:dispositivo, :data_scadenza, :tipo, :note, :utente)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':dispositivo' => $dispositivo_seriale,
        ':data_scadenza' => $data_scadenza,
        ':tipo' => $tipo_scadenza,
        ':note' => $note,
        ':utente' => $user_id
    ]);

    $_SESSION['message'] = 'Nuova scadenza aggiunta con successo!';
    $_SESSION['status'] = 'success';

} catch (PDOException $e) {
    $_SESSION['message'] = 'Errore nel salvataggio della scadenza: ' . $e->getMessage();
    $_SESSION['status'] = 'error';
}

// Reindirizza sempre alla pagina di modifica da cui proveniva
header('Location: ../Pages/modifica_dispositivo.php?id=' . $dispositivo_seriale);
exit();
?>