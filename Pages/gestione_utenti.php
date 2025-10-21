<?php
session_start();

// Includi il file di connessione al database
require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser_loggato = $_SESSION['is_superuser'] ?? false;
$show_superuser_column = $is_superuser_loggato;

// MODIFICATO: Controllo sul permesso specifico della dashboard
if (!isset($_SESSION['user_id']) || (!in_array('dashboard_gestione_utenti', $user_permessi) && !$is_superuser_loggato)) {
    header('Location: ../Pages/dashboard.php?error=Accesso non autorizzato');
    exit();
}

// Recupera i messaggi di stato dall'URL in base ai nomi corretti
$message = '';
$status = '';
$utenti = []; // Inizializza l'array per evitare errori

if (isset($_GET['success'])) {
    $message = $_GET['success'];
    $status = 'success';
} elseif (isset($_GET['error'])) {
    $message = $_GET['error'];
    $status = 'error';
}

try {
    // Query per recuperare gli utenti
    if ($is_superuser_loggato) {
        $sql = "SELECT ID, Nome, Cognome, Email, is_Superuser FROM Utenti ORDER BY Cognome, Nome";
    } else {
        $sql = "SELECT ID, Nome, Cognome, Email, is_Superuser FROM Utenti WHERE is_Superuser = 0 ORDER BY Cognome, Nome";
    }

    $stmt = $pdo->query($sql);
    $utenti = $stmt->fetchAll();

} catch (PDOException $e) {
    // Gestione dell'errore di connessione o query
    $message = "Errore durante il recupero degli utenti: " . $e->getMessage();
    $status = 'error';
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Utenti</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_tables.css">
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="table-container">
    <h2>Gestione Utenti</h2>
    
    <?php if (in_array('inserisci_utenti', $user_permessi) || $is_superuser_loggato): ?>
        <a href="aggiungi_utente.php" class="add-button">Aggiungi Nuovo Utente</a>
    <?php endif; ?>

    <input type="text" id="searchInput" class="search-box" placeholder="Cerca un utente...">
    
    <?php if ($message): ?>
        <p class="message <?= htmlspecialchars($status) ?>">
            <span class="icon">
                <?php if ($status === 'success'): ?>
                    &#10004; <?php else: ?>
                    &#10008; <?php endif; ?>
            </span>
            <?= htmlspecialchars(urldecode($message)) ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($utenti)): ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Cognome</th>
                <th>Email</th>
                <?php if ($show_superuser_column): ?>
                <th>Super Utente</th>
                <?php endif; ?>
                <th>Azioni</th>
            </tr>
        </thead>
        <tbody id="userTableBody">
            <?php foreach ($utenti as $utente): ?>
                <tr>
                    <td><?= htmlspecialchars($utente['ID']) ?></td>
                    <td><?= htmlspecialchars($utente['Nome']) ?></td>
                    <td><?= htmlspecialchars($utente['Cognome']) ?></td>
                    <td><?= htmlspecialchars($utente['Email']) ?></td>
                    <?php if ($show_superuser_column): ?>
                    <td><?= $utente['is_Superuser'] ? 'Sì' : 'No' ?></td>
                    <?php endif; ?>
                    <td class="action-buttons">
                        <?php if (in_array('modifica_utenti', $user_permessi) || $is_superuser_loggato): ?>
                            <a href="modifica_utente.php?id=<?= $utente['ID'] ?>" class="btn btn-modifica" title="Modifica">✏️</a>
                        <?php endif; ?>

                        <?php if (in_array('modifica_permessi_utenti', $user_permessi) || $is_superuser_loggato): ?>
                            <a href="modifica_permessi.php?id=<?= $utente['ID'] ?>" class="btn btn-visualizza" title="Permessi">⚙️</a>
                        <?php endif; ?>

                        <?php if (in_array('elimina_utenti', $user_permessi) || $is_superuser_loggato): ?>
                            <a href="../PHP/elimina_utente.php?id=<?= $utente['ID'] ?>" class="btn btn-elimina" onclick="return confirm('Sei sicuro di voler eliminare questo utente?');" title="Elimina">🗑️</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <?php if (empty($message) || $status != 'error'): ?>
            <p class="warning">Nessun utente trovato.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    // Funzione per filtrare la tabella (invariata)
    document.getElementById('searchInput').addEventListener('keyup', function() {
        let filter = this.value.toUpperCase();
        let table = document.getElementById('userTableBody');
        let tr = table.getElementsByTagName('tr');

        for (let i = 0; i < tr.length; i++) {
            let found = false;
            // Cerca in tutte le celle eccetto l'ultima (azioni)
            for (let j = 0; j < tr[i].cells.length - 1; j++) {
                let cell = tr[i].cells[j];
                if (cell) {
                    let textValue = cell.textContent || cell.innerText;
                    if (textValue.toUpperCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
            }
            if (found) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    });
</script>

<?php require_once '../PHP/footer.php'; ?>

</body>
</html>