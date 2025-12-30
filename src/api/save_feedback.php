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

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['id_libro']) || !isset($data['feedback'])) {
    echo json_encode(['success' => false, 'message' => 'Dati mancanti']);
    exit;
}

$id_utente = $_SESSION['id_utente'];
$id_libro = (int)$data['id_libro'];
$feedback = $data['feedback'];
$motivo = $data['motivo'] ?? null;

// Valida feedback
$feedback_validi = ['thumbs_up', 'thumbs_down', 'not_interested'];
if (!in_array($feedback, $feedback_validi)) {
    echo json_encode(['success' => false, 'message' => 'Feedback non valido']);
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    $engine = new RecommendationEngine($pdo);

    $success = $engine->saveFeedback($id_utente, $id_libro, $feedback, $motivo);

    // Se feedback negativo, rimuovi dalla cache raccomandazioni
    if ($feedback === 'thumbs_down' || $feedback === 'not_interested') {
        $stmt = $pdo->prepare("
            DELETE FROM cache_raccomandazioni 
            WHERE id_utente = :id_utente AND id_libro = :id_libro
        ");
        $stmt->execute([
            'id_utente' => $id_utente,
            'id_libro' => $id_libro
        ]);
    }

    echo json_encode([
        'success' => $success,
        'message' => 'Feedback salvato'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Errore: ' . $e->getMessage()
    ]);
}