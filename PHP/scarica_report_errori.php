<?php
session_start();

// Sicurezza: Controlla che l'utente sia loggato
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Accesso non autorizzato.');
}

// Controlla se esiste un report di errori nella sessione
if (!isset($_SESSION['error_report']) || empty($_SESSION['error_report'])) {
    die("Nessun report di errori da scaricare.");
}

$error_report = $_SESSION['error_report'];

// Pulisci il report dalla sessione per evitare download multipli dello stesso file
unset($_SESSION['error_report']);

// Prepara il nome del file
$filename = "report_errori_rientro_" . date('Y-m-d_H-i-s') . ".csv";

// Imposta gli header per forzare il download del file
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Apre lo stream di output per scrivere il file CSV
$output = fopen('php://output', 'w');

// Aggiunge il BOM per la compatibilità con Excel per i caratteri UTF-8
fputs($output, "\xEF\xBB\xBF");

// Scrive l'intestazione del file CSV
fputcsv($output, ['Seriale_Input', 'Motivo_Errore_o_Avviso'], ';');

// Scrive ogni riga di errore nel file CSV
foreach ($error_report as $error) {
    // MODIFICATO: Aggiunta la formattazione condizionale del seriale
    $seriale_originale = $error['seriale'];
    $seriale_formattato = $seriale_originale; // Di default, usa il valore originale

    // Se il seriale è puramente numerico, applica il padding a 10 cifre
    if (is_numeric($seriale_originale)) {
        $seriale_formattato = str_pad((string)$seriale_originale, 10, '0', STR_PAD_LEFT);
    }
    
    fputcsv($output, [$seriale_formattato, $error['motivo']], ';');
}

// Chiude lo stream
fclose($output);
exit();
?>