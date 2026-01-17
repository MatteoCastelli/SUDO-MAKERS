<?php
session_start();

use Proprietario\SudoMakers\core\Database;

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/functions.php';

$title = "Reset Password";
$message = '';
$messageType = '';

// Step 1: Richiesta reset password (invio email con token)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        echo "<script>alert('Inserisci un indirizzo email valido.');</script>";
    } else {
        $pdo = Database::getInstance()->getConnection();
        
        // Verifica se l'email esiste
        $stmt = $pdo->prepare("SELECT id_utente, nome, email FROM utente WHERE email = :email AND email_verificata = 1");
        $stmt->execute(['email' => $email]);
        $utente = $stmt->fetch();
        
        if ($utente) {
            // Genera token di reset
            list($token, $expiresAt) = generatePasswordResetToken();
            
            // Salva il token nel database
            $stmt = $pdo->prepare("
                INSERT INTO password_reset_tokens (id_utente, token, expires_at, used) 
                VALUES (:id_utente, :token, :expires_at, 0)
            ");
            
            $stmt->execute([
                'id_utente' => $utente['id_utente'],
                'token' => $token,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s')
            ]);
            
            // Invia email con link di reset
            $resetUrl = "http://localhost/SudoMakers/src/auth/password_reset.php?token=" . $token;
            sendPasswordResetEmail($utente['email'], $utente['nome'], $resetUrl);
            
            echo "<script>alert('Email di reset inviata! Controlla la tua casella di posta.');</script>";
        } else {
            // Per sicurezza, non rivelare se l'email esiste o meno
            echo "<script>alert('Se l\\'email esiste nel nostro sistema, riceverai un link per il reset.');</script>";
        }
    }
}

// Step 2: Reset effettivo della password (dopo aver cliccato sul link nell'email)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $token = $_POST['token'];
    $nuova_password = $_POST['nuova_password'];
    
    // Validazione password lato server
    $validLength = strlen($nuova_password) >= 8;
    $validUpper = preg_match('/[A-Z]/', $nuova_password);
    $validNumber = preg_match('/[0-9]/', $nuova_password);
    $validSymbol = preg_match('/[\W_]/', $nuova_password);
    
    if (!$validLength) {
        echo "<script>alert('La password deve essere di almeno 8 caratteri.');</script>";
        goto show_reset_form;
    } elseif (!$validUpper) {
        echo "<script>alert('La password deve contenere almeno una lettera maiuscola.');</script>";
        goto show_reset_form;
    } elseif (!$validNumber) {
        echo "<script>alert('La password deve contenere almeno un numero.');</script>";
        goto show_reset_form;
    } elseif (!$validSymbol) {
        echo "<script>alert('La password deve contenere almeno un simbolo speciale.');</script>";
        goto show_reset_form;
    } else {
        $pdo = Database::getInstance()->getConnection();
        
        // Verifica token valido
        $stmt = $pdo->prepare("
            SELECT prt.id_utente, prt.expires_at, prt.used 
            FROM password_reset_tokens prt 
            WHERE prt.token = :token
        ");
        $stmt->execute(['token' => $token]);
        $resetToken = $stmt->fetch();
        
        if (!$resetToken) {
            echo "<script>alert('Token non valido.');</script>";
            goto show_reset_form;
        } elseif ($resetToken['used'] == 1) {
            echo "<script>alert('Questo link è già stato utilizzato.');</script>";
            goto show_reset_form;
        } elseif (new DateTime() > new DateTime($resetToken['expires_at'])) {
            echo "<script>alert('Questo link è scaduto. Richiedi un nuovo reset.');</script>";
            goto show_reset_form;
        } else {
            // Aggiorna password
            $password_hash = password_hash($nuova_password, PASSWORD_BCRYPT);
            
            $stmt = $pdo->prepare("
                UPDATE utente 
                SET password_hash = :password_hash, 
                    tentativi_falliti = 0,
                    stato_account = 'attivo'
                WHERE id_utente = :id_utente
            ");
            
            $stmt->execute([
                'password_hash' => $password_hash,
                'id_utente' => $resetToken['id_utente']
            ]);
            
            // Marca token come usato
            $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = :token");
            $stmt->execute(['token' => $token]);
            
            // Invia email di conferma
            $stmtUser = $pdo->prepare("SELECT email, nome FROM utente WHERE id_utente = :id");
            $stmtUser->execute(['id' => $resetToken['id_utente']]);
            $utente = $stmtUser->fetch();
            
            sendPasswordChangedEmail($utente['email'], $utente['nome']);
            
            echo "<script>alert('Password modificata con successo! Ora puoi effettuare il login.'); window.location.href='login.php';</script>";
            exit;
        }
    }
}

show_reset_form:

// Verifica se siamo nella pagina di reset (con token nell'URL)
$token_presente = isset($_GET['token']) && !empty($_GET['token']);
$token_url = $token_presente ? htmlspecialchars($_GET['token']) : '';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../../public/assets/css/loginRegisterStyle.css">
</head>
<body>

<?php if ($token_presente): ?>
    <!-- Form per inserire la nuova password -->
    <form method="POST" action="password_reset.php" id="resetForm">
        <h2>Imposta Nuova Password</h2>
        
        <input type="hidden" name="token" value="<?= $token_url ?>">
        
        <div class="form-row">
            <label for="nuova_password">Nuova Password</label>
            <input type="password" id="password" name="nuova_password" required>
            <ul id="pwd-req">
                <li id="req-length">Minimo 8 caratteri</li>
                <li id="req-upper">Almeno 1 lettera maiuscola</li>
                <li id="req-number">Almeno 1 numero</li>
                <li id="req-symbol">Almeno 1 simbolo speciale</li>
            </ul>
        </div>
        
        <button type="submit" name="reset_password">Cambia Password</button>
        <a href="login.php" id="indietro">Torna al Login</a>
    </form>
    
<?php else: ?>
    <!-- Form per richiedere il reset -->
    <form method="POST" action="password_reset.php">
        <h2>Reset Password</h2>
        
        <p style="text-align: center; color: #666; margin: 20px 0;">
            Inserisci la tua email per ricevere un link di reset password
        </p>
        
        <div class="form-row">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required 
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        
        <button type="submit" name="request_reset">Invia Link di Reset</button>
        <a href="login.php" id="indietro">Torna al Login</a>
    </form>
<?php endif; ?>

<script src="../../public/assets/js/checkRegisterFormData.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById("password");
    const btnSubmit = document.querySelector("button[name='reset_password']");
    
    // Requisiti password
    const reqLength = document.getElementById("req-length");
    const reqUpper = document.getElementById("req-upper");
    const reqNumber = document.getElementById("req-number");
    const reqSymbol = document.getElementById("req-symbol");

    if (passwordInput && btnSubmit) {
        // Disabilita pulsante all'inizio
        btnSubmit.disabled = true;

        function checkForm() {
            // Usa la funzione validatePassword definita in checkRegisterFormData.js
            // Se non è disponibile, implementa una logica di fallback o assicurati che lo script sia caricato
            if (typeof validatePassword === 'function') {
                const isValid = validatePassword(passwordInput, reqLength, reqUpper, reqNumber, reqSymbol);
                btnSubmit.disabled = !isValid;
            }
        }

        passwordInput.addEventListener("input", checkForm);
        
        // Controllo iniziale
        checkForm();
    }
});
</script>

</body>
</html>
