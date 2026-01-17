<?php
/**
 * API per ottenere le statistiche di trending per un libro specifico
 * Chiamato quando l'utente visualizza un libro
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../core/Database.php';

use Proprietario\SudoMakers\core\Database;

try {
    // Verifica che sia una richiesta GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Metodo non consentito');
    }

    // Verifica parametro id_libro
    if (!isset($_GET['id_libro'])) {
        throw new Exception('Parametro id_libro mancante');
    }

    $id_libro = filter_var($_GET['id_libro'], FILTER_VALIDATE_INT);
    if ($id_libro === false || $id_libro <= 0) {
        throw new Exception('ID libro non valido');
    }

    $pdo = Database::getInstance()->getConnection();

    // Ottieni statistiche complete del libro
    $stmt = $pdo->prepare("
        SELECT 
            l.id_libro,
            l.titolo,
            l.autore,
            l.isbn,
            l.immagine_copertina_url,
            l.data_pubblicazione,
            
            -- Contatori base
            COALESCE(lt.visualizzazioni_totali, 0) as visualizzazioni_totali,
            COALESCE(lt.visualizzazioni_settimana, 0) as visualizzazioni_settimana,
            COALESCE(lt.visualizzazioni_mese, 0) as visualizzazioni_mese,
            
            COALESCE(lt.prestiti_totali, 0) as prestiti_totali,
            COALESCE(lt.prestiti_settimana, 0) as prestiti_settimana,
            COALESCE(lt.prestiti_mese, 0) as prestiti_mese,
            
            COALESCE(lt.prenotazioni_totali, 0) as prenotazioni_totali,
            COALESCE(lt.prenotazioni_attive, 0) as prenotazioni_attive,
            
            -- Score e rank
            COALESCE(lt.trend_score, 0) as trend_score,
            lt.rank_globale,
            lt.rank_categoria,
            
            -- Metriche calcolate
            CASE 
                WHEN lt.visualizzazioni_totali > 0 
                THEN ROUND((lt.prestiti_totali / lt.visualizzazioni_totali) * 100, 2)
                ELSE 0 
            END as conversion_rate,
            
            -- Copie disponibili
            (SELECT COUNT(*) 
             FROM copia c 
             WHERE c.id_libro = l.id_libro 
             AND c.stato = 'disponibile'
            ) as copie_disponibili,
            
            (SELECT COUNT(*) 
             FROM copia c 
             WHERE c.id_libro = l.id_libro
            ) as copie_totali,
            
            -- Valutazione media
            COALESCE(
                (SELECT AVG(valutazione) 
                 FROM prestito p 
                 WHERE p.id_copia IN (SELECT id_copia FROM copia WHERE id_libro = l.id_libro)
                 AND p.valutazione IS NOT NULL
                ), 0
            ) as valutazione_media,
            
            (SELECT COUNT(*) 
             FROM prestito p 
             WHERE p.id_copia IN (SELECT id_copia FROM copia WHERE id_libro = l.id_libro)
             AND p.valutazione IS NOT NULL
            ) as numero_valutazioni,
            
            -- Categoria
            cat.nome_categoria,
            
            -- Ultima interazione
            lt.ultimo_aggiornamento
            
        FROM libro l
        LEFT JOIN libro_trending lt ON l.id_libro = lt.id_libro
        LEFT JOIN categoria cat ON l.id_categoria = cat.id_categoria
        WHERE l.id_libro = :id_libro
    ");

    $stmt->execute(['id_libro' => $id_libro]);
    $libro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$libro) {
        throw new Exception('Libro non trovato');
    }

    // Ottieni anche le ultime 5 visualizzazioni recenti
    $stmt_recent = $pdo->prepare("
        SELECT 
            u.nome,
            u.cognome,
            i.data_interazione,
            i.tipo_interazione
        FROM interazione i
        JOIN utente u ON i.id_utente = u.id_utente
        WHERE i.id_libro = :id_libro
        AND i.tipo_interazione = 'visualizzazione'
        ORDER BY i.data_interazione DESC
        LIMIT 5
    ");

    $stmt_recent->execute(['id_libro' => $id_libro]);
    $recent_views = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);

    // Prepara risposta JSON
    $response = [
        'success' => true,
        'libro' => [
            'id' => $libro['id_libro'],
            'titolo' => $libro['titolo'],
            'autore' => $libro['autore'],
            'isbn' => $libro['isbn'],
            'immagine' => $libro['immagine_copertina_url'],
            'categoria' => $libro['nome_categoria'],
            'data_pubblicazione' => $libro['data_pubblicazione']
        ],
        'statistiche' => [
            'visualizzazioni' => [
                'totali' => (int)$libro['visualizzazioni_totali'],
                'settimana' => (int)$libro['visualizzazioni_settimana'],
                'mese' => (int)$libro['visualizzazioni_mese']
            ],
            'prestiti' => [
                'totali' => (int)$libro['prestiti_totali'],
                'settimana' => (int)$libro['prestiti_settimana'],
                'mese' => (int)$libro['prestiti_mese']
            ],
            'prenotazioni' => [
                'totali' => (int)$libro['prenotazioni_totali'],
                'attive' => (int)$libro['prenotazioni_attive']
            ],
            'copie' => [
                'disponibili' => (int)$libro['copie_disponibili'],
                'totali' => (int)$libro['copie_totali']
            ],
            'valutazioni' => [
                'media' => round((float)$libro['valutazione_media'], 2),
                'numero' => (int)$libro['numero_valutazioni']
            ]
        ],
        'ranking' => [
            'trend_score' => round((float)$libro['trend_score'], 2),
            'rank_globale' => $libro['rank_globale'] ? (int)$libro['rank_globale'] : null,
            'rank_categoria' => $libro['rank_categoria'] ? (int)$libro['rank_categoria'] : null,
            'conversion_rate' => (float)$libro['conversion_rate']
        ],
        'recent_views' => array_map(function($view) {
            return [
                'utente' => $view['nome'] . ' ' . substr($view['cognome'], 0, 1) . '.',
                'data' => $view['data_interazione'],
                'tipo' => $view['tipo_interazione']
            ];
        }, $recent_views),
        'ultimo_aggiornamento' => $libro['ultimo_aggiornamento']
    ];

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>