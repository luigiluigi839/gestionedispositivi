<?php
session_start();
require_once 'db_connect.php';

// Sicurezza: Controlla permessi utente
$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$id_utente_loggato = $_SESSION['user_id'] ?? null;

// MODIFICATO: Controllo sul permesso specifico di eliminazione
if (!isset($id_utente_loggato) || (!in_array('elimina_ricondizionamenti', $user_permessi) && !$is_superuser)) {
    header('Location: ../Pages/dashboard.php?error=' . urlencode('Accesso non autorizzato.'));
    exit();
}

// Validazione dell'input
$ricondizionamento_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$ricondizionamento_id) {
    header('Location: ../Pages/gestione_ricondizionamenti.php?error=' . urlencode('ID non valido.'));
    exit();
}

try {
    // Usa una transazione per garantire l'integrità dei dati
    $pdo->beginTransaction();

    // 1. Recupera i dati del ricondizionamento da eliminare
    $stmt_select = $pdo->prepare("SELECT * FROM Ricondizionamenti WHERE ID = ?");
    $stmt_select->execute([$ricondizionamento_id]);
    $ricondizionamento = $stmt_select->fetch(PDO::FETCH_ASSOC);

    if (!$ricondizionamento) {
        $pdo->rollBack();
        header('Location: ../Pages/gestione_ricondizionamenti.php?error=' . urlencode('Ricondizionamento non trovato.'));
        exit();
    }

    // 2. Inserisci il record nella tabella di archivio "Ricondizionamenti_Eliminati"
    $sql_insert = "INSERT INTO Ricondizionamenti_Eliminati 
                    (ID, Dispositivo_Seriale, Data_Inizio, Data_Fine, Stato_Globale, Operatore_ID, Note, Azienda_Destinazione, Cancellato_Da, Data_Cancellazione)
                   VALUES
                    (:id, :dispositivo_seriale, :data_inizio, :data_fine, :stato_globale, :operatore_id, :note, :azienda_dest, :cancellato_da, NOW())";
    
    $stmt_insert = $pdo->prepare($sql_insert);
    $stmt_insert->execute([
        ':id' => $ricondizionamento['ID'],
        ':dispositivo_seriale' => $ricondizionamento['Dispositivo_Seriale'],
        ':data_inizio' => $ricondizionamento['Data_Inizio'],
        ':data_fine' => $ricondizionamento['Data_Fine'],
        ':stato_globale' => $ricondizionamento['Stato_Globale'],
        ':operatore_id' => $ricondizionamento['Operatore_ID'],
        ':note' => $ricondizionamento['Note'],
        ':azienda_dest' => $ricondizionamento['Azienda_Destinazione'],
        ':cancellato_da' => $id_utente_loggato
    ]);

    // 3. Elimina il record dalla tabella originale "Ricondizionamenti"
    // NOTA: I dettagli in 'Ricondizionamenti_Dettagli' verranno eliminati automaticamente grazie a ON DELETE CASCADE.
    $stmt_delete = $pdo->prepare("DELETE FROM Ricondizionamenti WHERE ID = ?");
    $stmt_delete->execute([$ricondizionamento_id]);

    // Se tutto è andato a buon fine, conferma le modifiche
    $pdo->commit();
    header('Location: ../Pages/gestione_ricondizionamenti.php?success=' . urlencode('Ricondizionamento eliminato con successo.'));
    exit();

} catch (PDOException $e) {
    // In caso di errore, annulla tutte le operazioni
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ../Pages/gestione_ricondizionamenti.php?error=' . urlencode('Errore durante l\'eliminazione: ' . $e->getMessage()));
    exit();
}
?>