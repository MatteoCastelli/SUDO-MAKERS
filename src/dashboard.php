<?php
use Proprietario\SudoMakers\Database;

session_start();
require_once "Database.php";

if(!isset($_SESSION['id_utente'])) {
    header("Location: login.php");
    exit;
}

$pdo = Database::getInstance()->getConnection();

// Recupera tutti i ruoli dell'utente
$stmt = $pdo->prepare("
    SELECT r.nome_ruolo, r.livello_permesso
    FROM ruolo r
    JOIN utente_ruolo ur ON r.id_ruolo = ur.id_ruolo
    WHERE ur.id_utente = :id_utente
    ORDER BY r.livello_permesso DESC
");
$stmt->execute(['id_utente' => $_SESSION['id_utente']]);
$ruoli = $stmt->fetchAll();

// Salva i ruoli in sessione
$_SESSION['ruoli'] = array_column($ruoli, 'nome_ruolo');
$_SESSION['livello_massimo'] = $ruoli[0]['livello_permesso'] ?? 1;

// Determina il ruolo principale (quello con livello permesso più alto)
$ruolo_principale = $ruoli[0]['nome_ruolo'] ?? 'utente';

// Redirect alla dashboard appropriata
switch($ruolo_principale) {
    case 'amministratore':
        header("Location: admin/dashboard_admin.php");
        break;
    case 'bibliotecario':
        header("Location: bibliotecario/dashboard_bibliotecario.php");
        break;
    case 'utente':
    default:
        header("Location: homepage.php");
        break;
}
exit;
?>