<?php
use Proprietario\SudoMakers\Database;

session_start();
require_once "Database.php";

header('Content-Type: application/json');
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['id_utente'])) {
    echo json_encode(['success' => false, 'message' => 'Non autenticato']);
    exit;
}

try {
    $pdo = Database::getInstance()->getConnection();

    // ========================================================
    // CALCOLO IN TEMPO REALE dalle interazioni
    // ========================================================

    $stmt = $pdo->query("
        SELECT 
            l.id_libro,
            
            -- Prestiti ultimi 7 giorni
            COUNT(DISTINCT CASE 
                WHEN p.data_prestito >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                THEN p.id_prestito 
            END) as prestiti_ultimi_7_giorni,
            
            -- Prestiti ultimi 30 giorni
            COUNT(DISTINCT CASE 
                WHEN p.data_prestito >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                THEN p.id_prestito 
            END) as prestiti_ultimi_30_giorni,
            
            -- CLICK ultimi 7 giorni (SOLO click, NON view_dettaglio per evitare duplicati!)
            COUNT(DISTINCT CASE 
                WHEN i.data_interazione >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                AND i.tipo_interazione = 'click'
                THEN i.id_interazione 
            END) as click_ultimi_7_giorni,
            
            -- Prenotazioni attive
            COUNT(DISTINCT CASE 
                WHEN pr.stato = 'attiva' 
                THEN pr.id_prenotazione 
            END) as prenotazioni_attive,
            
            -- Velocità trend
            CASE 
                WHEN COUNT(DISTINCT CASE 
                    WHEN p.data_prestito >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                    THEN p.id_prestito 
                END) > 0
                THEN (
                    (COUNT(DISTINCT CASE 
                        WHEN p.data_prestito >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                        THEN p.id_prestito 
                    END) * 4.28) / 
                    COUNT(DISTINCT CASE 
                        WHEN p.data_prestito >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                        THEN p.id_prestito 
                    END)
                ) * 100 - 100
                ELSE 0
            END as velocita_trend
            
        FROM libro l
        LEFT JOIN copia c ON l.id_libro = c.id_libro
        LEFT JOIN prestito p ON c.id_copia = p.id_copia
        LEFT JOIN interazione_utente i ON l.id_libro = i.id_libro
        LEFT JOIN prenotazione pr ON l.id_libro = pr.id_libro
        GROUP BY l.id_libro
        HAVING click_ultimi_7_giorni > 0 
            OR prestiti_ultimi_7_giorni > 0 
            OR prenotazioni_attive > 0
        ORDER BY click_ultimi_7_giorni DESC, prestiti_ultimi_7_giorni DESC
    ");

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatta i dati per JavaScript
    $stats = [];
    foreach ($results as $row) {
        $stats[$row['id_libro']] = [
            'prestiti_ultimi_7_giorni' => (int)$row['prestiti_ultimi_7_giorni'],
            'prestiti_ultimi_30_giorni' => (int)$row['prestiti_ultimi_30_giorni'],
            'click_ultimi_7_giorni' => (int)$row['click_ultimi_7_giorni'],
            'prenotazioni_attive' => (int)$row['prenotazioni_attive'],
            'velocita_trend' => round((float)$row['velocita_trend'], 2)
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $stats,
        'timestamp' => date('Y-m-d H:i:s'),
        'total_books' => count($stats)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Errore server: ' . $e->getMessage()
    ]);
}