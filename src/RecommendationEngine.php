<?php

namespace Proprietario\SudoMakers;

use PDO;

class RecommendationEngine
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Traccia l'interazione dell'utente con un libro
     */
    public function trackInteraction($id_utente, $id_libro, $tipo, $fonte = null, $durata = null)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO interazione_utente 
                (id_utente, id_libro, tipo_interazione, fonte, durata_visualizzazione)
                VALUES (:id_utente, :id_libro, :tipo, :fonte, :durata)
            ");

            $stmt->execute([
                'id_utente' => $id_utente,
                'id_libro' => $id_libro,
                'tipo' => $tipo,
                'fonte' => $fonte,
                'durata' => $durata
            ]);

            return true;
        } catch (\Exception $e) {
            error_log("Errore tracking interazione: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Aggiorna il profilo di preferenze dell'utente
     */
    public function updateUserProfile($id_utente)
    {
        // Analizza ultimi 12 mesi di attività
        $stmt = $this->pdo->prepare("
            SELECT 
                l.categoria,
                COUNT(DISTINCT p.id_prestito) as prestiti,
                COUNT(DISTINCT i.id_interazione) as interazioni,
                AVG(r.voto) as voto_medio
            FROM libro l
            LEFT JOIN copia c ON l.id_libro = c.id_libro
            LEFT JOIN prestito p ON c.id_copia = p.id_copia 
                AND p.id_utente = :id_utente 
                AND p.data_prestito >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            LEFT JOIN interazione_utente i ON l.id_libro = i.id_libro 
                AND i.id_utente = :id_utente
                AND i.data_interazione >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            LEFT JOIN recensione r ON l.id_libro = r.id_libro 
                AND r.id_utente = :id_utente
            WHERE l.categoria IS NOT NULL
            GROUP BY l.categoria
            HAVING prestiti > 0 OR interazioni > 0
        ");

        $stmt->execute(['id_utente' => $id_utente]);
        $categorie_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcola score per ogni categoria
        $categorie_preferite = [];
        $total_weight = 0;

        foreach ($categorie_data as $cat) {
            $score = ($cat['prestiti'] * 3) + ($cat['interazioni'] * 0.5);
            if ($cat['voto_medio']) {
                $score *= (1 + ($cat['voto_medio'] - 3) * 0.2); // boost per voti alti
            }
            $categorie_preferite[$cat['categoria']] = round($score, 2);
            $total_weight += $score;
        }

        // Normalizza gli score
        if ($total_weight > 0) {
            foreach ($categorie_preferite as $cat => $score) {
                $categorie_preferite[$cat] = round($score / $total_weight, 3);
            }
        }

        // Analizza autori preferiti
        $stmt = $this->pdo->prepare("
            SELECT 
                CONCAT(a.nome, ' ', a.cognome) as autore,
                a.id_autore,
                COUNT(DISTINCT p.id_prestito) as prestiti,
                AVG(r.voto) as voto_medio
            FROM autore a
            JOIN libro_autore la ON a.id_autore = la.id_autore
            JOIN libro l ON la.id_libro = l.id_libro
            JOIN copia c ON l.id_libro = c.id_libro
            LEFT JOIN prestito p ON c.id_copia = p.id_copia 
                AND p.id_utente = :id_utente
                AND p.data_prestito >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            LEFT JOIN recensione r ON l.id_libro = r.id_libro 
                AND r.id_utente = :id_utente
            GROUP BY a.id_autore
            HAVING prestiti > 0
            ORDER BY prestiti DESC, voto_medio DESC
            LIMIT 10
        ");

        $stmt->execute(['id_utente' => $id_utente]);
        $autori_preferiti = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Salva nel profilo
        $stmt = $this->pdo->prepare("
            INSERT INTO profilo_preferenze 
            (id_utente, categorie_preferite, autori_preferiti)
            VALUES (:id_utente, :categorie, :autori)
            ON DUPLICATE KEY UPDATE
                categorie_preferite = :categorie,
                autori_preferiti = :autori,
                ultimo_aggiornamento = NOW()
        ");

        $stmt->execute([
            'id_utente' => $id_utente,
            'categorie' => json_encode($categorie_preferite),
            'autori' => json_encode($autori_preferiti)
        ]);

        return [
            'categorie' => $categorie_preferite,
            'autori' => $autori_preferiti
        ];
    }

    /**
     * Trova utenti con gusti simili (Collaborative Filtering)
     */
    private function findSimilarUsers($id_utente, $limit = 20)
    {
        $limit = (int)$limit; // Aggiungi questa riga

        $stmt = $this->pdo->prepare("
        WITH user_books AS (
            SELECT DISTINCT l.id_libro
            FROM prestito p
            JOIN copia c ON p.id_copia = c.id_copia
            JOIN libro l ON c.id_libro = l.id_libro
            WHERE p.id_utente = :id_utente
            AND p.data_prestito >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        )
        SELECT 
            p2.id_utente,
            COUNT(DISTINCT l2.id_libro) as libri_comuni,
            COUNT(DISTINCT p2.id_prestito) as totale_prestiti,
            (COUNT(DISTINCT l2.id_libro) * 1.0 / COUNT(DISTINCT p2.id_prestito)) as similarity_score
        FROM prestito p2
        JOIN copia c2 ON p2.id_copia = c2.id_copia
        JOIN libro l2 ON c2.id_libro = l2.id_libro
        WHERE p2.id_utente != :id_utente
        AND l2.id_libro IN (SELECT id_libro FROM user_books)
        AND p2.data_prestito >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY p2.id_utente
        HAVING libri_comuni >= 2 AND similarity_score >= 0.2
        ORDER BY similarity_score DESC, libri_comuni DESC
        LIMIT $limit
    ");

        $stmt->execute([
            'id_utente' => $id_utente
            // Rimuovi 'limit' => $limit
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Genera raccomandazioni personalizzate (Hybrid Approach)
     */
    public function generateRecommendations($id_utente, $limit = 10)
    {
        // Aggiorna il profilo utente
        $profilo = $this->updateUserProfile($id_utente);

        // Libri già letti/prenotati (da escludere)
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT l.id_libro
            FROM libro l
            JOIN copia c ON l.id_libro = c.id_libro
            LEFT JOIN prestito p ON c.id_copia = p.id_copia AND p.id_utente = :id_utente
            LEFT JOIN prenotazione pr ON l.id_libro = pr.id_libro AND pr.id_utente = :id_utente
            WHERE p.id_prestito IS NOT NULL OR pr.id_prenotazione IS NOT NULL
        ");
        $stmt->execute(['id_utente' => $id_utente]);
        $libri_esclusi = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Libri con feedback negativo (da escludere)
        $stmt = $this->pdo->prepare("
            SELECT id_libro FROM feedback_raccomandazione
            WHERE id_utente = :id_utente 
            AND feedback IN ('thumbs_down', 'not_interested')
        ");
        $stmt->execute(['id_utente' => $id_utente]);
        $libri_esclusi = array_merge($libri_esclusi, $stmt->fetchAll(PDO::FETCH_COLUMN));

        $raccomandazioni = [];

        // 1. COLLABORATIVE FILTERING (40% peso)
        $similar_users = $this->findSimilarUsers($id_utente);

        if (!empty($similar_users)) {
            $user_ids = array_column($similar_users, 'id_utente');
            $user_similarities = array_column($similar_users, 'similarity_score', 'id_utente');

            $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
            $excluded_placeholders = !empty($libri_esclusi)
                ? 'AND l.id_libro NOT IN (' . implode(',', array_fill(0, count($libri_esclusi), '?')) . ')'
                : '';

            $stmt = $this->pdo->prepare("
                SELECT 
                    l.id_libro,
                    l.titolo,
                    l.categoria,
                    GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori,
                    COUNT(DISTINCT p.id_prestito) as prestiti_simili,
                    AVG(r.voto) as rating_medio,
                    p.id_utente as prestato_da_utente
                FROM libro l
                JOIN copia c ON l.id_libro = c.id_libro
                JOIN prestito p ON c.id_copia = p.id_copia
                LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
                LEFT JOIN autore a ON la.id_autore = a.id_autore
                LEFT JOIN recensione r ON l.id_libro = r.id_libro
                WHERE p.id_utente IN ($placeholders)
                AND p.data_prestito >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                $excluded_placeholders
                GROUP BY l.id_libro
                ORDER BY prestiti_simili DESC, rating_medio DESC
                LIMIT 30
            ");

            $params = array_merge($user_ids, $libri_esclusi);
            $stmt->execute($params);
            $collaborative_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($collaborative_results as $libro) {
                $cf_score = 40 * ($libro['prestiti_simili'] / count($similar_users));

                if (!isset($raccomandazioni[$libro['id_libro']])) {
                    $raccomandazioni[$libro['id_libro']] = [
                        'libro' => $libro,
                        'score' => 0,
                        'motivi' => []
                    ];
                }

                $raccomandazioni[$libro['id_libro']]['score'] += $cf_score;
                $raccomandazioni[$libro['id_libro']]['motivi'][] =
                    "Apprezzato da utenti con gusti simili ai tuoi";
            }
        }

        // 2. CONTENT-BASED (categorie preferite) (30% peso)
        if (!empty($profilo['categorie'])) {
            foreach ($profilo['categorie'] as $categoria => $peso) {
                $excluded_placeholders = !empty($libri_esclusi)
                    ? 'AND l.id_libro NOT IN (' . implode(',', array_fill(0, count($libri_esclusi), '?')) . ')'
                    : '';

                $stmt = $this->pdo->prepare("
                    SELECT 
                        l.*,
                        GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori,
                        AVG(r.voto) as rating_medio,
                        COUNT(DISTINCT p.id_prestito) as popolarita
                    FROM libro l
                    LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
                    LEFT JOIN autore a ON la.id_autore = a.id_autore
                    LEFT JOIN recensione r ON l.id_libro = r.id_libro
                    LEFT JOIN copia c ON l.id_libro = c.id_libro
                    LEFT JOIN prestito p ON c.id_copia = p.id_copia
                        AND p.data_prestito >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    WHERE l.categoria = ?
                    $excluded_placeholders
                    GROUP BY l.id_libro
                    HAVING rating_medio IS NULL OR rating_medio >= 3
                    ORDER BY rating_medio DESC, popolarita DESC
                    LIMIT 10
                ");

                $params = array_merge([$categoria], $libri_esclusi);
                $stmt->execute($params);
                $category_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($category_results as $libro) {
                    $cb_score = 30 * $peso;

                    if (!isset($raccomandazioni[$libro['id_libro']])) {
                        $raccomandazioni[$libro['id_libro']] = [
                            'libro' => $libro,
                            'score' => 0,
                            'motivi' => []
                        ];
                    }

                    $raccomandazioni[$libro['id_libro']]['score'] += $cb_score;
                    $raccomandazioni[$libro['id_libro']]['motivi'][] =
                        "Ti piace il genere {$categoria}";
                }
            }
        }

        // 3. CLICK-BASED (categorie dei libri cliccati) (20% peso)
        $stmt = $this->pdo->prepare("
            SELECT l.categoria, COUNT(*) as clicks
            FROM interazione_utente i
            JOIN libro l ON i.id_libro = l.id_libro
            WHERE i.id_utente = :id_utente
            AND i.data_interazione >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            AND l.categoria IS NOT NULL
            GROUP BY l.categoria
            ORDER BY clicks DESC
            LIMIT 5
        ");
        $stmt->execute(['id_utente' => $id_utente]);
        $clicked_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($clicked_categories as $cat_data) {
            $categoria = $cat_data['categoria'];
            $clicks_weight = $cat_data['clicks'] / 10; // normalizza

            $excluded_placeholders = !empty($libri_esclusi)
                ? 'AND l.id_libro NOT IN (' . implode(',', array_fill(0, count($libri_esclusi), '?')) . ')'
                : '';

            $stmt = $this->pdo->prepare("
                SELECT 
                    l.*,
                    GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori,
                    AVG(r.voto) as rating_medio
                FROM libro l
                LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
                LEFT JOIN autore a ON la.id_autore = a.id_autore
                LEFT JOIN recensione r ON l.id_libro = r.id_libro
                WHERE l.categoria = ?
                $excluded_placeholders
                GROUP BY l.id_libro
                ORDER BY rating_medio DESC
                LIMIT 5
            ");

            $params = array_merge([$categoria], $libri_esclusi);
            $stmt->execute($params);
            $click_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($click_results as $libro) {
                $click_score = 20 * min($clicks_weight, 1);

                if (!isset($raccomandazioni[$libro['id_libro']])) {
                    $raccomandazioni[$libro['id_libro']] = [
                        'libro' => $libro,
                        'score' => 0,
                        'motivi' => []
                    ];
                }

                $raccomandazioni[$libro['id_libro']]['score'] += $click_score;
                $raccomandazioni[$libro['id_libro']]['motivi'][] =
                    "Hai mostrato interesse per {$categoria}";
            }
        }

        // 4. NOVITÀ E POPOLARITÀ (10% peso)
        $excluded_placeholders = !empty($libri_esclusi)
            ? 'AND l.id_libro NOT IN (' . implode(',', array_fill(0, count($libri_esclusi), '?')) . ')'
            : '';

        $stmt = $this->pdo->prepare("
            SELECT 
                l.*,
                GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori,
                AVG(r.voto) as rating_medio,
                COUNT(DISTINCT p.id_prestito) as prestiti_recenti,
                t.trend_score
            FROM libro l
            LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
            LEFT JOIN autore a ON la.id_autore = a.id_autore
            LEFT JOIN recensione r ON l.id_libro = r.id_libro
            LEFT JOIN copia c ON l.id_libro = c.id_libro
            LEFT JOIN prestito p ON c.id_copia = p.id_copia
                AND p.data_prestito >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
            LEFT JOIN trend_libri t ON l.id_libro = t.id_libro
            WHERE 1=1
            $excluded_placeholders
            GROUP BY l.id_libro
            HAVING rating_medio >= 4 OR prestiti_recenti >= 3
            ORDER BY COALESCE(t.trend_score, 0) DESC, prestiti_recenti DESC
            LIMIT 20
        ");

        $stmt->execute($libri_esclusi);
        $trending_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($trending_results as $libro) {
            $trend_score = 10;

            if (!isset($raccomandazioni[$libro['id_libro']])) {
                $raccomandazioni[$libro['id_libro']] = [
                    'libro' => $libro,
                    'score' => 0,
                    'motivi' => []
                ];
            }

            $raccomandazioni[$libro['id_libro']]['score'] += $trend_score;
            $raccomandazioni[$libro['id_libro']]['motivi'][] =
                "Molto richiesto recentemente";
        }

        // Boost per feedback positivo precedente
        $stmt = $this->pdo->prepare("
            SELECT id_libro FROM feedback_raccomandazione
            WHERE id_utente = :id_utente AND feedback = 'thumbs_up'
        ");
        $stmt->execute(['id_utente' => $id_utente]);
        $feedback_positivi = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Boost per libri simili a quelli con feedback positivo
        foreach ($feedback_positivi as $id_libro_positivo) {
            $stmt = $this->pdo->prepare("
                SELECT categoria FROM libro WHERE id_libro = ?
            ");
            $stmt->execute([$id_libro_positivo]);
            $categoria_positiva = $stmt->fetchColumn();

            if ($categoria_positiva) {
                foreach ($raccomandazioni as $id_libro => &$rec) {
                    if (isset($rec['libro']['categoria']) &&
                        $rec['libro']['categoria'] === $categoria_positiva) {
                        $rec['score'] *= 1.2; // 20% boost
                    }
                }
            }
        }

        // Ordina per score e prendi i top
        usort($raccomandazioni, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $raccomandazioni = array_slice($raccomandazioni, 0, $limit);

        // Salva in cache
        $this->saveRecommendationsToCache($id_utente, $raccomandazioni);

        return $raccomandazioni;
    }

    /**
     * Salva le raccomandazioni in cache
     */
    private function saveRecommendationsToCache($id_utente, $raccomandazioni)
    {
        // Elimina vecchie cache
        $stmt = $this->pdo->prepare("
            DELETE FROM cache_raccomandazioni 
            WHERE id_utente = :id_utente 
            AND data_generazione < DATE_SUB(NOW(), INTERVAL 1 DAY)
        ");
        $stmt->execute(['id_utente' => $id_utente]);

        // Inserisci nuove raccomandazioni
        foreach ($raccomandazioni as $rec) {
            $stmt = $this->pdo->prepare("
                INSERT INTO cache_raccomandazioni 
                (id_utente, id_libro, score, motivo_raccomandazione, algoritmo)
                VALUES (:id_utente, :id_libro, :score, :motivo, 'hybrid')
            ");

            $stmt->execute([
                'id_utente' => $id_utente,
                'id_libro' => $rec['libro']['id_libro'],
                'score' => round($rec['score'], 2),
                'motivo' => implode('; ', array_unique($rec['motivi']))
            ]);
        }
    }

    /**
     * Ottieni raccomandazioni dalla cache (se disponibili e recenti)
     */
    /**
     * Ottieni raccomandazioni dalla cache (se disponibili e recenti)
     */
    public function getCachedRecommendations($id_utente, $limit = 10)
    {
        // Cast a intero per sicurezza
        $limit = (int)$limit;

        $stmt = $this->pdo->prepare("
        SELECT 
            l.*,
            c.score,
            c.motivo_raccomandazione,
            GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori,
            AVG(r.voto) as rating_medio
        FROM cache_raccomandazioni c
        JOIN libro l ON c.id_libro = l.id_libro
        LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
        LEFT JOIN autore a ON la.id_autore = a.id_autore
        LEFT JOIN recensione r ON l.id_libro = r.id_libro
        WHERE c.id_utente = :id_utente
        AND c.data_generazione >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        GROUP BY l.id_libro, c.score, c.motivo_raccomandazione
        ORDER BY c.score DESC
        LIMIT $limit
    ");

        $stmt->execute([
            'id_utente' => $id_utente
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Salva feedback su una raccomandazione
     */
    public function saveFeedback($id_utente, $id_libro, $feedback, $motivo = null)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO feedback_raccomandazione 
            (id_utente, id_libro, feedback, motivo)
            VALUES (:id_utente, :id_libro, :feedback, :motivo)
            ON DUPLICATE KEY UPDATE
                feedback = :feedback,
                motivo = :motivo,
                data_feedback = NOW()
        ");

        return $stmt->execute([
            'id_utente' => $id_utente,
            'id_libro' => $id_libro,
            'feedback' => $feedback,
            'motivo' => $motivo
        ]);
    }

    /**
     * Libri correlati: "Chi ha letto questo ha letto anche..."
     */
    public function getBooksAlsoRead($id_libro, $limit = 6)
    {
        $limit = (int)$limit; // Aggiungi questa riga

        $stmt = $this->pdo->prepare("
        WITH book_users AS (
            SELECT DISTINCT p.id_utente
            FROM prestito p
            JOIN copia c ON p.id_copia = c.id_copia
            WHERE c.id_libro = :id_libro
            AND p.data_prestito >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        )
        SELECT 
            l.*,
            GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori,
            COUNT(DISTINCT p.id_utente) as utenti_comuni,
            (COUNT(DISTINCT p.id_utente) * 100.0 / (SELECT COUNT(*) FROM book_users)) as percentuale,
            AVG(r.voto) as rating_medio
        FROM libro l
        JOIN copia c ON l.id_libro = c.id_libro
        JOIN prestito p ON c.id_copia = p.id_copia
        LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
        LEFT JOIN autore a ON la.id_autore = a.id_autore
        LEFT JOIN recensione r ON l.id_libro = r.id_libro
        WHERE p.id_utente IN (SELECT id_utente FROM book_users)
        AND l.id_libro != :id_libro
        AND p.data_prestito >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY l.id_libro
        HAVING utenti_comuni >= 2
        ORDER BY utenti_comuni DESC, rating_medio DESC
        LIMIT $limit
    ");

        $stmt->execute([
            'id_libro' => $id_libro
            // Rimuovi 'limit' => $limit
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Aggiorna statistiche trending
     */
    /**
     * Aggiorna statistiche trending - FIXED
     * Questo metodo va SOSTITUITO in RecommendationEngine.php
     */
    public function updateTrendingStats()
    {
        echo "[" . date('Y-m-d H:i:s') . "] Inizio aggiornamento statistiche trending...\n";

        // Calcola statistiche per ogni libro
        $stmt = $this->pdo->query("
        SELECT 
            l.id_libro,
            
            -- Prestiti ultimi 7 giorni
            COUNT(DISTINCT CASE 
                WHEN p.data_prestito >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                THEN p.id_prestito 
            END) as prestiti_7d,
            
            -- Prestiti ultimi 30 giorni
            COUNT(DISTINCT CASE 
                WHEN p.data_prestito >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                THEN p.id_prestito 
            END) as prestiti_30d,
            
            -- CLICK ultimi 7 giorni (SOLO click, NON view_dettaglio)
            COUNT(DISTINCT CASE 
                WHEN i.data_interazione >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                AND i.tipo_interazione = 'click'  -- <-- CAMBIA QUESTA RIGA
                THEN i.id_interazione 
            END) as click_7d,
                        
            -- Prenotazioni attive
            COUNT(DISTINCT CASE 
                WHEN pr.stato = 'attiva' 
                THEN pr.id_prenotazione 
            END) as prenotazioni,
            
            -- Rating medio
            AVG(r.voto) as rating_medio
            
        FROM libro l
        LEFT JOIN copia c ON l.id_libro = c.id_libro
        LEFT JOIN prestito p ON c.id_copia = p.id_copia
        LEFT JOIN interazione_utente i ON l.id_libro = i.id_libro
        LEFT JOIN prenotazione pr ON l.id_libro = pr.id_libro
        LEFT JOIN recensione r ON l.id_libro = r.id_libro
        GROUP BY l.id_libro
    ");

        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $updated_count = 0;

        foreach ($stats as $stat) {
            // Calcola trend score con pesi corretti
            $time_decay = 0.7;
            $trend_score =
                ($stat['prestiti_7d'] * 10 * $time_decay) +
                ($stat['prestiti_30d'] * 3) +
                ($stat['click_7d'] * 0.5) +  // QUESTO ORA FUNZIONA!
                ($stat['prenotazioni'] * 15) +
                (($stat['rating_medio'] ?? 3) * 5);

            // Calcola velocità trend (confronto 7 vs 30 giorni)
            $velocita = 0;
            if ($stat['prestiti_30d'] > 0) {
                $velocita = (($stat['prestiti_7d'] * 4.28) / $stat['prestiti_30d']) * 100 - 100;
            }

            // Salva/aggiorna
            $stmt = $this->pdo->prepare("
            INSERT INTO trend_libri 
            (id_libro, trend_score, prestiti_ultimi_7_giorni, prestiti_ultimi_30_giorni, 
             click_ultimi_7_giorni, prenotazioni_attive, velocita_trend)
            VALUES (:id_libro, :trend_score, :p7d, :p30d, :c7d, :pren, :vel)
            ON DUPLICATE KEY UPDATE
                trend_score = :trend_score,
                prestiti_ultimi_7_giorni = :p7d,
                prestiti_ultimi_30_giorni = :p30d,
                click_ultimi_7_giorni = :c7d,
                prenotazioni_attive = :pren,
                velocita_trend = :vel,
                ultimo_aggiornamento = NOW()
        ");

            $stmt->execute([
                'id_libro' => $stat['id_libro'],
                'trend_score' => round($trend_score, 2),
                'p7d' => $stat['prestiti_7d'],
                'p30d' => $stat['prestiti_30d'],
                'c7d' => $stat['click_7d'],  // ORA CONTIENE I DATI REALI!
                'pren' => $stat['prenotazioni'],
                'vel' => round($velocita, 2)
            ]);

            $updated_count++;

            // Debug per i primi 5 libri
            if ($updated_count <= 5) {
                echo "  Libro {$stat['id_libro']}: Click 7d = {$stat['click_7d']}, Trend Score = " . round($trend_score, 2) . "\n";
            }
        }

        echo "[" . date('Y-m-d H:i:s') . "] Aggiornati $updated_count libri\n";
    }
    /**
     * Ottieni libri trending
     */
    public function getTrendingBooks($limit = 20)
    {
        $limit = (int)$limit; // Aggiungi questa riga

        $stmt = $this->pdo->prepare("
        SELECT 
            l.*,
            t.*,
            GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori,
            AVG(r.voto) as rating_medio,
            COUNT(DISTINCT c.id_copia) as totale_copie,
            SUM(CASE WHEN c.disponibile = 1 AND c.stato_fisico != 'smarrito' THEN 1 ELSE 0 END) as copie_disponibili,
            SUM(CASE WHEN c.stato_fisico = 'smarrito' THEN 1 ELSE 0 END) as copie_smarrite
        FROM trend_libri t
        JOIN libro l ON t.id_libro = l.id_libro
        LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
        LEFT JOIN autore a ON la.id_autore = a.id_autore
        LEFT JOIN recensione r ON l.id_libro = r.id_libro
        LEFT JOIN copia c ON l.id_libro = c.id_libro
        WHERE t.trend_score > 0
        GROUP BY l.id_libro
        ORDER BY t.trend_score DESC
        LIMIT $limit
    ");

        $stmt->execute(); // Nessun parametro da passare

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Ottieni raccomandazioni per nuovi utenti (fallback)
     * Quando l'utente non ha storico sufficiente
     */
    public function getNewUserRecommendations($limit = 10)
    {
        $limit = (int)$limit; // Aggiungi questa riga

        $stmt = $this->pdo->prepare("
        SELECT 
            l.*,
            GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori,
            AVG(r.voto) as rating_medio,
            COUNT(DISTINCT p.id_prestito) as popolarita,
            COUNT(DISTINCT c.id_copia) as totale_copie,
            SUM(CASE WHEN c.disponibile = 1 AND c.stato_fisico != 'smarrito' THEN 1 ELSE 0 END) as copie_disponibili,
            SUM(CASE WHEN c.stato_fisico = 'smarrito' THEN 1 ELSE 0 END) as copie_smarrite
        FROM libro l
        LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
        LEFT JOIN autore a ON la.id_autore = a.id_autore
        LEFT JOIN recensione r ON l.id_libro = r.id_libro
        LEFT JOIN copia c ON l.id_libro = c.id_libro
        LEFT JOIN prestito p ON c.id_copia = p.id_copia
            AND p.data_prestito >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY l.id_libro
        HAVING rating_medio >= 4 AND popolarita >= 5
        ORDER BY rating_medio DESC, popolarita DESC
        LIMIT $limit
    ");

        $stmt->execute(); // Nessun parametro da passare

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ottieni statistiche utente per dashboard
     */
    public function getUserStats($id_utente)
    {
        // Libri letti negli ultimi 12 mesi
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT l.id_libro) as libri_letti
            FROM prestito p
            JOIN copia c ON p.id_copia = c.id_copia
            JOIN libro l ON c.id_libro = l.id_libro
            WHERE p.id_utente = :id_utente
            AND p.data_prestito >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        ");
        $stmt->execute(['id_utente' => $id_utente]);
        $libri_letti = $stmt->fetchColumn();

        // Categoria preferita
        $stmt = $this->pdo->prepare("
            SELECT l.categoria, COUNT(*) as count
            FROM prestito p
            JOIN copia c ON p.id_copia = c.id_copia
            JOIN libro l ON c.id_libro = l.id_libro
            WHERE p.id_utente = :id_utente
            AND l.categoria IS NOT NULL
            AND p.data_prestito >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY l.categoria
            ORDER BY count DESC
            LIMIT 1
        ");
        $stmt->execute(['id_utente' => $id_utente]);
        $categoria_preferita = $stmt->fetch(PDO::FETCH_ASSOC);

        // Recensioni lasciate
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as recensioni
            FROM recensione
            WHERE id_utente = :id_utente
        ");
        $stmt->execute(['id_utente' => $id_utente]);
        $recensioni = $stmt->fetchColumn();

        // Media voti lasciati
        $stmt = $this->pdo->prepare("
            SELECT AVG(voto) as media_voti
            FROM recensione
            WHERE id_utente = :id_utente
        ");
        $stmt->execute(['id_utente' => $id_utente]);
        $media_voti = $stmt->fetchColumn();

        return [
            'libri_letti' => $libri_letti,
            'categoria_preferita' => $categoria_preferita['categoria'] ?? null,
            'count_categoria' => $categoria_preferita['count'] ?? 0,
            'recensioni_lasciate' => $recensioni,
            'media_voti' => $media_voti ? round($media_voti, 1) : null
        ];
    }

    /**
     * Pulisci vecchie interazioni (da chiamare periodicamente)
     */
    public function cleanOldInteractions($months = 6)
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM interazione_utente 
            WHERE data_interazione < DATE_SUB(NOW(), INTERVAL :months MONTH)
        ");
        $stmt->execute(['months' => $months]);
        return $stmt->rowCount();
    }

    /**
     * Pulisci vecchie cache raccomandazioni
     */
    public function cleanOldCache($days = 7)
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM cache_raccomandazioni 
            WHERE data_generazione < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->execute(['days' => $days]);
        return $stmt->rowCount();
    }

    /**
     * Ottieni raccomandazioni diversificate
     * Garantisce varietà tra categorie diverse
     */
    public function getDiversifiedRecommendations($id_utente, $limit = 12)
    {
        $recs = $this->generateRecommendations($id_utente, $limit * 2);

        // Raggruppa per categoria
        $by_category = [];
        foreach ($recs as $rec) {
            $cat = $rec['libro']['categoria'] ?? 'altro';
            if (!isset($by_category[$cat])) {
                $by_category[$cat] = [];
            }
            $by_category[$cat][] = $rec;
        }

        // Prendi massimo 2-3 libri per categoria
        $diversified = [];
        $per_category = max(2, floor($limit / count($by_category)));

        foreach ($by_category as $cat => $books) {
            $diversified = array_merge($diversified, array_slice($books, 0, $per_category));
            if (count($diversified) >= $limit) {
                break;
            }
        }

        // Se non bastano, aggiungi dai restanti
        if (count($diversified) < $limit) {
            foreach ($recs as $rec) {
                if (count($diversified) >= $limit) break;

                $found = false;
                foreach ($diversified as $d) {
                    if ($d['libro']['id_libro'] === $rec['libro']['id_libro']) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $diversified[] = $rec;
                }
            }
        }

        return array_slice($diversified, 0, $limit);
    }
}