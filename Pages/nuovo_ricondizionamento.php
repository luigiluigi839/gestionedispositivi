<?php
// File: ../Pages/nuovo_ricondizionamento.php
session_start();

require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;
$user_id = $_SESSION['user_id'] ?? null;

// MODIFICATO: Controllo sul permesso specifico di inserimento
if (!isset($user_id) || (!in_array('inserisci_ricondizionamenti', $user_permessi) && !$is_superuser)) {
    header('Location: ../Pages/dashboard.php?error=Accesso non autorizzato');
    exit();
}

$message = isset($_GET['warning']) ? $_GET['warning'] : (isset($_GET['error']) ? $_GET['error'] : (isset($_GET['success']) ? $_GET['success'] : ''));
$status = isset($_GET['warning']) ? 'warning' : (isset($_GET['error']) ? 'error' : (isset($_GET['success']) ? 'success' : ''));
$dispositivi_data = [];

try {
    // Recupera tutti i dispositivi con i seriali e i nomi per la ricerca JS
    $sql = "SELECT 
                d.Seriale_Inrete, 
                d.Seriale,
                ma.Nome AS Marca, 
                mo.Nome AS Modello
            FROM Dispositivi d
            LEFT JOIN Marche ma ON d.MarcaID = ma.ID
            LEFT JOIN Modelli mo ON d.ModelloID = mo.ID
            ORDER BY d.Seriale_Inrete DESC";
    $stmt = $pdo->query($sql);
    $dispositivi_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = "Errore nel recupero dei dispositivi: " . $e->getMessage();
    $status = 'error';
}

function formatSeriale($seriale) {
    // Questa funzione è usata solo in JS, quindi la definizione PHP potrebbe essere rimossa se non usata altrove.
    // return String($seriale)->padStart(10, '0');
    return str_pad((string)$seriale, 10, '0', STR_PAD_LEFT);
}

$dispositivi_json = json_encode($dispositivi_data);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Avvia Ricondizionamento</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_forms.css">
    <link rel="stylesheet" href="../CSS/_search.css"> </head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="form-container">
    <h2>Avvia Nuovo Ricondizionamento</h2>
    
    <?php if ($message): ?>
        <p class="message <?= htmlspecialchars($status) ?>">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <p>Inserisci o scansiona il Seriale Inrete o il Seriale Fisico del dispositivo.</p>

    <form action="../PHP/salva_ricondizionamento.php" method="POST">
        <div class="form-group">
            <label for="seriale_input">Cerca e Seleziona Dispositivo</label>
            
            <input type="text" id="seriale_input" name="seriale_input_display" 
                   placeholder="Cerca per marca, modello, seriale o Seriale Inrete..." required autofocus
                   autocomplete="off">
            
            <input type="hidden" id="seriale_inrete_hidden" name="seriale_inrete" value="">
            
            <div id="search-results-list" class="search-results-list hidden">
                </div>
            
            <input type="hidden" name="action" value="avvia_nuovo">
            <input type="hidden" name="operatore_id" value="<?= htmlspecialchars($user_id) ?>">
        </div>
        
        <button type="submit" id="submit-button" class="submit-button" disabled>Avvia Modulo</button>
    </form>
     <a href="gestione_ricondizionamenti.php" class="back-link">Torna ai Ricondizionamenti</a>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const inputField = document.getElementById('seriale_input');
        const hiddenField = document.getElementById('seriale_inrete_hidden');
        const resultsList = document.getElementById('search-results-list');
        const submitButton = document.getElementById('submit-button');
        
        const dispositivi = <?= $dispositivi_json ?>;

        function formatSeriale(seriale) {
            return String(seriale).padStart(10, '0');
        }
        
        function updateResults() {
            const query = inputField.value.trim().toUpperCase();
            resultsList.innerHTML = '';
            
            if (query.length < 2) {
                resultsList.classList.add('hidden');
                hiddenField.value = '';
                submitButton.disabled = true;
                return;
            }
            
            const filtered = dispositivi.filter(disp => {
                const serialeInrete = formatSeriale(disp.Seriale_Inrete).toUpperCase();
                const serialeFisico = (disp.Seriale || '').toUpperCase();
                const marcaModello = `${(disp.Marca || '').toUpperCase()} ${(disp.Modello || '').toUpperCase()}`;
                
                let normalizedQuery = query;
                if (!isNaN(query) && query.length > 0) {
                    normalizedQuery = String(Number(query));
                }

                return serialeInrete.includes(query) || 
                       serialeFisico.includes(query) ||
                       marcaModello.includes(query) ||
                       String(disp.Seriale_Inrete).includes(normalizedQuery);
            });

            if (filtered.length > 0) {
                filtered.slice(0, 10).forEach(disp => {
                    const serialeInreteFormatted = formatSeriale(disp.Seriale_Inrete);
                    const item = document.createElement('div');
                    item.className = 'search-result-item';
                    item.textContent = `${serialeInreteFormatted} - ${disp.Marca} ${disp.Modello} (S/N: ${disp.Seriale})`;
                    
                    item.addEventListener('click', () => selectDevice(disp.Seriale_Inrete, serialeInreteFormatted));
                    resultsList.appendChild(item);
                });
                resultsList.classList.remove('hidden');
            } else {
                const noResultsItem = document.createElement('div');
                noResultsItem.className = 'search-result-item no-results';
                noResultsItem.textContent = 'Nessun dispositivo trovato.';
                resultsList.appendChild(noResultsItem);
                resultsList.classList.remove('hidden');
            }
        }
        
        function selectDevice(serialeInrete, displayValue) {
            hiddenField.value = serialeInrete;
            inputField.value = displayValue;
            submitButton.disabled = false;
            
            resultsList.classList.add('hidden');
        }

        inputField.addEventListener('input', updateResults);
        inputField.addEventListener('focus', updateResults);
        
        inputField.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                const currentQuery = this.value.trim().toUpperCase();
                const match = dispositivi.find(disp => 
                    formatSeriale(disp.Seriale_Inrete).toUpperCase() === currentQuery ||
                    (disp.Seriale || '').toUpperCase() === currentQuery
                );
                
                if (match) {
                    selectDevice(match.Seriale_Inrete, formatSeriale(match.Seriale_Inrete));
                    if (!submitButton.disabled) {
                         submitButton.click();
                    }
                }
            }
        });

        document.addEventListener('click', function(e) {
            if (!inputField.contains(e.target) && !resultsList.contains(e.target)) {
                resultsList.classList.add('hidden');
            }
        });
    });
</script>

<?php require_once '../PHP/footer.php'; ?>

</body>
</html>