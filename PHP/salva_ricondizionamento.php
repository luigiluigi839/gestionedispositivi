<?php
// File: ../PHP/salva_ricondizionamento.php
session_start();

require_once 'db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;
$user_permessi = $_SESSION['permessi'] ?? []; // Recupera i permessi per il controllo
$is_superuser = $_SESSION['is_superuser'] ?? false; // Recupera lo stato di superuser
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (!$user_id) {
    header('Location: ../index.html');
    exit();
}

function redirect($page, $status, $message, $id = null) {
    $url = "../Pages/$page.php?$status=" . urlencode($message);
    if ($id) { $url .= "&id=" . $id; }
    header('Location: ' . $url);
    exit();
}

// AZIONE: AVVIA NUOVO RICONDIZIONAMENTO
if ($action === 'avvia_nuovo' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $seriale = trim($_POST['seriale_inrete'] ?? ''); 
    if (empty($seriale) || !is_numeric($seriale)) { redirect('nuovo_ricondizionamento', 'error', 'Seriale non valido.'); }
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT ID FROM Ricondizionamenti WHERE Dispositivo_Seriale = ? AND Stato_Globale IN ('IN CORSO')");
        $stmt->execute([$seriale]);
        if ($ric_esistente = $stmt->fetch()) {
            $pdo->rollBack();
            redirect('gestisci_ricondizionamento', 'warning', 'Ricondizionamento già in corso.', $ric_esistente['ID']);
        }
        $stmt = $pdo->prepare("INSERT INTO Ricondizionamenti (Dispositivo_Seriale, Data_Inizio, Stato_Globale, Operatore_ID) VALUES (?, NOW(), 'IN CORSO', ?)");
        $stmt->execute([$seriale, $user_id]);
        $ricondizionamento_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO Ricondizionamenti_Dettagli (Ricondizionamento_ID) VALUES (?)");
        $stmt->execute([$ricondizionamento_id]);
        $pdo->commit();

        // --- LOGICA DI REDIRECT MODIFICATA ---
        // Controlla se l'utente ha il permesso di modificare i ricondizionamenti.
        if (in_array('modifica_ricondizionamenti', $user_permessi) || $is_superuser) {
            // Se ha il permesso, reindirizza alla pagina di modifica.
            redirect('gestisci_ricondizionamento', 'success', 'Modulo avviato. Puoi iniziare la compilazione.', $ricondizionamento_id);
        } else {
            // Altrimenti, reindirizza alla lista generale.
            redirect('gestione_ricondizionamenti', 'success', 'Ricondizionamento avviato con successo.');
        }
        // --- FINE LOGICA DI REDIRECT ---

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirect('nuovo_ricondizionamento', 'error', 'Errore DB: ' . $e->getMessage());
    }
}

