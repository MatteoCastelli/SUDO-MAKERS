<?php
/**
 * CRON JOB NOTIFICHE AUTOMATICHE
 *
 * Eseguire ogni ora: 0 * * * * php /path/to/cron_notifiche.php
 *
 * Gestisce:
 * - Promemoria scadenza prestiti (3 giorni prima)
 * - Notifiche ritardo con escalation
 * - Promemoria prenotazioni (12 ore prima scadenza)
 * - Invio notifiche ritardate (dopo quiet hours)
 */

use Proprietario\SudoMakers\core\Database;
use Proprietario\SudoMakers\core\NotificationEngine;

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/NotificationEngine.php';

// Log file
$log_file = __DIR__ . '/../logs/cron_notifiche.log';

function scriviLog($messaggio) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_dir = dirname($log_file);

    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }

    file_put_contents($log_file, "[$timestamp] $messaggio\n", FILE_APPEND);
}

scriviLog("=== INIZIO CRON NOTIFICHE ===");

try {
    $pdo = Database::getInstance()->getConnection();
    $notifier = new NotificationEngine($pdo);

    // ========================================
    // 1. PROMEMORIA SCADENZA PRESTITI (3 giorni prima)
    // ========================================
    scriviLog("1. Controllo promemoria scadenza prestiti...");

    $stmt = $pdo->query("
        SELECT p.*, u.id_utente, u.nome, u.email, l.titolo, l.immagine_copertina_url
        FROM prestito p
        JOIN utente u ON p.id_utente = u.id_utente
        JOIN copia c ON p.id_copia = c.id_copia
        JOIN libro l ON c.id_libro = l.id_libro
        WHERE p.data_restituzione_effettiva IS NULL
        AND p.data_scadenza BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
        AND NOT EXISTS (
            SELECT 1 FROM notifica n
            WHERE n.id_utente = u.id_utente
            AND n.tipo = 'scadenza_promemoria'
            AND DATE(n.data_creazione) = CURDATE()
            AND JSON_EXTRACT(n.dati_extra, '$.id_prestito') = p.id_prestito
        )
    ");

    $prestiti_in_scadenza = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count_promemoria = 0;

    foreach ($prestiti_in_scadenza as $prestito) {
        $giorni_rimasti = floor((strtotime($prestito['data_scadenza']) - time()) / 86400);

        $notifier->creaNotifica(
            $prestito['id_utente'],
            NotificationEngine::TYPE_SCADENZA_PROMEMORIA,
            "Promemoria: Restituzione libro tra {$giorni_rimasti} giorni",
            "Il libro '{$prestito['titolo']}' deve essere restituito entro il " .
            date('d/m/Y', strtotime($prestito['data_scadenza'])),
            NotificationEngine::PRIORITY_MEDIUM,
            true,
            [
                'id_prestito' => $prestito['id_prestito'],
                'titolo_libro' => $prestito['titolo'],
                'data_scadenza' => $prestito['data_scadenza'],
                'immagine_copertina' => $prestito['immagine_copertina_url']
            ]
        );

        $count_promemoria++;
    }

    scriviLog("  Promemoria inviati: $count_promemoria");

    // ========================================
    // 2. GESTIONE RITARDI CON ESCALATION
    // ========================================
    scriviLog("2. Controllo ritardi e escalation...");

    $stmt = $pdo->query("
        SELECT p.*, u.id_utente, u.nome, u.email, l.titolo,
               DATEDIFF(NOW(), p.data_scadenza) as giorni_ritardo
        FROM prestito p
        JOIN utente u ON p.id_utente = u.id_utente
        JOIN copia c ON p.id_copia = c.id_copia
        JOIN libro l ON c.id_libro = l.id_libro
        WHERE p.data_restituzione_effettiva IS NULL
        AND p.data_scadenza < NOW()
    ");

    $prestiti_in_ritardo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count_ritardi = [
        'lieve' => 0,
        'medio' => 0,
        'grave' => 0,
        'critico' => 0
    ];

    foreach ($prestiti_in_ritardo as $prestito) {
        $giorni_ritardo = $prestito['giorni_ritardo'];
        $multa = $giorni_ritardo * 0.50;

        // Determina livello ritardo e tipo notifica
        if ($giorni_ritardo >= 1 && $giorni_ritardo <= 3) {
            $tipo = NotificationEngine::TYPE_RITARDO_LIEVE;
            $livello = 'lieve';
            $frequenza_giorni = 1; // Ogni giorno
        } elseif ($giorni_ritardo >= 4 && $giorni_ritardo <= 7) {
            $tipo = NotificationEngine::TYPE_RITARDO_MEDIO;
            $livello = 'medio';
            $frequenza_giorni = 2; // Ogni 2 giorni
        } elseif ($giorni_ritardo >= 8 && $giorni_ritardo <= 14) {
            $tipo = NotificationEngine::TYPE_RITARDO_GRAVE;
            $livello = 'grave';
            $frequenza_giorni = 3; // Ogni 3 giorni
        } else { // > 14 giorni
            $tipo = NotificationEngine::TYPE_RITARDO_CRITICO;
            $livello = 'critico';
            $frequenza_giorni = 7; // Ogni settimana
        }

        // Verifica se è il momento di inviare (in base alla frequenza)
        $stmt_check = $pdo->prepare("
            SELECT MAX(data_creazione) as ultima_notifica
            FROM notifica
            WHERE id_utente = :id_utente
            AND tipo LIKE 'ritardo_%'
            AND JSON_EXTRACT(dati_extra, '$.id_prestito') = :id_prestito
        ");
        $stmt_check->execute([
            'id_utente' => $prestito['id_utente'],
            'id_prestito' => $prestito['id_prestito']
        ]);
        $check = $stmt_check->fetch(PDO::FETCH_ASSOC);

        $deve_inviare = false;

        if (!$check['ultima_notifica']) {
            $deve_inviare = true; // Prima notifica
        } else {
            $giorni_da_ultima = floor((time() - strtotime($check['ultima_notifica'])) / 86400);
            if ($giorni_da_ultima >= $frequenza_giorni) {
                $deve_inviare = true;
            }
        }

        if ($deve_inviare) {
            // Blocca nuovi prestiti se non già bloccato
            $stmt_blocco = $pdo->prepare("
                UPDATE utente 
                SET prestiti_bloccati = 1 
                WHERE id_utente = :id 
                AND prestiti_bloccati = 0
            ");
            $stmt_blocco->execute(['id' => $prestito['id_utente']]);

            // Registra multa se non esiste
            $stmt_multa = $pdo->prepare("
                INSERT INTO multa (id_prestito, importo, stato)
                VALUES (:id_prestito, :importo, 'non_pagata')
                ON DUPLICATE KEY UPDATE
                importo = :importo
            ");
            $stmt_multa->execute([
                'id_prestito' => $prestito['id_prestito'],
                'importo' => $multa
            ]);

            // Crea notifica
            $priorita = ($tipo === NotificationEngine::TYPE_RITARDO_CRITICO)
                ? NotificationEngine::PRIORITY_URGENT
                : NotificationEngine::PRIORITY_HIGH;

            $notifier->creaNotifica(
                $prestito['id_utente'],
                $tipo,
                "Ritardo restituzione libro - {$giorni_ritardo} giorni",
                "Il libro '{$prestito['titolo']}' è in ritardo di {$giorni_ritardo} giorni. Multa: €" . number_format($multa, 2),
                $priorita,
                true,
                [
                    'id_prestito' => $prestito['id_prestito'],
                    'titolo_libro' => $prestito['titolo'],
                    'giorni_ritardo' => $giorni_ritardo,
                    'multa_attuale' => $multa
                ]
            );

            $count_ritardi[$livello]++;

            // Se critico, segnala al bibliotecario
            if ($tipo === NotificationEngine::TYPE_RITARDO_CRITICO) {
                $stmt_biblio = $pdo->query("
                    SELECT u.id_utente 
                    FROM utente u
                    JOIN utente_ruolo ur ON u.id_utente = ur.id_utente
                    JOIN ruolo r ON ur.id_ruolo = r.id_ruolo
                    WHERE r.nome_ruolo IN ('bibliotecario', 'amministratore')
                ");

                foreach ($stmt_biblio->fetchAll(PDO::FETCH_COLUMN) as $id_biblio) {
                    $notifier->creaNotifica(
                        $id_biblio,
                        'segnalazione_ritardo_critico',
                        "Ritardo critico - Intervento necessario",
                        "L'utente {$prestito['nome']} ha un prestito in ritardo di {$giorni_ritardo} giorni ('{$prestito['titolo']}')",
                        NotificationEngine::PRIORITY_HIGH,
                        true,
                        [
                            'id_prestito' => $prestito['id_prestito'],
                            'id_utente_problema' => $prestito['id_utente']
                        ]
                    );
                }
            }
        }
    }

    scriviLog("  Ritardi lieve: {$count_ritardi['lieve']}");
    scriviLog("  Ritardi medio: {$count_ritardi['medio']}");
    scriviLog("  Ritardi grave: {$count_ritardi['grave']}");
    scriviLog("  Ritardi critico: {$count_ritardi['critico']}");

    // ========================================
    // 3. PROMEMORIA PRENOTAZIONI (12 ore prima)
    // ========================================
    scriviLog("3. Controllo promemoria prenotazioni...");

    $stmt = $pdo->query("
        SELECT p.*, u.id_utente, u.nome, u.email, l.titolo
        FROM prenotazione p
        JOIN utente u ON p.id_utente = u.id_utente
        JOIN libro l ON p.id_libro = l.id_libro
        WHERE p.stato = 'disponibile'
        AND p.data_scadenza_ritiro BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 13 HOUR)
        AND p.data_scadenza_ritiro > DATE_ADD(NOW(), INTERVAL 11 HOUR)
        AND NOT EXISTS (
            SELECT 1 FROM notifica n
            WHERE n.id_utente = u.id_utente
            AND n.tipo = 'prenotazione_promemoria'
            AND DATE(n.data_creazione) = CURDATE()
            AND JSON_EXTRACT(n.dati_extra, '$.id_prenotazione') = p.id_prenotazione
        )
    ");

    $prenotazioni_promemoria = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count_pren_promemoria = 0;

    foreach ($prenotazioni_promemoria as $pren) {
        $ore_rimaste = floor((strtotime($pren['data_scadenza_ritiro']) - time()) / 3600);

        $notifier->creaNotifica(
            $pren['id_utente'],
            NotificationEngine::TYPE_PRENOTAZIONE_PROMEMORIA,
            "Promemoria: Ritira il tuo libro!",
            "Hai ancora {$ore_rimaste} ore per ritirare '{$pren['titolo']}' o la prenotazione scadrà!",
            NotificationEngine::PRIORITY_HIGH,
            true,
            [
                'id_prenotazione' => $pren['id_prenotazione'],
                'titolo_libro' => $pren['titolo'],
                'data_scadenza_ritiro' => $pren['data_scadenza_ritiro']
            ]
        );

        $count_pren_promemoria++;
    }

    scriviLog("  Promemoria prenotazioni: $count_pren_promemoria");

    // ========================================
    // 4. PROCESSA NOTIFICHE RITARDATE (quiet hours finite)
    // ========================================
    scriviLog("4. Processamento notifiche ritardate...");

    $notifier->processaNotificheRitardate();

    scriviLog("  Notifiche ritardate processate");

    // ========================================
    // 5. PULIZIA NOTIFICHE VECCHIE (>90 giorni lette)
    // ========================================
    scriviLog("5. Pulizia notifiche vecchie...");

    $stmt = $pdo->query("
        DELETE FROM notifica
        WHERE letta = 1
        AND data_creazione < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $notifiche_cancellate = $stmt->rowCount();

    scriviLog("  Notifiche vecchie eliminate: $notifiche_cancellate");

    scriviLog("=== FINE CRON NOTIFICHE ===\n");

} catch (Exception $e) {
    scriviLog("ERRORE: " . $e->getMessage());
    scriviLog($e->getTraceAsString());
    exit(1);
}