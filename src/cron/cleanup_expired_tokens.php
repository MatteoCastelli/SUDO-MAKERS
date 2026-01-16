<?php
/**
 * Script per pulire i token di reset password scaduti o già utilizzati
 * Da eseguire periodicamente tramite cron job (es: ogni ora)
 * 
 * Esempio crontab:
 * 0 * * * * php /path/to/cleanup_expired_tokens.php
 */

require_once __DIR__ . '/../core/Database.php';

use Proprietario\SudoMakers\core\Database;

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Elimina token scaduti o già utilizzati
    $stmt = $pdo->prepare("
        DELETE FROM password_reset_tokens 
        WHERE expires_at < NOW() OR used = 1
    ");
    
    $stmt->execute();
    $deleted = $stmt->rowCount();
    
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] Pulizia token completata: $deleted token rimossi\n";
    
    // Log opzionale
    if ($deleted > 0) {
        error_log("Password reset tokens cleanup: $deleted tokens removed at $timestamp");
    }
    
} catch (Exception $e) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] ERRORE durante pulizia token: " . $e->getMessage() . "\n";
    error_log("Password reset tokens cleanup error: " . $e->getMessage());
}
