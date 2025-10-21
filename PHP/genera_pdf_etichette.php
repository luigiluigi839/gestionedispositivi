<?php
// File: ../PHP/genera_pdf_etichette.php (LOGO INGRANDITO E BARCODE COMPRESSO)

// Disattiviamo la visualizzazione degli errori FATALI per impedire l'errore FPDF
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED); 

session_start();
require_once 'db_connect.php';
require_once 'fpdf/fpdf.php';

// **********************************************
// Inclusione del file autoload di Composer.
require_once __DIR__ . '/php-barcode-generator/vendor/autoload.php';
// **********************************************

// Controlla i permessi (invariato)
if (!isset($_SESSION['user_id'])) {
    die("Accesso non autorizzato.");
}

$seriali_array = [];
$output_filename = 'etichette_dispositivi.pdf';

// Logica per determinare l'input
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['seriali'])) {
    $seriali_input = trim($_POST['seriali']);
    $seriali_array = preg_split('/[\s,]+/', $seriali_input, -1, PREG_SPLIT_NO_EMPTY);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['seriale'])) {
    $seriale_input = filter_input(INPUT_GET, 'seriale', FILTER_SANITIZE_SPECIAL_CHARS);
    
    if ($seriale_input === false || $seriale_input === null) {
        // Se l'input GET fallisce, seriali_array resterà vuoto
    }

    $seriali_array = [trim($seriale_input)];
    $output_filename = 'etichetta_' . str_pad($seriale_input, 10, '0', STR_PAD_LEFT) . '.pdf';
    
} else {
    header('Location: ../Pages/stampa_etichette.php');
    exit();
}

if (empty($seriali_array) || empty(array_filter($seriali_array))) {
    die("Nessun seriale valido fornito."); 
}

// --- Logica Unificata di Recupero Dati (invariata) ---
try {
    $placeholders = implode(',', array_fill(0, count($seriali_array), '?'));
    $sql = "SELECT DISTINCT Seriale_Inrete FROM Dispositivi WHERE Seriale IN ($placeholders) OR Seriale_Inrete IN ($placeholders)";
    $params = array_merge($seriali_array, $seriali_array);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dispositivi = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($dispositivi)) {
        die("Nessun dispositivo trovato per i seriali forniti.");
    }
    
    // **********************************************
    // DEFINIZIONE LOGO E POSIZIONAMENTO AGGIORNATI
    $logo_path = '../IMG/logo_inrete.png'; 
    $logo_width = 25; // DIMENSIONE LOGO: 25mm
    $logo_height = 8; // DIMENSIONE LOGO: 8mm
    $margin_x = 1; 
    $top_y = 2; // Riga superiore
    
    // Posizione Seriale: Subito dopo il logo + 2mm di spazio
    $seriale_text_x = $margin_x + $logo_width + 2; 
    $font_size_seriale = 7; // Mantenuto a 7 per la compattezza
    
    // Y di partenza per il BARCODE (subito sotto la riga superiore + 1mm)
    $barcode_start_y = $top_y + $logo_height + 1; // 11mm
    
    // Dimensioni del BARCODE
    $barcode_width = 50; // DIMENSIONE BARCODE: 50mm
    $barcode_height = 15; // DIMENSIONE BARCODE: 15mm
    // **********************************************


    $pdf = new FPDF('L', 'mm', [30, 80]);
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false);
    
    $generator = new Picqer\Barcode\BarcodeGeneratorPNG();

    foreach ($dispositivi as $disp) {
        $seriale_inrete = str_pad($disp['Seriale_Inrete'], 10, '0', STR_PAD_LEFT);
        
        $pdf->AddPage();
        
        // --- 1. INCLUSIONE LOGO (RIGA SUPERIORE) ---
        if (file_exists($logo_path)) {
            $pdf->Image($logo_path, $margin_x, $top_y, $logo_width, $logo_height);
        }
        
        // --- 2. POSIZIONAMENTO TESTO SERIALE (AFFIANCATO) ---
        $pdf->SetFont('Arial', 'B', $font_size_seriale);
        // Centrato verticalmente rispetto al logo
        $text_y_center = $top_y + ($logo_height / 2) - 1.5; 
        
        $pdf->SetXY($seriale_text_x, $text_y_center); 
        $pdf->Cell(45, 5, "Seriale: " . $seriale_inrete, 0, 1, 'L'); 
        
        
        // --- 3. Generazione Barcode con Picqer ---
        $codice_con_invio = $seriale_inrete . "\r"; 
        $bar_height = 50;
        $bar_width_factor = 2; 

        $barcode_data = $generator->getBarcode($codice_con_invio, $generator::TYPE_CODE_128, $bar_width_factor, $bar_height);
        
        $tmp_file = tempnam(sys_get_temp_dir(), 'bc') . '.png';
        file_put_contents($tmp_file, $barcode_data);

        // --- 4. IMPOSTAZIONI FPDF (BARCODE IN ALTO) ---
        $barcode_x = (80 - $barcode_width) / 2; // X Centrato (es. 15mm)
        
        // Posiziona e inserisce l'immagine del barcode con le nuove dimensioni
        $pdf->Image($tmp_file, $barcode_x, $barcode_start_y, $barcode_width, $barcode_height, 'PNG');
        
        // Rimuove il file temporaneo
        unlink($tmp_file);
    }
    
    $pdf->Output('I', $output_filename);
    exit();

} catch (PDOException $e) {
    die("Errore del database: " . $e->getMessage());
} catch (Exception $e) {
    die("Errore nella generazione del PDF: " . $e->getMessage());
}
?>