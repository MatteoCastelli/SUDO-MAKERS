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

function requireAnyRole($ruoli) {
    if(!hasAnyRole($ruoli)) {
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