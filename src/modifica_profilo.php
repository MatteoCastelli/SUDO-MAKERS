<?php
use Proprietario\SudoMakers\Database;

session_start();
require_once "Database.php";

if(!isset($_SESSION['id_utente'])){
    header("Location: login.php");
    exit;
}

$pdo = Database::getInstance()->getConnection();
$idUtente = $_SESSION['id_utente'];

$campiConsentiti = ['nome','cognome','data_nascita','sesso','comune_nascita','email','password_hash','foto'];
$colonna = $_GET['colonna'] ?? null;

if(!$colonna || !in_array($colonna, $campiConsentiti)){
    header("Location: profile.php");
    exit;
}

function formatLabel($col){
    if($col==='password_hash') return 'Password';
    return ucfirst(str_replace('_',' ',$col));
}

// valore corrente
$stmt = $pdo->prepare("SELECT $colonna FROM utente WHERE id_utente = :id");
$stmt->execute(['id'=>$idUtente]);
$valoreCorrente = $stmt->fetchColumn();

// gestione invio form
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $nuovoValore = $_POST['valore'] ?? null;

    if($colonna === 'password_hash'){
        $nuovoValore = $_POST['valore'] ?? '';

        // 1. Verifica criteri password (min 8 caratteri, almeno 1 maiuscola, 1 numero, 1 simbolo)
        $pattern = '/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';
        if(!preg_match($pattern, $nuovoValore)){
            header("Location: modifica_profilo.php?colonna=password_hash&error=invalid");
            exit;
        }

        // 2. Controlla che la nuova password sia diversa dalla precedente
        $stmtOld = $pdo->prepare("SELECT password_hash FROM utente WHERE id_utente = :id");
        $stmtOld->execute(['id'=>$idUtente]);
        $hashPrecedente = $stmtOld->fetchColumn();

        if(password_verify($nuovoValore, $hashPrecedente)){
            header("Location: modifica_profilo.php?colonna=password_hash&error=same");
            exit;
        }

        // 3. Hash e aggiornamento
        $nuovoValoreHash = password_hash($nuovoValore, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE utente SET password_hash = :valore WHERE id_utente = :id");
        $stmt->execute(['valore'=>$nuovoValoreHash,'id'=>$idUtente]);

        header("Location: profile.php?updated=password");
        exit;
    }
    elseif($colonna === 'foto'){
        if(isset($_FILES['valore']) && $_FILES['valore']['error'] === UPLOAD_ERR_OK){
            $nomeFile = $_FILES['valore']['name'];
            $tmpFile = $_FILES['valore']['tmp_name'];
            $nuovoValore = "../uploads/" . uniqid() . "_" . basename($nomeFile);
            if(move_uploaded_file($tmpFile, $nuovoValore)){
                $stmt = $pdo->prepare("UPDATE utente SET foto = :valore WHERE id_utente = :id");
                $stmt->execute(['valore'=>$nuovoValore,'id'=>$idUtente]);
            }
        }
    }
    elseif($colonna === 'sesso'){
        if(!in_array($nuovoValore,['M','F'])) header("Location: profile.php");
        $stmt = $pdo->prepare("UPDATE utente SET sesso = :valore WHERE id_utente = :id");
        $stmt->execute(['valore'=>$nuovoValore,'id'=>$idUtente]);
    }
    elseif($colonna === 'data_nascita'){
        $dataObj = DateTime::createFromFormat('Y-m-d', $nuovoValore);
        if(!$dataObj) header("Location: profile.php");
        $stmt = $pdo->prepare("UPDATE utente SET data_nascita = :valore WHERE id_utente = :id");
        $stmt->execute(['valore'=>$nuovoValore,'id'=>$idUtente]);
    }
    else{
        $stmt = $pdo->prepare("UPDATE utente SET $colonna = :valore WHERE id_utente = :id");
        $stmt->execute(['valore'=>$nuovoValore,'id'=>$idUtente]);
    }

    header("Location: profile.php");
    exit;
}
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <title>Modifica <?= htmlspecialchars(formatLabel($colonna)) ?></title>
    <link rel="stylesheet" href="../style/loginRegisterStyle.css">
</head>
<body style="margin:0;">

<form method="POST" action="" <?= $colonna==='foto'?'enctype="multipart/form-data"':'' ?>>

    <h2>Modifica <?= formatLabel($colonna) ?></h2>

    <div class="form-row">
        <label for="valore"><?= formatLabel($colonna) ?></label>

        <?php if($colonna==='password_hash'): ?>
            <input type="password" id="valore" name="valore" placeholder="Nuova password" required>
            <ul id="pwd-req">
                <li id="req-length">Minimo 8 caratteri</li>
                <li id="req-upper">Almeno 1 lettera maiuscola</li>
                <li id="req-number">Almeno 1 numero</li>
                <li id="req-symbol">Almeno 1 simbolo speciale</li>
            </ul>
        <?php elseif($colonna==='foto'): ?>
            <input type="file" id="valore" name="valore" accept="image/*" required>
        <?php elseif($colonna==='sesso'): ?>
            <select id="valore" name="valore" required>
                <option value="M" <?= $valoreCorrente==='M'?'selected':'' ?>>Maschio</option>
                <option value="F" <?= $valoreCorrente==='F'?'selected':'' ?>>Femmina</option>
            </select>
        <?php elseif($colonna==='data_nascita'): ?>
            <input type="date" id="valore" name="valore" value="<?= htmlspecialchars($valoreCorrente) ?>" required>
        <?php else: ?>
            <input type="text" id="valore" name="valore" value="<?= htmlspecialchars($valoreCorrente) ?>" required>
        <?php endif; ?>

    </div>

    <button type="submit">Salva</button>
    <a href="profile.php" id="indietro">Indietro</a>

</form>
<script src="../scripts/checkRegisterFormData.js"></script>
</body>
</html>
