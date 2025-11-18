<?php
session_start();

require_once '../PHP/db_connect.php';

// --- CONTROLLO SICUREZZA E PERMESSI ---
$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$user_id = $_SESSION['user_id'] ?? null;
$aziende = $_SESSION['aziende_data'] ?? []; 

// CORRETTO: L'utente può accedere se può visualizzare O modificare (usando il nome del permesso corretto)
if (!isset($user_id) || (!in_array('visualizza_ricondizionamento', $user_permessi) && !in_array('modifica_ricondizionamenti', $user_permessi) && !$is_superuser)) {
    header('Location: ../Pages/dashboard.php?error=Accesso non autorizzato');
    exit();
}

// CORRETTO: Variabile helper per controllare se l'utente può modificare (usando il nome del permesso corretto)
$can_edit = in_array('modifica_ricondizionamenti', $user_permessi) || $is_superuser;

// --- FUNZIONI HELPER ---
function formatSerialeInrete($seriale) {
    return str_pad((string)$seriale, 10, '0', STR_PAD_LEFT);
}

function display_radio_group($label, $name, $current_value, $disabled) {
    $current_value = strtoupper($current_value ?? '');
    $disabled_attr = $disabled ? 'disabled' : '';
    $output = "<div class='checklist-item'><label class='radio-group-label'>$label</label><div class='radio-group'>";
    $output .= "<label class='radio-label'><input type='radio' name='$name' value='SI' " . ($current_value === 'SI' ? 'checked' : '') . " $disabled_attr> SI</label>";
    $output .= "<label class='radio-label'><input type='radio' name='$name' value='NO' " . ($current_value === 'NO' ? 'checked' : '') . " $disabled_attr> NO</label>";
    $output .= "<label class='radio-label'><input type='radio' name='$name' value='' " . ($current_value === '' ? 'checked' : '') . " $disabled_attr> N/A</label>";
    $output .= "</div></div>";
    return $output;
}

// --- INIZIALIZZAZIONE VARIABILI ---
$ricondizionamento_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$message = isset($_GET['warning']) ? $_GET['warning'] : (isset($_GET['error']) ? $_GET['error'] : (isset($_GET['success']) ? $_GET['success'] : ''));
$status = isset($_GET['warning']) ? 'warning' : (isset($_GET['error']) ? 'error' : (isset($_GET['success']) ? 'success' : ''));

$data = null;
$stati_finali = [];
$is_completed = false;
$is_accessory = false;

if (!$ricondizionamento_id) {
    header('Location: gestione_ricondizionamenti.php?error=' . urlencode('ID Ricondizionamento mancante.'));
    exit();
}

// --- RECUPERO DATI DAL DATABASE ---
try {
    $sql = "SELECT 
                r.*, 
                rd.*, 
                d.Seriale, d.Pin, 
                ma.Nome AS Marca, 
                mo.Nome AS Modello, 
                t.Nome AS TipologiaNome,
                o.Nome AS Nome_Operatore, 
                o.Cognome AS Cognome_Operatore 
            FROM Ricondizionamenti r 
            LEFT JOIN Ricondizionamenti_Dettagli rd ON r.ID = rd.Ricondizionamento_ID 
            LEFT JOIN Dispositivi d ON r.Dispositivo_Seriale = d.Seriale_Inrete 
            LEFT JOIN Marche ma ON d.MarcaID = ma.ID 
            LEFT JOIN Modelli mo ON d.ModelloID = mo.ID
            LEFT JOIN Tipologie t ON mo.Tipologia = t.ID
            LEFT JOIN Utenti o ON r.Operatore_ID = o.ID 
            WHERE r.ID = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ricondizionamento_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        header('Location: gestione_ricondizionamenti.php?error=' . urlencode('Ricondizionamento non trovato.'));
        exit();
    }
    
    if (isset($data['TipologiaNome']) && strpos($data['TipologiaNome'], 'Accessorio') !== false) {
        $is_accessory = true;
    }

    $is_completed = in_array($data['Stato_Globale'], ['COMPLETATO', 'DEMOLITO']);
    $stmt_stati = $pdo->query("SELECT ID, Nome FROM Stati WHERE Nome LIKE 'Ricondizionato Grado%' OR Nome IN ('Demolito', 'Da Cannibalizzare') ORDER BY Nome");
    $stati_finali = $stmt_stati->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = "Errore nel recupero dei dati: " . $e->getMessage();
    $status = 'error';
    $is_completed = true; 
}

