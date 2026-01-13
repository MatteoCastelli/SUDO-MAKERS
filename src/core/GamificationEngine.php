<?php

namespace Proprietario\SudoMakers\core;

use PDO;

class GamificationEngine {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Controlla e assegna badge dopo un'azione
     */
    public function checkAndAwardBadges(int $id_utente, string $tipo_azione, array $context = []): array {
        $awarded = [];

        switch($tipo_azione) {
            case 'prestito_completato':
                $awarded = array_merge($awarded, $this->checkLettureBadges($id_utente));
                break;
            case 'recensione_pubblicata':
                $awarded = array_merge($awarded, $this->checkRecensioniBadges($id_utente));
                break;
            case 'restituzione_anticipata':
                $awarded = array_merge($awarded, $this->checkVelocitaBadges($id_utente));
                break;
            case 'genere_esplorato':
                $awarded = array_merge($awarded, $this->checkGenereBadges($id_utente, $context['categoria'] ?? null));
                break;
        }

        // Assegna XP e aggiorna livello
        foreach($awarded as $badge) {
            $this->assignXP($id_utente, $badge['punti_esperienza'], 'badge', $badge['id_badge']);
        }

        return $awarded;
    }

    /**
     * Badge letture (Pagina Uno, Esploratore, Maratoneta, ecc.)
     */
    private function checkLettureBadges(int $id_utente): array {
        $awarded = [];

        // Conta libri letti (prestiti restituiti)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT c.id_libro) as libri_letti
            FROM prestito p
            JOIN copia c ON p.id_copia = c.id_copia
            WHERE p.id_utente = :id_utente 
            AND p.data_restituzione_effettiva IS NOT NULL
        ");
        $stmt->execute(['id_utente' => $id_utente]);
        $count = $stmt->fetchColumn();

        // Badge per numero letture
        $badge_letture = [
            1 => 1,   // Pagina Uno
            5 => 7,   // Lettore Curioso
            10 => 2,  // Esploratore
            25 => 8,  // Divoratore
            50 => 9,  // Bibliofilo
            100 => 3  // Maratoneta
        ];

        foreach($badge_letture as $soglia => $id_badge) {
            if($count >= $soglia) {
                $awarded_badge = $this->awardBadge($id_utente, $id_badge);
                if($awarded_badge) {
                    $awarded[] = $awarded_badge;
                }
            }
        }

        return $awarded;
    }

    /**
     * Badge per genere
     */
    private function checkGenereBadges(int $id_utente, ?string $categoria): array {
        $awarded = [];

        if(!$categoria) return $awarded;

        // Conta libri letti per categoria
        $stmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT l.id_libro) as count
            FROM prestito p
            JOIN copia c ON p.id_copia = c.id_copia
            JOIN libro l ON c.id_libro = l.id_libro
            WHERE p.id_utente = :id_utente 
            AND l.categoria = :categoria
            AND p.data_restituzione_effettiva IS NOT NULL
        ");
        $stmt->execute(['id_utente' => $id_utente, 'categoria' => $categoria]);
        $count = $stmt->fetchColumn();

        // Mapping categoria -> id_badge
        $badge_generi = [
            'Giallo' => 4,
            'Fantasy' => 5,
            'Fiction' => 10,
            'Health & Fitness' => 11
        ];

        if(isset($badge_generi[$categoria]) && $count >= 5) {
            $awarded_badge = $this->awardBadge($id_utente, $badge_generi[$categoria]);
            if($awarded_badge) {
                $awarded[] = $awarded_badge;
            }
        }

