<?php
session_start();
require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;

// Controlla che l'utente sia loggato e abbia i permessi necessari
if (!isset($_SESSION['user_id']) || (!in_array('gestione_utenti', $user_permessi) && !$is_superuser)) {
    header('Location: ../index.html');
    exit();
}

$message = '';

// Se il modulo è stato inviato, elabora i dati
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $cognome = trim($_POST['cognome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    // L'utente non superuser non può impostare is_superuser, quindi il valore di default è 0
    $is_superuser_new = $is_superuser && isset($_POST['is_superuser']) ? 1 : 0;
    
    // Validazione base
    if (empty($nome) || empty($cognome) || empty($email) || empty($password)) {
        $message = "Per favore, compila tutti i campi obbligatori.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Formato email non valido.";
    } elseif (strlen($password) < 6) {
        $message = "La password deve contenere almeno 6 caratteri.";
    } else {
        try {
            // Controlla se l'email esiste già
            $sql_check = "SELECT ID FROM Utenti WHERE Email = :email";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->execute([':email' => $email]);
            if ($stmt_check->fetch()) {
                $message = "Esiste già un utente con questa email.";
            } else {
                // Cripta la password
                $password_hashed = password_hash($password, PASSWORD_DEFAULT);

                // Inserisci il nuovo utente nel database
                $sql_insert = "INSERT INTO Utenti (Nome, Cognome, Email, Password, is_Superuser) VALUES (:nome, :cognome, :email, :password, :is_superuser)";
                $stmt_insert = $pdo->prepare($sql_insert);
                $stmt_insert->execute([
                    ':nome' => $nome,
                    ':cognome' => $cognome,
                    ':email' => $email,
                    ':password' => $password_hashed,
                    ':is_superuser' => $is_superuser_new
                ]);
                
                // Reindirizza con un messaggio di successo
                header('Location: gestione_utenti.php?success=' . urlencode('Utente aggiunto con successo.'));
                exit();
            }
        } catch (PDOException $e) {
            $message = "Errore durante l'aggiunta dell'utente: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Aggiungi Utente</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_forms.css">
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="form-container">
    <h2>Aggiungi Nuovo Utente</h2>
    
    <?php if ($message): ?>
        <p class="message error"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    
    <form action="aggiungi_utente.php" method="POST">
        <div class="form-group">
            <label for="nome">Nome</label>
            <input type="text" id="nome" name="nome" required value="<?= isset($nome) ? htmlspecialchars($nome) : '' ?>">
        </div>
        <div class="form-group">
            <label for="cognome">Cognome</label>
            <input type="text" id="cognome" name="cognome" required value="<?= isset($cognome) ? htmlspecialchars($cognome) : '' ?>">
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <?php if ($is_superuser): ?>
        <div class="form-group checkbox-group">
            <input type="checkbox" id="is_superuser" name="is_superuser" <?= isset($_POST['is_superuser']) ? 'checked' : '' ?>>
            <label for="is_superuser">È un Super Utente</label>
        </div>
        <?php endif; ?>
        
        <button type="submit">Aggiungi Utente</button>
    </form>
    
    <a href="gestione_utenti.php" class="back-link">Torna alla Gestione Utenti</a>
</div>

<?php require_once '../PHP/footer.php'; ?>

</body>
</html>