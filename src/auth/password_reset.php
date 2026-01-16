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
        $message = 'Inserisci un indirizzo email valido.';
        $messageType = 'error';
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
            
            $message = 'Email di reset inviata! Controlla la tua casella di posta.';
            $messageType = 'success';
        } else {
            // Per sicurezza, non rivelare se l'email esiste o meno
            $message = 'Se l\'email esiste nel nostro sistema, riceverai un link per il reset.';
            $messageType = 'success';
        }
    }
}

// Step 2: Reset effettivo della password (dopo aver cliccato sul link nell'email)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $token = $_POST['token'];
    $nuova_password = $_POST['nuova_password'];
    $conferma_password = $_POST['conferma_password'];
    
    // Validazione password
    if (strlen($nuova_password) < 8) {
        $message = 'La password deve essere di almeno 8 caratteri.';
        $messageType = 'error';
    } elseif ($nuova_password !== $conferma_password) {
        $message = 'Le password non coincidono.';
        $messageType = 'error';
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
            $message = 'Token non valido.';
            $messageType = 'error';
        } elseif ($resetToken['used'] == 1) {
            $message = 'Questo link è già stato utilizzato.';
            $messageType = 'error';
        } elseif (new DateTime() > new DateTime($resetToken['expires_at'])) {
            $message = 'Questo link è scaduto. Richiedi un nuovo reset.';
            $messageType = 'error';
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
            
            $message = 'Password modificata con successo! Ora puoi effettuare il login.';
            $messageType = 'success';
            
            // Redirect al login dopo 3 secondi
            header("refresh:3;url=login.php");
        }
    }
}

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
    <style>
        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            text-align: center;
            font-weight: 500;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .password-requirements {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        .link-back {
            text-align: center;
            margin-top: 20px;
        }
        .link-back a {
            color: #007bff;
            text-decoration: none;
        }
        .link-back a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body style="margin: 0;">

<?php if ($token_presente): ?>
    <!-- Form per inserire la nuova password -->
    <form method="POST" action="password_reset.php">
        <h2>Imposta Nuova Password</h2>
        
        <?php if (!empty($message)): ?>
            <div class="message <?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <input type="hidden" name="token" value="<?= $token_url ?>">
        
        <div class="form-row">
            <label for="nuova_password">Nuova Password</label>
            <input type="password" id="nuova_password" name="nuova_password" required minlength="8">
            <div class="password-requirements">
                Minimo 8 caratteri
            </div>
        </div>
        
        <div class="form-row">
            <label for="conferma_password">Conferma Password</label>
            <input type="password" id="conferma_password" name="conferma_password" required minlength="8">
        </div>
        
        <button type="submit" name="reset_password">Cambia Password</button>
        
        <div class="link-back">
            <a href="login.php">Torna al Login</a>
        </div>
    </form>
    
<?php else: ?>
    <!-- Form per richiedere il reset -->
    <form method="POST" action="password_reset.php">
        <h2>Reset Password</h2>
        
        <?php if (!empty($message)): ?>
            <div class="message <?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <p style="text-align: center; color: #666; margin: 20px 0;">
            Inserisci la tua email per ricevere un link di reset password
        </p>
        
        <div class="form-row">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required 
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        
        <button type="submit" name="request_reset">Invia Link di Reset</button>
        
        <div class="link-back">
            <a href="login.php">Torna al Login</a>
        </div>
    </form>
<?php endif; ?>

</body>
</html>
