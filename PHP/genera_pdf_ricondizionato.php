<?php
// File: ../PHP/genera_pdf_ricondizionato.php (MODIFICATO CON LAYOUT FORZATO SU PAGINA SINGOLA)
session_start();

require_once 'db_connect.php';
require_once 'fpdf/fpdf.php'; // Assicurati che il percorso alla libreria FPDF sia corretto

// --- CONTROLLO SICUREZZA E PERMESSI ---
$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$user_id = $_SESSION['user_id'] ?? null;

if (!isset($user_id) || (!in_array('visualizza_ricondizionamento', $user_permessi) && !$is_superuser)) {
    header('HTTP/1.1 403 Forbidden');
    exit('Accesso negato.');
}

// --- RECUPERO E VALIDAZIONE INPUT ---
$ricondizionamento_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$ricondizionamento_id) {
    die('ID ricondizionamento non valido o mancante.');
}

// --- RECUPERO DATI DAL DATABASE ---
try {
    $sql = "SELECT 
                r.Azienda_Destinazione,
                d.Seriale,
                mo.Nome AS Modello,
                rd.toner_ciano_perc,
                rd.toner_magenta_perc,
                rd.toner_giallo_perc,
                rd.toner_nero_perc
            FROM Ricondizionamenti r
            JOIN Ricondizionamenti_Dettagli rd ON r.ID = rd.Ricondizionamento_ID
            JOIN Dispositivi d ON r.Dispositivo_Seriale = d.Seriale_Inrete
            JOIN Modelli mo ON d.ModelloID = mo.ID
            WHERE r.ID = ? AND r.Stato_Globale = 'COMPLETATO'";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ricondizionamento_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        die('Dati del ricondizionamento completato non trovati.');
    }

} catch (PDOException $e) {
    die('Errore database: ' . $e->getMessage());
}


// --- GENERAZIONE PDF ---

// Orientamento Landscape (orizzontale) e formato A4
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetMargins(10, 10, 10);
// Disabilita l'aggiunta automatica di pagine per forzare tutto su una singola pagina
$pdf->SetAutoPageBreak(false); 
$pdf->AddPage();

// Aggiunta del logo
$logoPath = '../IMG/logo_inrete.png';
if (file_exists($logoPath)) {
    // Calcola la posizione X per centrare il logo
    $pageWidth = $pdf->GetPageWidth();
    $logoWidth = 80;
    $logoX = ($pageWidth - $logoWidth) / 2;
    $pdf->Image($logoPath, $logoX, 15, $logoWidth);
}

// Sposta il cursore verticalmente sotto il logo per il blocco di testo
$pdf->SetY(65);

// Gestione del cliente non assegnato
if (!empty($data['Azienda_Destinazione'])) {
    $cliente_text = 'CLIENTE: ' . $data['Azienda_Destinazione'];
} else {
    $cliente_text = 'CLIENTE DA ASSEGNARE';
}
$cliente = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $cliente_text);

// MODIFICA: Dimensioni font leggermente ridotte per garantire che tutto rientri
$pdf->SetFont('Arial', 'B', 44);
$pdf->MultiCell(0, 22, $cliente, 0, 'C');
$pdf->Ln(5); // Aggiunge un piccolo spazio

// Modello
$pdf->SetFont('Arial', '', 32);
$pdf->Cell(0, 22, 'MODELLO: ' . iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $data['Modello']), 0, 1, 'C');
$pdf->Ln(5);

// Seriale
$pdf->Cell(0, 22, 'S/N: ' . iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $data['Seriale']), 0, 1, 'C');
$pdf->Ln(5);

// Riga Toner
$toner_string = sprintf(
    'C %d%% - M %d%% - Y %d%% - K %d%%',
    $data['toner_ciano_perc'] ?? 0,
    $data['toner_magenta_perc'] ?? 0,
    $data['toner_giallo_perc'] ?? 0,
    $data['toner_nero_perc'] ?? 0
);
$pdf->SetFont('Arial', 'B', 26);
$pdf->Cell(0, 22, $toner_string, 0, 1, 'C');


// Output del PDF
$filename = "scheda_ricondizionato_" . $ricondizionamento_id . ".pdf";
$pdf->Output('I', $filename);
exit();
?>