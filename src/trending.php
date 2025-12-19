<?php
use Proprietario\SudoMakers\Database;
use Proprietario\SudoMakers\RecommendationEngine;


session_start();
require_once "Database.php";
require_once "RecommendationEngine.php";

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
        return ['icona' => '📚', 'testo' => 'Sempre apprezzato', 'classe' => 'trending-classic'];
    }
}
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../public/assets/css/privateAreaStyle.css">
    <link rel="stylesheet" href="../public/assets/css/catalogoStyle.css">
    <link rel="stylesheet" href="../public/assets/css/ricercaStyle.css">
    <style>
        .trending-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px 20px;
            background: linear-gradient(135deg, #1f1f21 0%, #2a2a2c 100%);
            border: 2px solid #303033;
            border-radius: 10px;
        }

        .trending-header h1 {
            font-size: 32px;
            margin: 0 0 15px;
            color: #ebebed;
        }

        .trending-header p {
            color: #888;
            font-size: 16px;
            margin: 0;
        }

        .trending-badge-overlay {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
            z-index: 10;
        }

        .trending-hot {
            background: linear-gradient(135deg, #ff6b6b 0%, #ff5252 100%);
            color: white;
            animation: pulse 2s infinite;
        }

        .trending-up {
            background: linear-gradient(135deg, #ffa500 0%, #ff8c00 100%);
            color: white;
        }

        .trending-stable {
            background: linear-gradient(135deg, #0c8a1f 0%, #0a6f18 100%);
            color: white;
        }

        .trending-classic {
            background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
            color: white;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .trending-stats {
            background: #2a2a2c;
            padding: 12px 15px;
            border-radius: 6px;
            margin-top: 10px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            font-size: 12px;
        }

        .trend-stat {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #aaa;
        }

        .trend-stat strong {
            color: #ebebed;
        }

        .trend-rank {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 40px;
            height: 40px;
            background: #0c8a1f;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: bold;
            box-shadow: 0 2px 10px rgba(12, 138, 31, 0.4);
            z-index: 10;
        }

        .trend-rank.top-3 {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
            font-size: 22px;
        }

        .filters-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            padding: 20px;
            background: #1f1f21;
            border: 2px solid #303033;
            border-radius: 10px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-group label {
            font-size: 14px;
            color: #ebebed;
        }

        .filter-group select {
            padding: 8px 12px;
            border-radius: 6px;
            border: 2px solid #303033;
            background: #2a2a2c;
            color: #ebebed;
            font-size: 14px;
            cursor: pointer;
            transition: border-color 0.3s;
        }

        .filter-group select:focus {
            outline: none;
            border-color: #0c8a1f;
        }
    </style>
</head>
<body>
<?php require_once 'navigation.php'; ?>

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
                    <a href="dettaglio_libro.php?id=<?= $libro['id_libro'] ?>&from=trending" class="card-link">
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

                            <div class="disponibilita-badge <?= $disponibilita['classe'] ?>">
                                <?= $disponibilita['testo'] ?>
                            </div>
                        </div>

                        <div class="libro-info">
                            <h3 class="libro-titolo"><?= htmlspecialchars($libro['titolo']) ?></h3>
                            <p class="libro-autore"><?= htmlspecialchars($libro['autori'] ?? 'Autore sconosciuto') ?></p>

                            <?php if (isset($libro['rating_medio']) && $libro['rating_medio']): ?>
                                <div class="libro-rating">
                                    ⭐ <?= round($libro['rating_medio'], 1) ?>
                                </div>
                            <?php endif; ?>

                            <div class="libro-meta">
                                <span class="meta-item">
                                    <strong>Anno:</strong> <?= $libro['anno_pubblicazione'] ?? 'N/D' ?>
                                </span>
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
                            📅 <strong class="prenotazioni-count"><?= $libro['prenotazioni_attive'] ?></strong> prenotazioni
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

<script src="scripts/trackInteraction.js"></script>
</body>
</html>