<?php
// Prova a scrivere un log con percorso assoluto, modalità "append" (3)
$log_file = __DIR__ . '/logs/php_error_log';

if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0777, true);
}

error_log("TEST LOG MANUALE: " . date('Y-m-d H:i:s') . "\n", 3, $log_file);

echo "Log scritto manualmente in $log_file\n";