        return $awarded;
    }

    /**
     * Badge recensioni
     */
    private function checkRecensioniBadges(int $id_utente): array {
        $awarded = [];

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM recensione 
            WHERE id_utente = :id_utente
        ");
        $stmt->execute(['id_utente' => $id_utente]);
        $count = $stmt->fetchColumn();

        $badge_recensioni = [
            1 => 16,  // Critico Novizio
            10 => 17  // Critico Esperto
        ];

        foreach($badge_recensioni as $soglia => $id_badge) {
            if($count >= $soglia) {
                $awarded_badge = $this->awardBadge($id_utente, $id_badge);
                if($awarded_badge) {
                    $awarded[] = $awarded_badge;
                }
            }
        }

        return $awarded;
    }

    /**
     * Badge velocità restituzione
     */
    private function checkVelocitaBadges(int $id_utente): array {
        $awarded = [];

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM prestito
            WHERE id_utente = :id_utente
            AND data_restituzione_effettiva IS NOT NULL
            AND data_restituzione_effettiva < data_scadenza
        ");
        $stmt->execute(['id_utente' => $id_utente]);
        $count = $stmt->fetchColumn();

        $badge_velocita = [
            5 => 6,   // Riconsegna Lampo
            10 => 12  // Puntuale
        ];

        foreach($badge_velocita as $soglia => $id_badge) {
            if($count >= $soglia) {
                $awarded_badge = $this->awardBadge($id_utente, $id_badge);
                if($awarded_badge) {
                    $awarded[] = $awarded_badge;
                }
            }
        }

        return $awarded;
    }

    /**
     * Assegna badge se non già posseduto
     */
    private function awardBadge(int $id_utente, int $id_badge): ?array {
        // Verifica se già possiede il badge
        $stmt = $this->pdo->prepare("
            SELECT id_utente_badge 
            FROM utente_badge 
            WHERE id_utente = :id_utente AND id_badge = :id_badge
        ");
        $stmt->execute(['id_utente' => $id_utente, 'id_badge' => $id_badge]);

        if($stmt->fetch()) {
            return null; // Già posseduto
        }

        // Recupera info badge
        $stmt = $this->pdo->prepare("
            SELECT * FROM badge WHERE id_badge = :id_badge AND attivo = 1
        ");
        $stmt->execute(['id_badge' => $id_badge]);
        $badge = $stmt->fetch();

        if(!$badge) return null;

        // Assegna badge
        $stmt = $this->pdo->prepare("
            INSERT INTO utente_badge (id_utente, id_badge, data_ottenimento, notificato)
            VALUES (:id_utente, :id_badge, NOW(), 0)
        ");
        $stmt->execute(['id_utente' => $id_utente, 'id_badge' => $id_badge]);

        // Crea notifica
        $this->createNotification(
            $id_utente,
            'badge',
            'Nuovo Badge Sbloccato! 🏆',
            "Hai ottenuto il badge '{$badge['nome']}': {$badge['descrizione']}"
        );

        return $badge;
    }

    /**
     * Assegna XP e aggiorna livello
     */
    public function assignXP(int $id_utente, int $punti, string $motivo, int $riferimento_id = null): void {
        // Recupera livello attuale
        $stmt = $this->pdo->prepare("
            SELECT * FROM livello_utente WHERE id_utente = :id_utente
        ");
        $stmt->execute(['id_utente' => $id_utente]);
        $livello = $stmt->fetch();

        if(!$livello) {
            // Crea record livello se non esiste
            $stmt = $this->pdo->prepare("
                INSERT INTO livello_utente (id_utente, livello, esperienza_totale, esperienza_livello_corrente, esperienza_prossimo_livello, titolo)
                VALUES (:id_utente, 1, 0, 0, 100, 'Lettore Novizio')
            ");
            $stmt->execute(['id_utente' => $id_utente]);

            $stmt = $this->pdo->prepare("SELECT * FROM livello_utente WHERE id_utente = :id_utente");
            $stmt->execute(['id_utente' => $id_utente]);
            $livello = $stmt->fetch();
        }

        // Aggiungi XP
        $nuova_exp_totale = $livello['esperienza_totale'] + $punti;
        $nuova_exp_corrente = $livello['esperienza_livello_corrente'] + $punti;

        // Salva nello storico
        $stmt = $this->pdo->prepare("
            INSERT INTO storico_xp (id_utente, punti_guadagnati, motivo, riferimento_tipo, riferimento_id)
            VALUES (:id_utente, :punti, :motivo, 'badge', :riferimento_id)
        ");
        $stmt->execute([
            'id_utente' => $id_utente,
            'punti' => $punti,
            'motivo' => $motivo,
            'riferimento_id' => $riferimento_id
        ]);

        // Check level up
        if($nuova_exp_corrente >= $livello['esperienza_prossimo_livello']) {
            $nuovo_livello = $livello['livello'] + 1;
            $exp_overflow = $nuova_exp_corrente - $livello['esperienza_prossimo_livello'];
            $prossimo_livello_xp = $this->calculateNextLevelXP($nuovo_livello);
            $nuovo_titolo = $this->getTitleForLevel($nuovo_livello);

            $stmt = $this->pdo->prepare("
                UPDATE livello_utente 
                SET livello = :livello,
                    esperienza_totale = :exp_totale,
                    esperienza_livello_corrente = :exp_corrente,
                    esperienza_prossimo_livello = :exp_prossimo,
                    titolo = :titolo
                WHERE id_utente = :id_utente
            ");
            $stmt->execute([
                'livello' => $nuovo_livello,
                'exp_totale' => $nuova_exp_totale,
                'exp_corrente' => $exp_overflow,
                'exp_prossimo' => $prossimo_livello_xp,
                'titolo' => $nuovo_titolo,
                'id_utente' => $id_utente
            ]);

            // Notifica level up
            $this->createNotification(
                $id_utente,
                'sistema',
                'Level Up! 🎉',
                "Sei salito al livello {$nuovo_livello}: {$nuovo_titolo}!"
            );
        } else {
            // Aggiorna solo XP
            $stmt = $this->pdo->prepare("
                UPDATE livello_utente 
                SET esperienza_totale = :exp_totale,
                    esperienza_livello_corrente = :exp_corrente
                WHERE id_utente = :id_utente
            ");
            $stmt->execute([
                'exp_totale' => $nuova_exp_totale,
                'exp_corrente' => $nuova_exp_corrente,
                'id_utente' => $id_utente
            ]);
        }
    }

    /**
     * Calcola XP necessari per livello successivo
     */
    private function calculateNextLevelXP(int $livello): int {
        return 100 * $livello; // Formula lineare: 100, 200, 300, ...
    }

    /**
     * Titolo in base al livello
     */
    private function getTitleForLevel(int $livello): string {
        $titoli = [
            1 => 'Lettore Novizio',
            5 => 'Lettore Appassionato',
            10 => 'Bibliofilo Esperto',
            15 => 'Maestro dei Libri',
            20 => 'Custode della Biblioteca',
            25 => 'Leggenda Vivente'
        ];

        foreach(array_reverse($titoli, true) as $soglia => $titolo) {
            if($livello >= $soglia) {
                return $titolo;
            }
        }

        return 'Lettore Novizio';
    }

    /**
     * Crea notifica
     */
    private function createNotification(int $id_utente, string $tipo, string $titolo, string $messaggio): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO notifica (id_utente, tipo, titolo, messaggio)
            VALUES (:id_utente, :tipo, :titolo, :messaggio)
        ");
        $stmt->execute([
            'id_utente' => $id_utente,
            'tipo' => $tipo,
            'titolo' => $titolo,
            'messaggio' => $messaggio
        ]);
    }

    /**
     * Ottieni badge utente
     */
    public function getUserBadges(int $id_utente): array {
        $stmt = $this->pdo->prepare("
            SELECT b.*, ub.data_ottenimento, ub.progressione_attuale
            FROM badge b
            LEFT JOIN utente_badge ub ON b.id_badge = ub.id_badge AND ub.id_utente = :id_utente
            WHERE b.attivo = 1
            ORDER BY 
                CASE WHEN ub.id_utente_badge IS NOT NULL THEN 0 ELSE 1 END,
                b.ordine_visualizzazione,
                b.id_badge
        ");
        $stmt->execute(['id_utente' => $id_utente]);
        return $stmt->fetchAll();
    }

    /**
     * Ottieni classifica globale
     */
    public function getLeaderboard(int $limit = 10): array {
        $stmt = $this->pdo->prepare("
            SELECT 
                u.id_utente,
                u.username,
                u.foto,
                l.livello,
                l.esperienza_totale,
                l.titolo,
                COUNT(DISTINCT ub.id_badge) as badge_count,
                COUNT(DISTINCT p.id_prestito) as libri_letti
            FROM utente u
            LEFT JOIN livello_utente l ON u.id_utente = l.id_utente
            LEFT JOIN utente_badge ub ON u.id_utente = ub.id_utente
            LEFT JOIN prestito p ON u.id_utente = p.id_utente AND p.data_restituzione_effettiva IS NOT NULL
            WHERE u.stato_account = 'attivo'
            GROUP BY u.id_utente
            ORDER BY l.esperienza_totale DESC, badge_count DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Aggiorna progressi obiettivi
     */
    public function updateObjectiveProgress(int $id_utente): void {
        // Ottieni obiettivi attivi
        $stmt = $this->pdo->query("SELECT * FROM obiettivo WHERE attivo = 1");
        $obiettivi = $stmt->fetchAll();

        foreach($obiettivi as $obj) {
            $progresso = $this->calculateObjectiveProgress($id_utente, $obj);

            // Verifica se esiste già progresso
            $stmt = $this->pdo->prepare("
                SELECT * FROM progresso_obiettivo 
                WHERE id_utente = :id_utente AND id_obiettivo = :id_obiettivo
            ");
            $stmt->execute([
                'id_utente' => $id_utente,
                'id_obiettivo' => $obj['id_obiettivo']
            ]);
            $existing = $stmt->fetch();

            $completato = $progresso >= $obj['target'];

            if($existing) {
                // Aggiorna
                $stmt = $this->pdo->prepare("
                    UPDATE progresso_obiettivo 
                    SET progresso_attuale = :progresso,
                        completato = :completato,
                        data_completamento = CASE WHEN :completato = 1 AND completato = 0 THEN NOW() ELSE data_completamento END
                    WHERE id_progresso = :id_progresso
                ");
                $stmt->execute([
                    'progresso' => $progresso,
                    'completato' => $completato ? 1 : 0,
                    'id_progresso' => $existing['id_progresso']
                ]);

                // Notifica se appena completato
                if($completato && !$existing['completato']) {
                    $this->createNotification(
                        $id_utente,
                        'obiettivo',
                        'Obiettivo Completato! 🎯',
                        "Hai completato l'obiettivo '{$obj['nome']}'!"
                    );
                    $this->assignXP($id_utente, $obj['punti_esperienza'], 'obiettivo', $obj['id_obiettivo']);
                }
            } else {
                // Crea nuovo
                $stmt = $this->pdo->prepare("
                    INSERT INTO progresso_obiettivo 
                    (id_utente, id_obiettivo, anno_riferimento, progresso_attuale, completato, data_completamento)
                    VALUES (:id_utente, :id_obiettivo, YEAR(NOW()), :progresso, :completato, :data_completamento)
                ");
                $stmt->execute([
                    'id_utente' => $id_utente,
                    'id_obiettivo' => $obj['id_obiettivo'],
                    'progresso' => $progresso,
                    'completato' => $completato ? 1 : 0,
                    'data_completamento' => $completato ? date('Y-m-d H:i:s') : null
                ]);
            }
        }
    }

    /**
     * Calcola progresso per un obiettivo
     */
    private function calculateObjectiveProgress(int $id_utente, array $obiettivo): int {
        switch($obiettivo['tipo']) {
            case 'libri_letti':
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(DISTINCT c.id_libro)
                    FROM prestito p
                    JOIN copia c ON p.id_copia = c.id_copia
                    WHERE p.id_utente = :id_utente
                    AND p.data_restituzione_effettiva IS NOT NULL
                    AND YEAR(p.data_restituzione_effettiva) = :anno
                ");
                $stmt->execute([
                    'id_utente' => $id_utente,
                    'anno' => $obiettivo['anno_riferimento'] ?? date('Y')
                ]);
                return (int)$stmt->fetchColumn();

            case 'generi_diversi':
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(DISTINCT l.categoria)
                    FROM prestito p
                    JOIN copia c ON p.id_copia = c.id_copia
                    JOIN libro l ON c.id_libro = l.id_libro
                    WHERE p.id_utente = :id_utente
                    AND p.data_restituzione_effettiva IS NOT NULL
                    AND YEAR(p.data_restituzione_effettiva) = :anno
                ");
                $stmt->execute([
                    'id_utente' => $id_utente,
                    'anno' => $obiettivo['anno_riferimento'] ?? date('Y')
                ]);
                return (int)$stmt->fetchColumn();

            case 'recensioni':
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*)
                    FROM recensione
                    WHERE id_utente = :id_utente
                    AND YEAR(data_recensione) = :anno
                ");
                $stmt->execute([
                    'id_utente' => $id_utente,
                    'anno' => $obiettivo['anno_riferimento'] ?? date('Y')
                ]);
                return (int)$stmt->fetchColumn();

            default:
                return 0;
        }
    }
}
