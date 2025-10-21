<?php
// Imposta il tempo di vita del cookie di sessione a 1 ora (3600 secondi)
ini_set('session.cookie_lifetime', 3600);
// Imposta la durata massima della sessione in base all'attività dell'utente a 1 ora (3600 secondi)
ini_set('session.gc_maxlifetime', 3600);

session_start();
require 'db_connect.php'; // Connessione al database Utenti e Dispositivi (MySQL)
require 'db_connect_sql.php'; // Connessione al database Aziende (SQL Server)

// Controlla se l'utente è già loggato.
if (isset($_SESSION['user_id'])) {
    header('Location: ../Pages/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Per favore, inserisci email e password.";
        header("Location: ../index.html?error=" . urlencode($error));
        exit();
    }

    // Query per gli utenti usando la connessione PDO (MySQL)
    $sql = "SELECT ID, Nome, Cognome, is_Superuser, Password FROM Utenti WHERE Email = :email";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['Password'])) {
        // Accesso riuscito
        $_SESSION['user_id'] = $user['ID'];
        $_SESSION['user_name'] = $user['Nome'] . ' ' . $user['Cognome'];
        $_SESSION['is_superuser'] = $user['is_Superuser'];

        // --- LOGICA PERMESSI MODIFICATA ---
        // Ora recuperiamo anche Nome_Visuallizzato e il gruppo per ogni permesso
        $sql_permessi = "SELECT p.Nome_Pagina, p.Nome_Visuallizzato, p.Gruppo
                         FROM Permessi pe
                         JOIN Pagine p ON pe.Sezione = p.ID
                         WHERE pe.Utente = :user_id";
        $stmt_permessi = $pdo->prepare($sql_permessi);
        $stmt_permessi->execute([':user_id' => $_SESSION['user_id']]);
        
        $permessi_raw = $stmt_permessi->fetchAll(PDO::FETCH_ASSOC);
        
        $permessi_list = [];
        $permessi_raggruppati = [];

        foreach ($permessi_raw as $perm) {
            // 1. Popola la lista semplice con il nome programmatico per i controlli
            $permessi_list[] = $perm['Nome_Pagina'];

            // 2. Popola un array associativo con i permessi raggruppati
            if (!empty($perm['Gruppo'])) {
                if (!isset($permessi_raggruppati[$perm['Gruppo']])) {
                    $permessi_raggruppati[$perm['Gruppo']] = [];
                }
                // Ora salviamo l'intero oggetto del permesso per avere tutti i dati
                $permessi_raggruppati[$perm['Gruppo']][] = $perm;
            }
        }
        
        $_SESSION['permessi'] = $permessi_list;
        $_SESSION['permessi_raggruppati'] = $permessi_raggruppati;
        // --- FINE LOGICA PERMESSI ---

        // --- LOGICA AZIENDE (invariata) ---
        try {
            $sql_aziende = "SELECT A.IdAnagrafica, A.RagioneSociale, A.Indirizzo, A.CAP, A.Localita, A.Provincia, A.Nazione, A.Regione, A.Telefono, A.Mail, A.CodiceFiscale, A.PartitaIva
                            FROM Anagrafica AS A
                            INNER JOIN AnagraficaDitta AS AD ON A.IdAnagrafica = AD.IdAnagrafica AND AD.IdDitta = '991D3CEF-3A9A-4AAB-AB61-18CA8AC6865F'
                            INNER JOIN SpecificheAnagrafica AS S ON A.IdAnagrafica = S.IdAnagrafica AND S.TipoSpecifica = 1";

            $stmt_aziende = sqlsrv_query($conn, $sql_aziende);
            $aziende_data = [];
            if ($stmt_aziende) {
                while ($row = sqlsrv_fetch_array($stmt_aziende, SQLSRV_FETCH_ASSOC)) {
                    $aziende_data[] = $row;
                }
            } else {
                throw new Exception(print_r(sqlsrv_errors(), true));
            }
            $_SESSION['aziende_data'] = $aziende_data;
            $_SESSION['dispositivi_data'] = [];

        } catch (Exception $e) {
            $_SESSION['aziende_data'] = [];
            $_SESSION['dispositivi_data'] = [];
            $_SESSION['data_error'] = "Errore nel recupero dei dati delle aziende: " . $e->getMessage();
        }
        // --- FINE LOGICA AZIENDE ---

        header('Location: ../Pages/dashboard.php');
        exit();
    } else {
        $error = "Email o password non corretti.";
        header("Location: ../index.html?error=" . urlencode($error));
        exit();
    }
} else {
    header('Location: ../index.html');
    exit();
}
?>