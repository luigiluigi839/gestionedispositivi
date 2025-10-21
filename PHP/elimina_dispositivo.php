<?php
session_start();
require_once 'db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;

// MODIFICATO: Controllo sul permesso specifico di eliminazione
if (!isset($_SESSION['user_id']) || (!in_array('elimina_gestione_dispositivi', $user_permessi) && !$is_superuser)) {
    header('Location: ../Pages/dashboard.php?error=' . urlencode('Accesso non autorizzato.'));
    exit();
}

// Verifica che l'ID del dispositivo da eliminare sia passato nell'URL
if (!isset($_GET['id'])) {
    header('Location: ../Pages/gestione_dispositivi.php?error=' . urlencode('ID dispositivo non specificato.'));
    exit();
}

$dispositivo_serial_inrete = $_GET['id'];
$id_utente_loggato = $_SESSION['user_id'];
$data_cancellazione = date('Y-m-d H:i:s');

try {
    // Inizia una transazione per garantire l'integrità dei dati
    $pdo->beginTransaction();

    // 1. Recupera i dati del dispositivo, inclusi i nomi da altre tabelle
    $sql_select = "SELECT 
                       d.*, 
                       ma.Nome AS Marca, 
                       mo.Nome AS Modello,
                       t.Nome AS Tipologia
                   FROM Dispositivi d
                   LEFT JOIN Marche ma ON d.MarcaID = ma.ID
                   LEFT JOIN Modelli mo ON d.ModelloID = mo.ID
                   LEFT JOIN Tipologie t ON mo.Tipologia = t.ID
                   WHERE d.Seriale_Inrete = :id";
                   
    $stmt_select = $pdo->prepare($sql_select);
    $stmt_select->execute([':id' => $dispositivo_serial_inrete]);
    $dispositivo = $stmt_select->fetch(PDO::FETCH_ASSOC);

    if (!$dispositivo) {
        $pdo->rollBack();
        header('Location: ../Pages/gestione_dispositivi.php?error=' . urlencode('Dispositivo non trovato.'));
        exit();
    }

    // 2. Inserisci il record nella tabella "Dispositivi_Eliminati"
    $sql_insert = "INSERT INTO Dispositivi_Eliminati (
                        Seriale_Inrete, Codice_Articolo, Ubicazione, Stato, Marca, Modello, Tipologia, Seriale, Pin,
                        Prenotato_Da, Data_Prenotazione, Data_Ordine, Note, Proprieta, `B/N`, Colore, `C.C.C.`,
                        Utente_Ultima_Mod, Data_Ultima_Mod, Cancellato_Da, Data_Cancellazione
                    ) VALUES (
                        :seriale_inrete, :codice_articolo, :ubicazione, :stato, :marca, :modello, :tipologia, :seriale, :pin,
                        :prenotato_da, :data_prenotazione, :data_ordine, :note, :proprieta, :bn, :colore, :ccc,
                        :utente_ultima_mod, :data_ultima_mod, :cancellato_da, :data_cancellazione
                    )";

    $stmt_insert = $pdo->prepare($sql_insert);
    $stmt_insert->execute([
        ':seriale_inrete' => $dispositivo['Seriale_Inrete'],
        ':codice_articolo' => $dispositivo['Codice_Articolo'],
        ':ubicazione' => $dispositivo['Ubicazione'],
        ':stato' => $dispositivo['Stato'],
        ':marca' => $dispositivo['Marca'],
        ':modello' => $dispositivo['Modello'],
        ':tipologia' => $dispositivo['Tipologia'],
        ':seriale' => $dispositivo['Seriale'],
        ':pin' => $dispositivo['Pin'],
        ':prenotato_da' => $dispositivo['Prenotato_Da'],
        ':data_prenotazione' => $dispositivo['Data_Prenotazione'],
        ':data_ordine' => $dispositivo['Data_Ordine'],
        ':note' => $dispositivo['Note'],
        ':proprieta' => $dispositivo['Proprieta'],
        ':bn' => $dispositivo['B/N'],
        ':colore' => $dispositivo['Colore'],
        ':ccc' => $dispositivo['C.C.C.'],
        ':utente_ultima_mod' => $dispositivo['Utente_Ultima_Mod'],
        ':data_ultima_mod' => $dispositivo['Data_Ultima_Mod'],
        ':cancellato_da' => $id_utente_loggato,
        ':data_cancellazione' => $data_cancellazione
    ]);

    // 3. Elimina il record dalla tabella "Dispositivi"
    $sql_delete = "DELETE FROM Dispositivi WHERE Seriale_Inrete = :id";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->execute([':id' => $dispositivo_serial_inrete]);

    // Se tutto va a buon fine, conferma la transazione
    $pdo->commit();
    header('Location: ../Pages/gestione_dispositivi.php?success=' . urlencode('Dispositivo eliminato con successo!'));
    exit();

} catch (PDOException $e) {
    // In caso di errore, annulla tutte le operazioni
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ../Pages/gestione_dispositivi.php?error=' . urlencode('Errore durante l\'eliminazione: ' . $e->getMessage()));
    exit();
}
?>