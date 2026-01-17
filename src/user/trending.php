<?php

use Proprietario\SudoMakers\core\Database;
use Proprietario\SudoMakers\core\RecommendationEngine;

session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/RecommendationEngine.php';
require_once __DIR__ . '/../utils/pagination_helper.php';

$pdo = Database::getInstance()->getConnection();
$title = "Trending Now";

/* ============================================================
   PAGINAZIONE
   ============================================================ */
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$itemsPerPage = 24;
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : '30';

$engine = new RecommendationEngine($pdo);

// Aggiorna statistiche se necessario
if (!isset($_SESSION['last_trend_update']) || time() - $_SESSION['last_trend_update'] > 3600) {
    $engine->updateTrendingStats();
    $_SESSION['last_trend_update'] = time();
}

// Ottieni tutti i libri trending
$trending_libri = $engine->getTrendingBooks(100);

// Arricchisci con info copie
foreach ($trending_libri as &$libro) {
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

// Calcola paginazione
$totalItems = count($trending_libri);
$pagination = new PaginationHelper($totalItems, $itemsPerPage, $page);

// Estrai solo gli item della pagina corrente
$trending_paginate = array_slice($trending_libri, $pagination->getOffset(), $pagination->getLimit());

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
    <link rel="stylesheet" href="../../public/assets/css/ricercaStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/trendingStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/widgetsStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/paginationStyle.css">
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="catalogo-container">
    <div class="trending-header">
        <h1>Trending Now</h1>
        <p class="subtitle">I libri più popolari e richiesti in questo momento</p>
    </div>

    <?php if (!empty($trending_paginate)): ?>
        <div class="filters-bar">
            <div class="filter-group">
                <label for="periodo">Periodo:</label>
                <select id="periodo">
                    <option value="7" <?= $periodo == '7' ? 'selected' : '' ?>>Ultimi 7 giorni</option>
                    <option value="30" <?= $periodo == '30' ? 'selected' : '' ?>>Ultimi 30 giorni</option>
                    <option value="all" <?= $periodo == 'all' ? 'selected' : '' ?>>Tutto il tempo</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="categoria-filter">Categoria:</label>
                <select id="categoria-filter" onchange="filterByCategory(this.value)">
                    <option value="">Tutte le categorie</option>
                    <?php
                    $categorie = array_unique(array_column($trending_libri, 'categoria'));
                    sort($categorie);
                    foreach ($categorie as $cat):
                        if ($cat):
                            ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php
                        endif;
                    endforeach;
                    ?>
                </select>
            </div>
        </div>

        <div class="catalogo-grid trending-section">
            <?php
            $rank_offset = ($page - 1) * $itemsPerPage;
            foreach($trending_paginate as $index => $libro):
                $rank = $rank_offset + $index + 1;
                $disponibilita = getDisponibilita($libro['copie_disponibili'], $libro['totale_copie'], $libro['copie_smarrite']);
                $trending_badge = getTrendingBadge($libro['velocita_trend']);
                ?>
                <div class="libro-card"
                     data-categoria="<?= htmlspecialchars($libro['categoria'] ?? '') ?>"
                     data-id-libro="<?= $libro['id_libro'] ?>">
                    <a href="../catalog/dettaglio_libro.php?id=<?= $libro['id_libro'] ?>&from=trending" class="card-link" data-libro-id="<?= $libro['id_libro'] ?>">
                        <div class="libro-copertina">
                            <?php if($libro['immagine_copertina_url']): ?>
                                <img src="<?= htmlspecialchars($libro['immagine_copertina_url']) ?>"
                                     alt="Copertina di <?= htmlspecialchars($libro['titolo']) ?>">
                            <?php else: ?>
                                <div class="copertina-placeholder">
                                    <span>📖</span>
                                </div>
                            <?php endif; ?>

                            <!-- Badge Ranking -->
                            <div class="trend-rank <?= $rank <= 3 ? 'top-3' : '' ?>">
                                <?= $rank ?>
                            </div>

                            <!-- Badge Trending -->
                            <div class="trending-badge-overlay <?= $trending_badge['classe'] ?>">
                                <?= $trending_badge['icona'] ?> <?= $trending_badge['testo'] ?>
                            </div>

                            <!-- Badge Disponibilità -->
                            <div class="disponibilita-badge <?= $disponibilita['classe'] ?>" style="top: 60px;">
                                <?= $disponibilita['testo'] ?>
                            </div>
                        </div>

                        <div class="libro-info">
                            <h3 class="libro-titolo"><?= htmlspecialchars($libro['titolo']) ?></h3>
                            <p class="libro-autore"><?= htmlspecialchars($libro['autori'] ?? 'Autore sconosciuto') ?></p>

                            <div class="libro-rating">
                                <?php if(isset($libro['rating_medio']) && $libro['rating_medio']):
                                    $media = round($libro['rating_medio'], 1);
                                    for($i = 1; $i <= 5; $i++):
                                        if($i <= floor($media)): ?>
                                            <span class="star-small filled">★</span>
                                        <?php elseif($i == ceil($media) && $media - floor($media) >= 0.5): ?>
                                            <span class="star-small half">★</span>
                                        <?php else: ?>
                                            <span class="star-small">☆</span>
                                        <?php endif;
                                    endfor;
                                else:
                                    for($i = 1; $i <= 5; $i++): ?>
                                        <span class="star-small">☆</span>
                                    <?php endfor;
                                endif; ?>
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

                    <!-- Statistiche Trending -->
                    <div class="trending-stats">
                        <div class="trend-stat">
                            📊 <strong class="prestiti-count"><?= $libro['prestiti_ultimi_7_giorni'] ?></strong> prestiti (7g)
                        </div>
                        <div class="trend-stat">
                            👁️ <strong class="click-count"><?= $libro['click_ultimi_7_giorni'] ?></strong> visualizzazioni
                        </div>
                        <div class="trend-stat">
                            📅 <strong class="prenotazioni-count"><?= $libro['prenotazioni_attive'] ?></strong> prenotazioni (7g)
                        </div>
                        <div class="trend-stat crescita-count">
                            <?php if ($libro['velocita_trend'] > 0): ?>
                                📈 <strong>+<?= round($libro['velocita_trend']) ?>%</strong> crescita
                            <?php else: ?>
                                📉 <strong><?= round($libro['velocita_trend']) ?>%</strong>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- PAGINAZIONE -->
        <?php echo $pagination->render('trending.php'); ?>

    <?php else: ?>
        <div class="empty-state">
            <p>Nessun libro trending al momento</p>
        </div>
    <?php endif; ?>
</div>

<script src="../../public/assets/js/trackInteraction.js?v=<?= time() ?>"></script>

<script>
    // Track click interactions
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
                    fonte: 'trending',
                })
            }).catch(console.error);
        });
    });

    // Funzione per filtrare per categoria
    function filterByCategory(categoria) {
        const cards = document.querySelectorAll('.libro-card');

        cards.forEach(card => {
            if (categoria === '' || card.dataset.categoria === categoria) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });

        // Aggiorna i numeri di ranking
        updateRankings();
    }

    // Funzione per aggiornare i numeri di ranking dopo il filtro
    function updateRankings() {
        const visibleCards = document.querySelectorAll('.libro-card:not([style*="display: none"])');

        visibleCards.forEach((card, index) => {
            const rankBadge = card.querySelector('.trend-rank');
            if (rankBadge) {
                const currentPage = <?= $page ?>;
                const itemsPerPage = <?= $itemsPerPage ?>;
                const globalRank = ((currentPage - 1) * itemsPerPage) + index + 1;

                rankBadge.textContent = globalRank;

                // Aggiorna classe top-3
                if (globalRank <= 3) {
                    rankBadge.classList.add('top-3');
                } else {
                    rankBadge.classList.remove('top-3');
                }
            }
        });
    }

    // Filtro per periodo
    document.getElementById('periodo')?.addEventListener('change', function() {
        const periodo = this.value;
        const url = new URL(window.location.href);
        url.searchParams.set('periodo', periodo);
        url.searchParams.set('page', 1); // Reset pagina
        window.location.href = url.toString();
    });
</script>

</body>
</html>