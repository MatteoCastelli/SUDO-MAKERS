<?php

use Proprietario\SudoMakers\core\Database;

session_start();
require_once __DIR__ . '/../core/Database.php';

$pdo = Database::getInstance()->getConnection();
$title = "Risultati Ricerca Avanzata";

// Parametri di ricerca
$titolo = trim($_GET['titolo'] ?? '');
$id_autore = $_GET['autore'] ?? '';
$categoria = $_GET['categoria'] ?? '';
$isbn = trim($_GET['isbn'] ?? '');
$editore = trim($_GET['editore'] ?? '');
$anno_min = (int)($_GET['anno_min'] ?? 0);
$anno_max = (int)($_GET['anno_max'] ?? 9999);
$rating_min = $_GET['rating_min'] ?? '';
$disponibile = isset($_GET['disponibile']);
$condizioni = $_GET['condizione'] ?? ['ottimo', 'buono', 'discreto'];
$ordinamento = $_GET['ordinamento'] ?? 'rilevanza';

// Costruzione query
$sql = "
    SELECT 
        l.*,
        GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori,
        GROUP_CONCAT(DISTINCT a.id_autore) as id_autori,
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
$filtri_attivi = [];

// Filtro titolo
if($titolo) {
    $sql .= " AND l.titolo LIKE :titolo";
    $params['titolo'] = "%$titolo%";
    $filtri_attivi[] = "Titolo: $titolo";
}

// Filtro autore
if($id_autore) {
    $sql .= " AND a.id_autore = :id_autore";
    $params['id_autore'] = $id_autore;
    $stmt = $pdo->prepare("SELECT CONCAT(nome, ' ', cognome) FROM autore WHERE id_autore = :id");
    $stmt->execute(['id' => $id_autore]);
    $nome_autore = $stmt->fetchColumn();
    $filtri_attivi[] = "Autore: $nome_autore";
}

// Filtro categoria
if($categoria) {
    $sql .= " AND l.categoria = :categoria";
    $params['categoria'] = $categoria;
    $filtri_attivi[] = "Categoria: $categoria";
}

// Filtro ISBN
if($isbn) {
    $sql .= " AND l.isbn LIKE :isbn";
    $params['isbn'] = "%$isbn%";
    $filtri_attivi[] = "ISBN: $isbn";
}

// Filtro editore
if($editore) {
    $sql .= " AND l.editore LIKE :editore";
    $params['editore'] = "%$editore%";
    $filtri_attivi[] = "Editore: $editore";
}

// Filtro anno
if($anno_min > 0) {
    $sql .= " AND l.anno_pubblicazione >= :anno_min";
    $params['anno_min'] = $anno_min;
}
if($anno_max < 9999) {
    $sql .= " AND l.anno_pubblicazione <= :anno_max";
    $params['anno_max'] = $anno_max;
}
if($anno_min > 0 || $anno_max < 9999) {
    $filtri_attivi[] = "Anno: $anno_min - $anno_max";
}

// Filtro condizione fisica
if(!empty($condizioni) && count($condizioni) < 3) {
    $placeholders = [];
    foreach($condizioni as $i => $cond) {
        $key = "cond_$i";
        $placeholders[] = ":$key";
        $params[$key] = $cond;
    }
    $sql .= " AND c.stato_fisico IN (" . implode(',', $placeholders) . ")";
    $filtri_attivi[] = "Condizione: " . implode(', ', $condizioni);
}

$sql .= " GROUP BY l.id_libro";

// Filtro rating minimo (HAVING dopo GROUP BY)
if($rating_min) {
    $sql .= " HAVING media_voti >= :rating_min";
    $params['rating_min'] = $rating_min;
    $filtri_attivi[] = "Valutazione: ★ $rating_min+";
}

// Filtro disponibilità immediata (HAVING dopo GROUP BY)
if($disponibile) {
    if($rating_min) {
        $sql .= " AND copie_disponibili > 0";
    } else {
        $sql .= " HAVING copie_disponibili > 0";
    }
    $filtri_attivi[] = "Solo disponibili";
}

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
    default:
        $sql .= " ORDER BY l.id_libro DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$risultati = $stmt->fetchAll();

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
        <h1>Risultati Ricerca Avanzata</h1>
        <?php if(!empty($filtri_attivi)): ?>
            <div class="filtri-attivi">
                <strong>Filtri applicati:</strong>
                <?php foreach($filtri_attivi as $filtro): ?>
                    <span class="filtro-tag"><?= htmlspecialchars($filtro) ?></span>
                <?php endforeach; ?>
                <a href="ricerca_avanzata.php" class="btn-modifica-filtri">Modifica filtri</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="ricerca-controls">
        <div class="risultati-count">
            Trovati <strong><?= count($risultati) ?></strong> risultati
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
            <p>Prova a modificare i filtri di ricerca o a usare criteri meno restrittivi.</p>
            <a href="ricerca_avanzata.php" class="btn-primary">← Torna alla ricerca avanzata</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
