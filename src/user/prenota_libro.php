<?php

use Proprietario\SudoMakers\core\Database;

session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/check_permissions.php';

// Verifica autenticazione
if(!isset($_SESSION['id_utente'])) {
    header("Location: login.php");
    exit;
}

$pdo = Database::getInstance()->getConnection();
$id_utente = $_SESSION['id_utente'];

// Gestione richiesta prenotazione
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_libro'])) {
    $id_libro = (int)$_POST['id_libro'];

    try {
        // Verifica che il libro esista
        $stmt = $pdo->prepare("SELECT titolo FROM libro WHERE id_libro = :id");
        $stmt->execute(['id' => $id_libro]);
        $libro = $stmt->fetch();

        if(!$libro) {
            throw new Exception("Libro non trovato");
        }

        // Verifica che l'utente non abbia già 5 prenotazioni attive
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM prenotazione 
            WHERE id_utente = :id_utente 
            AND stato IN ('attiva', 'disponibile')
        ");
        $stmt->execute(['id_utente' => $id_utente]);
        $num_prenotazioni = $stmt->fetchColumn();

        if($num_prenotazioni >= 5) {
            throw new Exception("Hai raggiunto il limite di 5 prenotazioni attive");
        }

        // Verifica che l'utente non abbia già prenotato questo libro
        $stmt = $pdo->prepare("
            SELECT * 
            FROM prenotazione 
            WHERE id_utente = :id_utente 
            AND id_libro = :id_libro 
            AND stato IN ('attiva', 'disponibile')
        ");
        $stmt->execute(['id_utente' => $id_utente, 'id_libro' => $id_libro]);

        if($stmt->fetch()) {
            throw new Exception("Hai già una prenotazione attiva per questo libro");
        }

        // Verifica se ci sono copie disponibili (non dovrebbe, ma controllo)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM copia 
            WHERE id_libro = :id_libro 
            AND disponibile = 1 
            AND stato_fisico != 'smarrito'
        ");
        $stmt->execute(['id_libro' => $id_libro]);
        $copie_disponibili = $stmt->fetchColumn();

        if($copie_disponibili > 0) {
            throw new Exception("Ci sono copie disponibili! Puoi prenderlo direttamente.");
        }

        // Calcola posizione in coda
        $stmt = $pdo->prepare("
            SELECT MAX(posizione_coda) as max_pos 
            FROM prenotazione 
            WHERE id_libro = :id_libro 
            AND stato = 'attiva'
        ");
        $stmt->execute(['id_libro' => $id_libro]);
        $result = $stmt->fetch();
        $nuova_posizione = ($result['max_pos'] ?? 0) + 1;

        // Calcola stima tempo di attesa (in giorni)
        // Basata sulla durata media dei prestiti passati per questo libro
        $stmt = $pdo->prepare("
            SELECT AVG(DATEDIFF(data_restituzione_effettiva, data_prestito)) as durata_media
            FROM prestito p
            JOIN copia c ON p.id_copia = c.id_copia
            WHERE c.id_libro = :id_libro
            AND p.data_restituzione_effettiva IS NOT NULL
        ");
        $stmt->execute(['id_libro' => $id_libro]);
        $result = $stmt->fetch();
        $durata_media = $result['durata_media'] ?? 14; // Default 14 giorni

        // Conta quante copie sono in prestito
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM copia 
            WHERE id_libro = :id_libro 
            AND disponibile = 0
        ");
        $stmt->execute(['id_libro' => $id_libro]);
        $copie_in_prestito = $stmt->fetchColumn();

        // Stima: (posizione / copie_in_prestito) * durata_media
        $giorni_stima = $copie_in_prestito > 0
            ? ceil(($nuova_posizione / $copie_in_prestito) * $durata_media)
            : ceil($nuova_posizione * $durata_media);

        // Inserisci prenotazione
        $stmt = $pdo->prepare("
            INSERT INTO prenotazione 
            (id_utente, id_libro, stato, posizione_coda) 
            VALUES (:id_utente, :id_libro, 'attiva', :posizione)
        ");
        $stmt->execute([
            'id_utente' => $id_utente,
            'id_libro' => $id_libro,
            'posizione' => $nuova_posizione
        ]);

        // Crea notifica
        $stmt = $pdo->prepare("
            INSERT INTO notifica 
            (id_utente, tipo, titolo, messaggio) 
            VALUES 
            (:id_utente, 'prenotazione', 'Prenotazione confermata', 
             :messaggio)
        ");
        $messaggio = "Hai prenotato '{$libro['titolo']}'. Posizione in coda: {$nuova_posizione}. Tempo stimato: circa {$giorni_stima} giorni.";
        $stmt->execute([
            'id_utente' => $id_utente,
            'messaggio' => $messaggio
        ]);

        header("Location: ../catalog/dettaglio_libro.php?id={$id_libro}&prenotazione=success&posizione={$nuova_posizione}&stima={$giorni_stima}");
        exit;

    } catch(Exception $e) {
        $error = $e->getMessage();
        header("Location: ../catalog/dettaglio_libro.php?id={$id_libro}&error=" . urlencode($error));
        exit;
    }
}

// Se arriva qui senza POST, redirect alla homepage
header("Location: homepage.php");
exit;
?>
