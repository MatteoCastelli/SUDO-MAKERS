<?php
/**
 * Esecutore cron - Backend per trigger_cron.php
 */

$type = $_GET['type'] ?? '';
$output = '';

switch($type) {
    case 'notifiche':
        ob_start();
        include __DIR__ . '/cron_notifiche.php';
        $output = ob_get_clean();
        
        // Leggi anche il log file
        $log_file = __DIR__ . '/../logs/cron_notifiche.log';
        if(file_exists($log_file)) {
            $log_content = file_get_contents($log_file);
            $log_lines = array_slice(explode("\n", $log_content), -50); // Ultime 50 righe
            $output .= "\n\n<strong>Log recente:</strong>\n" . implode("\n", $log_lines);
        }
        break;

    case 'prenotazioni':
        ob_start();
        include __DIR__ . '/cron_prenotazioni.php';
        $output = ob_get_clean();
        break;

    case 'trends':
        ob_start();
        include __DIR__ . '/cron_update_trends.php';
        $output = ob_get_clean();
        break;

    case 'cleanup':
        ob_start();
        include __DIR__ . '/cleanup_expired_tokens.php';
        $output = ob_get_clean();
        break;

    default:
        $output = '<span class="error">Tipo cron non valido</span>';
}

// Formatta output
$output = htmlspecialchars($output);
$output = str_replace('[SUCCESS]', '<span class="success">[SUCCESS]</span>', $output);
$output = str_replace('[ERROR]', '<span class="error">[ERROR]</span>', $output);
$output = str_replace('=== INIZIO', '<strong>=== INIZIO', $output);
$output = str_replace('=== FINE', '=== FINE</strong>', $output);
$output = nl2br($output);

echo $output;
