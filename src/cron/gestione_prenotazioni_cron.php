<?php
/**
 * CRON JOB - Da eseguire ogni ora
 * Comando: 0 * * * * php /path/to/cron/gestione_prenotazioni_cron.php
 */

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../librarian/gestione_prenotazioni_functions.php';

use Proprietario\SudoMakers\core\Database;

$pdo = Database::getInstance()->getConnection();

// Esegui controlli
scadenzaPrenotazioniNonRitirate($pdo);
inviaPromemoria($pdo);

echo "[" . date('Y-m-d H:i:s') . "] Gestione prenotazioni eseguita\n";
