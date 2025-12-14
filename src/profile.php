<?php
use Proprietario\SudoMakers\Database;

$title = "Il tuo profilo";
session_start();
require_once "Database.php";

if(!isset($_SESSION['id_utente'])) {
    echo "<script>alert('Non autenticato');</script>";
    header("location: login.php");
    exit;
}

$pdo = Database::getInstance()->getConnection();
$idUtente = $_SESSION['id_utente'];

// Recupera i dati dell'utente
$stmt = $pdo->prepare("SELECT * FROM utente WHERE id_utente = :id");
$stmt->execute(['id' => $idUtente]);
$utente = $stmt->fetch();

if(!$utente) {
    echo "<script>alert('Errore nel recupero dati utente');</script>";
    header("location: homepage.php");
    exit;
}

// Gestione modifica dati
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if($action === 'change_username') {
        $nuovoUsername = trim($_POST['username'] ?? '');
        if(!empty($nuovoUsername)) {
            // Verifica che l'username non sia già in uso
            $check = $pdo->prepare("SELECT id_utente FROM utente WHERE username = :username AND id_utente != :id");
            $check->execute(['username' => $nuovoUsername, 'id' => $idUtente]);
            if($check->fetch()) {
                $error = "Username già in uso";
            } else {
                $stmt = $pdo->prepare("UPDATE utente SET username = :username WHERE id_utente = :id");
                $stmt->execute(['username' => $nuovoUsername, 'id' => $idUtente]);
                $success = "Username aggiornato con successo";
                $utente['username'] = $nuovoUsername;
            }
        }
    }

    elseif($action === 'change_password') {
        $vecchiaPassword = $_POST['old_password'] ?? '';
        $nuovaPassword = $_POST['new_password'] ?? '';
        $confermaPassword = $_POST['confirm_password'] ?? '';

        // Verifica password vecchia
        if(!password_verify($vecchiaPassword, $utente['password_hash'])) {
            $error = "Password attuale non corretta";
        }
        // Verifica che le nuove password coincidano
        elseif($nuovaPassword !== $confermaPassword) {
            $error = "Le nuove password non coincidono";
        }
        // Verifica requisiti password
        elseif(!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $nuovaPassword)) {
            $error = "La password non soddisfa i requisiti di sicurezza";
        }
        // Verifica che non sia uguale alla vecchia
        elseif(password_verify($nuovaPassword, $utente['password_hash'])) {
            $error = "La nuova password deve essere diversa dalla precedente";
        }
        else {
            $nuovoHash = password_hash($nuovaPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE utente SET password_hash = :hash WHERE id_utente = :id");
            $stmt->execute(['hash' => $nuovoHash, 'id' => $idUtente]);
            $success = "Password aggiornata con successo";
        }
    }

    elseif($action === 'change_photo') {
        if(isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = $_FILES['photo']['type'];

            if(in_array($fileType, $allowedTypes)) {
                $uploadDir = '../public/uploads/avatars/';
                if(!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $newFilename = uniqid('avatar_') . '.' . $extension;
                $uploadPath = $uploadDir . $newFilename;

                if(move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
                    // Elimina la vecchia foto se non è quella di default
                    if(!empty($utente['foto']) && file_exists($utente['foto']) && strpos($utente['foto'], 'default') === false) {
                        unlink($utente['foto']);
                    }

                    $relativePath = '../public/uploads/avatars/' . $newFilename;
                    $stmt = $pdo->prepare("UPDATE utente SET foto = :foto WHERE id_utente = :id");
                    $stmt->execute(['foto' => $relativePath, 'id' => $idUtente]);
                    $success = "Foto profilo aggiornata con successo";
                    $utente['foto'] = $relativePath;
                }
            } else {
                $error = "Formato file non supportato";
            }
        }
    }
}
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../public/assets/css/privateAreaStyle.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
        }

        .modal-content {
            background-color: #1f1f21;
            margin: 5% auto;
            padding: 30px;
            border: 2px solid #303033;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
        }

        .close {
            color: #888;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 20px;
        }

        .close:hover {
            color: #ebebed;
        }

        .modal h2 {
            margin-top: 0;
            color: #ebebed;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #ebebed;
            font-weight: 400;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            border: 2px solid #b30000;
            background: #2a2a2c;
            color: #ebebed;
            font-size: 14px;
            font-family: inherit;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #888;
        }

        .form-group input:valid:not(:placeholder-shown),
        .form-group input.valid-input {
            border-color: #0c8a1f;
        }

        .form-group input.invalid-input {
            border-color: #b30000;
        }

        .form-group input[type="password"]:not(.valid-input) {
            border-color: #b30000;
        }

        .pwd-requirements {
            font-size: 12px;
            margin-top: 10px;
            padding-left: 0;
            list-style: none;
        }

        .pwd-requirements li {
            color: #b30000;
            margin: 5px 0;
            transition: color 0.2s;
        }

        .pwd-requirements li.valid {
            color: #0c8a1f;
            font-weight: bold;
        }

        .pwd-requirements li::before {
            content: "• ";
            margin-right: 5px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            font-size: 14px;
        }

        .alert-success {
            background: rgba(12, 138, 31, 0.2);
            border: 1px solid #0c8a1f;
            color: #0c8a1f;
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid #dc3545;
            color: #dc3545;
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #1f1f21;
            color: #ebebed;
            border: 2px solid #303033;
            border-radius: 4px;
            font-size: 15px;
            font-weight: 300;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
            font-family: inherit;
        }

        .btn-submit:hover:not(:disabled) {
            background: #3b3b3d;
            border-color: #3b3b3d;
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .download-field {
            display: grid;
            grid-template-columns: 1fr;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            background: #1f1f21;
            border-radius: 8px;
            border: 2px solid #303033;
        }

        .download-btn {
            display: block;
            padding: 12px;
            background: #0c8a1f;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: background 0.3s;
            text-align: center;
            font-size: 15px;
        }

        .download-btn:hover {
            background: #0a6f18;
        }
    </style>
</head>
<body>
<?php require_once 'navigation.php'; ?>

<div class="profile-wrapper">
    <?php if(isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if(isset($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <h1><?= $title ?></h1>

    <div class="profile-container">
        <img src="<?= htmlspecialchars($utente['foto'] ?? '../public/assets/images/default_profile.png') ?>"
             alt="Foto Profilo" class="profile-pic">
        <br>
        <button class="edit-btn" onclick="openModal('photoModal')">Modifica Foto</button>
    </div>

    <div class="profile-field">
        <span>Nome:</span> <?= htmlspecialchars($utente['nome']) ?>
    </div>

    <div class="profile-field">
        <span>Cognome:</span> <?= htmlspecialchars($utente['cognome']) ?>
    </div>

    <div class="profile-field">
        <span>Sesso:</span> <?= $utente['sesso'] === 'M' ? 'Maschio' : 'Femmina' ?>
    </div>

    <div class="profile-field">
        <span>Data di nascita:</span> <?= date('d/m/Y', strtotime($utente['data_nascita'])) ?>
    </div>

    <div class="profile-field">
        <span>Comune di nascita:</span> <?= htmlspecialchars($utente['comune_nascita']) ?>
    </div>

    <div class="profile-field">
        <span>Codice Fiscale:</span> <?= htmlspecialchars($utente['codice_fiscale']) ?>
    </div>

    <div class="profile-field">
        <span>Username:</span> <?= htmlspecialchars($utente['username']) ?>
        <button class="edit-btn" onclick="openModal('usernameModal')">Modifica</button>
    </div>

    <div class="profile-field">
        <span>Email:</span> <?= htmlspecialchars($utente['email']) ?>
    </div>

    <div class="profile-field">
        <span>Password:</span> ••••••••
        <button class="edit-btn" onclick="openModal('passwordModal')">Modifica</button>
    </div>

    <div class="download-field">
        <a href="genera_pdf_tessera.php" class="download-btn">📄 Scarica Tessera PDF</a>
    </div>
</div>

<!-- Modal Username -->
<div id="usernameModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('usernameModal')">&times;</span>
        <h2>Modifica Username</h2>
        <form method="POST">
            <input type="hidden" name="action" value="change_username">
            <div class="form-group">
                <label for="username">Nuovo Username</label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($utente['username']) ?>" placeholder=" " required>
            </div>
            <button type="submit" class="btn-submit">Salva Modifiche</button>
        </form>
    </div>
</div>

<!-- Modal Password -->
<div id="passwordModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('passwordModal')">&times;</span>
        <h2>Modifica Password</h2>
        <form method="POST" id="passwordForm">
            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
                <label for="old_password">Password Attuale</label>
                <input type="password" id="old_password" name="old_password" placeholder=" " required>
            </div>

            <div class="form-group">
                <label for="new_password">Nuova Password</label>
                <input type="password" id="new_password" name="new_password" placeholder=" " required>
                <ul class="pwd-requirements">
                    <li id="req-length">Minimo 8 caratteri</li>
                    <li id="req-upper">Almeno 1 lettera maiuscola</li>
                    <li id="req-number">Almeno 1 numero</li>
                    <li id="req-symbol">Almeno 1 simbolo speciale</li>
                </ul>
            </div>

            <div class="form-group">
                <label for="confirm_password">Conferma Nuova Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder=" " required>
                <ul class="pwd-requirements">
                    <li id="req-match">Le password devono coincidere</li>
                </ul>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn" disabled>Salva Modifiche</button>
        </form>
    </div>
</div>

<!-- Modal Foto -->
<div id="photoModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('photoModal')">&times;</span>
        <h2>Modifica Foto Profilo</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="change_photo">
            <div class="form-group">
                <label for="photo">Seleziona Nuova Foto</label>
                <input type="file" id="photo" name="photo" accept="image/*" required>
            </div>
            <button type="submit" class="btn-submit">Carica Foto</button>
        </form>
    </div>
</div>

<script>
    // Gestione modal
    function openModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }

    // Validazione username
    const usernameInput = document.getElementById('username');
    const usernameSubmitBtn = document.querySelector('#usernameModal .btn-submit');

    if(usernameInput && usernameSubmitBtn) {
        // Inizialmente disabilitato
        usernameSubmitBtn.disabled = true;

        usernameInput.addEventListener('input', function() {
            const isValid = this.value.trim().length > 0;

            if(isValid) {
                this.classList.add('valid-input');
                this.classList.remove('invalid-input');
                usernameSubmitBtn.disabled = false;
            } else {
                this.classList.remove('valid-input');
                this.classList.add('invalid-input');
                usernameSubmitBtn.disabled = true;
            }
        });

        // Check iniziale
        if(usernameInput.value.trim().length > 0) {
            usernameInput.classList.add('valid-input');
            usernameSubmitBtn.disabled = false;
        }
    }

    // Validazione password in tempo reale
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const oldPasswordInput = document.getElementById('old_password');
    const submitBtn = document.getElementById('submitBtn');

    function checkPasswordRequirements() {
        if(!newPasswordInput) return false;
        const pwd = newPasswordInput.value;

        const lengthValid = pwd.length >= 8;
        const upperValid = /[A-Z]/.test(pwd);
        const numberValid = /\d/.test(pwd);
        const symbolValid = /[\W_]/.test(pwd);

        document.getElementById('req-length').classList.toggle('valid', lengthValid);
        document.getElementById('req-upper').classList.toggle('valid', upperValid);
        document.getElementById('req-number').classList.toggle('valid', numberValid);
        document.getElementById('req-symbol').classList.toggle('valid', symbolValid);

        const allValid = lengthValid && upperValid && numberValid && symbolValid;

        // Diventa verde SOLO se tutti i criteri sono soddisfatti
        if(allValid) {
            newPasswordInput.classList.add('valid-input');
            newPasswordInput.classList.remove('invalid-input');
        } else {
            newPasswordInput.classList.remove('valid-input');
            newPasswordInput.classList.add('invalid-input');
        }

        return allValid;
    }

    function checkPasswordMatch() {
        if(!confirmPasswordInput || !newPasswordInput) return false;
        const newPwd = newPasswordInput.value;
        const confirmPwd = confirmPasswordInput.value;
        const matchReq = document.getElementById('req-match');

        if(confirmPwd === '') {
            matchReq.classList.remove('valid');
            confirmPasswordInput.classList.remove('valid-input', 'invalid-input');
            return false;
        }

        // Diventa verde SOLO se le password coincidono E la password nuova è valida
        const isMatch = newPwd === confirmPwd && checkPasswordRequirements();

        matchReq.classList.toggle('valid', isMatch);

        if(isMatch) {
            confirmPasswordInput.classList.add('valid-input');
            confirmPasswordInput.classList.remove('invalid-input');
        } else {
            confirmPasswordInput.classList.remove('valid-input');
            confirmPasswordInput.classList.add('invalid-input');
        }

        return isMatch;
    }

    function checkOldPassword() {
        if(!oldPasswordInput) return false;
        const hasValue = oldPasswordInput.value.length > 0;

        // Diventa verde non appena scrivi qualcosa
        if(hasValue) {
            oldPasswordInput.classList.add('valid-input');
            oldPasswordInput.classList.remove('invalid-input');
        } else {
            oldPasswordInput.classList.remove('valid-input');
            oldPasswordInput.classList.add('invalid-input');
        }

        return hasValue;
    }

    function updateSubmitButton() {
        if(!submitBtn) return;
        const oldValid = checkOldPassword();
        const reqValid = checkPasswordRequirements();
        const matchValid = checkPasswordMatch();
        submitBtn.disabled = !(oldValid && reqValid && matchValid);
    }

    if(newPasswordInput && confirmPasswordInput && oldPasswordInput) {
        oldPasswordInput.addEventListener('input', updateSubmitButton);
        newPasswordInput.addEventListener('input', updateSubmitButton);
        confirmPasswordInput.addEventListener('input', updateSubmitButton);
    }

    // Validazione form password
    const passwordForm = document.getElementById('passwordForm');
    if(passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            if(!checkOldPassword() || !checkPasswordRequirements() || !checkPasswordMatch()) {
                e.preventDefault();
            }
        });
    }
</script>
</body>
</html>