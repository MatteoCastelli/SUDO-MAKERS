<?php
header('Content-Type: application/json');

use Proprietario\SudoMakers\core\Database;

require_once __DIR__ . '/../core/Database.php';

// Ottieni il termine di ricerca
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$pdo = Database::getInstance()->getConnection();

$queryLower = strtolower($query);
$risultati = [];

try {
    // ========================================
    // 1. CERCA TITOLI DI LIBRI
    // ========================================
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            l.id_libro,
            l.titolo,
            GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori,
            'libro' as tipo
        FROM libro l
        LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
        LEFT JOIN autore a ON la.id_autore = a.id_autore
        WHERE LOWER(l.titolo) LIKE :query
        GROUP BY l.id_libro
        ORDER BY 
            CASE 
                WHEN LOWER(l.titolo) = :query_exact THEN 1
                WHEN LOWER(l.titolo) LIKE :query_start THEN 2
                ELSE 3
            END,
            l.titolo
        LIMIT 5
    ");

    $stmt->execute([
        'query' => "%$queryLower%",
        'query_exact' => $queryLower,
        'query_start' => "$queryLower%"
    ]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $risultati[] = [
            'tipo' => 'libro',
            'id' => $row['id_libro'],
            'titolo' => $row['titolo'],
            'autore' => $row['autori'] ?? 'Autore sconosciuto'
        ];
    }

    // ========================================
    // 2. CERCA AUTORI (solo se non abbiamo già 5 risultati)
    // ========================================
    if (count($risultati) < 5) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT 
                CONCAT(a.nome, ' ', a.cognome) as nome_completo,
                COUNT(DISTINCT la.id_libro) as num_libri,
                'autore' as tipo
            FROM autore a
            LEFT JOIN libro_autore la ON a.id_autore = la.id_autore
            WHERE LOWER(CONCAT(a.nome, ' ', a.cognome)) LIKE :query
            GROUP BY a.id_autore
            ORDER BY 
                CASE 
                    WHEN LOWER(CONCAT(a.nome, ' ', a.cognome)) = :query_exact THEN 1
                    WHEN LOWER(CONCAT(a.nome, ' ', a.cognome)) LIKE :query_start THEN 2
                    ELSE 3
                END,
                nome_completo
            LIMIT :limit
        ");

        $limit = 5 - count($risultati);
        $stmt->bindValue(':query', "%$queryLower%", PDO::PARAM_STR);
        $stmt->bindValue(':query_exact', $queryLower, PDO::PARAM_STR);
        $stmt->bindValue(':query_start', "$queryLower%", PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $risultati[] = [
                'tipo' => 'autore',
                'nome' => $row['nome_completo'],
                'num_libri' => (int)$row['num_libri']
            ];
        }
    }

    // ========================================
    // 3. CERCA CATEGORIE (solo se non abbiamo già 5 risultati)
    // ========================================
    if (count($risultati) < 5) {
        $stmt = $pdo->prepare("
            SELECT DISTINCT 
                l.categoria,
                COUNT(DISTINCT l.id_libro) as num_libri,
                'categoria' as tipo
            FROM libro l
            WHERE l.categoria IS NOT NULL 
            AND LOWER(l.categoria) LIKE :query
            GROUP BY l.categoria
            ORDER BY 
                CASE 
                    WHEN LOWER(l.categoria) = :query_exact THEN 1
                    WHEN LOWER(l.categoria) LIKE :query_start THEN 2
                    ELSE 3
                END,
                l.categoria
            LIMIT :limit
        ");

        $limit = 5 - count($risultati);
        $stmt->bindValue(':query', "%$queryLower%", PDO::PARAM_STR);
        $stmt->bindValue(':query_exact', $queryLower, PDO::PARAM_STR);
        $stmt->bindValue(':query_start', "$queryLower%", PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $risultati[] = [
                'tipo' => 'categoria',
                'nome' => $row['categoria'],
                'num_libri' => (int)$row['num_libri']
            ];
        }
    }

    // ========================================
    // 4. FUZZY SEARCH (se non ci sono risultati)
    // ========================================
    if (empty($risultati)) {
        $stmt = $pdo->query("SELECT DISTINCT titolo FROM libro");
        $tutti_titoli = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $matches = [];
        foreach ($tutti_titoli as $titolo) {
            // Calcola similarità usando similar_text
            similar_text(strtolower($query), strtolower($titolo), $percent);

            // Calcola anche distanza Levenshtein per maggiore precisione
            $distance = levenshtein(strtolower($query), strtolower($titolo));
            $maxLen = max(strlen($query), strlen($titolo));
            $levenshtein_percent = $maxLen > 0 ? (1 - $distance / $maxLen) * 100 : 0;

            // Usa la media delle due misure per maggiore accuratezza
            $final_percent = ($percent + $levenshtein_percent) / 2;

            // Soglia abbassata a 45% (era 60%) per maggiore tolleranza
            if ($final_percent > 45) {
                $matches[$titolo] = $final_percent;
            }
        }

        if (!empty($matches)) {
            arsort($matches); // Ordina per percentuale decrescente
            $suggerimenti = array_slice(array_keys($matches), 0, 5); // Aumentato a 5 suggerimenti

            foreach ($suggerimenti as $sugg) {
                $stmt = $pdo->prepare("
                    SELECT 
                        l.id_libro,
                        l.titolo,
                        GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori
                    FROM libro l
                    LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
                    LEFT JOIN autore a ON la.id_autore = a.id_autore
                    WHERE l.titolo = :titolo
                    GROUP BY l.id_libro
                ");
                $stmt->execute(['titolo' => $sugg]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    $risultati[] = [
                        'tipo' => 'suggerimento',
                        'id' => $row['id_libro'],
                        'titolo' => $row['titolo'],
                        'autore' => $row['autori'] ?? 'Autore sconosciuto'
                    ];
                }
            }
        }
    }

} catch (Exception $e) {
    error_log("Errore autocomplete: " . $e->getMessage());
    // In caso di errore, restituisci array vuoto invece di errore
    $risultati = [];
}

echo json_encode($risultati);