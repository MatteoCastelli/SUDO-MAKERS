<?php

use Proprietario\SudoMakers\core\Database;

session_start();
require_once __DIR__ . '/../core/Database.php';

$pdo = Database::getInstance()->getConnection();
$title = "Risultati ricerca";

// Parametri ricerca
$query = trim($_GET['q'] ?? '');
$autore_filtro = trim($_GET['autore'] ?? '');
$categoria_filtro = trim($_GET['categoria'] ?? '');
$ordinamento = $_GET['sort'] ?? 'rilevanza';

// Se non c'è nessun parametro di ricerca, torna alla homepage
if(empty($query) && empty($autore_filtro) && empty($categoria_filtro)) {
    header("Location: ../user/homepage.php");
    exit;
}

// Funzione per calcolare la similarità (Levenshtein distance)
function fuzzyMatch($str1, $str2) {
    $str1 = strtolower($str1);
    $str2 = strtolower($str2);
    $distance = levenshtein($str1, $str2);
    $maxLen = max(strlen($str1), strlen($str2));
    return $maxLen > 0 ? (1 - $distance / $maxLen) * 100 : 0;
}

// Costruzione query SQL
$sql = "
    SELECT 
        l.*,
        GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori,
        COUNT(DISTINCT c.id_copia) as totale_copie,
        SUM(CASE WHEN c.disponibile = 1 AND c.stato_fisico != 'smarrito' THEN 1 ELSE 0 END) as copie_disponibili,
        SUM(CASE WHEN c.stato_fisico = 'smarrito' THEN 1 ELSE 0 END) as copie_smarrite,
        AVG(r.voto) as media_voti,
        COUNT(DISTINCT r.id_recensione) as numero_recensioni,
        COUNT(DISTINCT p.id_prestito) as numero_prestiti
    FROM libro l
    LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
    LEFT JOIN autore a ON la.id_autore = a.id_autore
    LEFT JOIN copia c ON l.id_libro = c.id_libro
    LEFT JOIN recensione r ON l.id_libro = r.id_libro
    LEFT JOIN prestito p ON c.id_copia = p.id_copia
    WHERE 1=1
";

$params = [];

if($query) {
    $sql .= " AND (l.titolo LIKE :query 
              OR CONCAT(a.nome, ' ', a.cognome) LIKE :query
              OR l.isbn LIKE :query
              OR l.editore LIKE :query
              OR l.categoria LIKE :query)";
    $params['query'] = "%$query%";
}

if($autore_filtro) {
    $sql .= " AND CONCAT(a.nome, ' ', a.cognome) LIKE :autore";
    $params['autore'] = "%$autore_filtro%";
}

if($categoria_filtro) {
    $sql .= " AND l.categoria = :categoria";
    $params['categoria'] = $categoria_filtro;
}

$sql .= " GROUP BY l.id_libro";


