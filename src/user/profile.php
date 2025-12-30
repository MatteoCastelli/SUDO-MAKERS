<?php

use Proprietario\SudoMakers\core\Database;

$title = "Il tuo profilo";
session_start();
require_once __DIR__ . '/../core/Database.php';

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
                $uploadDir = '../../public/uploads/avatars/';
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

                    $relativePath = '../../public/uploads/avatars/' . $newFilename;
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
    <link rel="stylesheet" href="../../public/assets/css/privateAreaStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/profileStyle.css">
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="profile-wrapper">
    <?php if(isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if(isset($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <h1><?= $title ?></h1>

    <div class="profile-container">
        <img src="<?= $utente['foto'] ?>" alt="Foto Profilo" class="profile-pic">
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
        <a href="../utils/genera_pdf_tessera.php" class="download-btn">📄 Scarica Tessera PDF</a>
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

<script src="../../public/assets/js/profile.js"></script>
</body>
</html>