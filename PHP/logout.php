<?php
session_start();

// Distrugge tutte le variabili di sessione.
$_SESSION = array();

// Se si usa anche un cookie di sessione, lo si cancella.
// Nota: questo distruggerà la sessione, e non solo i dati della sessione!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, distrugge la sessione.
session_destroy();

// Reindirizza l'utente alla pagina di login (index.html), che si trova nella cartella principale
header('Location: ../index.html');
exit();
?>