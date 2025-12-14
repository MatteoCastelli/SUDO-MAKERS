<?php
use Proprietario\SudoMakers\Database;
use Proprietario\SudoMakers\RecommendationEngine;

session_start();
require_once "Database.php";
require_once "RecommendationEngine.php";

header('Content-Type: application/json');

if (!isset($_SESSION['id_utente'])) {
    echo json_encode(['success' => false, 'message' => 'Non autenticato']);
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    $engine = new RecommendationEngine($pdo);

    // Ottieni libri trending (ad esempio ultimi 24h o 30 giorni, come preferisci)
    $libri_trending = $engine->getTrendingBooks(24);

    // Prepara array con solo dati essenziali (id libro e statistiche)
    $stats = [];
    foreach ($libri_trending as $libro) {
        $stats[$libro['id_libro']] = [
            'prestiti_ultimi_7_giorni' => $libro['prestiti_ultimi_7_giorni'],
            'click_ultimi_7_giorni' => $libro['click_ultimi_7_giorni'],
            'prenotazioni_attive' => $libro['prenotazioni_attive'],
            'velocita_trend' => $libro['velocita_trend']
        ];
    }

    echo json_encode(['success' => true, 'data' => $stats]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Errore server: ' . $e->getMessage()]);
}
