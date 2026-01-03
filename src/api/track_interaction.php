<?php

use Proprietario\SudoMakers\core\Database;
use Proprietario\SudoMakers\core\RecommendationEngine;

session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/RecommendationEngine.php';

header('Content-Type: application/json');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['id_utente'])) {
    echo json_encode(['success' => false, 'message' => 'Non autenticato']);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

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

    // Salva l'interazione
    $success = $engine->trackInteraction($id_utente, $id_libro, $tipo, $fonte, $durata);

    // ========================================================
    // NUOVO: Calcola le statistiche aggiornate per questo libro
    // ========================================================

    $stmt = $pdo->prepare("
        SELECT 
            -- Click ultimi 7 giorni (SOLO tipo 'click', non 'view_dettaglio')
            COUNT(DISTINCT CASE 
                WHEN i.data_interazione >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                AND i.tipo_interazione = 'click'
                THEN i.id_interazione 
            END) as click_ultimi_7_giorni,
            
            -- Prestiti ultimi 7 giorni
            COUNT(DISTINCT CASE 
                WHEN p.data_prestito >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                THEN p.id_prestito 
            END) as prestiti_ultimi_7_giorni,
            
            -- Prenotazioni attive
            COUNT(DISTINCT CASE 
                WHEN pr.stato = 'attiva' 
                THEN pr.id_prenotazione 
            END) as prenotazioni_attive
            
        FROM libro l
        LEFT JOIN interazione_utente i ON l.id_libro = i.id_libro
        LEFT JOIN copia c ON l.id_libro = c.id_libro
        LEFT JOIN prestito p ON c.id_copia = p.id_copia
        LEFT JOIN prenotazione pr ON l.id_libro = pr.id_libro
        WHERE l.id_libro = :id_libro
        GROUP BY l.id_libro
    ");

    $stmt->execute(['id_libro' => $id_libro]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Interazione tracciata' : 'Errore durante il salvataggio',
        'updated_stats' => $stats  // NUOVO: Restituisce le statistiche aggiornate!
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Errore server: ' . $e->getMessage()
    ]);
}