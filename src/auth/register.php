<?php

use Proprietario\SudoMakers\core\Database;

require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/functions.php';
$title = "Registrati";

if(!empty($_POST)) {

    $pdo = Database::getInstance()->getConnection();

    // 1. Verifiche preliminari (Email e Username)
    $stmt = $pdo->prepare("SELECT id_utente FROM utente WHERE email = :email");
    $stmt->execute([":email" => trim($_POST["email"])]);
    if($stmt->fetch()){
        echo "<script>alert('Email già presente');</script>";
        goto show_form;
    }

    $username = trim($_POST["username"]);
    $stmt = $pdo->prepare("SELECT id_utente FROM utente WHERE username = :username");
    $stmt->execute([":username" => $username]);
    if($stmt->fetch()){
        echo "<script>alert('Username già in uso');</script>";
        goto show_form;
    }

    // 2. Recupero e validazione dati
    $nome = trim($_POST["nome"]);
    $cognome = trim($_POST["cognome"]);
    $data = $_POST["data_nascita"];
    $sesso = $_POST["sesso"];
    $comune_nascita = trim($_POST["comune_nascita"]);

    $comune_catastale = getCodiceCatastale($comune_nascita);
    if(!$comune_catastale) {
        echo "<script>alert('Comune non trovato');</script>";
        goto show_form;
    }

    $cf_inserito = isset($_POST['codice_fiscale']) ? strtoupper(trim($_POST['codice_fiscale'])) : '';

    if(empty($cf_inserito)) {
        $cf_inserito = checkAndGenerateCF($nome, $cognome, $data, $sesso, $comune_nascita);
        if(!$cf_inserito) {
            echo "<script>alert('Errore nella generazione automatica del codice fiscale.');</script>";
            goto show_form;
        }
    } else {
        if(!verificaChecksumCF($cf_inserito)) {
            echo "<script>alert('Codice fiscale non valido (checksum errato).');</script>";
            goto show_form;
        }
        if(!verificaCFvsDati($cf_inserito, $nome, $cognome, $data, $sesso, $comune_catastale)) {
            echo "<script>alert('Il codice fiscale non corrisponde ai dati anagrafici.');</script>";
            goto show_form;
        }
    }

    // Verifica se il CF esiste già
    $stmt = $pdo->prepare("SELECT id_utente FROM utente WHERE codice_fiscale = :cf");
    $stmt->execute([':cf' => $cf_inserito]);
    if($stmt->fetch()) {
        echo "<script>alert('Questo codice fiscale è già registrato.');</script>";
        goto show_form;
    }

    // 3. Processo di inserimento con Transazione
    try {
        $pdo->beginTransaction();

        $token_info = generateEmailVerificationToken();

        $stmt = $pdo->prepare("INSERT INTO utente (username, nome, cognome, data_nascita, sesso, comune_nascita, codice_catastale, codice_fiscale, email, password_hash, verification_token, verification_expires) 
            VALUES (:username, :nome, :cognome, :data_nascita, :sesso, :comune_nascita, :codice_catastale, :codice_fiscale, :email, :password_hash, :verification_token, :verification_expires)");

        $stmt->execute([
                ':username' => $username,
                ':nome' => $nome,
                ':cognome' => $cognome,
                ':data_nascita' => $data,
                ':sesso' => $sesso,
                ':comune_nascita' => $comune_nascita,
                ':codice_catastale' => $comune_catastale,
                ':codice_fiscale' => $cf_inserito,
                ':email' => trim($_POST["email"]),
                ':password_hash' => password_hash($_POST["password"], PASSWORD_DEFAULT),
                ":verification_token" => $token_info[0],
                ":verification_expires" => ($token_info[1] instanceof DateTimeInterface) ? $token_info[1]->format("Y-m-d H:i:s") : $token_info[1],
        ]);

        // Recupero l'ID appena creato e genero il codice tessera
        $id_utente_nuovo = $pdo->lastInsertId();
        $codice_tessera = 'USER' . str_pad($id_utente_nuovo, 8, '0', STR_PAD_LEFT);

        // Aggiorno l'utente con il codice tessera
        $stmtUpdate = $pdo->prepare("UPDATE utente SET codice_tessera = :codice WHERE id_utente = :id");
        $stmtUpdate->execute([
                'codice' => $codice_tessera,
                'id' => $id_utente_nuovo
        ]);

        // Se arriviamo qui, salviamo tutto nel database definitivamente
        $pdo->commit();

        // Invio email (fuori dalla transazione per evitare timeout DB se il server mail è lento)
        $url = 'http://localhost/SUDO-MAKERS/src/auth/confirm_verification.php?token=' . urlencode($token_info[0]);
        sendVerificationEmail(trim($_POST["email"]), $nome, $url, $token_info[0]);

        echo "<script>alert('Registrazione effettuata! Controlla la tua email.'); window.location.href='login.php';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<script>alert('Errore durante la registrazione: " . addslashes($e->getMessage()) . "');</script>";
    }
}

show_form:
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../../public/assets/css/loginRegisterStyle.css">
</head>
<body>
<form method="POST" action="register.php">
    <h2>Registrati</h2>

    <div class="form-row">
        <label for="nome">Nome</label>
        <input type="text" id="nome" name="nome" required value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
    </div>

    <div class="form-row">
        <label for="cognome">Cognome</label>
        <input type="text" id="cognome" name="cognome" required value="<?= htmlspecialchars($_POST['cognome'] ?? '') ?>">
    </div>

    <div class="form-row">
        <label for="data_nascita">Data di nascita</label>
        <input type="date" id="data_nascita" name="data_nascita" required value="<?= htmlspecialchars($_POST['data_nascita'] ?? '') ?>">
    </div>

    <div class="form-row">
        <label for="sesso">Sesso</label>
        <select id="sesso" name="sesso" required>
            <option value=""></option>
            <option value="M" <?= (($_POST['sesso'] ?? '') === 'M') ? 'selected' : '' ?>>Maschio</option>
            <option value="F" <?= (($_POST['sesso'] ?? '') === 'F') ? 'selected' : '' ?>>Femmina</option>
        </select>
    </div>

    <div class="form-row">
        <label for="comune_nascita">Comune di nascita</label>
        <input type="text" id="comune_nascita" name="comune_nascita" required value="<?= htmlspecialchars($_POST['comune_nascita'] ?? '') ?>">
    </div>

    <div class="form-row">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
    </div>

    <div class="form-row">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>

    <div class="form-row">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <ul id="pwd-req">
            <li id="req-length">Minimo 8 caratteri</li>
            <li id="req-upper">Almeno 1 lettera maiuscola</li>
            <li id="req-number">Almeno 1 numero</li>
            <li id="req-symbol">Almeno 1 simbolo speciale</li>
        </ul>
    </div>

    <div class="form-row">
        <label for="codice_fiscale">Codice Fiscale (opzionale)</label>
        <input type="text" id="codice_fiscale" name="codice_fiscale" maxlength="16" value="<?= htmlspecialchars($_POST['codice_fiscale'] ?? '') ?>">
        <small style="color: #888; font-size: 12px; margin-top: 5px;">Lascia vuoto per generazione automatica</small>
    </div>

    <button type="submit">Registrati</button>
    <a href="login.php" id="indietro">Login</a>
    <a href="../user/homepage.php" id="indietro">Indietro</a>
</form>

<script src="../../public/assets/js/checkRegisterFormData.js"></script>
<script src="../../public/assets/js/autocompleteComune.js"></script>
</body>
</html>