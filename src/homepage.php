<?php
use Proprietario\SudoMakers\Database;
use Proprietario\SudoMakers\RecommendationEngine;

session_start();
require_once "Database.php";
require_once "RecommendationEngine.php";

$title = "Catalogo Biblioteca";
$pdo = Database::getInstance()->getConnection();

/* ============================================================
   SEZIONE RACCOMANDAZIONI PERSONALIZZATE HOMEPAGE
   ============================================================ */

$raccomandazioni_homepage = [];

if (isset($_SESSION['id_utente'])) {
    $engine = new RecommendationEngine($pdo);

    // Tentativo di recupero cache
    $cached = $engine->getCachedRecommendations($_SESSION['id_utente'], 6);

    if (!empty($cached)) {
        $raccomandazioni_homepage = $cached;
    } else {
        // Generazione nuove raccomandazioni
        $recs_raw = $engine->generateRecommendations($_SESSION['id_utente'], 6);

        foreach ($recs_raw as $rec) {
            $libro = $rec['libro'];
            $libro['motivo_raccomandazione'] = implode('; ', $rec['motivi']);
            $libro['score'] = $rec['score'];
            $raccomandazioni_homepage[] = $libro;
        }
    }

    // Recupero informazioni copie
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
   QUERY CATALOGO COMPLETO (codice originale)
   ============================================================ */

$query = "
    SELECT 
        l.*,
        GROUP_CONCAT(CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori,
        COUNT(c.id_copia) as totale_copie,
        SUM(CASE WHEN c.disponibile = 1 AND c.stato_fisico != 'smarrito' THEN 1 ELSE 0 END) as copie_disponibili,
        SUM(CASE WHEN c.stato_fisico = 'smarrito' THEN 1 ELSE 0 END) as copie_smarrite
    FROM libro l
    LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
    LEFT JOIN autore a ON la.id_autore = a.id_autore
    LEFT JOIN copia c ON l.id_libro = c.id_libro
    GROUP BY l.id_libro
    ORDER BY l.titolo
";

$stmt = $pdo->query($query);
$libri = $stmt->fetchAll();

// Funzione disponibilità (originale)
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

    <link rel="stylesheet" href="../style/privateAreaStyle.css">
    <link rel="stylesheet" href="../style/catalogoStyle.css">
    <link rel="stylesheet" href="../style/ricercaStyle.css">

    <style>
        /* ============================================================
           STILI WIDGET RACCOMANDAZIONI PERSONALIZZATE
           ============================================================ */

        .raccomandazioni-widget {
            background: #1f1f21;
            border: 2px solid #303033;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 40px;
        }

        .widget-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .widget-header h2 {
            margin: 0;
            font-size: 26px;
            color: #ebebed;
        }

        .widget-header a {
            color: #0c8a1f;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }

        .widget-header a:hover {
            color: #0a6f18;
            text-decoration: underline;
        }

        .recommendations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 20px;
        }

        .quick-links {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .quick-link {
            padding: 12px 20px;
            background: #2a2a2c;
            border: 2px solid #303033;
            border-radius: 8px;
            text-decoration: none;
            color: #ebebed;
            font-size: 14px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .quick-link:hover {
            background: #3b3b3d;
            border-color: #0c8a1f;
            transform: translateY(-2px);
        }
    </style>
</head>

<body>
<?php require_once 'navigation.php'; ?>

<div class="catalogo-container">

    <!-- ============================================================
         QUICK LINKS
         ============================================================ -->
    <div class="quick-links">
        <a href="raccomandazioni.php" class="quick-link">✨ Consigliati per te</a>
        <a href="trending.php" class="quick-link">🔥 Trending</a>
        <a href="ricerca_avanzata.php" class="quick-link">🔍 Ricerca avanzata</a>
    </div>

    <!-- ============================================================
         WIDGET RACCOMANDAZIONI
         ============================================================ -->
    <?php if (!empty($raccomandazioni_homepage)): ?>
        <div class="raccomandazioni-widget">
            <div class="widget-header">
                <h2>✨ Consigliati per te</h2>
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
                    <div class="libro-card-mini">
                        <a href="dettaglio_libro.php?id=<?= $libro['id_libro'] ?>&from=homepage_widget">
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

                            <?php if (!empty($libro['rating_medio'])): ?>
                                <div style="padding: 0 12px 8px; font-size: 12px; color: #ffa500;">
                                    ⭐ <?= round($libro['rating_medio'], 1) ?>
                                </div>
                            <?php endif; ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         CATALOGO ORIGINALE
         ============================================================ -->
    <div class="catalogo-header">
        <h1>Catalogo Biblioteca</h1>
        <p class="subtitle">Esplora la nostra collezione di libri</p>
    </div>

    <div class="catalogo-grid">
        <?php foreach ($libri as $libro):
            $disp = getDisponibilita($libro['copie_disponibili'], $libro['totale_copie'], $libro['copie_smarrite']);
            ?>
            <div class="libro-card">
                <a href="dettaglio_libro.php?id=<?= $libro['id_libro'] ?>"
                   class="card-link"
                   data-libro-id="<?= $libro['id_libro'] ?>">

                    <div class="libro-copertina">
                        <?php if ($libro['immagine_copertina_url']): ?>
                            <img src="<?= htmlspecialchars($libro['immagine_copertina_url']) ?>"
                                 alt="Copertina di <?= htmlspecialchars($libro['titolo']) ?>">
                        <?php else: ?>
                            <div class="copertina-placeholder"><span>📖</span></div>
                        <?php endif; ?>

                        <div class="disponibilita-badge <?= $disp['classe'] ?>">
                            <?= $disp['testo'] ?>
                        </div>
                    </div>

                    <div class="libro-info">
                        <h3 class="libro-titolo"><?= htmlspecialchars($libro['titolo']) ?></h3>
                        <p class="libro-autore"><?= htmlspecialchars($libro['autori'] ?? 'Autore sconosciuto') ?></p>

                        <div class="libro-meta">
                            <span class="meta-item"><strong>Anno:</strong> <?= $libro['anno_pubblicazione'] ?? 'N/D' ?></span>
                            <span class="meta-item"><strong>Categoria:</strong> <?= htmlspecialchars($libro['categoria'] ?? 'N/D') ?></span>
                        </div>

                        <div class="libro-copie">
                            <span class="copie-info">
                                <?= $libro['copie_disponibili'] ?>
                                di <?= $libro['totale_copie'] - $libro['copie_smarrite'] ?>
                                disponibili
                            </span>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($libri)): ?>
        <div class="empty-state">
            <p>Nessun libro presente nel catalogo</p>
        </div>
    <?php endif; ?>
</div>

<!-- TRACKING -->
<script src="scripts/trackInteraction.js"></script>

</body>
</html>
