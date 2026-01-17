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
$itemsPerPage = 10;

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
    <link rel="stylesheet" href="../../public/assets/css/widgetsStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/paginationStyle.css">
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="catalogo-container">
    <div class="catalogo-header">
        <h1>Trending Now</h1>
        <p class="subtitle">I libri più popolari del momento</p>
    </div>

    <?php if (!empty($trending_paginate)): ?>
        <div class="catalogo-grid">
            <?php
            $rank_offset = ($page - 1) * $itemsPerPage;
            foreach($trending_paginate as $index => $libro):
                $rank = $rank_offset + $index + 1;
                $disponibilita = getDisponibilita($libro['copie_disponibili'], $libro['totale_copie'], $libro['copie_smarrite']);
                $trending_badge = getTrendingBadge($libro['velocita_trend']);
                ?>
                <div class="libro-card">
                    <a href="../catalog/dettaglio_libro.php?id=<?= $libro['id_libro'] ?>&from=trending" class="card-link" data-libro-id="<?= $libro['id_libro'] ?>">
                        <div class="libro-copertina" style="position: relative;">
                            <?php if($libro['immagine_copertina_url']): ?>
                                <img src="<?= htmlspecialchars($libro['immagine_copertina_url']) ?>"
                                     alt="Copertina di <?= htmlspecialchars($libro['titolo']) ?>">
                            <?php else: ?>
                                <div class="copertina-placeholder">
                                    <span>📖</span>
                                </div>
                            <?php endif; ?>

                            <!-- Badge Trending -->
                            <div class="trending-badge-card <?= $trending_badge['classe'] ?>" style="position: absolute; top: 10px; left: 10px; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; z-index: 10; box-shadow: 0 2px 8px rgba(0,0,0,0.3);">
                                <?= $trending_badge['icona'] ?> <?= $trending_badge['testo'] ?>
                            </div>

                            <!-- Badge Disponibilità -->
                            <div class="disponibilita-badge <?= $disponibilita['classe'] ?>">
                                <?= $disponibilita['testo'] ?>
                            </div>

                            <!-- Numero Ranking -->
                            <div style="position: absolute; top: 10px; right: 10px; width: 35px; height: 35px; background: <?= $rank <= 3 ? '#FFD700' : ($rank <= 10 ? '#0c8a1f' : '#3b3b3d') ?>; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: bold; box-shadow: 0 2px 8px rgba(0,0,0,0.4); z-index: 10;">
                                <?= $rank ?>
                            </div>
                        </div>

                        <div class="libro-info">
                            <h3 class="libro-titolo"><?= htmlspecialchars($libro['titolo']) ?></h3>
                            <p class="libro-autore"><?= htmlspecialchars($libro['autori'] ?? 'Autore sconosciuto') ?></p>

                            <div class="libro-rating">
                                <?php if($libro['rating_medio']):
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
                                else: ?>
                                    <span style="color: #666;">Nessuna recensione</span>
                                <?php endif; ?>
                            </div>

                            <div class="libro-meta">
                                <span class="meta-item">
                                    <strong>Categoria:</strong> <?= htmlspecialchars($libro['categoria'] ?? 'N/D') ?>
                                </span>
                            </div>

                            <!-- Statistiche Trending -->
                            <div style="display: flex; gap: 15px; padding: 8px 0; margin-top: 8px; border-top: 1px solid #2a2a2c; font-size: 12px; color: #aaa;">
                                <span>👁️ <strong style="color: #0c8a1f;"><?= $libro['click_ultimi_7_giorni'] ?></strong> visualizzazioni</span>
                                <span>📚 <strong style="color: #0c8a1f;"><?= $libro['prestiti_ultimi_7_giorni'] ?></strong> prestiti</span>
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

        <!-- PAGINAZIONE -->
        <?php echo $pagination->render('trending.php'); ?>

    <?php else: ?>
        <div class="empty-state">
            <p>Nessun libro trending al momento</p>
        </div>
    <?php endif; ?>
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
                    fonte: 'trending',
                })
            }).catch(console.error);
        });
    });
</script>

</body>
</html>