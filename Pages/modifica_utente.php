<?php
session_start();
require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$current_user_id = $_SESSION['user_id'] ?? null;

// --- NUOVA LOGICA PERMESSI ---
$utente_id_da_modificare = $_GET['id'] ?? null;

if (!$current_user_id || !$utente_id_da_modificare) {
    header('Location: ../index.html'); // Se non loggato o manca l'ID, esci
    exit();
}

$is_self_edit = ($current_user_id == $utente_id_da_modificare);
$can_edit_others = in_array('modifica_utenti', $user_permessi) || $is_superuser;

// L'accesso è negato se l'utente non sta modificando se stesso E non ha i permessi per modificare gli altri
if (!$is_self_edit && !$can_edit_others) {
    header('Location: ../Pages/dashboard.php?error=Accesso non autorizzato');
    exit();
}
// --- FINE NUOVA LOGICA ---


$message = '';
$status = 'error'; // Default a 'error'
$utente = null;

// Se il modulo è stato inviato, elabora l'aggiornamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $cognome = $_POST['cognome'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    try {
        // Se si sta cercando di cambiare la password
        if (!empty($password)) {
            if ($password !== $password_confirm) {
                $message = "Le password non coincidono. Riprova.";
                // Salta al blocco catch per evitare l'aggiornamento
                throw new Exception($message);
            }
            
            // Se le password coincidono, la hash e la aggiorna
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE Utenti SET Nome = :nome, Cognome = :cognome, Email = :email, Password = :password_hash WHERE ID = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nome' => $nome,
                ':cognome' => $cognome,
                ':email' => $email,
                ':password_hash' => $password_hash,
                ':id' => $utente_id_da_modificare
            ]);
        } else {
            // Se la password non è stata inserita, aggiorna solo gli altri campi
            $sql = "UPDATE Utenti SET Nome = :nome, Cognome = :cognome, Email = :email WHERE ID = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nome' => $nome,
                ':cognome' => $cognome,
                ':email' => $email,
                ':id' => $utente_id_da_modificare
            ]);
        }
        $message = "Dati utente aggiornati con successo!";
        $status = 'success';

        // Se l'utente ha modificato se stesso, aggiorna anche il nome nella sessione
        if ($is_self_edit) {
            $_SESSION['user_name'] = $nome . ' ' . $cognome;
        }

    } catch (Exception $e) {
        // Se il messaggio non è già stato impostato (es. password non coincidenti), usa quello dell'eccezione
        if (empty($message)) {
            $message = "Errore durante l'aggiornamento: " . $e->getMessage();
        }
    }
}

// Recupera i dati correnti dell'utente per pre-compilare il form
try {
    $sql = "SELECT Nome, Cognome, Email FROM Utenti WHERE ID = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $utente_id_da_modificare]);
    $utente = $stmt->fetch();

    if (!$utente) {
        die("Utente non trovato.");
    }
} catch (PDOException $e) {
    die("Errore di recupero dati: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Modifica Dati Utente</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_forms.css">
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="form-container">
    <h2>Modifica Dati Utente: <?= htmlspecialchars($utente['Nome'] . ' ' . $utente['Cognome']) ?></h2>

    <?php if ($message): ?>
        <p class="message <?= htmlspecialchars($status) ?>"><?= $message ?></p>
    <?php endif; ?>

    <form action="modifica_utente.php?id=<?= htmlspecialchars($utente_id_da_modificare) ?>" method="POST">
        <div class="form-group">
            <label for="nome">Nome</label>
            <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($utente['Nome']) ?>" required>
        </div>
        <div class="form-group">
            <label for="cognome">Cognome</label>
            <input type="text" id="cognome" name="cognome" value="<?= htmlspecialchars($utente['Cognome']) ?>" required>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($utente['Email']) ?>" required>
        </div>
        <hr>
        <div class="form-group">
            <label for="password">Nuova Password (lascia vuoto per non cambiare)</label>
            <input type="password" id="password" name="password" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label for="password_confirm">Conferma Nuova Password</label>
            <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password">
        </div>
        <button type="submit">Salva Modifiche</button>
    </form>

    <a href="dashboard.php" class="back-link">Torna alla Dashboard</a>
</div>

<?php require_once '../PHP/footer.php'; ?>

</body>
</html>