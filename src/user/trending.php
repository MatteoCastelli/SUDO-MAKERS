<?php

use Proprietario\SudoMakers\core\Database;
use Proprietario\SudoMakers\core\RecommendationEngine;


session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/RecommendationEngine.php';

$title = "Libri Trending";
$pdo = Database::getInstance()->getConnection();
$engine = new RecommendationEngine($pdo);

// Aggiorna statistiche trending (esegui periodicamente via cron in produzione)
if (!isset($_SESSION['last_trend_update']) ||
    time() - $_SESSION['last_trend_update'] > 3600) { // ogni ora
    $engine->updateTrendingStats();
    $_SESSION['last_trend_update'] = time();
}

// Ottieni libri trending
$libri_trending = $engine->getTrendingBooks(24);

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
        return ['icona' => '🔥', 'testo' => 'In forte crescita', 'classe' => 'trending-hot'];
    } elseif ($velocita > 20) {
        return ['icona' => '📈', 'testo' => 'In crescita', 'classe' => 'trending-up'];
    } elseif ($velocita > 0) {
        return ['icona' => '⭐', 'testo' => 'Popolare', 'classe' => 'trending-stable'];
    } else {
        return ['icona' => '', 'testo' => 'Sempre apprezzato', 'classe' => 'trending-classic'];
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
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="catalogo-container">
    <div class="trending-header">
        <h1>🔥 Libri Trending</h1>
        <p>I libri più richiesti e apprezzati in questo momento</p>
    </div>

    <?php if (!empty($libri_trending)): ?>
        <div class="filters-bar">
            <div class="filter-group">
                <label for="periodo">Periodo:</label>
                <select id="periodo">
                    <option value="7">Ultimi 7 giorni</option>
                    <option value="30" selected>Ultimi 30 giorni</option>
                    <option value="all">Tutto il tempo</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="categoria-filter">Categoria:</label>
                <select id="categoria-filter" onchange="filterByCategory(this.value)">
                    <option value="">Tutte le categorie</option>
                    <?php
                    $categorie = array_unique(array_column($libri_trending, 'categoria'));
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
            $rank = 1;
            foreach ($libri_trending as $libro):
                $disponibilita = getDisponibilita(
                    $libro['copie_disponibili'],
                    $libro['totale_copie'],
                    $libro['copie_smarrite']
                );
                $trending_badge = getTrendingBadge($libro['velocita_trend']);
                ?>
                <div class="libro-card"
                     data-categoria="<?= htmlspecialchars($libro['categoria'] ?? '') ?>"
                     data-id-libro="<?= $libro['id_libro'] ?>">
                    <a href="../catalog/dettaglio_libro.php?id=<?= $libro['id_libro'] ?>&from=trending" class="card-link">
                        <div class="libro-copertina">
                            <?php if ($libro['immagine_copertina_url']): ?>
                                <img src="<?= htmlspecialchars($libro['immagine_copertina_url']) ?>"
                                     alt="Copertina di <?= htmlspecialchars($libro['titolo']) ?>">
                            <?php else: ?>
                                <div class="copertina-placeholder">
                                    <span>📖</span>
                                </div>
                            <?php endif; ?>

                            <div class="trend-rank <?= $rank <= 3 ? 'top-3' : '' ?>">
                                <?= $rank ?>
                            </div>

                            <div class="trending-badge-overlay <?= $trending_badge['classe'] ?>">
                                <?= $trending_badge['icona'] ?> <?= $trending_badge['testo'] ?>
                            </div>

                            <div class="disponibilita-badge <?= $disponibilita['classe'] ?>" style="top: 60px">
                                <?= $disponibilita['testo'] ?>
                            </div>
                        </div>

                        <div class="libro-info">
                            <h3 class="libro-titolo"><?= htmlspecialchars($libro['titolo']) ?></h3>
                            <p class="libro-autore"><?= htmlspecialchars($libro['autori'] ?? 'Autore sconosciuto') ?></p>

                            <div class="libro-rating">
                                <?php if (isset($libro['rating_medio']) && $libro['rating_medio']): 
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
                <?php
                $rank++;
            endforeach;
            ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <p>Nessun dato trending disponibile al momento</p>
        </div>
    <?php endif; ?>
</div>

<script src="../../public/assets/js/trackInteraction.js"></script>

<script>
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
            rankBadge.textContent = index + 1;
            
            // Aggiorna classe top-3
            if (index < 3) {
                rankBadge.classList.add('top-3');
            } else {
                rankBadge.classList.remove('top-3');
            }
        }
    });
}

// Filtro per periodo (placeholder per futura implementazione con AJAX)
document.getElementById('periodo')?.addEventListener('change', function() {
    const periodo = this.value;
    console.log('Periodo selezionato:', periodo);
    
    // TODO: Implementare chiamata AJAX per ricaricare i dati con il nuovo periodo
    // Per ora mostra solo un messaggio
    alert('Filtro per periodo in fase di implementazione. Mostra sempre ultimi 30 giorni.');
});
</script>

</body>
</html>