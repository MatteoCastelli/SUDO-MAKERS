<?php
/**
 * GESTIONE AUTOMATICA PRENOTAZIONI - SENZA CRON JOB
 *
 * Questo file viene incluso automaticamente e gestisce tutte le verifiche
 * necessarie senza bisogno di configurare cron job o task scheduler.
 *
 * Si attiva ogni volta che viene caricata una pagina del sito.
 */

use Proprietario\SudoMakers\Database;

// Evita esecuzioni multiple nella stessa sessione
if (isset($_SESSION['ultimo_check_prenotazioni'])) {
    $secondi_trascorsi = time() - $_SESSION['ultimo_check_prenotazioni'];
    // Esegui controlli solo se sono passati almeno 5 minuti (300 secondi)
    if ($secondi_trascorsi < 300) {
        return; // Salta l'esecuzione
    }
}

try {
    $pdo = Database::getInstance()->getConnection();

    // ===========================================
    // 1. ANNULLA PRENOTAZIONI SCADUTE (48 ore)
    // ===========================================
    $stmt = $pdo->query("
        SELECT p.*, l.titolo
        FROM prenotazione p
        JOIN libro l ON p.id_libro = l.id_libro
        WHERE p.stato = 'disponibile'
        AND p.data_scadenza_ritiro < NOW()
    ");
    $prenotazioni_scadute = $stmt->fetchAll();

    foreach ($prenotazioni_scadute as $pren) {
        // Annulla prenotazione
        $stmt = $pdo->prepare("
            UPDATE prenotazione 
            SET stato = 'scaduta' 
            WHERE id_prenotazione = :id
        ");
        $stmt->execute(['id' => $pren['id_prenotazione']]);

        // Libera la copia riservata
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
        foreach ($prenotazioni_libro as $p) {
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

        // Assegna al prossimo in coda
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

        if ($prossimo) {
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

            // Notifica nuovo utente
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

            // Invia email se le funzioni esistono
            if (function_exists('sendNotificaLibroDisponibile')) {
                sendNotificaLibroDisponibile(
                    $prossimo['email'],
                    $prossimo['nome'],
                    $pren['titolo'],
                    $data_scadenza
                );
            }
        }
    }

    // ===========================================
    // 2. INVIA PROMEMORIA (12 ore prima scadenza)
    // ===========================================
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

    foreach ($promemoria as $pren) {
        // Verifica se non è già stato inviato nelle ultime 24 ore
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM notifica 
            WHERE id_utente = :id_utente 
            AND tipo = 'prenotazione'
            AND titolo LIKE '%Promemoria%'
            AND data_creazione > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute(['id_utente' => $pren['id_utente']]);

        if ($stmt->fetchColumn() == 0) {
            // Invia notifica in-app
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

            // Invia email se le funzioni esistono
            if (function_exists('sendPromemoriaRitiro')) {
                sendPromemoriaRitiro(
                    $pren['email'],
                    $pren['nome'],
                    $pren['titolo'],
                    $pren['data_scadenza_ritiro']
                );
            }
        }
    }

    // ===========================================
    // 3. PULIZIA NOTIFICHE VECCHIE (>30 giorni)
    // ===========================================
    $stmt = $pdo->query("
        DELETE FROM notifica 
        WHERE letta = 1 
        AND data_creazione < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");

    // Aggiorna timestamp ultimo check
    $_SESSION['ultimo_check_prenotazioni'] = time();

} catch (Exception $e) {
    // Errore silenzioso - non blocca l'utente
    error_log("Errore gestione prenotazioni: " . $e->getMessage());
}
?>
