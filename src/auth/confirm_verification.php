<?php

use Proprietario\SudoMakers\core\Database;

require_once __DIR__ . '/../core/Database.php';
$title = "Conferma verifica";
$pdo = Database::getInstance()->getConnection();

if(!isset($_GET["token"])){
    header("Location: login.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM utente WHERE verification_token = :token AND 
verification_expires > NOW() AND email_verificata = false");

$stmt->execute(['token' => $_GET["token"]]);
$utente = $stmt->fetch();

if($utente){
    $stmt = $pdo->prepare("UPDATE utente SET email_verificata = true,
    verification_token = NULL, verification_expires = NULL WHERE id_utente = :id_utente");
    $stmt->execute(['id_utente' => $utente["id_utente"]]);

    // IMPORTANTE: Prima fai l'UPDATE, poi fai il redirect
    // Non mescolare echo/alert con header
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <title>Email Verificata</title>
    </head>
    <body>
    <script>
        alert('Email verificata con successo! Ora puoi effettuare il login.');
        window.location.href = 'login.php';
    </script>
    </body>
    </html>
    <?php
    exit();
} else {
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <title>Link Scaduto</title>
    </head>
    <body>
    <script>
        alert('Link scaduto o già utilizzato.');
        window.location.href = 'homepage.php';
    </script>
    </body>
    </html>
    <?php
    exit();
}