// Ordinamento
switch($ordinamento) {
    case 'alfabetico':
        $sql .= " ORDER BY l.titolo ASC";
        break;
    case 'anno_desc':
        $sql .= " ORDER BY l.anno_pubblicazione DESC";
        break;
    case 'anno_asc':
        $sql .= " ORDER BY l.anno_pubblicazione ASC";
        break;
    case 'popolarita':
        $sql .= " ORDER BY numero_prestiti DESC";
        break;
    case 'rating':
        $sql .= " ORDER BY media_voti DESC, numero_recensioni DESC";
        break;
    default: // rilevanza
        // SOLO se c'è una query, usa l'ordinamento per rilevanza
        if($query) {
            $sql .= " ORDER BY 
                CASE 
                    WHEN l.titolo LIKE :query_exact THEN 1
                    WHEN l.titolo LIKE :query_start THEN 2
                    ELSE 3
                END";
            $params['query_exact'] = $query;
            $params['query_start'] = "$query%";
        } else {
            // Se non c'è query (solo autore/categoria), ordina alfabetico
            $sql .= " ORDER BY l.titolo ASC";
        }
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$risultati = $stmt->fetchAll();

// Se nessun risultato, cerca suggerimenti simili
$suggerimenti = [];
if(empty($risultati) && $query) {
    $stmt = $pdo->query("SELECT DISTINCT titolo FROM libro");
    $tutti_titoli = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $matches = [];
    foreach($tutti_titoli as $titolo) {
        $similarity = fuzzyMatch($query, $titolo);
        if($similarity > 60) { // 60% di somiglianza minima
            $matches[$titolo] = $similarity;
        }
    }
    arsort($matches);
    $suggerimenti = array_slice(array_keys($matches), 0, 3);
}

// Funzione disponibilità
function getDisponibilita($copie_disponibili, $totale_copie, $copie_smarrite) {
    $copie_attive = $totale_copie - $copie_smarrite;
    if ($copie_attive == 0 || $copie_smarrite == $totale_copie) {
        return ['stato' => 'non_disponibile', 'testo' => 'Non disponibile', 'classe' => 'badge-red'];
    } elseif ($copie_disponibili > 0) {
        return ['stato' => 'disponibile', 'testo' => 'Disponibile', 'classe' => 'badge-green'];
    } else {
        return ['stato' => 'prenotabile', 'testo' => 'Prenotabile', 'classe' => 'badge-orange'];
    }
}
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../../public/assets/css/privateAreaStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/catalogoStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/ricercaStyle.css">
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="ricerca-container">
    <div class="ricerca-header">
        <h1>Risultati ricerca</h1>
        <?php if($query || $autore_filtro || $categoria_filtro): ?>
            <div class="search-info">
                <?php if($query): ?>
                    <span class="search-tag">Query: <strong><?= htmlspecialchars($query) ?></strong></span>
                <?php endif; ?>
                <?php if($autore_filtro): ?>
                    <span class="search-tag">Autore: <strong><?= htmlspecialchars($autore_filtro) ?></strong></span>
                <?php endif; ?>
                <?php if($categoria_filtro): ?>
                    <span class="search-tag">Categoria: <strong><?= htmlspecialchars($categoria_filtro) ?></strong></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="ricerca-controls">
        <div class="risultati-count">
            Trovati <strong><?= count($risultati) ?></strong> risultati
        </div>

        <div class="ordinamento">
            <label for="sort">Ordina per:</label>
            <select id="sort" onchange="changeSort(this.value)">
                <option value="rilevanza" <?= $ordinamento === 'rilevanza' ? 'selected' : '' ?>>Rilevanza</option>
                <option value="alfabetico" <?= $ordinamento === 'alfabetico' ? 'selected' : '' ?>>Alfabetico</option>
                <option value="anno_desc" <?= $ordinamento === 'anno_desc' ? 'selected' : '' ?>>Anno (più recente)</option>
                <option value="anno_asc" <?= $ordinamento === 'anno_asc' ? 'selected' : '' ?>>Anno (più vecchio)</option>
                <option value="popolarita" <?= $ordinamento === 'popolarita' ? 'selected' : '' ?>>Popolarità</option>
                <option value="rating" <?= $ordinamento === 'rating' ? 'selected' : '' ?>>Valutazione</option>
            </select>
        </div>
    </div>

    <?php if(!empty($risultati)): ?>
        <div class="catalogo-grid">
            <?php foreach($risultati as $libro):
                $disponibilita = getDisponibilita($libro['copie_disponibili'], $libro['totale_copie'], $libro['copie_smarrite']);
                ?>
                <div class="libro-card">
                    <a href="../catalog/dettaglio_libro.php?id=<?= $libro['id_libro'] ?>" class="card-link" data-libro-id="<?= $libro['id_libro'] ?>">
                        <div class="libro-copertina">
                            <?php if($libro['immagine_copertina_url']): ?>
                                <img src="<?= htmlspecialchars($libro['immagine_copertina_url']) ?>"
                                     alt="Copertina di <?= htmlspecialchars($libro['titolo']) ?>">
                            <?php else: ?>
                                <div class="copertina-placeholder">
                                    <span>📖</span>
                                </div>
                            <?php endif; ?>
                            <div class="disponibilita-badge <?= $disponibilita['classe'] ?>">
                                <?= $disponibilita['testo'] ?>
                            </div>
                        </div>

                        <div class="libro-info">
                            <h3 class="libro-titolo"><?= htmlspecialchars($libro['titolo']) ?></h3>
                            <p class="libro-autore"><?= htmlspecialchars($libro['autori'] ?? 'Autore sconosciuto') ?></p>

                            <div class="libro-rating">
                                <?php if($libro['media_voti']):
                                    $media = round($libro['media_voti'], 1);
                                    for($i = 1; $i <= 5; $i++):
                                        if($i <= floor($media)): ?>
                                            <span class="star-small filled">★</span>
                                        <?php elseif($i == ceil($media) && $media - floor($media) >= 0.5): ?>
                                            <span class="star-small half">★</span>
                                        <?php else: ?>
                                            <span class="star-small">☆</span>
                                        <?php endif;
                                    endfor;
                                else: ?>
                                    <span style="color: #666;">Nessuna recensione</span>
                                <?php endif; ?>
                            </div>

                            <div class="libro-meta">
                            <span class="meta-item">
                                <strong>Categoria:</strong> <?= htmlspecialchars($libro['categoria'] ?? 'N/D') ?>
                            </span>
                            </div>

                            <div class="libro-copie">
                            <span class="copie-info">
                                <?= $libro['copie_disponibili'] ?> di <?= $libro['totale_copie'] - $libro['copie_smarrite'] ?> disponibili
                            </span>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-results">
            <h2>Nessun risultato trovato</h2>
            <?php if(!empty($suggerimenti)): ?>
                <div class="suggerimenti">
                    <p>Forse cercavi...</p>
                    <ul>
                        <?php foreach($suggerimenti as $sugg): ?>
                            <li>
                                <a href="ricerca.php?q=<?= urlencode($sugg) ?>">
                                    📖 <?= htmlspecialchars($sugg) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <p>Prova a:</p>
            <ul>
                <li>Verificare l'ortografia</li>
                <li>Usare parole chiave diverse</li>
                <li>Usare termini più generici</li>
                <li><a href="ricerca_avanzata.php">Provare la ricerca avanzata</a></li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<script>
    function changeSort(value) {
        const url = new URL(window.location.href);
        url.searchParams.set('sort', value);
        window.location.href = url.toString();
    }
</script>

</body>
</html>