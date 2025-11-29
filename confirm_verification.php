<?php
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
    $message = "Email verificata";
    header("location: login.php");
} else {
    $message = "Token non valido o scaduto";
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
</head>
<body>
<?php if(!empty($message)) echo $message; ?>
</body>
</html>
