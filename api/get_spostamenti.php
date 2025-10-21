<?php
// File: api/get_spostamenti.php
// Questo endpoint recupera i dati degli spostamenti e li restituisce in formato JSON.

// Abilita la visualizzazione degli errori per il debug (da rimuovere in produzione)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
session_start();
require_once '../PHP/db_connect.php';

// Sicurezza: Controlla che l'utente sia loggato e abbia i permessi
$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$id_utente_loggato = $_SESSION['user_id'] ?? null;

if (!$id_utente_loggato || (!in_array('dashboard_gestione_spostamenti', $user_permessi) && !$is_superuser)) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Accesso non autorizzato']);
    exit();
}

$grouped_spostamenti = [];

try {
    $query = "
        SELECT 
            s.ID, s.Dispositivo, d.Seriale, d.Seriale_Inrete, ma.Nome as Marca, mo.Nome as Modello, 
            s.Azienda, s.Data_Install, s.Data_Ritiro, s.Nolo_Cash, s.Assistenza,
            COALESCE(b1.CorpoMacchina_Seriale, b2.CorpoMacchina_Seriale) as bundle_parent_id,
            CASE WHEN b1.CorpoMacchina_Seriale IS NOT NULL THEN 1 ELSE 0 END as is_main_device,
            CASE WHEN (b1.CorpoMacchina_Seriale IS NOT NULL OR b2.Accessorio_Seriale IS NOT NULL) THEN 1 ELSE 0 END AS is_bundle_part
        FROM Spostamenti s
        LEFT JOIN Dispositivi d ON s.Dispositivo = d.Seriale_Inrete
        LEFT JOIN Modelli mo ON d.ModelloID = mo.ID
        LEFT JOIN Marche ma ON d.MarcaID = ma.ID
        LEFT JOIN Bundle_Dispositivi b1 ON d.Seriale_Inrete = b1.CorpoMacchina_Seriale
        LEFT JOIN Bundle_Dispositivi b2 ON d.Seriale_Inrete = b2.Accessorio_Seriale
        GROUP BY s.ID
        ORDER BY s.Data_Install DESC, s.Azienda, is_main_device DESC";
    $stmt = $pdo->query($query);
    $all_spostamenti = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $installations = [];
    foreach ($all_spostamenti as $spostamento) {
        $key = $spostamento['Azienda'] . '|' . $spostamento['Data_Install'];
        if ($spostamento['bundle_parent_id']) {
            $key .= '|' . $spostamento['bundle_parent_id'];
        } else {
            $key .= '|single-' . $spostamento['ID'];
        }
        if (!isset($installations[$key])) {
            $installations[$key] = [];
        }
        $installations[$key][] = $spostamento;
    }

    foreach ($installations as $group_key => $group) {
        $main_device = null;
        $accessories = [];
        foreach ($group as $spostamento) {
            if ($spostamento['is_main_device'] || !$spostamento['bundle_parent_id']) {
                $main_device = $spostamento;
                break;
            }
        }
        if (!$main_device) $main_device = $group[0];

        foreach ($group as $spostamento) {
            if ($spostamento['ID'] !== $main_device['ID']) {
                $accessories[] = $spostamento;
            }
        }
        $grouped_spostamenti[$group_key] = ['main' => $main_device, 'accessori' => $accessories];
    }

    echo json_encode($grouped_spostamenti);

} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Errore di connessione al database: ' . $e->getMessage()]);
}
?>
