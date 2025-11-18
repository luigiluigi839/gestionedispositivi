<?php
// File: ../PHP/salva_ricondizionamento.php
session_start();

require_once 'db_connect.php';

$user_id = $_SESSION['user_id'] ?? null;
$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// NUOVO: Definisci l'ID dello stato "In Ricondizionamento" (assumiamo sia 14)
define('STATO_IN_RICONDIZIONAMENTO', 14);


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
        $stmt = $pdo->prepare("SELECT ID FROM Ricondizionamenti WHERE Dispositivo_Seriale = ? AND Stato_Globale NOT IN ('COMPLETATO', 'DEMOLITO')");
        $stmt->execute([$seriale]);
        if ($ric_esistente = $stmt->fetch()) {
            $pdo->rollBack();
            $stato_esistente_stmt = $pdo->prepare("SELECT Stato_Globale FROM Ricondizionamenti WHERE ID = ?");
            $stato_esistente_stmt->execute([$ric_esistente['ID']]);
            $stato_esistente = $stato_esistente_stmt->fetchColumn();
            $warning_msg = ($stato_esistente === 'IN CORSO') ? 'Ricondizionamento già in corso.' : 'Ricondizionamento già avviato (stato: Da Verificare/Ricondizionare).';
            redirect('gestisci_ricondizionamento', 'warning', $warning_msg, $ric_esistente['ID']);
        }

        $stato_iniziale_ricondizionamento = 'Da Verificare/Ricondizionare';
        $stmt_insert_ricond = $pdo->prepare("INSERT INTO Ricondizionamenti (Dispositivo_Seriale, Data_Inizio, Stato_Globale, Operatore_ID) VALUES (?, NOW(), ?, ?)");
        $stmt_insert_ricond->execute([$seriale, $stato_iniziale_ricondizionamento, $user_id]);
        $ricondizionamento_id = $pdo->lastInsertId();

        $stmt_insert_details = $pdo->prepare("INSERT INTO Ricondizionamenti_Dettagli (Ricondizionamento_ID) VALUES (?)");
        $stmt_insert_details->execute([$ricondizionamento_id]);

        // NUOVO: Aggiorna lo stato del dispositivo a "In Ricondizionamento" (ID 14)
        $stmt_update_dispositivo = $pdo->prepare("UPDATE Dispositivi SET Stato = :stato_ricond, Utente_Ultima_Mod = :user, Data_Ultima_Mod = CURDATE() WHERE Seriale_Inrete = :seriale");
        $stmt_update_dispositivo->execute([
            ':stato_ricond' => STATO_IN_RICONDIZIONAMENTO,
            ':user' => $user_id,
            ':seriale' => $seriale
        ]);
        // FINE NUOVO BLOCCO

        $pdo->commit();

        if (in_array('modifica_ricondizionamenti', $user_permessi) || $is_superuser) {
             redirect('gestisci_ricondizionamento', 'success', 'Modulo avviato (stato: Da Verificare/Ricondizionare). Stato dispositivo aggiornato. Puoi iniziare la compilazione.', $ricondizionamento_id);
        } else {
             redirect('gestione_ricondizionamenti', 'success', 'Ricondizionamento avviato con successo. Stato dispositivo aggiornato.');
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirect('nuovo_ricondizionamento', 'error', 'Errore DB: ' . $e->getMessage());
    }
}

