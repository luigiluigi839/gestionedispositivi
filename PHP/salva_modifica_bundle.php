<?php
// File: PHP/salva_modifica_bundle.php
session_start();
require_once 'db_connect.php';

// Sicurezza e Permessi
$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$user_id = $_SESSION['user_id'] ?? null;
$id_ubicazione_cliente = 9; // ID per "Installato Presso Cliente"

if (!isset($user_id) || (!in_array('modifica_bundle', $user_permessi) && !$is_superuser)) {
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
// Lista finale degli accessori che devono essere nel bundle
$accessori_finali = $_POST['accessori'] ?? [];

if (empty($corpo_macchina_seriale)) {
    $_SESSION['bundle_message'] = 'Errore: ID del dispositivo principale mancante.';
    $_SESSION['bundle_status'] = 'error';
    header('Location: ../Pages/gestione_bundle.php');
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Recupera i dati del corpo macchina (soprattutto l'ubicazione)
    $stmt_corpo = $pdo->prepare("SELECT Ubicazione FROM Dispositivi WHERE Seriale_Inrete = :id");
    $stmt_corpo->execute([':id' => $corpo_macchina_seriale]);
    $corpo_macchina = $stmt_corpo->fetch(PDO::FETCH_ASSOC);

    if (!$corpo_macchina) {
        throw new Exception('Corpo macchina non trovato.');
    }
    $is_installed = ($corpo_macchina['Ubicazione'] == $id_ubicazione_cliente);

    // 2. Recupera gli accessori attualmente nel bundle (prima della modifica)
    $stmt_originali = $pdo->prepare("SELECT Accessorio_Seriale FROM Bundle_Dispositivi WHERE CorpoMacchina_Seriale = :id");
    $stmt_originali->execute([':id' => $corpo_macchina_seriale]);
    $accessori_originali = $stmt_originali->fetchAll(PDO::FETCH_COLUMN);

    // 3. Calcola le differenze
    $accessori_da_aggiungere = array_diff($accessori_finali, $accessori_originali);
    // $accessori_da_rimuovere = array_diff($accessori_originali, $accessori_finali); // Logica di rimozione gestita solo nella tabella bundle

    // 4. GESTIONE AGGIUNTE: Se il bundle è installato e ci sono nuovi accessori
    $installati_automaticamente = 0;
    if ($is_installed && !empty($accessori_da_aggiungere)) {
        // 4a. Trova i dati dell'installazione attiva del corpo macchina
        $stmt_spostamento = $pdo->prepare("SELECT Azienda, Nolo_Cash, Assistenza FROM Spostamenti WHERE Dispositivo = :id AND Data_Ritiro IS NULL ORDER BY Data_Install DESC LIMIT 1");
        $stmt_spostamento->execute([':id' => $corpo_macchina_seriale]);
        $spostamento_attivo = $stmt_spostamento->fetch(PDO::FETCH_ASSOC);

        if (!$spostamento_attivo) {
            throw new Exception('Stato dati inconsistente: il dispositivo è installato (Ubicazione 9) ma non ha uno spostamento attivo.');
        }

        $data_oggi = date('Y-m-d');
        
        // MODIFICATO: Aggiornata la nota come da richiesta
        $note_aggiunta = 'Aggiunto a bundle con dispositivo principale ' . $corpo_macchina_seriale . ' in data ' . $data_oggi;
        
        $sql_insert_spostamento = "INSERT INTO Spostamenti (Dispositivo, Data_Install, Azienda, Nolo_Cash, Assistenza, Note, Utente_Ultima_Mod, Data_Ultima_Mod) VALUES (:dispositivo, :data_install, :azienda, :nolo_cash, :assistenza, :note, :utente_mod, NOW())";
        $stmt_insert_spostamento = $pdo->prepare($sql_insert_spostamento);
        
        $sql_update_dispositivo = "UPDATE Dispositivi SET Ubicazione = :id_ubicazione, Utente_Ultima_Mod = :utente_mod, Data_Ultima_Mod = NOW() WHERE Seriale_Inrete = :seriale_dispositivo";
        $stmt_update_dispositivo = $pdo->prepare($sql_update_dispositivo);

        foreach ($accessori_da_aggiungere as $accessorio_seriale) {
            // Aggiungi nuovo spostamento per l'accessorio
            $stmt_insert_spostamento->execute([
                ':dispositivo' => $accessorio_seriale,
                ':data_install' => $data_oggi,
                ':azienda' => $spostamento_attivo['Azienda'],
                ':nolo_cash' => $spostamento_attivo['Nolo_Cash'],
                ':assistenza' => $spostamento_attivo['Assistenza'],
                ':note' => $note_aggiunta,
                ':utente_mod' => $user_id
            ]);
            
            // Aggiorna l'ubicazione dell'accessorio a "Installato"
            $stmt_update_dispositivo->execute([
                ':id_ubicazione' => $id_ubicazione_cliente,
                ':utente_mod' => $user_id,
                ':seriale_dispositivo' => $accessorio_seriale
            ]);
            $installati_automaticamente++;
        }
    }

    // 5. Aggiorna la tabella Bundle_Dispositivi (cancellando e ricreando)
    $sql_delete = "DELETE FROM Bundle_Dispositivi WHERE CorpoMacchina_Seriale = :corpo_macchina";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->execute([':corpo_macchina' => $corpo_macchina_seriale]);

    if (!empty($accessori_finali)) {
        $sql_insert_bundle = "INSERT INTO Bundle_Dispositivi (CorpoMacchina_Seriale, Accessorio_Seriale, Utente_Creazione_ID) VALUES (:corpo_macchina, :accessorio, :utente_id)";
        $stmt_insert_bundle = $pdo->prepare($sql_insert_bundle);

        foreach ($accessori_finali as $accessorio_seriale) {
            $stmt_insert_bundle->execute([
                ':corpo_macchina' => $corpo_macchina_seriale,
                ':accessorio' => $accessorio_seriale,
                ':utente_id' => $user_id
            ]);
        }
    }
    
    $pdo->commit();

    // 6. Prepara il messaggio di successo
    if (empty($accessori_finali)) {
        $_SESSION['bundle_message'] = 'Tutti gli accessori sono stati rimossi. Il bundle è stato eliminato.';
    } else {
        $message = 'Bundle aggiornato con successo!';
        if ($installati_automaticamente > 0) {
            $message .= " $installati_automaticamente nuovo/i accessorio/i sono stati installati automaticamente presso il cliente.";
        }
    }
    $_SESSION['bundle_message'] = $message;
    $_SESSION['bundle_status'] = 'success';
    
    header('Location: ../Pages/gestione_bundle.php');
    exit();

} catch (Exception $e) { 
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['bundle_message'] = 'Errore durante la modifica: ' . $e->getMessage();
    $_SESSION['bundle_status'] = 'error';
    header('Location: ../Pages/modifica_bundle.php?id=' . $corpo_macchina_seriale);
    exit();
}
?>