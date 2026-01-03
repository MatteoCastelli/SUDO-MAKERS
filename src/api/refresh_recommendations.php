<?php

use Proprietario\SudoMakers\core\Database;
use Proprietario\SudoMakers\core\RecommendationEngine;

session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/RecommendationEngine.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_utente'])) {
    echo json_encode(['success' => false, 'message' => 'Non autenticato']);
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    $engine = new RecommendationEngine($pdo);

    // Elimina cache vecchia
    $stmt = $pdo->prepare("
        DELETE FROM cache_raccomandazioni 
        WHERE id_utente = :id_utente
    ");
    $stmt->execute(['id_utente' => $_SESSION['id_utente']]);

    // Genera nuove raccomandazioni
    $engine->generateRecommendations($_SESSION['id_utente'], 12);

    echo json_encode([
        'success' => true,
        'message' => 'Raccomandazioni aggiornate'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Errore: ' . $e->getMessage()
    ]);
}