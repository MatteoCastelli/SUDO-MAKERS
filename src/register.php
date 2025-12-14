<?php

use Proprietario\SudoMakers\Database;

require '../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();
require "Database.php";
require "functions.php";
$title = "Registrati";

if(!empty($_POST)) {

    $pdo = Database::getInstance()->getConnection();

    // Verifica email duplicata
    $stmt = $pdo->prepare("SELECT * FROM utente WHERE email = :email");
    $stmt->execute([":email" => trim($_POST["email"])]);
    $utente = $stmt->fetch();

    if($utente){
        echo "<script>alert('Email già presente');</script>";
        goto show_form;
    }

    // Verifica username duplicato
    $username = trim($_POST["username"]);
    $stmt = $pdo->prepare("SELECT * FROM utente WHERE username = :username");
    $stmt->execute([":username" => $username]);
    $utenteUsername = $stmt->fetch();

    if($utenteUsername){
        echo "<script>alert('Username già in uso');</script>";
        goto show_form;
    }

    $nome = trim($_POST["nome"]);
    $cognome = trim($_POST["cognome"]);
    $data = $_POST["data_nascita"];
    $sesso = $_POST["sesso"];
    $comune_nascita = trim($_POST["comune_nascita"]);

    // Ottieni codice catastale
    $comune_catastale = getCodiceCatastale($comune_nascita);

    if(!$comune_catastale) {
        echo "<script>alert('Comune non trovato');</script>";
    } else {

        $cf_inserito = isset($_POST['codice_fiscale']) ? strtoupper(trim($_POST['codice_fiscale'])) : '';

        // Se il CF è vuoto, generalo automaticamente
        if(empty($cf_inserito)) {
            $cf_inserito = checkAndGenerateCF($nome, $cognome, $data, $sesso, $comune_nascita);

            if(!$cf_inserito) {
                echo "<script>alert('Errore nella generazione automatica del codice fiscale.');</script>";
                exit;
            }
        } else {
            // CF inserito manualmente - VALIDALO

            // 1) Verifica formato e checksum
            if(!verificaChecksumCF($cf_inserito)) {
                echo "<script>alert('Il codice fiscale inserito non è valido (errore nel formato o checksum). Controlla e riprova.');</script>";
                goto show_form;
            }

            // 2) Verifica che corrisponda ai dati inseriti
            if(!verificaCFvsDati($cf_inserito, $nome, $cognome, $data, $sesso, $comune_catastale)) {
                echo "<script>alert('Il codice fiscale inserito non corrisponde ai dati anagrafici. Verifica nome, cognome, data di nascita, sesso e comune.');</script>";
                goto show_form;
            }
        }

        // Controlla se il CF è già registrato
        $stmt = $pdo->prepare("SELECT * FROM utente WHERE codice_fiscale = :cf");
        $stmt->execute([':cf' => $cf_inserito]);
        if($stmt->fetch()) {
            echo "<script>alert('Questo codice fiscale è già registrato.');</script>";
            goto show_form;
        }

        // Tutto ok, procedi con la registrazione
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

        $url = 'http://localhost/SudoMakers/src/confirm_verification.php?token=' . urlencode($token_info[0]);
        sendVerificationEmail(trim($_POST["email"]), $nome, $url, $token_info[0]);

        // Registrazione completata, non mostra il form
        exit;
    }
}

show_form:
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../public/assets/css/loginRegisterStyle.css">
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
    <a href="homepage.php" id="indietro">Indietro</a>
</form>
</body>
<script src="../public/assets/js/checkRegisterFormData.js"></script>
<script src="../public/assets/js/autocompleteComune.js"></script>
</html>