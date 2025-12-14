<?php
/**
* Su Windows: crea un Task Scheduler che esegue questo file ogni 15 minuti
*/

use Proprietario\SudoMakers\Database;

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/Database.php";
require_once __DIR__ . "/functions.php";

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$pdo = Database::getInstance()->getConnection();

// Log delle operazioni
$log_file = __DIR__ . '/../logs/cron_prenotazioni.log';
function scriviLog($messaggio) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $messaggio\n", FILE_APPEND);
}

scriviLog("=== INIZIO ESECUZIONE CRON ===");

// ========================================
// 1. VERIFICA PRENOTAZIONI SCADUTE
// ========================================
scriviLog("1. Verifica prenotazioni scadute...");

$stmt = $pdo->query("
    SELECT p.*, l.titolo, u.email, u.nome
    FROM prenotazione p
    JOIN libro l ON p.id_libro = l.id_libro
    JOIN utente u ON p.id_utente = u.id_utente
    WHERE p.stato = 'disponibile'
    AND p.data_scadenza_ritiro < NOW()
");
$prenotazioni_scadute = $stmt->fetchAll();

foreach($prenotazioni_scadute as $pren) {
    scriviLog("  - Scaduta: Libro '{$pren['titolo']}' per utente {$pren['nome']} (ID: {$pren['id_utente']})");

    // Annulla prenotazione
    $stmt = $pdo->prepare("
        UPDATE prenotazione 
        SET stato = 'scaduta' 
        WHERE id_prenotazione = :id
    ");
    $stmt->execute(['id' => $pren['id_prenotazione']]);

    // Libera copia riservata
    $stmt = $pdo->prepare("
        UPDATE copia 
        SET disponibile = 1 
        WHERE id_libro = :id_libro 
        AND disponibile = 0
        LIMIT 1
    ");
    $stmt->execute(['id_libro' => $pren['id_libro']]);

    // Notifica utente
    $stmt = $pdo->prepare("
        INSERT INTO notifica 
        (id_utente, tipo, titolo, messaggio) 
        VALUES 
        (:id_utente, 'prenotazione', 'Prenotazione scaduta', 
         :messaggio)
    ");
    $messaggio = "La tua prenotazione per '{$pren['titolo']}' è scaduta. Il libro è stato assegnato al successivo in lista.";
    $stmt->execute([
        'id_utente' => $pren['id_utente'],
        'messaggio' => $messaggio
    ]);

    // Ricalcola posizioni in coda
    $stmt = $pdo->prepare("
        SELECT id_prenotazione 
        FROM prenotazione 
        WHERE id_libro = :id_libro 
        AND stato = 'attiva'
        ORDER BY posizione_coda ASC
    ");
    $stmt->execute(['id_libro' => $pren['id_libro']]);
    $prenotazioni_libro = $stmt->fetchAll();

    $posizione = 1;
    foreach($prenotazioni_libro as $p) {
        $stmt = $pdo->prepare("
            UPDATE prenotazione 
            SET posizione_coda = :pos 
            WHERE id_prenotazione = :id
        ");
        $stmt->execute([
            'pos' => $posizione,
            'id' => $p['id_prenotazione']
        ]);
        $posizione++;
    }

    // Assegna al prossimo
    $stmt = $pdo->prepare("
        SELECT p.*, u.email, u.nome
        FROM prenotazione p
        JOIN utente u ON p.id_utente = u.id_utente
        WHERE p.id_libro = :id_libro
        AND p.stato = 'attiva'
        ORDER BY p.posizione_coda ASC
        LIMIT 1
    ");
    $stmt->execute(['id_libro' => $pren['id_libro']]);
    $prossimo = $stmt->fetch();

    if($prossimo) {
        $data_disponibilita = date('Y-m-d H:i:s');
        $data_scadenza = date('Y-m-d H:i:s', strtotime('+48 hours'));

        $stmt = $pdo->prepare("
            UPDATE prenotazione 
            SET stato = 'disponibile',
                data_disponibilita = :data_disp,
                data_scadenza_ritiro = :data_scad
            WHERE id_prenotazione = :id
        ");
        $stmt->execute([
            'data_disp' => $data_disponibilita,
            'data_scad' => $data_scadenza,
            'id' => $prossimo['id_prenotazione']
        ]);

        // Riserva copia
        $stmt = $pdo->prepare("
            UPDATE copia 
            SET disponibile = 0 
            WHERE id_libro = :id_libro 
            AND disponibile = 1 
            AND stato_fisico != 'smarrito'
            LIMIT 1
        ");
        $stmt->execute(['id_libro' => $pren['id_libro']]);

        // Invia notifica
        sendNotificaLibroDisponibile(
            $prossimo['email'],
            $prossimo['nome'],
            $pren['titolo'],
            $data_scadenza
        );

        $stmt = $pdo->prepare("
            INSERT INTO notifica 
            (id_utente, tipo, titolo, messaggio) 
            VALUES 
            (:id_utente, 'prenotazione', 'Libro disponibile per il ritiro!', 
             :messaggio)
        ");
        $messaggio = "Il libro '{$pren['titolo']}' è disponibile! Ritiralo entro 48 ore (scadenza: " . date('d/m/Y H:i', strtotime($data_scadenza)) . ")";
        $stmt->execute([
            'id_utente' => $prossimo['id_utente'],
            'messaggio' => $messaggio
        ]);

        scriviLog("  - Assegnato a nuovo utente: {$prossimo['nome']} (ID: {$prossimo['id_utente']})");
    }
}

scriviLog("  Prenotazioni scadute gestite: " . count($prenotazioni_scadute));

// ========================================
// 2. INVIO PROMEMORIA (12 ore prima)
// ========================================
scriviLog("2. Invio promemoria...");

$stmt = $pdo->query("
    SELECT p.*, u.email, u.nome, l.titolo
    FROM prenotazione p
    JOIN utente u ON p.id_utente = u.id_utente
    JOIN libro l ON p.id_libro = l.id_libro
    WHERE p.stato = 'disponibile'
    AND p.data_scadenza_ritiro BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 13 HOUR)
    AND p.data_scadenza_ritiro > DATE_ADD(NOW(), INTERVAL 11 HOUR)
");
$promemoria = $stmt->fetchAll();

foreach($promemoria as $pren) {
    // Verifica se non è già stato inviato
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM notifica 
        WHERE id_utente = :id_utente 
        AND tipo = 'prenotazione'
        AND titolo LIKE '%Promemoria%'
        AND data_creazione > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute(['id_utente' => $pren['id_utente']]);

    if($stmt->fetchColumn() == 0) {
        sendPromemoriaRitiro(
            $pren['email'],
            $pren['nome'],
            $pren['titolo'],
            $pren['data_scadenza_ritiro']
        );

        $stmt = $pdo->prepare("
            INSERT INTO notifica 
            (id_utente, tipo, titolo, messaggio) 
            VALUES 
            (:id_utente, 'prenotazione', 'Promemoria: ritira il libro!', 
             :messaggio)
        ");
        $ore_rimaste = round((strtotime($pren['data_scadenza_ritiro']) - time()) / 3600);
        $messaggio = "PROMEMORIA: Ritira '{$pren['titolo']}' entro {$ore_rimaste} ore o la prenotazione scadrà!";
        $stmt->execute([
            'id_utente' => $pren['id_utente'],
            'messaggio' => $messaggio
        ]);

        scriviLog("  - Promemoria inviato a: {$pren['nome']} per '{$pren['titolo']}'");
    }
}

scriviLog("  Promemoria inviati: " . count($promemoria));

// ========================================
// 3. PULIZIA NOTIFICHE VECCHIE (>30 giorni)
// ========================================
scriviLog("3. Pulizia notifiche vecchie...");

$stmt = $pdo->query("
    DELETE FROM notifica 
    WHERE letta = 1 
    AND data_creazione < DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$notifiche_cancellate = $stmt->rowCount();

scriviLog("  Notifiche vecchie eliminate: $notifiche_cancellate");

scriviLog("=== FINE ESECUZIONE CRON ===\n");

echo "Esecuzione completata. Vedi log in: $log_file\n";
?>
