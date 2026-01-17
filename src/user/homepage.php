<?php

use Proprietario\SudoMakers\core\Database;
use Proprietario\SudoMakers\core\RecommendationEngine;

session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/RecommendationEngine.php';
require_once __DIR__ . '/../utils/pagination_helper.php';

$title = "Catalogo Biblioteca";
$pdo = Database::getInstance()->getConnection();

/* ============================================================
   PAGINAZIONE CATALOGO
   ============================================================ */
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$itemsPerPage = 10;

/* ============================================================
   SEZIONE RACCOMANDAZIONI PERSONALIZZATE HOMEPAGE (TOP 6)
   ============================================================ */

$raccomandazioni_homepage = [];

if ($page === 1 && isset($_SESSION['id_utente'])) {
    $engine = new RecommendationEngine($pdo);

    $cached = $engine->getCachedRecommendations($_SESSION['id_utente'], 6);

    if (!empty($cached)) {
        $raccomandazioni_homepage = $cached;
    } else {
        $recs_raw = $engine->generateRecommendations($_SESSION['id_utente'], 6);

        foreach ($recs_raw as $rec) {
            $libro = $rec['libro'];
            $libro['motivo_raccomandazione'] = implode('; ', $rec['motivi']);
            $libro['score'] = $rec['score'];
            $raccomandazioni_homepage[] = $libro;
        }
    }

    foreach ($raccomandazioni_homepage as &$libro) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as totale_copie,
                SUM(CASE WHEN disponibile = 1 AND stato_fisico != 'smarrito' THEN 1 ELSE 0 END) as copie_disponibili,
                SUM(CASE WHEN stato_fisico = 'smarrito' THEN 1 ELSE 0 END) as copie_smarrite
            FROM copia
            WHERE id_libro = :id_libro
        ");
        $stmt->execute(['id_libro' => $libro['id_libro']]);
        $copie_info = $stmt->fetch();

        $libro['totale_copie'] = $copie_info['totale_copie'] ?? 0;
        $libro['copie_disponibili'] = $copie_info['copie_disponibili'] ?? 0;
        $libro['copie_smarrite'] = $copie_info['copie_smarrite'] ?? 0;
    }
    unset($libro);
}

/* ============================================================
   SEZIONE TRENDING HOMEPAGE (TOP 6)
   ============================================================ */

$trending_homepage = [];

if ($page === 1 && isset($_SESSION['id_utente'])) {
    $engine = new RecommendationEngine($pdo);

    if (!isset($_SESSION['last_trend_update']) ||
            time() - $_SESSION['last_trend_update'] > 3600) {
        $engine->updateTrendingStats();
        $_SESSION['last_trend_update'] = time();
    }

    $trending_homepage = $engine->getTrendingBooks(6);

    foreach ($trending_homepage as &$libro) {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as totale_copie,
                SUM(CASE WHEN disponibile = 1 AND stato_fisico != 'smarrito' THEN 1 ELSE 0 END) as copie_disponibili,
                SUM(CASE WHEN stato_fisico = 'smarrito' THEN 1 ELSE 0 END) as copie_smarrite
            FROM copia
            WHERE id_libro = :id_libro
        ");
        $stmt->execute(['id_libro' => $libro['id_libro']]);
        $copie_info = $stmt->fetch();

        $libro['totale_copie'] = $copie_info['totale_copie'] ?? 0;
        $libro['copie_disponibili'] = $copie_info['copie_disponibili'] ?? 0;
        $libro['copie_smarrite'] = $copie_info['copie_smarrite'] ?? 0;
    }
    unset($libro);
}

/* ============================================================
   CATALOGO COMPLETO CON PAGINAZIONE
   ============================================================ */

// PRIMA: Conta il totale dei libri
$countQuery = "SELECT COUNT(DISTINCT l.id_libro) as total FROM libro l";
$totalItems = $pdo->query($countQuery)->fetchColumn();

// Crea oggetto paginazione
$pagination = new PaginationHelper($totalItems, $itemsPerPage, $page);

