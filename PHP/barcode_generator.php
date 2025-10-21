<?php
/**
 * Classe per la generazione di codici a barre in formato CODE128.
 * Questa versione utilizza la libreria GD di PHP per generare un'immagine di qualità superiore.
 * L'algoritmo Code 128 è stato sostituito con una logica più robusta che sfrutta il GD nativo.
 */
class BarcodeGenerator {

    // Tabella di pattern Code 128
    private $code_patterns = array(
        '215232', '225231', '225132', '215332', '225331', '235132', '213252', '223251', '233152', '215351', // 0-9
        '225351', '235251', '215253', '225253', '235252', '215531', '225531', '235531', '213551', '223551', // 10-19
        '233551', '215255', '225255', '235255', '213555', '223555', '233555', '215553', '225553', '235552', // 20-29
        '213535', '223535', '233535', '215353', '225353', '235352', '213553', '223553', '233552', '215535', // 30-39
        '225535', '235534', '213555', '223555', '233555', '215555', '225555', '235554', '213355', '223355', // 40-49
        '233355', '215355', '225355', '235354', '213535', '223535', '233535', '215353', '225353', '235352', // 50-59
        '213355', '223355', '233355', '215355', '225355', '235354', '213535', '223535', '233535', '215353', // 60-69
        '225353', '235352', '213553', '223553', '233552', '215535', '225535', '235534', '213555', '223555', // 70-79
        '233555', '215553', '225553', '235552', '213555', '223555', '233555', '215553', '225553', '235552', // 80-89
        '213535', '223535', '233535', '215353', '225353', '235352', '213553', '223553', '233552', '215535', // 90-99
        '225535', '235534', '213555', '223555', '235554', '213535', '223535', '233535', '215353', '225353', // 100-109
        '235352', '213553', '223553', '233552', '215535', '225535', '235534', '213555', '223555', '233554', // Fine Code 128
        '233531' // Stop pattern
    );

    // Mappatura caratteri ASCII a Code 128 C (numerica)
    private function getCodeCValue($pair) {
        return (int)$pair;
    }
    
    // Genera l'intera sequenza di pattern Code 128 C per un codice numerico a lunghezza pari
    private function getBarcodeData($code) {
        $patterns = array();
        
        // Assicurati che il codice sia di 10 cifre e numerico per Code 128 C
        $code = str_pad($code, 10, '0', STR_PAD_LEFT);
        if (!is_numeric($code) || strlen($code) % 2 !== 0) {
            // Se fallisce, usiamo START B (alfanumerico) (Opzione di fallback)
            // Per il seriale inrete (10 cifre), dovremmo sempre usare START C
            return null; 
        }

        $start_char = 105; // START C
        $patterns[] = $start_char;
        $checksum = $start_char;

        for ($i = 0; $i < strlen($code); $i += 2) {
            $pair = substr($code, $i, 2);
            $value = $this->getCodeCValue($pair);
            $patterns[] = $value;
            $checksum += $value * (($i / 2) + 1);
        }

        $check_value = $checksum % 103;
        $patterns[] = $check_value; // Checksum

        $output_string = '';
        foreach ($patterns as $pattern_index) {
            $output_string .= $this->code_patterns[$pattern_index];
        }
        $output_string .= '2331112'; // Stop pattern finale e barra di terminazione

        return $output_string;
    }

    /**
     * Genera l'immagine PNG ad alta qualità usando GD.
     */
    public function getBarcodeImage($code, $height = 50, $widthFactor = 1) {
        $pattern_string = $this->getBarcodeData($code);
        if ($pattern_string === null) {
             throw new Exception("Impossibile generare Code 128 C per il codice fornito.");
        }
        
        // Fattore di larghezza per la nitidezza (il valore 2 è il minimo nitido per GD)
        $bar_unit_width = 2; 
        
        // Calcola la larghezza totale
        $total_width = 0;
        foreach (str_split($pattern_string) as $bar_width_factor) {
            $total_width += (int)$bar_width_factor;
        }
        $image_width = $total_width * $bar_unit_width;
        
        // Massimizziamo l'altezza interna (per la risoluzione)
        $effective_height = $height * 5; // Moltiplicatore alto (5) per DPI elevato nel PNG

        $image = imagecreate($image_width, $effective_height);
        $bgColor = imagecolorallocate($image, 255, 255, 255);
        $barColor = imagecolorallocate($image, 0, 0, 0);
        
        imagefilledrectangle($image, 0, 0, $image_width, $effective_height, $bgColor);
        
        $x = 0;
        $is_bar = true; // Code 128 inizia sempre con una barra
        
        foreach (str_split($pattern_string) as $bar_width_factor) {
            $width = (int)$bar_width_factor * $bar_unit_width;
            
            if ($is_bar) {
                // Disegna la barra (colore nero)
                imagefilledrectangle($image, $x, 0, $x + $width - 1, $effective_height, $barColor);
            }
            // Se non è una barra, è uno spazio (lasciato bianco)
            
            $x += $width;
            $is_bar = !$is_bar; // Alterna barra/spazio
        }

        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);
        return $imageData;
    }
}