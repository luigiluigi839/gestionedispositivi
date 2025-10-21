<?php
// File: PHP/get_bundle_accessories.php (Unificato)
session_start();
require_once 'db_connect.php';

// Sicurezza base
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Accesso non autorizzato.']);
    exit();
}

$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING) ?? 'get_accessories'; // Default è prendere gli accessori

if (!$id) {
    header('Content-Type: application/json');
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'ID non valido.']);
    exit();
}

try {
    if ($action === 'get_device_details') {
        // --- Logica che prima era in get_device_details.php ---
        $sql = "SELECT d.Seriale_Inrete, d.Seriale, ma.Nome AS Marca, mo.Nome AS Modello, d.Proprieta
                FROM Dispositivi d
                LEFT JOIN Marche ma ON d.MarcaID = ma.ID
                LEFT JOIN Modelli mo ON d.ModelloID = mo.ID
                WHERE d.Seriale_Inrete = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            http_response_code(404); // Not Found
            $result = ['error' => 'Dispositivo non trovato.'];
        }

    } else {
        // --- Logica originale di questo file (get_accessories) ---
        $sql = "SELECT d.Seriale, ma.Nome AS Marca, mo.Nome AS Modello
                FROM Bundle_Dispositivi bd
                JOIN Dispositivi d ON bd.Accessorio_Seriale = d.Seriale_Inrete
                JOIN Marche ma ON d.MarcaID = ma.ID
                JOIN Modelli mo ON d.ModelloID = mo.ID
                WHERE bd.CorpoMacchina_Seriale = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    header('Content-Type: application/json');
    echo json_encode($result);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Errore database: ' . $e->getMessage()]);
}
?>