// Query con LIMIT e OFFSET
$query = "
    SELECT 
        l.*,
        GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori,
        COUNT(c.id_copia) as totale_copie,
        SUM(CASE WHEN c.disponibile = 1 AND c.stato_fisico != 'smarrito' THEN 1 ELSE 0 END) as copie_disponibili,
        SUM(CASE WHEN c.stato_fisico = 'smarrito' THEN 1 ELSE 0 END) as copie_smarrite,
        AVG(r.voto) as media_voti,
        COUNT(DISTINCT r.id_recensione) as numero_recensioni
    FROM libro l
    LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
    LEFT JOIN autore a ON la.id_autore = a.id_autore
    LEFT JOIN copia c ON l.id_libro = c.id_libro
    LEFT JOIN recensione r ON l.id_libro = r.id_libro
    GROUP BY l.id_libro
    ORDER BY l.titolo
    LIMIT {$pagination->getLimit()} OFFSET {$pagination->getOffset()}
";

$stmt = $pdo->query($query);
$libri = $stmt->fetchAll();

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

// Funzione per badge trending
function getTrendingBadge($velocita) {
    if ($velocita > 50) {
        return ['icona' => '🔥', 'testo' => 'Hot', 'classe' => 'trending-hot'];
    } elseif ($velocita > 20) {
        return ['icona' => '📈', 'testo' => 'Rising', 'classe' => 'trending-up'];
    } elseif ($velocita > 0) {
        return ['icona' => '⭐', 'testo' => 'Popular', 'classe' => 'trending-stable'];
    } else {
        return ['icona' => '📚', 'testo' => 'Classic', 'classe' => 'trending-classic'];
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
    <link rel="stylesheet" href="../../public/assets/css/paginationStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/ricercaStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/widgetsStyle.css">
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="catalogo-container">
    <!-- WIDGET RACCOMANDAZIONI -->
    <?php if (!empty($raccomandazioni_homepage)): ?>
        <div class="raccomandazioni-widget">
            <div class="widget-header">
                <h2>Consigliati per te</h2>
                <a href="raccomandazioni.php">Vedi tutti →</a>
            </div>

            <div class="recommendations-grid">
                <?php foreach ($raccomandazioni_homepage as $libro):
                    $disp = getDisponibilita(
                            $libro['copie_disponibili'],
                            $libro['totale_copie'],
                            $libro['copie_smarrite']
                    );
                    ?>
                    <div class="libro-card-mini" data-id-libro="<?= $libro['id_libro'] ?>">
                        <a href="../catalog/dettaglio_libro.php?id=<?= $libro['id_libro'] ?>&from=homepage_widget">
                            <div class="copertina-mini">
                                <?php if ($libro['immagine_copertina_url']): ?>
                                    <img src="<?= htmlspecialchars($libro['immagine_copertina_url']) ?>"
                                         alt="<?= htmlspecialchars($libro['titolo']) ?>">
                                <?php else: ?>
                                    <div class="placeholder-mini">📖</div>
                                <?php endif; ?>

                                <div class="badge-mini <?= $disp['classe'] ?>">
                                    <?= $disp['testo'] ?>
                                </div>
                            </div>

                            <h4><?= htmlspecialchars($libro['titolo']) ?></h4>
                            <p><?= htmlspecialchars($libro['autori'] ?? 'Autore sconosciuto') ?></p>

                            <div style="padding: 0 12px 8px; font-size: 12px; display: flex; gap: 2px;">
                                <?php if (!empty($libro['rating_medio'])):
                                    $media = round($libro['rating_medio'], 1);
                                    for($i = 1; $i <= 5; $i++):
                                        if($i <= floor($media)): ?>
                                            <span style="color: #ffa500; font-size: 14px;">★</span>
                                        <?php elseif($i == ceil($media) && $media - floor($media) >= 0.5): ?>
                                            <span style="color: #ffa500; opacity: 0.6; font-size: 14px;">★</span>
                                        <?php else: ?>
                                            <span style="color: #444; font-size: 14px;">☆</span>
                                        <?php endif;
                                    endfor;
                                else: ?>
                                    <span style="color: #666;">Nessuna recensione</span>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- WIDGET TRENDING (TOP 6) -->
    <?php if (!empty($trending_homepage)): ?>
        <div class="trending-widget">
            <div class="widget-header">
                <h2>Trending Now</h2>
                <a href="trending.php">Vedi tutti →</a>
            </div>

            <div class="recommendations-grid">
                <?php
                $rank = 1;
                foreach ($trending_homepage as $libro):
                    $disp = getDisponibilita(
                            $libro['copie_disponibili'],
                            $libro['totale_copie'],
                            $libro['copie_smarrite']
                    );
                    $trending_badge = getTrendingBadge($libro['velocita_trend']);
                    ?>
                    <div class="libro-card-mini" data-id-libro="<?= $libro['id_libro'] ?>">
                        <a href="../catalog/dettaglio_libro.php?id=<?= $libro['id_libro'] ?>&from=homepage_trending">
                            <div class="copertina-mini">
                                <?php if ($libro['immagine_copertina_url']): ?>
                                    <img src="<?= htmlspecialchars($libro['immagine_copertina_url']) ?>"
                                         alt="<?= htmlspecialchars($libro['titolo']) ?>">
                                <?php else: ?>
                                    <div class="placeholder-mini">📖</div>
                                <?php endif; ?>

                                <div class="trending-badge-mini <?= $trending_badge['classe'] ?>">
                                    <?= $trending_badge['icona'] ?> <?= $trending_badge['testo'] ?>
                                </div>

                                <div class="badge-mini <?= $disp['classe'] ?>" style="top: auto; bottom: 8px;">
                                    <?= $disp['testo'] ?>
                                </div>

                                <div style="position: absolute; top: 8px; right: 8px;
                                        width: 30px; height: 30px; background: <?= $rank <= 3 ? '#FFD700' : '#0c8a1f' ?>;
                                        color: white; border-radius: 50%; display: flex;
                                        align-items: center; justify-content: center;
                                        font-size: 14px; font-weight: bold;
                                        box-shadow: 0 2px 8px rgba(0,0,0,0.3); z-index: 10;">
                                    <?= $rank ?>
                                </div>
                            </div>

                            <h4><?= htmlspecialchars($libro['titolo']) ?></h4>
                            <p><?= htmlspecialchars($libro['autori'] ?? 'Autore sconosciuto') ?></p>

                            <div style="padding: 0 12px 4px; font-size: 12px; display: flex; gap: 2px;">
                                <?php if (!empty($libro['rating_medio'])):
                                    $media = round($libro['rating_medio'], 1);
                                    for($i = 1; $i <= 5; $i++):
                                        if($i <= floor($media)): ?>
                                            <span style="color: #ffa500; font-size: 14px;">★</span>
                                        <?php elseif($i == ceil($media) && $media - floor($media) >= 0.5): ?>
                                            <span style="color: #ffa500; opacity: 0.6; font-size: 14px;">★</span>
                                        <?php else: ?>
                                            <span style="color: #444; font-size: 14px;">☆</span>
                                        <?php endif;
                                    endfor;
                                else: ?>
                                    <span style="color: #666;">Nessuna recensione</span>
                                <?php endif; ?>
                            </div>

                            <div class="trending-stats-mini">
                                <span>👁️ <strong><?= $libro['click_ultimi_7_giorni'] ?></strong></span>
                                <span>📚 <strong><?= $libro['prestiti_ultimi_7_giorni'] ?></strong></span>
                            </div>
                        </a>
                    </div>
                    <?php
                    $rank++;
                endforeach;
                ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- SEZIONE CATALOGO COMPLETO -->
    <div">
        <h2 style="text-align: center; margin-bottom: 30px; font-size: 28px; color: #ebebed;">
            Catalogo Completo
            <p class="subtitle">Qui potrai consultare tutti i libri della biblioteca</p>
        </h2>

        <div class="catalogo-grid">
            <?php foreach($libri as $libro):
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

        <?php if(empty($libri)): ?>
            <div class="empty-state">
                <p>Nessun libro presente nel catalogo</p>
            </div>
        <?php endif; ?>

        <!-- PAGINAZIONE -->
        <?php echo $pagination->render('homepage.php'); ?>
    </div>
</div>

<script>
    document.querySelectorAll('.card-link').forEach(link => {
        link.addEventListener('click', function(event) {
            const libroId = this.dataset.libroId;
            const idUtente = <?= json_encode($_SESSION['id_utente'] ?? null) ?>;

            if (!idUtente) return;

            fetch('../api/track_interaction.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_libro: libroId,
                    tipo: 'click',
                    fonte: 'catalogo',
                })
            }).catch(console.error);
        });
    });
</script>

</body>
</html>