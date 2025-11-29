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

    $stmt  = $pdo->prepare("SELECT * FROM utente WHERE email = :email");
    $stmt->execute([":email" => $_POST["email"]]);
    $utente = $stmt->fetch();

    if($utente){
        echo "<script>alert('Email già presente');</script>";
    }else if(getCodiceCatastale($_POST["comune_nascita"])){

        $token_info = generateEmailVerificationToken();
        $stmt = $pdo->prepare("INSERT INTO utente (nome, cognome, data_nascita, sesso, comune_nascita, codice_catastale
            , codice_fiscale, email, password_hash, verification_token, verification_expires) 
            values (:nome, :cognome, :data_nascita, :sesso,:comune_nascita, :codice_catastale,
            :codice_fiscale, :email, :password_hash, :verification_token,:verification_expires)");

        $stmt->execute([
                ':nome' => $_POST["nome"],
                ':cognome' => $_POST["cognome"],
                ':data_nascita' => $_POST["data_nascita"],
                ':sesso' => $_POST["sesso"],
                ':comune_nascita' => getCodiceCatastale($_POST["comune_nascita"]),
                ':codice_catastale' => getCodiceCatastale($_POST["comune_nascita"]),
                ':codice_fiscale' => strtoupper($_POST['codice_fiscale']),
                ':email' => $_POST["email"],
                ':password_hash' => password_hash($_POST["password"], PASSWORD_DEFAULT),
                ":verification_token" => $token_info[0],
                ":verification_expires" => ($token_info[1] instanceof DateTimeInterface) ? $token_info[1]->format("Y-m-d H:i:s") : $token_info[1],
        ]);

        $url = "http://localhost/SudoMakers/src/confirm_verification.php?token=" . urlencode($token_info[0]);
        sendVerificationEmail($_POST["email"], $_POST["nome"], $url, $token_info[0]);
    }else{
        echo "<script>alert('Comune non trovato');</script>";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../style/loginRegisterStyle.css">
</head>
<body>
<form method="POST" action="register.php">

    <h2>Registrati</h2>

    <div class="form-row">
        <label for="nome">Nome</label>
        <input type="text" id="nome" name="nome" required value="<?= $_POST['nome'] ?? '' ?>">
    </div>

    <div class="form-row">
        <label for="cognome">Cognome</label>
        <input type="text" id="cognome" name="cognome" required value="<?= $_POST['cognome'] ?? '' ?>">
    </div>

    <div class="form-row">
        <label for="data_nascita">Data di nascita</label>
        <input type="date" id="data_nascita" name="data_nascita" required value="<?= $_POST['data_nascita'] ?? '' ?>">
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
        <input type="text" id="comune_nascita" name="comune_nascita" required value="<?= $_POST['comune_nascita'] ?? '' ?>">
    </div>

    <div class="form-row">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required value="<?= $_POST['email'] ?? '' ?>">
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
        <label for="codice_fiscale">Codice Fiscale</label>
        <input type="text" id="codice_fiscale" name="codice_fiscale" value="<?= $_POST['codice_fiscale'] ?? '' ?>">
    </div>

    <button type="submit">Registrati</button>
    <a href="index.php" id="indietro">Indietro</a>
</form>
</body>
<script src="../scripts/checkFormData.js"></script>
</html>