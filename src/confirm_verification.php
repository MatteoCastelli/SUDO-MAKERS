<?php

use Proprietario\SudoMakers\Database;

require_once 'Database.php';
$title = "Conferma verifica";
$pdo = database::getInstance()->getConnection();

if(!isset($_GET["token"])){
    header("location: login.php");
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
    header("location: login.php");
} else {
    echo "<script>alert('Link scaduto');</script>";
    header("location: index.php");
}