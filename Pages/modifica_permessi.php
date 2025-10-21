<?php
session_start();
require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser_loggato = $_SESSION['is_superuser'] ?? false;

// Aggiornato controllo permesso
if (!isset($_SESSION['user_id']) || (!in_array('modifica_permessi_utenti', $user_permessi) && !$is_superuser_loggato)) {
    header('Location: ../index.html');
    exit();
}

if (!isset($_GET['id'])) {
    die("ID utente non specificato.");
}

$utente_id = $_GET['id'];
$message = '';
$is_superuser_da_modificare = false;

// Logica di salvataggio POST (invariata)...
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $permessi_selezionati = $_POST['permessi'] ?? [];
    $is_superuser_da_modificare_new = $is_superuser_loggato && isset($_POST['is_superuser_mod']) ? 1 : 0;
    
    try {
        if ($is_superuser_loggato && $utente_id == $_SESSION['user_id'] && $is_superuser_da_modificare_new == 0) {
            $sql_count = "SELECT COUNT(*) FROM Utenti WHERE is_Superuser = 1";
            $stmt_count = $pdo->query($sql_count);
            $count = $stmt_count->fetchColumn();
            if ($count <= 1) {
                $message = "Non puoi rimuovere i tuoi privilegi da Super Utente perché sei l'ultimo rimasto.";
                goto end_of_post_try_block;
            }
        }
        $pdo->beginTransaction();
        if ($is_superuser_loggato) {
            $sql_update_user = "UPDATE Utenti SET is_Superuser = :is_superuser WHERE ID = :utente_id";
            $stmt_update_user = $pdo->prepare($sql_update_user);
            $stmt_update_user->execute([':is_superuser' => $is_superuser_da_modificare_new, ':utente_id' => $utente_id]);
        }
        $sql_delete = "DELETE FROM Permessi WHERE Utente = :utente_id";
        $stmt_delete = $pdo->prepare($sql_delete);
        $stmt_delete->execute([':utente_id' => $utente_id]);
        if (!empty($permessi_selezionati)) {
            $sql_insert = "INSERT INTO Permessi (Utente, Sezione) VALUES (:utente_id, :sezione_id)";
            $stmt_insert = $pdo->prepare($sql_insert);
            foreach ($permessi_selezionati as $sezione_id) {
                $stmt_insert->execute([':utente_id' => $utente_id, ':sezione_id' => $sezione_id]);
            }
        }
        $pdo->commit();
        $message = "Permessi utente aggiornati con successo.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Errore durante l'aggiornamento dei permessi: " . $e->getMessage();
    }
    end_of_post_try_block:;
}


// Recupera i dati per la visualizzazione
try {
    $sql_user = "SELECT Nome, Cognome, is_Superuser FROM Utenti WHERE ID = :id";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->execute([':id' => $utente_id]);
    $utente = $stmt_user->fetch();

    if (!$utente) { die("Utente non trovato."); }
    $is_superuser_da_modificare = $utente['is_Superuser'];

    // MODIFICATO: La query ora recupera anche Nome_Visuallizzato
    $sql_pagine = "SELECT ID, Nome_Pagina, Nome_Visuallizzato, Gruppo FROM Pagine ORDER BY Gruppo, ID";
    $stmt_pagine = $pdo->query($sql_pagine);
    $pagine = $stmt_pagine->fetchAll(PDO::FETCH_ASSOC);

    $pagine_raggruppate = [];
    foreach ($pagine as $pagina) {
        $gruppo = !empty($pagina['Gruppo']) ? $pagina['Gruppo'] : 'Generale';
        if (!isset($pagine_raggruppate[$gruppo])) {
            $pagine_raggruppate[$gruppo] = [];
        }
        $pagine_raggruppate[$gruppo][] = $pagina;
    }

    $sql_permessi_correnti = "SELECT Sezione FROM Permessi WHERE Utente = :utente_id";
    $stmt_permessi_correnti = $pdo->prepare($sql_permessi_correnti);
    $stmt_permessi_correnti->execute([':utente_id' => $utente_id]);
    $permessi_correnti = $stmt_permessi_correnti->fetchAll(PDO::FETCH_COLUMN, 0);

} catch (PDOException $e) {
    die("Errore di recupero dati: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Modifica Permessi</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_forms.css">
    <style>
        .permessi-group { border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; margin: 0; background-color: #f8f9fa; }
        .permessi-group legend { font-weight: bold; color: #007bff; padding: 0 10px; font-size: 1.2em; }
    </style>
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="form-container">
    <h2>Modifica Permessi per: <?= htmlspecialchars($utente['Nome']) . ' ' . htmlspecialchars($utente['Cognome']) ?></h2>
    <?php if ($message): ?>
        <p class="message <?= strpos($message, 'successo') !== false ? 'success' : 'error' ?>"><?= $message ?></p>
    <?php endif; ?>
    
    <form action="modifica_permessi.php?id=<?= $utente_id ?>" method="POST">
        <?php if ($is_superuser_loggato): ?>
        <div class="form-group checkbox-group">
            <input type="checkbox" id="is_superuser_mod" name="is_superuser_mod" <?= $is_superuser_da_modificare ? 'checked' : '' ?>>
            <label for="is_superuser_mod">È un Super Utente (ha accesso a tutto)</label>
        </div>
        <hr style="margin-bottom: 25px;">
        <?php endif; ?>
        
        <div class="permessi-container">
            <?php foreach ($pagine_raggruppate as $gruppo_nome => $permessi_nel_gruppo): ?>
                <fieldset class="permessi-group">
                    <legend><?= htmlspecialchars($gruppo_nome) ?></legend>
                    <div class="permessi-grid">
                        <?php foreach ($permessi_nel_gruppo as $pagina): ?>
                            <div class="permesso-item">
                                <input type="checkbox"
                                       id="permesso-<?= $pagina['ID'] ?>"
                                       name="permessi[]"
                                       value="<?= $pagina['ID'] ?>"
                                       <?= in_array($pagina['ID'], $permessi_correnti) ? 'checked' : '' ?>>
                                <label for="permesso-<?= $pagina['ID'] ?>">
                                    <?= htmlspecialchars($pagina['Nome_Visuallizzato']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endforeach; ?>
        </div>
        
        <button type="submit" style="margin-top: 25px;">Salva Permessi</button>
    </form>
    
    <a href="gestione_utenti.php" class="back-link">Torna alla Gestione Utenti</a>
</div>

<?php require_once '../PHP/footer.php'; ?>

</body>
</html>