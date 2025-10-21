<?php
session_start();
require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;

// MODIFICATO: Controlla il nuovo permesso 'stampa_etichette'
if (!isset($_SESSION['user_id']) || (!in_array('stampa_etichette', $user_permessi) && !$is_superuser)) {
    header('Location: dashboard.php?error=' . urlencode('Accesso non autorizzato alla stampa etichette.'));
    exit();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Stampa Etichette con Codice a Barre</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_forms.css">
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="form-container">
    <h2>Stampa Etichette</h2>
    <p>Inserisci o scansiona i seriali dei dispositivi (fisici o interni) per generare un PDF con i codici a barre dei corrispondenti seriali Inrete.</p>
    
    <form action="../PHP/genera_pdf_etichette.php" method="POST" target="_blank">
        <div class="form-group">
            <label for="seriali_input">Elenco Seriali</label>
            <textarea id="seriali_input" name="seriali" rows="15" 
                      placeholder="Incolla o scansiona i seriali qui, uno per riga o separati da spazi/virgole..." 
                      required></textarea>
        </div>
        
        <button type="submit" class="submit-button">Genera PDF Etichette</button>
    </form>
     <a href="dashboard.php" class="back-link">Torna alla dashboard</a>
</div>

<?php require_once '../PHP/footer.php'; ?>

</body>
</html>