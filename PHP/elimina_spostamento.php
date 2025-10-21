<?php
// File: PHP/elimina_spostamento.php
session_start();
require_once 'db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$id_utente_loggato = $_SESSION['user_id'];

// Controllo sul permesso specifico di eliminazione
if (!isset($id_utente_loggato) || (!in_array('elimina_spostamenti', $user_permessi) && !$is_superuser)) {
    $_SESSION['form_message'] = ['text' => 'Accesso non autorizzato.', 'status' => 'error'];
    header('Location: ../Pages/dashboard_gestione_spostamenti.php');
    exit();
}

$id_spostamento = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

if (!$id_spostamento) {
    $_SESSION['form_message'] = ['text' => 'ID dello spostamento non valido o non fornito.', 'status' => 'error'];
    header('Location: ../Pages/dashboard_gestione_spostamenti.php');
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Recupera i dettagli dello spostamento da eliminare
    $stmt_orig = $pdo->prepare("SELECT Dispositivo, Azienda, Data_Install FROM Spostamenti WHERE ID = :id");
    $stmt_orig->execute([':id' => $id_spostamento]);
    $original_spostamento = $stmt_orig->fetch();

    if (!$original_spostamento) {
        throw new Exception('Record di spostamento non trovato.');
    }
    
    $dispositivo_id_originale = $original_spostamento['Dispositivo'];

    // 2. Controlla se il dispositivo fa parte di un bundle
    $stmt_bundle = $pdo->prepare("SELECT CorpoMacchina_Seriale FROM Bundle_Dispositivi WHERE CorpoMacchina_Seriale = :id1 OR Accessorio_Seriale = :id2 LIMIT 1");
    $stmt_bundle->execute([':id1' => $dispositivo_id_originale, ':id2' => $dispositivo_id_originale]);
    $bundle_info = $stmt_bundle->fetch();

    if ($bundle_info) {
        // Il dispositivo è in un bundle. Elimina tutti i record di quella installazione per quel bundle.
        $corpo_macchina_id = $bundle_info['CorpoMacchina_Seriale'];

        // Trova tutti i dispositivi in quel bundle
        $stmt_all_bundle_devs = $pdo->prepare("SELECT CorpoMacchina_Seriale as seriale FROM Bundle_Dispositivi WHERE CorpoMacchina_Seriale = :id1 UNION SELECT Accessorio_Seriale as seriale FROM Bundle_Dispositivi WHERE CorpoMacchina_Seriale = :id2");
        $stmt_all_bundle_devs->execute([':id1' => $corpo_macchina_id, ':id2' => $corpo_macchina_id]);
        $bundle_device_ids = $stmt_all_bundle_devs->fetchAll(PDO::FETCH_COLUMN);

        $in_placeholders = implode(',', array_fill(0, count($bundle_device_ids), '?'));
        
        $delete_sql = "DELETE FROM Spostamenti WHERE Dispositivo IN ($in_placeholders) AND Azienda = ? AND Data_Install = ?";
        $stmt_delete = $pdo->prepare($delete_sql);
        
        $params = array_merge($bundle_device_ids, [$original_spostamento['Azienda'], $original_spostamento['Data_Install']]);
        $stmt_delete->execute($params);

        $success_message = "L'intero spostamento del bundle è stato eliminato con successo!";
    } else {
        // Dispositivo singolo, elimina solo questo record
        $delete_sql = "DELETE FROM Spostamenti WHERE ID = :id";
        $stmt_delete = $pdo->prepare($delete_sql);
        $stmt_delete->execute([':id' => $id_spostamento]);
        $success_message = 'Record di spostamento eliminato con successo!';
    }

    $pdo->commit();
    $_SESSION['form_message'] = ['text' => $success_message, 'status' => 'success'];

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['form_message'] = ['text' => 'Errore durante l\'eliminazione: ' . $e->getMessage(), 'status' => 'error'];
}

header('Location: ../Pages/dashboard_gestione_spostamenti.php');
exit();
?>