// Strutture checklist
$checklist_fields_full = [
    'OPERAZIONI DI INIZIALIZZAZIONE' => ['reset_fabbrica' => 'Reset Fabbrica', 'reset_rete' => 'Reset Rete', 'reset_rubrica' => 'Reset Rubrica', 'reset_nome_macchina' => 'Reset Nome Macchina', 'azzeramento_contatori' => 'Azzeramento Contatori', 'aggiornamento_firmware' => 'Aggiornamento Firmware'],
    'OPERAZIONI DI VERIFICA E MANUTENZIONE' => ['verifica_vaschetta' => 'Verifica Vaschetta Recupero', 'verifica_drum' => 'Verifica Drum', 'verifica_belt' => 'Verifica Belt', 'verifica_rullo2' => 'Verifica 2° Rullo', 'verifica_lama' => 'Verifica Lama Pulizia', 'verifica_filtro' => 'Verifica Filtro Ozono', 'verifica_fusore' => 'Verifica Fusore', 'verifica_cassetti' => 'Verifica Cassetti', 'verifica_plastiche' => 'Verifica Plastiche', 'verifica_scheda_rete' => 'Verifica Scheda Rete', 'verifica_fotocopia_lastra' => 'Test Fotocopia da Lastra', 'verifica_fotocopia_dadf' => 'Test Fotocopia da DADF', 'verifica_stampa_fr_retro' => 'Test Stampa F/R', 'verifica_rumori' => 'Verifica Rumori Anomali', 'verifica_documenti' => 'Verifica Documenti/Manuali', 'verifica_cavo' => 'Verifica Cavo Alimentazione', 'verifica_etichetta' => 'Verifica Etichetta Seriale', 'pulizia_interna' => 'Pulizia Interna', 'pulizia_esterna' => 'Pulizia Esterna']
];
$checklist_fields_accessorio = [
    'OPERAZIONI DI VERIFICA' => ['reset_fabbrica' => 'Reset Impostazioni (se applicabile)', 'azzeramento_contatori' => 'Azzeramento Contatori (se applicabile)', 'verifica_cassetti' => 'Verifica Componenti Meccaniche', 'verifica_plastiche' => 'Verifica Integrità Plastiche', 'verifica_cavo' => 'Verifica Cavi di Collegamento', 'verifica_etichetta' => 'Verifica Etichetta Seriale'],
    'PULIZIA' => ['pulizia_interna' => 'Pulizia Interna', 'pulizia_esterna' => 'Pulizia Esterna']
];
$checklist_to_use = $is_accessory ? $checklist_fields_accessorio : $checklist_fields_full;
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestisci Ricondizionamento #<?= htmlspecialchars($ricondizionamento_id) ?></title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_forms.css">
    <link rel="stylesheet" href="../CSS/_cards.css">
    <link rel="stylesheet" href="../CSS/_tables.css">
    <link rel="stylesheet" href="../CSS/_search.css">
    <style> .consumables-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; } </style>
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="card-container" style="max-width: 900px;">
    <h2>Gestione Ricondizionamento #<?= htmlspecialchars($ricondizionamento_id) ?></h2>
    
    <?php if ($message): ?><p class="message <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($message) ?></p><?php endif; ?>

    <?php if ($data): ?>
    <div class="card">
        <h3>Dettagli Dispositivo</h3>
        <p><strong>Seriale Inrete:</strong> <?= htmlspecialchars(formatSerialeInrete($data['Dispositivo_Seriale'])) ?></p>
        <p><strong>Tipo:</strong> <span style="font-weight:bold; color: <?= $is_accessory ? '#dc3545' : '#007bff' ?>;"><?= htmlspecialchars($is_accessory ? 'Accessorio' : 'Corpo Macchina') ?></span></p>
        <p><strong>Marca/Modello:</strong> <?= htmlspecialchars(($data['Marca'] ?? 'N/D') . ' ' . ($data['Modello'] ?? '')) ?></p>
        <p><strong>Inizio:</strong> <?= date('d/m/Y H:i', strtotime($data['Data_Inizio'])) ?> | <strong>Operatore:</strong> <?= htmlspecialchars(($data['Nome_Operatore'] ?? 'Sconosciuto') . ' ' . ($data['Cognome_Operatore'] ?? '')) ?></p>
        <p><strong>Stato:</strong> <span class="status-badge status-<?= str_replace(' ', '_', strtolower($data['Stato_Globale'])) ?>"><?= htmlspecialchars($data['Stato_Globale']) ?></span>
            <?php if ($is_completed): ?> | <strong>Fine:</strong> <?= date('d/m/Y H:i', strtotime($data['Data_Fine'])) ?><?php endif; ?>
        </p>
    </div>

    <form action="../PHP/salva_ricondizionamento.php" method="POST">
        <input type="hidden" name="ricondizionamento_id" value="<?= htmlspecialchars($ricondizionamento_id) ?>">
        <input type="hidden" name="action" id="form-action" value="aggiorna_progresso">
        
        <fieldset <?= ($is_completed || !$can_edit) ? 'disabled' : '' ?>>

            <?php if (!$is_accessory): ?>
                <div class="card">
                    <h3>Contatori</h3>
                    <div class="consumables-grid">
                        <div class="form-group"><label>B/N (Prima)</label><input type="number" name="contatore_bn_prima" value="<?= htmlspecialchars($data['contatore_bn_prima'] ?? '') ?>" required></div>
                        <div class="form-group"><label>Colore (Prima)</label><input type="number" name="contatore_colore_prima" value="<?= htmlspecialchars($data['contatore_colore_prima'] ?? '') ?>"></div>
                        <div class="form-group"><label>C.C.C. (Prima)</label><input type="number" name="ccc_prima" value="<?= htmlspecialchars($data['ccc_prima'] ?? '') ?>"></div>
                        <div class="form-group"><label>B/N (Dopo)</label><input type="number" id="contatore_bn_dopo" name="contatore_bn_dopo" value="<?= htmlspecialchars($data['contatore_bn_dopo'] ?? '') ?>"></div>
                        <div class="form-group"><label>Colore (Dopo)</label><input type="number" id="contatore_colore_dopo" name="contatore_colore_dopo" value="<?= htmlspecialchars($data['contatore_colore_dopo'] ?? '') ?>"></div>
                        <div class="form-group"><label>C.C.C. (Dopo)</label><input type="number" id="ccc_dopo" name="ccc_dopo" value="<?= htmlspecialchars($data['ccc_dopo'] ?? '') ?>"></div>
                    </div>
                </div>
                <div class="card">
                    <h3>Stato Consumabili (%)</h3>
                    <h4>Toner</h4>
                    <div class="consumables-grid">
                        <div class="form-group"><label>Nero %</label><input type="number" id="toner_nero_perc" name="toner_nero_perc" min="0" max="100" value="<?= htmlspecialchars($data['toner_nero_perc'] ?? '') ?>"></div>
                        <div class="form-group"><label>Ciano %</label><input type="number" id="toner_ciano_perc" name="toner_ciano_perc" min="0" max="100" value="<?= htmlspecialchars($data['toner_ciano_perc'] ?? '') ?>"></div>
                        <div class="form-group"><label>Magenta %</label><input type="number" id="toner_magenta_perc" name="toner_magenta_perc" min="0" max="100" value="<?= htmlspecialchars($data['toner_magenta_perc'] ?? '') ?>"></div>
                        <div class="form-group"><label>Giallo %</label><input type="number" id="toner_giallo_perc" name="toner_giallo_perc" min="0" max="100" value="<?= htmlspecialchars($data['toner_giallo_perc'] ?? '') ?>"></div>
                    </div>
                    <h4>Drum (Facoltativo)</h4>
                    <div class="consumables-grid">
                        <div class="form-group"><label>Nero %</label><input type="number" name="drum_nero_perc" min="0" max="100" value="<?= htmlspecialchars($data['drum_nero_perc'] ?? '') ?>"></div>
                        <div class="form-group"><label>Ciano %</label><input type="number" name="drum_ciano_perc" min="0" max="100" value="<?= htmlspecialchars($data['drum_ciano_perc'] ?? '') ?>"></div>
                        <div class="form-group"><label>Magenta %</label><input type="number" name="drum_magenta_perc" min="0" max="100" value="<?= htmlspecialchars($data['drum_magenta_perc'] ?? '') ?>"></div>
                        <div class="form-group"><label>Giallo %</label><input type="number" name="drum_giallo_perc" min="0" max="100" value="<?= htmlspecialchars($data['drum_giallo_perc'] ?? '') ?>"></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach ($checklist_to_use as $section_title => $fields): ?>
            <div class="card checklist-section">
                <h3><?= htmlspecialchars($section_title) ?></h3>
                <?php foreach ($fields as $db_field => $label): ?>
                    <?= display_radio_group($label, $db_field, $data[$db_field] ?? null, ($is_completed || !$can_edit)) ?>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

             <div class="card">
                <h3>Note</h3>
                <div class="form-group"><label for="note">Note Aggiuntive</label><textarea id="note" name="note" rows="4"><?= htmlspecialchars($data['Note'] ?? '') ?></textarea></div>
            </div>
            
            <?php if (!$is_completed && $can_edit): ?>
                 <button type="submit" onclick="document.getElementById('form-action').value='aggiorna_progresso';">Salva Progresso</button>
             <?php endif; ?>

        <?php if (!$is_completed && $can_edit): ?>
            <div class="card finalizza-box">
                <h3>Finalizza Ricondizionamento</h3>
                <div class="form-group">
                    <label for="searchAziendaInput">Assegna ad Azienda (Facoltativo)</label>
                    <input type="text" id="searchAziendaInput" class="search-box" placeholder="Cerca per Ragione Sociale..." autocomplete="off">
                    <input type="hidden" name="azienda_destinazione" id="azienda_destinazione">
                    <div id="searchAziendeResults" class="search-results-list hidden"></div>
                </div>
                <div class="form-group">
                    <label for="grado_finale">Grado Finale del Dispositivo</label>
                    <select id="grado_finale" name="grado_finale">
                        <option value="">Seleziona lo stato finale...</option>
                        <?php foreach ($stati_finali as $stato): ?><option value="<?= htmlspecialchars($stato['ID']) ?>"><?= htmlspecialchars($stato['Nome']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" id="finalizza-button" onclick="return confirmFinalize();">Finalizza e Completa Lavorazione</button>
            </div>
        <?php endif; ?>
    </form>
    
    <div class="button-group">
        <a href="gestione_ricondizionamenti.php" class="back-button">Torna alla Lista</a>
        <?php if ($data['Stato_Globale'] === 'COMPLETATO' && (in_array('stampa_etichette', $user_permessi) || $is_superuser)): ?>
            <a href="../PHP/genera_pdf_ricondizionato.php?id=<?= htmlspecialchars($ricondizionamento_id) ?>" target="_blank" class="print-button">Stampa Etichetta Ricondizionato</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
    const isAccessorio = <?= $is_accessory ? 'true' : 'false' ?>;
    const aziende = <?= json_encode($aziende) ?>;

    function confirmFinalize() {
        let fieldsToValidate = {};
        if (isAccessorio) {
            fieldsToValidate = { 'Grado Finale': document.getElementById('grado_finale') };
        } else {
            fieldsToValidate = {
                'Grado Finale': document.getElementById('grado_finale'),
                'Contatore B/N (Dopo)': document.getElementById('contatore_bn_dopo'),
                'Toner Nero %': document.getElementById('toner_nero_perc')
            };
        }
        let errors = [];
        for (const [label, element] of Object.entries(fieldsToValidate)) {
            if (!element || element.value.trim() === '') {
                // Controlla se il valore non è '0' prima di considerarlo un errore
                if (element.value !== '0') {
                    errors.push(label);
                }
            }
        }
        if (errors.length > 0) {
            alert("Errore: I seguenti campi sono obbligatori per finalizzare:\n- " + errors.join("\n- "));
            return false;
        }
        const gradoText = fieldsToValidate['Grado Finale'].options[fieldsToValidate['Grado Finale'].selectedIndex].text;
        const confirmation = confirm(`Confermi la FINALIZZAZIONE con il grado: "${gradoText}"?`);
        if (confirmation) {
            document.getElementById('form-action').value = 'finalizza_ricondizionamento';
            return true;
        }
        return false;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchAziendaInput');
        const resultsContainer = document.getElementById('searchAziendeResults');
        const hiddenInput = document.getElementById('azienda_destinazione');
        if (!searchInput) return;

        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            resultsContainer.innerHTML = '';
            hiddenInput.value = ''; 
            if (query.length < 2) {
                resultsContainer.classList.add('hidden');
                return;
            }
            const filtered = aziende.filter(a => a.RagioneSociale.toLowerCase().includes(query));
            if (filtered.length > 0) {
                filtered.slice(0, 10).forEach(azienda => {
                    const item = document.createElement('div');
                    item.className = 'search-result-item';
                    item.textContent = azienda.RagioneSociale;
                    item.addEventListener('click', () => {
                        searchInput.value = azienda.RagioneSociale;
                        hiddenInput.value = azienda.RagioneSociale;
                        resultsContainer.classList.add('hidden');
                    });
                    resultsContainer.appendChild(item);
                });
                resultsContainer.classList.remove('hidden');
            } else {
                 resultsContainer.innerHTML = '<div class="search-result-item no-results">Nessuna azienda trovata</div>';
                 resultsContainer.classList.remove('hidden');
            }
        });

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
                resultsContainer.classList.add('hidden');
            }
        });
    });
    
   

</script>

<?php require_once '../PHP/footer.php'; ?>

</body>
</html>