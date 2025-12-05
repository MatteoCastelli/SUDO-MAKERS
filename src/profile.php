<?php
use Proprietario\SudoMakers\Database;

$title = "Il tuo profilo";
session_start();
require_once "Database.php";
$pdo = database::getInstance()->getConnection();

if(isset($_SESSION['id_utente'])) {
    $statement = $pdo->prepare("SELECT * FROM utente WHERE id_utente = :id_utente");
    $statement->execute(array('id_utente' => $_SESSION['id_utente']));
    $datiUtente = $statement->fetchAll();
}else{
    echo "<script>alert('Non autenticato');</script>";
    header("location: homepage.php");
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../style/privateAreaStyle.css">
</head>
<body>
<?php require_once 'navigation.php'; ?>

<div class="profile-wrapper">

    <h1><?= $title ?></h1>

    <div class="profile-container">
        <img src="<?= $datiUtente[0]["foto"] ?? 'default_profile.png' ?>" alt="Foto Profilo" class="profile-pic"><br>
        <button class="edit-btn" onclick="window.location.href='modifica_profilo.php?colonna=foto'">Modifica Foto</button>
    </div>

    <div class="profile-field">
        <span>Nome:</span> <?= $datiUtente[0]["nome"] ?>
        <button class="edit-btn" onclick="window.location.href='modifica_profilo.php?colonna=nome'">Modifica</button>
    </div>

    <div class="profile-field">
        <span>Cognome:</span> <?= $datiUtente[0]["cognome"] ?>
        <button class="edit-btn" onclick="window.location.href='modifica_profilo.php?colonna=cognome'">Modifica</button>
    </div>

    <div class="profile-field">
        <span>Data di nascita:</span> <?= $datiUtente[0]["data_nascita"] ?>
        <button class="edit-btn" onclick="window.location.href='modifica_profilo.php?colonna=data_nascita'">Modifica</button>
    </div>

    <div class="profile-field">
        <span>Sesso:</span> <?= $datiUtente[0]["sesso"] ?>
        <button class="edit-btn" onclick="window.location.href='modifica_profilo.php?colonna=sesso'">Modifica</button>
    </div>

    <div class="profile-field">
        <span>Comune di nascita:</span> <?= $datiUtente[0]["comune_nascita"] ?>
        <button class="edit-btn" onclick="window.location.href='modifica_profilo.php?colonna=comune_nascita'">Modifica</button>
    </div>

    <div class="profile-field">
        <span>Password:</span> ******
        <button class="edit-btn" onclick="window.location.href='modifica_profilo.php?colonna=password_hash'">Modifica</button>
    </div>
</div>
</body>
</html>
