<?php
session_start();

require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;

// Controllo sul permesso specifico di visualizzazione
if (!isset($_SESSION['user_id']) || (!in_array('visualizza_gestione_dispositivi', $user_permessi) && !$is_superuser)) {
    header('Location: ../Pages/dashboard.php?error=Accesso non autorizzato');
    exit();
}

$dispositivo_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$dispositivo = null;
$spostamenti = [];
$scadenze = []; 
$message = '';
$status = '';

if ($dispositivo_id) {
    try {
        $sql = "SELECT 
                    d.Seriale_Inrete, d.Codice_Articolo, ma.Nome AS Marca, mo.Nome AS Modello, 
                    t.Nome AS TipologiaNome,
                    d.Seriale, d.Pin, d.Data_Ordine, d.Data_Prenotazione, d.Note,
                    d.`B/N`, d.Colore, d.`C.C.C.`, d.Data_Ultima_Mod,
                    p.Nome AS ProprietaNome, ub.Nome AS UbicazioneNome, s.Nome AS StatoNome,
                    ut.Nome AS UtenteModNome, ut.Cognome AS UtenteModCognome,
                    utp.Nome AS PrenotatoNome, utp.Cognome AS PrenotatoCognome
                FROM Dispositivi d
                LEFT JOIN Marche ma ON d.MarcaID = ma.ID
                LEFT JOIN Modelli mo ON d.ModelloID = mo.ID
                LEFT JOIN Tipologie t ON mo.Tipologia = t.ID
                LEFT JOIN Proprieta p ON d.Proprieta = p.ID
                LEFT JOIN Ubicazioni ub ON d.Ubicazione = ub.ID
                LEFT JOIN Stati s ON d.Stato = s.ID
                LEFT JOIN Utenti ut ON d.Utente_Ultima_Mod = ut.ID
                LEFT JOIN Utenti utp ON d.Prenotato_Da = utp.ID
                WHERE d.Seriale_Inrete = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $dispositivo_id]);
        $dispositivo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dispositivo) {
            $message = "Dispositivo non trovato.";
            $status = 'error';
        } else {
            if (in_array('visualizza_spostamenti', $user_permessi) || $is_superuser) {
                $sql_spostamenti = "SELECT * FROM Spostamenti WHERE Dispositivo = :id ORDER BY Data_Install DESC";
                $stmt_spostamenti = $pdo->prepare($sql_spostamenti);
                $stmt_spostamenti->execute([':id' => $dispositivo_id]);
                $spostamenti = $stmt_spostamenti->fetchAll(PDO::FETCH_ASSOC);
            }

            // MODIFICATO: Carica le scadenze solo se l'utente ha il permesso
            if (in_array('dashboard_reminder', $user_permessi) || $is_superuser) {
                $sql_scadenze = "SELECT * FROM Scadenze_Reminder WHERE Dispositivo_Seriale = :id ORDER BY Data_Scadenza ASC";
                $stmt_scadenze = $pdo->prepare($sql_scadenze);
                $stmt_scadenze->execute([':id' => $dispositivo_id]);
                $scadenze = $stmt_scadenze->fetchAll(PDO::FETCH_ASSOC);
            }
        }

    } catch (PDOException $e) {
        $message = "Errore nel recupero dei dati: " . $e->getMessage();
        $status = 'error';
    }
} else {
    $message = "ID dispositivo non specificato.";
    $status = 'error';
}

function formatDate($dateString) {
    if (!$dateString) return '';
    return date('d/m/Y', strtotime($dateString));
}

function formatSeriale($seriale) {
    return str_pad((string)$seriale, 10, '0', STR_PAD_LEFT);
}

