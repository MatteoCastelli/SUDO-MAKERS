<?php

/**
 * Riconosce automaticamente il tipo di codice scansionato
 *
 * Tipi supportati:
 * - EAN-13 (13 cifre): ISBN libro
 * - LIBxxxxxx: Codice copia specifica
 * - USERxxxxxx: Tessera utente
 *
 * @param string $codice Il codice scansionato
 * @return array ['tipo' => string, 'valore' => string, 'descrizione' => string]
 */
function riconosciTipoCodice($codice) {
    $codice = trim($codice);

    // EAN-13 o ISBN-13 (13 cifre numeriche)
    if (preg_match('/^[0-9]{13}$/', $codice)) {
        return [
            'tipo' => 'isbn',
            'valore' => $codice,
            'descrizione' => 'ISBN/EAN-13 (Libro da catalogare o cercare)'
        ];
    }

    // ISBN-10 (10 cifre, possibile X finale)
    if (preg_match('/^[0-9]{9}[0-9X]$/i', $codice)) {
        return [
            'tipo' => 'isbn',
            'valore' => $codice,
            'descrizione' => 'ISBN-10 (Libro da catalogare o cercare)'
        ];
    }

    // Codice copia biblioteca (formato: LIBxxxxxx...)
    if (preg_match('/^LIB[0-9]+$/i', $codice)) {
        return [
            'tipo' => 'copia',
            'valore' => strtoupper($codice),
            'descrizione' => 'Codice copia specifica (Prestito/Restituzione)'
        ];
    }

    // Tessera utente (formato: USERxxxxxx)
    if (preg_match('/^USER[0-9]+$/i', $codice)) {
        return [
            'tipo' => 'tessera',
            'valore' => strtoupper($codice),
            'descrizione' => 'Tessera utente (Identificazione utente)'
        ];
    }

    // Codice generico numerico (potrebbe essere ID libro)
    if (preg_match('/^[0-9]+$/', $codice) && strlen($codice) < 13) {
        return [
            'tipo' => 'id_numerico',
            'valore' => $codice,
            'descrizione' => 'ID numerico (Potrebbe essere ID libro)'
        ];
    }

    // Codice sconosciuto
    return [
        'tipo' => 'sconosciuto',
        'valore' => $codice,
        'descrizione' => 'Tipo di codice non riconosciuto'
    ];
}

/**
 * Processa il codice e determina l'azione da compiere
 *
 * @param string $codice Il codice scansionato
 * @param PDO $pdo Connessione database
 * @return array ['success' => bool, 'action' => string, 'redirect' => string, 'message' => string, 'data' => array]
 */
function processaCodice($codice, $pdo) {
    $info = riconosciTipoCodice($codice);

    switch ($info['tipo']) {
        case 'isbn':
            // Cerca se il libro esiste già
            $stmt = $pdo->prepare("
                SELECT id_libro, titolo 
                FROM libro 
                WHERE isbn = :isbn OR ean = :ean
                LIMIT 1
            ");
            $stmt->execute([
                'isbn' => $info['valore'],
                'ean' => $info['valore']
            ]);
            $libro = $stmt->fetch();

            if ($libro) {
                // Libro già catalogato → vai al dettaglio
                return [
                    'success' => true,
                    'action' => 'libro_esistente',
                    'redirect' => "../catalog/dettaglio_libro.php?id=" . $libro['id_libro'],
                    'message' => "Libro trovato: " . $libro['titolo'],
                    'data' => $libro
                ];
            } else {
                // Libro non catalogato → vai a catalogazione
                return [
                    'success' => true,
                    'action' => 'catalogazione',
                    'redirect' => "../librarian/cataloga_libro.php?isbn=" . $info['valore'],
                    'message' => "Libro non presente. Procedi con la catalogazione.",
                    'data' => ['isbn' => $info['valore']]
                ];
            }

        case 'copia':
            // Cerca la copia specifica
            $stmt = $pdo->prepare("
                SELECT c.*, l.titolo, l.id_libro
                FROM copia c
                JOIN libro l ON c.id_libro = l.id_libro
                WHERE c.codice_barcode = :codice
                LIMIT 1
            ");
            $stmt->execute(['codice' => $info['valore']]);
            $copia = $stmt->fetch();

            if ($copia) {
                // Controlla se è in prestito
                $stmt = $pdo->prepare("
                    SELECT p.*, u.nome, u.cognome
                    FROM prestito p
                    JOIN utente u ON p.id_utente = u.id_utente
                    WHERE p.id_copia = :id_copia
                    AND p.data_restituzione_effettiva IS NULL
                    LIMIT 1
                ");
                $stmt->execute(['id_copia' => $copia['id_copia']]);
                $prestito = $stmt->fetch();

                if ($prestito) {
                    // In prestito → vai a restituzione
                    return [
                        'success' => true,
                        'action' => 'restituzione',
                        'redirect' => "../librarian/restituzione_rapida.php?codice=" . $info['valore'],
                        'message' => "Copia in prestito a " . $prestito['nome'] . " " . $prestito['cognome'],
                        'data' => ['copia' => $copia, 'prestito' => $prestito]
                    ];
                } else {
                    // Disponibile → vai a prestito
                    return [
                        'success' => true,
                        'action' => 'prestito',
                        'redirect' => "../librarian/prestito_rapido.php?copia=" . $info['valore'],
                        'message' => "Copia disponibile: " . $copia['titolo'],
                        'data' => ['copia' => $copia]
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'action' => 'errore',
                    'redirect' => null,
                    'message' => "Copia non trovata nel database",
                    'data' => null
                ];
            }

        case 'tessera':
            // Cerca utente
            $stmt = $pdo->prepare("
                SELECT id_utente, nome, cognome, email, codice_tessera
                FROM utente
                WHERE codice_tessera = :codice
                LIMIT 1
            ");
            $stmt->execute(['codice' => $info['valore']]);
            $utente = $stmt->fetch();

            if ($utente) {
                // ✅ CORREZIONE: Mostra pagina info utente per bibliotecario
                // NON il profilo personale
                return [
                    'success' => true,
                    'action' => 'utente',
                    'redirect' => "../librarian/info_utente.php?id=" . $utente['id_utente'],
                    'message' => "Utente: " . $utente['nome'] . " " . $utente['cognome'],
                    'data' => $utente
                ];
            } else {
                return [
                    'success' => false,
                    'action' => 'errore',
                    'redirect' => null,
                    'message' => "Tessera non valida",
                    'data' => null
                ];
            }

        case 'id_numerico':
            // Prova come ID libro
            $stmt = $pdo->prepare("
                SELECT id_libro, titolo 
                FROM libro 
                WHERE id_libro = :id
                LIMIT 1
            ");
            $stmt->execute(['id' => $info['valore']]);
            $libro = $stmt->fetch();

            if ($libro) {
                return [
                    'success' => true,
                    'action' => 'libro_esistente',
                    'redirect' => "../catalog/dettaglio_libro.php?id=" . $libro['id_libro'],
                    'message' => "Libro trovato: " . $libro['titolo'],
                    'data' => $libro
                ];
            } else {
                return [
                    'success' => false,
                    'action' => 'errore',
                    'redirect' => null,
                    'message' => "Nessun libro trovato con ID: " . $info['valore'],
                    'data' => null
                ];
            }

        default:
            return [
                'success' => false,
                'action' => 'errore',
                'redirect' => null,
                'message' => "Tipo di codice non riconosciuto: " . $codice,
                'data' => null
            ];
    }
}