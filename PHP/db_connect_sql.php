<?php
$serverName = "192.168.3.61";
$connectionInfo = array(
    "UID" => "readonly",
    "PWD" => "Grp!rt2023",
    "Database" => "NEiT", // Sostituisci con il nome del tuo database
    "CharacterSet" => "UTF-8"
);

try {
    $conn = sqlsrv_connect($serverName, $connectionInfo);
    if ($conn === false) {
        throw new Exception(sqlsrv_errors()[0]['message']);
    }
} catch (Exception $e) {
    die("Errore di connessione a SQL Server: " . $e->getMessage());
}
?>