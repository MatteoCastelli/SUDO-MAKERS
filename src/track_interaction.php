<?php
use Proprietario\SudoMakers\Database;
use Proprietario\SudoMakers\RecommendationEngine;

session_start();
require_once "Database.php";
require_once "RecommendationEngine.php";

header('Content-Type: application/json');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");


header('Content-Type: application/json');

// Verifica che l'utente sia autenticato
if (!isset($_SESSION['id_utente'])) {
    echo json_encode(['success' => false, 'message' => 'Non autenticato']);
    exit;
}

// Leggi i dati JSON inviati
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Debug
error_log('track_interaction.php ricevuto: ' . print_r($data, true));

if (!$data || !isset($data['id_libro']) || !isset($data['tipo_interazione'])) {
    echo json_encode(['success' => false, 'message' => 'Dati mancanti']);
    exit;
}

$id_utente = $_SESSION['id_utente'];
$id_libro = (int)$data['id_libro'];
$tipo = $data['tipo_interazione'];
$durata = isset($data['durata_visualizzazione']) ? (int)$data['durata_visualizzazione'] : null;
$fonte = isset($data['fonte']) ? $data['fonte'] : null;

$tipi_validi = ['click', 'view_dettaglio', 'ricerca', 'prenotazione_tentata'];
if (!in_array($tipo, $tipi_validi)) {
    echo json_encode(['success' => false, 'message' => 'Tipo interazione non valido']);
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();
    $engine = new RecommendationEngine($pdo);

    $success = $engine->trackInteraction($id_utente, $id_libro, $tipo, $fonte, $durata);

    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Interazione tracciata' : 'Errore durante il salvataggio'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Errore server: ' . $e->getMessage()
    ]);
}