$seriale_formattato = formatSeriale($dispositivo['Seriale_Inrete'] ?? '');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Visualizza Dispositivo</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_forms.css">
    <link rel="stylesheet" href="../CSS/_cards.css">
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div id="main-content" class="card-container">
    <h2>Dettagli Dispositivo</h2>
    <?php if ($message): ?>
        <p class="message <?= htmlspecialchars($status) ?>"><span class="icon">&#10008;</span> <?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if ($dispositivo): ?>
        <div class="card">
            <h3>Informazioni Generali</h3>
            <p><strong>Seriale Inrete:</strong> <?= htmlspecialchars($seriale_formattato) ?></p>
            <p><strong>Tipologia:</strong> <?= htmlspecialchars($dispositivo['TipologiaNome'] ?? 'Non definita') ?></p>
            <p><strong>Codice Articolo:</strong> <?= htmlspecialchars($dispositivo['Codice_Articolo'] ?? '') ?></p>
            <p><strong>Marca:</strong> <?= htmlspecialchars($dispositivo['Marca'] ?? '') ?></p>
            <p><strong>Modello:</strong> <?= htmlspecialchars($dispositivo['Modello'] ?? '') ?></p>
            <p><strong>Seriale:</strong> <?= htmlspecialchars($dispositivo['Seriale'] ?? '') ?></p>
        </div>
        
        <div class="card">
            <h3>Dati Amministrativi</h3>
            <p><strong>Data Ordine:</strong> <?= formatDate($dispositivo['Data_Ordine'] ?? '') ?></p>
            <p><strong>Data Prenotazione:</strong> <?= formatDate($dispositivo['Data_Prenotazione'] ?? '') ?></p>
            <p><strong>Prenotato Da:</strong> <?= htmlspecialchars(($dispositivo['PrenotatoNome'] ?? '') . ' ' . ($dispositivo['PrenotatoCognome'] ?? '')) ?></p>
            <p><strong>Proprietà:</strong> <?= htmlspecialchars($dispositivo['ProprietaNome'] ?? 'Non Assegnata') ?></p>
        </div>

        <div class="card">
            <h3>Dati di Magazzino</h3>
            <p><strong>Ubicazione:</strong> <?= htmlspecialchars($dispositivo['UbicazioneNome'] ?? '') ?></p>
            <p><strong>Stato:</strong> <?= htmlspecialchars($dispositivo['StatoNome'] ?? '') ?></p>
        </div>

        <?php if (isset($dispositivo['TipologiaNome']) && strpos($dispositivo['TipologiaNome'], 'Stampante') !== false): ?>
            <div class="card">
                <h3>Dati Tecnici</h3>
                <p><strong>Pin:</strong> <?= htmlspecialchars($dispositivo['Pin'] ?? '') ?></p>
                <p><strong>B/N:</strong> <?= htmlspecialchars($dispositivo['B/N'] ?? '') ?></p>
                <p><strong>Colore:</strong> <?= htmlspecialchars($dispositivo['Colore'] ?? '') ?></p>
                
                <?php if (isset($dispositivo['TipologiaNome']) && strpos($dispositivo['TipologiaNome'], 'Production') !== false): ?>
                    <p><strong>C.C.C.:</strong> <?= htmlspecialchars($dispositivo['C.C.C.'] ?? '') ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (in_array('dashboard_reminder', $user_permessi) || $is_superuser): ?>
            <div class="card">
                <h3>Scadenze Associate</h3>
                <?php if (count($scadenze) > 0): ?>
                    <table class="spostamenti-table">
                        <thead>
                            <tr>
                                <th>Tipo Scadenza</th>
                                <th>Data Scadenza</th>
                                <th>Stato</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scadenze as $scadenza): 
                                $isScaduto = strtotime($scadenza['Data_Scadenza']) < time();
                            ?>
                                <tr class="<?= $isScaduto && $scadenza['Stato'] === 'Attiva' ? 'scaduto' : '' ?>">
                                    <td><?= htmlspecialchars($scadenza['Tipo_Scadenza']) ?></td>
                                    <td><?= formatDate($scadenza['Data_Scadenza']) ?></td>
                                    <td><?= htmlspecialchars($scadenza['Stato']) ?></td>
                                    <td><?= htmlspecialchars($scadenza['Note']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Nessuna scadenza associata a questo dispositivo.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (in_array('visualizza_spostamenti', $user_permessi) || $is_superuser): ?>
            <div class="card">
                <h3>Spostamenti</h3>
                <?php if (count($spostamenti) > 0): ?>
                    <table class="spostamenti-table">
                        <thead><tr><th>Azienda</th><th>Data Installazione</th><th>Data Rientro</th><th>Note</th></tr></thead>
                        <tbody>
                            <?php foreach ($spostamenti as $spostamento): ?>
                                <tr>
                                    <td><?= htmlspecialchars($spostamento['Azienda'] ?? '') ?></td>
                                    <td><?= formatDate($spostamento['Data_Install'] ?? '') ?></td>
                                    <td><?= formatDate($spostamento['Data_Ritiro'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($spostamento['Note'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Nessun spostamento registrato per questo dispositivo.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3>Note</h3>
            <p><?= nl2br(htmlspecialchars($dispositivo['Note'] ?? '')) ?></p>
        </div>
        
        <div class="card">
            <h3>Log Modifiche</h3>
            <p><strong>Ultima Modifica:</strong> <?= formatDate($dispositivo['Data_Ultima_Mod'] ?? '') ?></p>
            <p><strong>Utente Modifica:</strong> <?= htmlspecialchars(($dispositivo['UtenteModNome'] ?? '') . ' ' . ($dispositivo['UtenteModCognome'] ?? '')) ?></p>
        </div>
        
        <div class="button-group">
            <a href="gestione_dispositivi.php" class="back-button">Torna alla gestione</a>
            
            <?php if (in_array('stampa_etichette', $user_permessi) || $is_superuser): ?>
                <a href="../PHP/genera_pdf_etichette.php?seriale=<?= htmlspecialchars($dispositivo['Seriale_Inrete'] ?? '') ?>" 
                   target="_blank" class="print-button">Genera Etichetta PDF</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../PHP/footer.php'; ?>
</body>
</html>