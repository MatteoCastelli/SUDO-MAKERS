<?php
session_start();

require_once 'Database.php';
require_once 'RecommendationEngine.php';

use Proprietario\SudoMakers\RecommendationEngine;

$pdo = Database::getInstance()->getConnection();
$engine = new RecommendationEngine($pdo);

// Recupera i dati POST JSON
$data = json_decode(file_get_contents('php://input'), true);

$id_utente = $data['id_utente'] ?? null;
$id_libro = $data['id_libro'] ?? null;
$tipo = $data['tipo'] ?? 'click';

if ($id_utente && $id_libro) {
    $success = $engine->trackInteraction($id_utente, $id_libro, $tipo);

    if ($success) {
        http_response_code(200);
        echo json_encode(['message' => 'Interazione tracciata']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Errore durante il tracking']);
    }
} else {
    http_response_code(400);
    echo json_encode(['message' => 'Parametri mancanti']);
}
