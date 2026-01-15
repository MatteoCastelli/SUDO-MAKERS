<?php
/**
 * Funzioni per la gestione automatica delle prenotazioni
 */

/**
 * Assegna automaticamente un libro al primo utente in coda
 */
function assegnaLibroAlPrimoInCoda($id_libro, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM prenotazione 
            WHERE id_libro = :id_libro 
            AND stato = 'attiva'
            ORDER BY posizione_coda ASC
            LIMIT 1
        ");
        $stmt->execute(['id_libro' => $id_libro]);
        $prenotazione = $stmt->fetch();

        if (!$prenotazione) return false;

        // Trova una copia disponibile
        $stmt = $pdo->prepare("
            SELECT id_copia FROM copia 
            WHERE id_libro = :id_libro 
            AND disponibile = 1 
            AND stato_fisico != 'smarrito'
            LIMIT 1
        ");
        $stmt->execute(['id_libro' => $id_libro]);
        $copia = $stmt->fetch();

        if (!$copia) return false;

        // Aggiorna prenotazione
        $stmt = $pdo->prepare("
            UPDATE prenotazione 
            SET stato = 'disponibile',
                id_copia_assegnata = :id_copia,
                data_scadenza_ritiro = DATE_ADD(NOW(), INTERVAL 48 HOUR)
            WHERE id_prenotazione = :id_prenotazione
        ");
        $stmt->execute([
            'id_copia' => $copia['id_copia'],
            'id_prenotazione' => $prenotazione['id_prenotazione']
        ]);

        // Marca la copia come non disponibile
        $stmt = $pdo->prepare("UPDATE copia SET disponibile = 0 WHERE id_copia = :id_copia");
        $stmt->execute(['id_copia' => $copia['id_copia']]);

        // Crea notifica interna
        $stmt = $pdo->prepare("SELECT titolo FROM libro WHERE id_libro = :id");
        $stmt->execute(['id' => $id_libro]);
        $libro = $stmt->fetch();

        $stmt = $pdo->prepare("
            INSERT INTO notifica (id_utente, tipo, titolo, messaggio, data_creazione)
            VALUES (:id_utente, 'libro_disponibile', 'Libro Disponibile', :messaggio, NOW())
        ");
        $stmt->execute([
            'id_utente' => $prenotazione['id_utente'],
            'messaggio' => "Il libro '{$libro['titolo']}' è ora disponibile per il ritiro. Hai 48 ore per ritirarlo."
        ]);

        // Ricalcola posizioni
        ricalcolaPosizioniCoda($id_libro, $pdo);

        return true;

    } catch(Exception $e) {
        error_log("Errore assegnazione libro: " . $e->getMessage());
        return false;
    }
}

/**
 * Ricalcola le posizioni in coda dopo una cancellazione
 */
function ricalcolaPosizioniCoda($id_libro, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT id_prenotazione 
            FROM prenotazione 
            WHERE id_libro = :id_libro 
            AND stato = 'attiva'
            ORDER BY data_prenotazione ASC
        ");
        $stmt->execute(['id_libro' => $id_libro]);
        $prenotazioni = $stmt->fetchAll();

        $posizione = 1;
        foreach($prenotazioni as $pren) {
            $stmt = $pdo->prepare("
                UPDATE prenotazione 
                SET posizione_coda = :posizione 
                WHERE id_prenotazione = :id
            ");
            $stmt->execute([
                'posizione' => $posizione,
                'id' => $pren['id_prenotazione']
            ]);
            $posizione++;
        }

        return true;

    } catch(Exception $e) {
        error_log("Errore ricalcolo posizioni: " . $e->getMessage());
        return false;
    }
}

/**
 * Scade prenotazioni non ritirate
 */
function scadenzaPrenotazioniNonRitirate($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT p.*, c.id_copia
            FROM prenotazione p
            LEFT JOIN copia c ON p.id_copia_assegnata = c.id_copia
            WHERE p.stato = 'disponibile'
            AND p.data_scadenza_ritiro < NOW()
        ");
        $prenotazioni_scadute = $stmt->fetchAll();

        foreach($prenotazioni_scadute as $pren) {
            $stmt = $pdo->prepare("UPDATE prenotazione SET stato = 'scaduta' WHERE id_prenotazione = :id");
            $stmt->execute(['id' => $pren['id_prenotazione']]);

            if ($pren['id_copia']) {
                $stmt = $pdo->prepare("UPDATE copia SET disponibile = 1 WHERE id_copia = :id");
                $stmt->execute(['id' => $pren['id_copia']]);

                // Assegna al prossimo in coda
                assegnaLibroAlPrimoInCoda($pren['id_libro'], $pdo);
            }

            // Notifica utente
            $stmt = $pdo->prepare("
                INSERT INTO notifica (id_utente, tipo, titolo, messaggio, data_creazione)
                VALUES (:id_utente, 'prenotazione_scaduta', 'Prenotazione Scaduta', :messaggio, NOW())
            ");
            $stmt->execute([
                'id_utente' => $pren['id_utente'],
                'messaggio' => "La tua prenotazione è scaduta perché non hai ritirato il libro entro 48 ore."
            ]);
        }

        return count($prenotazioni_scadute);

    } catch(Exception $e) {
        error_log("Errore scadenza prenotazioni: " . $e->getMessage());
        return false;
    }
}

/**
 * Invia promemoria agli utenti con prenotazioni disponibili in scadenza entro 12 ore
 */
function inviaPromemoria($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT p.id_prenotazione, p.data_scadenza_ritiro,
                   u.id_utente, u.email,
                   l.titolo
            FROM prenotazione p
            JOIN utente u ON p.id_utente = u.id_utente
            JOIN libro l ON p.id_libro = l.id_libro
            WHERE p.stato = 'disponibile'
            AND p.data_scadenza_ritiro BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 12 HOUR)
        ");
        $prenotazioni = $stmt->fetchAll();

        foreach ($prenotazioni as $pren) {
            $stmt = $pdo->prepare("
                INSERT INTO notifica (id_utente, tipo, titolo, messaggio, data_creazione)
                VALUES (:id_utente, 'promemoria_ritiro', 'Promemoria Ritiro Libro', :msg, NOW())
            ");
            $stmt->execute([
                'id_utente' => $pren['id_utente'],
                'msg' => "Promemoria: il libro '{$pren['titolo']}' deve essere ritirato entro poche ore."
            ]);
        }

        return count($prenotazioni);

    } catch(Exception $e) {
        error_log("Errore invio promemoria: " . $e->getMessage());
        return false;
    }
}
