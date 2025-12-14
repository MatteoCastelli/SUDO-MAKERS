<?php
/**
 * Script per aggiornare le statistiche trending
 *
 * ESECUZIONE:
 * - Via cron job ogni ora: 0 * * * * php /path/to/cron_update_trends.php
 * - Oppure chiamalo manualmente: php cron_update_trends.php
 *
 * NOTA: In ambiente di sviluppo, questo viene eseguito automaticamente
 * in trending.php ogni ora quando un utente visita la pagina
 */

require_once "Database.php";
require_once "RecommendationEngine.php";

use Proprietario\SudoMakers\Database;
use Proprietario\SudoMakers\RecommendationEngine;

// Solo per esecuzione da CLI o con token segreto
if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== 'secret_token_change_this')) {
    http_response_code(403);
    die('Accesso negato');
}

try {
    echo "[" . date('Y-m-d H:i:s') . "] Inizio aggiornamento statistiche trending...\n";

    $pdo = Database::getInstance()->getConnection();
    $engine = new RecommendationEngine($pdo);

    // Aggiorna trending
    $engine->updateTrendingStats();

    echo "[" . date('Y-m-d H:i:s') . "] Statistiche trending aggiornate con successo\n";

    // Opzionale: Pulisci vecchie interazioni (oltre 6 mesi)
    $stmt = $pdo->query("
        DELETE FROM interazione_utente 
        WHERE data_interazione < DATE_SUB(NOW(), INTERVAL 6 MONTH)
    ");
    $deleted = $stmt->rowCount();
    echo "[" . date('Y-m-d H:i:s') . "] Eliminate $deleted interazioni vecchie\n";

    // Opzionale: Pulisci vecchie cache raccomandazioni (oltre 7 giorni)
    $stmt = $pdo->query("
        DELETE FROM cache_raccomandazioni 
        WHERE data_generazione < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $deleted = $stmt->rowCount();
    echo "[" . date('Y-m-d H:i:s') . "] Eliminate $deleted cache vecchie\n";

    echo "[" . date('Y-m-d H:i:s') . "] Aggiornamento completato\n";

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRORE: " . $e->getMessage() . "\n";
    exit(1);
}