// AZIONE: AGGIORNA PROGRESO O FINALIZZA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'aggiorna_progresso' || $action === 'finalizza_ricondizionamento')) {
    
    $ricond_id = $_POST['ricondizionamento_id'] ?? null;
    if (!$ricond_id) { redirect('gestione_ricondizionamenti', 'error', 'ID mancante.'); }

    // Controllo aggiuntivo: solo chi può modificare può salvare
    if (!in_array('modifica_ricondizionamenti', $user_permessi) && !$is_superuser) {
        redirect('gestisci_ricondizionamento', 'error', 'Non hai i permessi per salvare le modifiche.', $ricond_id);
    }

    try {
        $pdo->beginTransaction();

        function getValueOrNull($post_field) {
            return (isset($_POST[$post_field]) && $_POST[$post_field] !== '') ? $_POST[$post_field] : null;
        }
        
        $params = [
            ':bn_prima' => getValueOrNull('contatore_bn_prima'), ':bn_dopo' => getValueOrNull('contatore_bn_dopo'),
            ':colore_prima' => getValueOrNull('contatore_colore_prima'), ':colore_dopo' => getValueOrNull('contatore_colore_dopo'),
            ':ccc_prima' => getValueOrNull('ccc_prima'), ':ccc_dopo' => getValueOrNull('ccc_dopo'),
            ':toner_n' => getValueOrNull('toner_nero_perc'), ':toner_c' => getValueOrNull('toner_ciano_perc'), ':toner_m' => getValueOrNull('toner_magenta_perc'), ':toner_g' => getValueOrNull('toner_giallo_perc'),
            ':drum_n' => getValueOrNull('drum_nero_perc'), ':drum_c' => getValueOrNull('drum_ciano_perc'), ':drum_m' => getValueOrNull('drum_magenta_perc'), ':drum_g' => getValueOrNull('drum_giallo_perc'),
            ':ricond_id' => $ricond_id
        ];

        $update_details_sql = "UPDATE Ricondizionamenti_Dettagli SET 
            contatore_bn_prima = :bn_prima, contatore_bn_dopo = :bn_dopo,
            contatore_colore_prima = :colore_prima, contatore_colore_dopo = :colore_dopo,
            ccc_prima = :ccc_prima, ccc_dopo = :ccc_dopo,
            toner_nero_perc = :toner_n, toner_ciano_perc = :toner_c, toner_magenta_perc = :toner_m, toner_giallo_perc = :toner_g,
            drum_nero_perc = :drum_n, drum_ciano_perc = :drum_c, drum_magenta_perc = :drum_m, drum_giallo_perc = :drum_g,";

        $enum_fields = [
            'reset_fabbrica', 'reset_rete', 'reset_rubrica', 'reset_nome_macchina', 'azzeramento_contatori', 
            'aggiornamento_firmware', 'verifica_vaschetta', 'verifica_drum', 'verifica_belt', 'verifica_rullo2', 
            'verifica_lama', 'verifica_filtro', 'verifica_fusore', 'verifica_cassetti', 'verifica_plastiche', 
            'verifica_scheda_rete', 'verifica_fotocopia_lastra', 'verifica_fotocopia_dadf', 'verifica_stampa_fr_retro', 
            'verifica_rumori', 'verifica_documenti', 'verifica_cavo', 'verifica_etichetta', 'pulizia_interna', 'pulizia_esterna'
        ];

        foreach ($enum_fields as $field) {
            if (isset($_POST[$field])) {
                $value = strtoupper($_POST[$field]);
                $params[":$field"] = in_array($value, ['SI', 'NO']) ? $value : null; 
                $update_details_sql .= " $field = :$field,";
            }
        }

        if ($action === 'finalizza_ricondizionamento') {
            $grado_finale = $_POST['grado_finale'] ?? null;
            if ($grado_finale) {
                $params[':grado_finale'] = $grado_finale;
                $update_details_sql .= " Grado_Finale = :grado_finale,";
            }
        }
        
        $stmt_details = $pdo->prepare(rtrim($update_details_sql, ',') . " WHERE Ricondizionamento_ID = :ricond_id");
        $stmt_details->execute($params);

        $note = $_POST['note'] ?? '';
        $update_ricond_sql = "UPDATE Ricondizionamenti SET Note = :note";
        $params_ricond = [':note' => $note, ':ricond_id' => $ricond_id];
        
        if ($action === 'finalizza_ricondizionamento') {
            $azienda_dest = $_POST['azienda_destinazione'] ?: null;
            $update_ricond_sql .= ", Azienda_Destinazione = :azienda_dest";
            $params_ricond[':azienda_dest'] = $azienda_dest;
            $stmt_stato = $pdo->prepare("SELECT Nome FROM Stati WHERE ID = ?");
            $stmt_stato->execute([$grado_finale]);
            $nome_stato = $stmt_stato->fetchColumn();
            $stato_globale = (strpos($nome_stato, 'Demolire') !== false || strpos($nome_stato, 'Cannibalizzare') !== false || strpos($nome_stato, 'Demolito') !== false) ? 'DEMOLITO' : 'COMPLETATO';
            $update_ricond_sql .= ", Stato_Globale = :stato_globale, Data_Fine = NOW()";
            $params_ricond[':stato_globale'] = $stato_globale;
        }

        $stmt_ricond = $pdo->prepare($update_ricond_sql . " WHERE ID = :ricond_id");
        $stmt_ricond->execute($params_ricond);

        if ($action === 'finalizza_ricondizionamento') {
            $stmt = $pdo->prepare("SELECT Dispositivo_Seriale FROM Ricondizionamenti WHERE ID = ?");
            $stmt->execute([$ricond_id]);
            $dispositivo_seriale = $stmt->fetchColumn();
            
            $stmt_dispositivo = $pdo->prepare("UPDATE Dispositivi SET Stato = :stato, `B/N` = :bn, `Colore` = :colore, `C.C.C.` = :ccc, Data_Ultima_Mod = CURDATE(), Utente_Ultima_Mod = :user WHERE Seriale_Inrete = :seriale");
            $stmt_dispositivo->execute([':stato' => $grado_finale, ':bn' => $params[':bn_dopo'], ':colore' => $params[':colore_dopo'], ':ccc' => $params[':ccc_dopo'], ':user' => $user_id, ':seriale' => $dispositivo_seriale]);
            
            $pdo->commit();
            redirect('gestisci_ricondizionamento', 'success', 'Ricondizionamento FINALIZZATO!', $ricond_id);
        } else {
            $pdo->commit();
            redirect('gestisci_ricondizionamento', 'success', 'Progresso salvato.', $ricond_id);
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirect('gestisci_ricondizionamento', 'error', 'Errore DB: ' . $e->getMessage(), $ricond_id);
    }
}

redirect('gestione_ricondizionamenti', 'error', 'Azione non riconosciuta.');
?>