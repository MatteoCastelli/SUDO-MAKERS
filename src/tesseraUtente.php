<?php
session_start();

if(isset($_SESSION['id_utente'])) {
    //genera tessera
}else{
    header("location:login.php");
}
?>

