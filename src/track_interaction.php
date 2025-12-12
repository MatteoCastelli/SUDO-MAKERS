<?php
session_start();
require_once 'Database.php';
require_once 'RecommendationEngine.php';

use Proprietario\SudoMakers\RecommendationEngine;

header('Content-Type: application/json');

// Leggi input JSON
$input = json_decode(file_get_contents('php://input'), true);

// Preleva ID utente dalla sessione
$id_utente = $_SESSION['id_utente'] ?? null;

// Sanitizzazione input
$id_libro = isset($input['id_libro']) ? filter_var($input['id_libro'], FILTER_VALIDATE_INT) : null;
$tipo = isset($input['tipo']) ? trim($input['tipo']) : null;
$fonte = isset($input['fonte']) ? substr(strip_tags($input['fonte']), 0, 50) : null;
$durata = isset($input['durata']) ? filter_var($input['durata'], FILTER_VALIDATE_INT) : null;

// Tipi ammessi (aggiunto 'view_dettaglio' che è nel tuo array)
$valid_types = [
    'click',
    'view',
    'view_dettaglio',
    'prenotazione',
    'rating',
    'like',
    'dislike'
];

// Validazioni
if (!$id_utente) {
    http_response_code(401);
    echo json_encode(['error' => 'Utente non autenticato']);
    exit;
}

if (!$id_libro || !$tipo) {
    http_response_code(400);
    echo json_encode(['error' => 'Parametri mancanti o non validi']);
    exit;
}

if (!in_array($tipo, $valid_types, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo di interazione non valido']);
    exit;
}

// Per durata, se presente, assicurati che sia positiva o null
if ($durata !== null && $durata < 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Durata non valida']);
    exit;
}

// Inizializza DB ed engine
$pdo = Database::getInstance()->getConnection();
$engine = new RecommendationEngine($pdo);

// Salva interazione
$success = $engine->trackInteraction(
    $id_utente,
    $id_libro,
    $tipo,
    $fonte,
    $durata
);

// Risposta finale
if ($success) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Errore nel tracciamento dell\'interazione']);
}