// AZIONE: AGGIORNA PROGRESO O FINALIZZA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'aggiorna_progresso' || $action === 'finalizza_ricondizionamento')) {

    $ricond_id = $_POST['ricondizionamento_id'] ?? null;
    if (!$ricond_id) { redirect('gestione_ricondizionamenti', 'error', 'ID mancante.'); }

    if (!in_array('modifica_ricondizionamenti', $user_permessi) && !$is_superuser) {
        redirect('gestisci_ricondizionamento', 'error', 'Non hai i permessi per salvare le modifiche.', $ricond_id);
    }

    try {
        $pdo->beginTransaction();

        $stmt_stato_attuale = $pdo->prepare("SELECT Stato_Globale FROM Ricondizionamenti WHERE ID = :id");
        $stmt_stato_attuale->execute([':id' => $ricond_id]);
        $stato_attuale = $stmt_stato_attuale->fetchColumn();

        function getValueOrNull($post_field) {
            return (isset($_POST[$post_field]) && $_POST[$post_field] !== '') ? $_POST[$post_field] : null;
        }

        $bn_prima_value = getValueOrNull('contatore_bn_prima');
        $params = [
             ':bn_prima' => $bn_prima_value ?? 0,
            ':bn_dopo' => getValueOrNull('contatore_bn_dopo'),
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

        $grado_finale = $_POST['grado_finale'] ?? null; // Leggi il grado finale
        if ($action === 'finalizza_ricondizionamento') {
            if ($grado_finale) {
                $params[':grado_finale'] = $grado_finale;
                $update_details_sql .= " Grado_Finale = :grado_finale,";
            } else {
                 $pdo->rollBack();
                 redirect('gestisci_ricondizionamento', 'error', 'Il Grado Finale è obbligatorio per finalizzare.', $ricond_id);
            }
        }

        $stmt_details = $pdo->prepare(rtrim($update_details_sql, ',') . " WHERE Ricondizionamento_ID = :ricond_id");
        $stmt_details->execute($params);

        $note = $_POST['note'] ?? '';
        $update_ricond_sql = "UPDATE Ricondizionamenti SET Note = :note";
        $params_ricond = [':note' => $note, ':ricond_id' => $ricond_id];

        if ($action === 'aggiorna_progresso' && $stato_attuale === 'Da Verificare/Ricondizionare') {
            $update_ricond_sql .= ", Stato_Globale = 'IN CORSO'"; // Cambia stato ricondizionamento
            $success_message = 'Progresso salvato. Stato aggiornato a "IN CORSO".';

            // NUOVO: Aggiorna lo stato del dispositivo (di nuovo, per sicurezza) a "In Ricondizionamento"
            $stmt_get_seriale = $pdo->prepare("SELECT Dispositivo_Seriale FROM Ricondizionamenti WHERE ID = ?");
            $stmt_get_seriale->execute([$ricond_id]);
            $dispositivo_seriale_update = $stmt_get_seriale->fetchColumn();
            if ($dispositivo_seriale_update) {
                 $stmt_update_dispositivo_prog = $pdo->prepare("UPDATE Dispositivi SET Stato = :stato_ricond, Utente_Ultima_Mod = :user, Data_Ultima_Mod = CURDATE() WHERE Seriale_Inrete = :seriale");
                 $stmt_update_dispositivo_prog->execute([
                     ':stato_ricond' => STATO_IN_RICONDIZIONAMENTO,
                     ':user' => $user_id,
                     ':seriale' => $dispositivo_seriale_update
                 ]);
            }
            // FINE NUOVO BLOCCO

        } elseif ($action === 'aggiorna_progresso') {
             $success_message = 'Progresso salvato.';
        }

        if ($action === 'finalizza_ricondizionamento') {
            $azienda_dest = $_POST['azienda_destinazione'] ?: null;
            $update_ricond_sql .= ", Azienda_Destinazione = :azienda_dest";
            $params_ricond[':azienda_dest'] = $azienda_dest;

            $stmt_stato_nome = $pdo->prepare("SELECT Nome FROM Stati WHERE ID = ?");
            $stmt_stato_nome->execute([$grado_finale]);
            $nome_stato_finale = $stmt_stato_nome->fetchColumn();
            $stato_globale_ricond = (strpos($nome_stato_finale, 'Demolire') !== false || strpos($nome_stato_finale, 'Cannibalizzare') !== false || strpos($nome_stato_finale, 'Demolito') !== false) ? 'DEMOLITO' : 'COMPLETATO';

            $update_ricond_sql .= ", Stato_Globale = :stato_globale, Data_Fine = NOW()";
            $params_ricond[':stato_globale'] = $stato_globale_ricond;
        }

        $stmt_ricond = $pdo->prepare($update_ricond_sql . " WHERE ID = :ricond_id");
        $stmt_ricond->execute($params_ricond);

        if ($action === 'finalizza_ricondizionamento') {
            $stmt_get_seriale = $pdo->prepare("SELECT Dispositivo_Seriale FROM Ricondizionamenti WHERE ID = ?");
            $stmt_get_seriale->execute([$ricond_id]);
            $dispositivo_seriale = $stmt_get_seriale->fetchColumn();

            // MODIFICATO: Aggiorna lo stato del dispositivo con $grado_finale (l'ID dello stato scelto)
            $stmt_update_dispositivo_final = $pdo->prepare("UPDATE Dispositivi SET Stato = :stato_finale, `B/N` = :bn, `Colore` = :colore, `C.C.C.` = :ccc, Data_Ultima_Mod = CURDATE(), Utente_Ultima_Mod = :user WHERE Seriale_Inrete = :seriale");
            $stmt_update_dispositivo_final->execute([
                ':stato_finale' => $grado_finale, // Usa l'ID del grado finale selezionato
                ':bn' => $params[':bn_dopo'],
                ':colore' => $params[':colore_dopo'],
                ':ccc' => $params[':ccc_dopo'],
                ':user' => $user_id,
                ':seriale' => $dispositivo_seriale
            ]);
            // FINE MODIFICA

            $pdo->commit();
            redirect('gestisci_ricondizionamento', 'success', 'Ricondizionamento FINALIZZATO! Stato dispositivo aggiornato.', $ricond_id);
        } else { // Se era solo 'aggiorna_progresso'
            $pdo->commit();
            redirect('gestisci_ricondizionamento', 'success', $success_message, $ricond_id);
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirect('gestisci_ricondizionamento', 'error', 'Errore DB: ' . $e->getMessage(), $ricond_id);
    }
}

redirect('gestione_ricondizionamenti', 'error', 'Azione non riconosciuta.');
?>