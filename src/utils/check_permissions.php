<?php

function hasRole($ruolo) {
    return isset($_SESSION['ruoli']) && in_array($ruolo, $_SESSION['ruoli']);
}

function hasAnyRole($ruoli) {
    if(!isset($_SESSION['ruoli'])) return false;
    foreach($ruoli as $ruolo) {
        if(in_array($ruolo, $_SESSION['ruoli'])) {
            return true;
        }
    }
    return false;
}

function requireRole($ruolo) {
    if(!hasRole($ruolo)) {
        header("Location: ../user/homepage.php?error=unauthorized");
        exit;
    }
}

function requireAnyRole($ruoli) {
    if(!hasAnyRole($ruoli)) {
        header("Location: ../user/homepage.php?error=unauthorized");
        exit;
    }
}

function requireMinLevel($livello_minimo) {
    if(!isset($_SESSION['livello_massimo']) || $_SESSION['livello_massimo'] < $livello_minimo) {
        header("Location: ../user/homepage.php?error=unauthorized");
        exit;
    }
}

function isAdmin() {
    return hasRole('amministratore');
}

function isBibliotecario() {
    return hasRole('bibliotecario');
}

function isUtente() {
    return hasRole('utente');
}
?>