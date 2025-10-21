<?php
session_start();
require_once '../PHP/db_connect.php';

$user_permessi = $_SESSION['permessi'] ?? [];
$is_superuser = $_SESSION['is_superuser'] ?? false;

if (!isset($_SESSION['user_id']) || (!in_array('inserisci_reminder', $user_permessi) && !$is_superuser)) {
    header('Location: ../Pages/dashboard.php?error=Accesso non autorizzato');
    exit();
}

// Dati per i suggerimenti... (invariato)
$email_suggestions = [];
$utenti = $pdo->query("SELECT Nome, Cognome, Email FROM Utenti WHERE Email IS NOT NULL AND Email != '' ORDER BY Cognome, Nome")->fetchAll(PDO::FETCH_ASSOC);
foreach ($utenti as $utente) {
    $email_suggestions[$utente['Email']] = [ 'email' => $utente['Email'], 'name' => trim($utente['Nome'] . ' ' . $utente['Cognome']) ];
}
$aziende_data = $_SESSION['aziende_data'] ?? [];
foreach ($aziende_data as $azienda) {
    if (!empty($azienda['Mail'])) {
        $email_suggestions[$azienda['Mail']] = [ 'email' => $azienda['Mail'], 'name' => $azienda['RagioneSociale'] ];
    }
}
$email_suggestions_json = json_encode(array_values($email_suggestions));
$dispositivi = $pdo->query("SELECT d.Seriale_Inrete, d.Seriale, ma.Nome AS Marca, mo.Nome AS Modello, CONCAT(ma.Nome, ' ', mo.Nome, ' (S/N: ', d.Seriale, ')') AS DisplayName FROM Dispositivi d JOIN Marche ma ON d.MarcaID = ma.ID JOIN Modelli mo ON d.ModelloID = mo.ID ORDER BY d.Seriale_Inrete DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Aggiungi Nuovo Reminder</title>
    <link rel="stylesheet" href="../CSS/_base.css">
    <link rel="stylesheet" href="../CSS/_forms.css">
    <link rel="stylesheet" href="../CSS/_search.css">
    <style>
        .email-list-container {
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-top: 10px;
            padding: 5px;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            min-height: 40px;
        }
        .email-tag {
            background-color: #007bff;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            font-size: 0.9em;
        }
        .email-tag .remove-tag {
            margin-left: 8px;
            cursor: pointer;
            font-weight: bold;
            color: #e9ecef;
        }
        .email-tag .remove-tag:hover {
            color: white;
        }
    </style>
</head>
<body>

<?php require_once '../PHP/header.php'; ?>

<div class="form-container">
    <h2>Aggiungi Nuovo Reminder</h2>
    <form action="../PHP/salva_reminder.php" method="POST" id="reminder-form">
        
        <div class="form-group">
            <label for="tipo_scadenza">Tipo Scadenza / Descrizione</label>
            <input type="text" id="tipo_scadenza" name="tipo_scadenza" required>
        </div>
        
        <div class="form-group">
            <label for="data_scadenza">Data Scadenza</label>
            <input type="date" id="data_scadenza" name="data_scadenza" required>
        </div>

        <?php if (in_array('dashboard_reminder', $user_permessi) || $is_superuser): ?>
        <div class="form-group">
            <label for="is_privato" class="permesso-item" style="padding: 0; background: none; border: none;">
                <input type="checkbox" id="is_privato" name="is_privato" value="1">
                <span>Reminder Privato (visibile solo a te)</span>
            </label>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="searchAziendaInput">Azienda Associata (Opzionale)</label>
            <input type="text" id="searchAziendaInput" class="search-box" placeholder="Cerca per Ragione Sociale...">
            <input type="hidden" name="azienda" id="azienda">
            <div id="searchAziendeResults" class="search-results-list hidden"></div>
        </div>

        <div class="form-group">
            <label for="searchDeviceInput">Dispositivo Associato (Opzionale)</label>
            <input type="text" id="searchDeviceInput" class="search-box" placeholder="Cerca dispositivo...">
            <input type="hidden" name="dispositivo_seriale" id="dispositivo_seriale">
            <div id="searchResults" class="search-results-list hidden"></div>
        </div>

        <div class="form-group">
            <label for="search-email">Destinatari Notifica</label>
            <p class="form-hint" style="margin-top: -8px; margin-bottom: 8px;">Digita un'email o cerca per nome/azienda, poi premi Invio o clicca per aggiungere.</p>
            <input type="text" id="search-email" placeholder="Cerca o inserisci un'email..." autocomplete="off">
            <div id="results-email" class="search-results-list hidden"></div>
            <div id="email-list" class="email-list-container"></div>
        </div>

        <div class="form-group">
            <label for="note">Note</label>
            <textarea id="note" name="note" rows="3"></textarea>
        </div>
        
        <button type="submit">Salva Reminder</button>
    </form>
     <a href="gestione_reminder.php" class="back-link">Torna alla Gestione Reminder</a>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Logica ricerca dispositivo
    const dispositivi = <?= json_encode($dispositivi) ?>;
    const searchInputDev = document.getElementById('searchDeviceInput');
    const hiddenInputDev = document.getElementById('dispositivo_seriale');
    const resultsListDev = document.getElementById('searchResults');
    searchInputDev.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        resultsListDev.innerHTML = '';
        hiddenInputDev.value = '';
        if (query.length < 2) {
            resultsListDev.classList.add('hidden');
            return;
        }
        const filtered = dispositivi.filter(d => 
            (String(d.Seriale_Inrete).includes(query)) ||
            (d.Seriale && d.Seriale.toLowerCase().includes(query)) ||
            (d.Marca && d.Marca.toLowerCase().includes(query)) ||
            (d.Modello && d.Modello.toLowerCase().includes(query))
        );
        if (filtered.length > 0) {
            filtered.slice(0, 5).forEach(d => {
                const item = document.createElement('div');
                item.className = 'search-result-item';
                item.textContent = `${d.Marca} ${d.Modello} (S/N: ${d.Seriale})`;
                item.addEventListener('click', () => {
                    searchInputDev.value = item.textContent;
                    hiddenInputDev.value = d.Seriale_Inrete;
                    resultsListDev.classList.add('hidden');
                });
                resultsListDev.appendChild(item);
            });
            resultsListDev.classList.remove('hidden');
        } else {
            resultsListDev.innerHTML = '<div class="search-result-item no-results">Nessun dispositivo trovato</div>';
            resultsListDev.classList.remove('hidden');
        }
    });

    // Logica ricerca azienda
    const aziende = <?= json_encode($aziende_data) ?>;
    const searchAziendaInput = document.getElementById('searchAziendaInput');
    const hiddenAziendaInput = document.getElementById('azienda');
    const searchAziendeResults = document.getElementById('searchAziendeResults');
    searchAziendaInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        searchAziendeResults.innerHTML = '';
        hiddenAziendaInput.value = '';
        if (query.length < 2) {
            searchAziendeResults.classList.add('hidden');
            return;
        }
        const filtered = aziende.filter(item => item['RagioneSociale'] && item['RagioneSociale'].toLowerCase().includes(query));
        if (filtered.length > 0) {
            filtered.slice(0, 5).forEach(item => {
                const div = document.createElement('div');
                div.className = 'search-result-item';
                div.textContent = item['RagioneSociale'];
                div.addEventListener('click', () => {
                    searchAziendaInput.value = item['RagioneSociale'];
                    hiddenAziendaInput.value = item['RagioneSociale'];
                    searchAziendeResults.classList.add('hidden');
                });
                searchAziendeResults.appendChild(div);
            });
            searchAziendeResults.classList.remove('hidden');
        }
    });

    // Logica ricerca email
    const emailSuggestions = <?= $email_suggestions_json ?>;
    const searchInputEmail = document.getElementById('search-email');
    const resultsListEmail = document.getElementById('results-email');
    const emailListContainer = document.getElementById('email-list');
    const reminderForm = document.getElementById('reminder-form');

    function addEmailToList(email) {
        email = email.trim();
        if (email === '' || !validateEmail(email)) return;
        if (reminderForm.querySelector(`input[name="email_notifica[]"][value="${email}"]`)) return;

        const tag = document.createElement('div');
        tag.className = 'email-tag';
        const text = document.createElement('span');
        text.textContent = email;
        const removeBtn = document.createElement('span');
        removeBtn.className = 'remove-tag';
        removeBtn.textContent = '✖';
        removeBtn.onclick = () => tag.remove();
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'email_notifica[]';
        hiddenInput.value = email;
        tag.appendChild(text);
        tag.appendChild(removeBtn);
        tag.appendChild(hiddenInput);
        emailListContainer.appendChild(tag);
    }
    searchInputEmail.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        resultsListEmail.innerHTML = '';
        if (query.length < 2) {
            resultsListEmail.classList.add('hidden');
            return;
        }
        const filtered = emailSuggestions.filter(s => 
            s.email.toLowerCase().includes(query) || s.name.toLowerCase().includes(query)
        );
        filtered.slice(0, 10).forEach(suggestion => {
            const item = document.createElement('div');
            item.className = 'search-result-item';
            item.innerHTML = `${suggestion.name} <small style="color:#777;">(${suggestion.email})</small>`;
            item.addEventListener('click', () => {
                addEmailToList(suggestion.email);
                searchInputEmail.value = '';
                resultsListEmail.classList.add('hidden');
                searchInputEmail.focus();
            });
            resultsListEmail.appendChild(item);
        });
        resultsListEmail.classList.remove('hidden');
    });
    searchInputEmail.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addEmailToList(this.value);
            this.value = '';
            resultsListEmail.classList.add('hidden');
        }
    });
    function validateEmail(email) {
        const re = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }

    // Listener globale per nascondere i popup
    document.addEventListener('click', e => {
        if (!searchInputDev.contains(e.target) && !resultsListDev.contains(e.target)) {
            resultsListDev.classList.add('hidden');
        }
        if (!searchAziendaInput.contains(e.target) && !searchAziendeResults.contains(e.target)) {
            searchAziendeResults.classList.add('hidden');
        }
        if (!searchInputEmail.contains(e.target) && !resultsListEmail.contains(e.target)) {
            resultsListEmail.classList.add('hidden');
        }
    });
});
</script>
</body>
</html>