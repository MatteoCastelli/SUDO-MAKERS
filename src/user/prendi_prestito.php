<?php

use Proprietario\SudoMakers\core\Database;

session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/check_permissions.php';

// Verifica autenticazione
if(!isset($_SESSION['id_utente'])) {
    header("Location: ../auth/login.php");
    exit;
}

$pdo = Database::getInstance()->getConnection();
$id_utente = $_SESSION['id_utente'];

// Gestione richiesta prestito
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

        // Verifica che l'utente non abbia già 10 prestiti attivi
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM prestito 
            WHERE id_utente = :id_utente 
            AND data_restituzione_effettiva IS NULL
        ");
        $stmt->execute(['id_utente' => $id_utente]);
        $num_prestiti = $stmt->fetchColumn();

        if($num_prestiti >= 10) {
            throw new Exception("Hai raggiunto il limite di 10 prestiti attivi");
        }

        // Verifica che l'utente non abbia già questo libro in prestito
        $stmt = $pdo->prepare("
            SELECT p.* 
            FROM prestito p
            JOIN copia c ON p.id_copia = c.id_copia
            WHERE p.id_utente = :id_utente 
            AND c.id_libro = :id_libro 
            AND p.data_restituzione_effettiva IS NULL
        ");
        $stmt->execute(['id_utente' => $id_utente, 'id_libro' => $id_libro]);

        if($stmt->fetch()) {
            throw new Exception("Hai già questo libro in prestito");
        }

        // Cerca una copia disponibile
        $stmt = $pdo->prepare("
            SELECT id_copia 
            FROM copia 
            WHERE id_libro = :id_libro 
            AND disponibile = 1 
            AND stato_fisico != 'smarrito'
            LIMIT 1
        ");
        $stmt->execute(['id_libro' => $id_libro]);
        $copia = $stmt->fetch();

        if(!$copia) {
            throw new Exception("Nessuna copia disponibile al momento");
        }

        $id_copia = $copia['id_copia'];

        // Calcola data scadenza (1 mese dopo)
        $data_scadenza = date('Y-m-d H:i:s', strtotime('+1 month'));

        // Inizia transazione
        $pdo->beginTransaction();

        // Crea il prestito
        $stmt = $pdo->prepare("
            INSERT INTO prestito 
            (id_utente, id_copia, data_prestito, data_scadenza, note) 
            VALUES (:id_utente, :id_copia, NOW(), :data_scadenza, 'Prestito diretto dal catalogo')
        ");
        $stmt->execute([
            'id_utente' => $id_utente,
            'id_copia' => $id_copia,
            'data_scadenza' => $data_scadenza
        ]);

        // Segna la copia come non disponibile
        $stmt = $pdo->prepare("
            UPDATE copia 
            SET disponibile = 0 
            WHERE id_copia = :id_copia
        ");
        $stmt->execute(['id_copia' => $id_copia]);

        // Crea notifica
        $stmt = $pdo->prepare("
            INSERT INTO notifica 
            (id_utente, tipo, titolo, messaggio) 
            VALUES 
            (:id_utente, 'prestito', 'Prestito attivato', 
             :messaggio)
        ");
        $messaggio = "Hai preso in prestito '{$libro['titolo']}'. Data di restituzione: " . date('d/m/Y', strtotime($data_scadenza));
        $stmt->execute([
            'id_utente' => $id_utente,
            'messaggio' => $messaggio
        ]);

        $pdo->commit();

        // ===== HOOK GAMIFICATION - Check Badge =====
        require_once __DIR__ . '/../core/GamificationEngine.php';
        $gamification = new \Proprietario\SudoMakers\core\GamificationEngine($pdo);

// Recupera categoria libro
        $stmt = $pdo->prepare("SELECT categoria FROM libro WHERE id_libro = :id");
        $stmt->execute(['id' => $id_libro]);
        $categoria = $stmt->fetchColumn();

// Check badge letture
        $badges_awarded = $gamification->checkAndAwardBadges($id_utente, 'prestito_completato');

// Check badge genere se categoria esiste
        if($categoria) {
            $badges_genere = $gamification->checkAndAwardBadges($id_utente, 'genere_esplorato', ['categoria' => $categoria]);
            $badges_awarded = array_merge($badges_awarded, $badges_genere);
        }

// Aggiorna obiettivi
        $gamification->updateObjectiveProgress($id_utente);
// ===== FINE HOOK GAMIFICATION =====

        header("Location: ../catalog/dettaglio_libro.php?id={$id_libro}&prestito=success&scadenza=" . urlencode(date('d/m/Y', strtotime($data_scadenza))));
        exit;

    } catch(Exception $e) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
        header("Location: ../catalog/dettaglio_libro.php?id={$id_libro}&error=" . urlencode($error));
        exit;
    }
}

// Se arriva qui senza POST, redirect alla homepage
header("Location: homepage.php");
exit;
?>