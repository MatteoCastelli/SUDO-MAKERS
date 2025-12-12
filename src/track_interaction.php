<?php
session_start();
require_once 'Database.php';
require_once 'RecommendationEngine.php';

use Proprietario\SudoMakers\RecommendationEngine;

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$id_utente = $_SESSION['id_utente'] ?? null;
$id_libro = $input['id_libro'] ?? null;
$tipo = $input['tipo'] ?? null;
$fonte = $input['fonte'] ?? null;
$durata = $input['durata'] ?? null;

$valid_types = ['click', 'view', 'prenotazione', 'rating', 'like', 'dislike'];

if (!$id_utente) {
    http_response_code(401);
    echo json_encode(['error' => 'Utente non autenticato']);
    exit;
}

if (!$id_libro || !$tipo) {
    http_response_code(400);
    echo json_encode(['error' => 'Parametri mancanti']);
    exit;
}

if (!in_array($tipo, $valid_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo di interazione non valido']);
    exit;
}

$pdo = Database::getInstance()->getConnection();
$engine = new RecommendationEngine($pdo);

$success = $engine->trackInteraction($id_utente, $id_libro, $tipo, $fonte, $durata);

if ($success) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Errore nel tracciamento']);
}
