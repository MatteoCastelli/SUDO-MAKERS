<?php

use Proprietario\SudoMakers\Database;

$title = "Accedi";
session_start();
require_once "Database.php";
require_once "functions.php";
if(!empty($_POST)){
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare("SELECT * FROM utente WHERE email = :email");
    $stmt->execute(['email' => $_POST['email']]);
    $utente = $stmt->fetch();

    if($utente){
        if($utente["email_verificata"] == 1) {
            if (password_verify($_POST['password'], $utente['password_hash'])) {
                $_SESSION['email'] = $utente['email'];
                $_SESSION['id_utente'] = $utente['id_utente'];
                sendLoginMail($utente['email'] . "@gmail.com", $utente['password_hash']);
                header("Location: homepage.php");
                exit;
            } else {
                echo "<script>alert('Password Sbagliata');</script>";
            }
        }else{
            echo "<script>alert('Email non verificata');</script>";
        }
    }else{
        echo "<script>alert('Utente non trovato');</script>";
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
<body style="margin: 0;">
<form method="POST" action="login.php">

    <h2>Accedi</h2>

    <div class="form-row">
        <label for="email">Email</label>
        <input type="text" id="email" name="email" value="<?= $_POST['email'] ?? '' ?>" required>
    </div>

    <div class="form-row">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
    </div>

    <button type="submit">Accedi</button>
    <a href="index.php" id="indietro">Indietro</a>
</form>
</body>
</html>
