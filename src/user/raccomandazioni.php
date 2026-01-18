<?php

use Proprietario\SudoMakers\core\Database;
use Proprietario\SudoMakers\core\RecommendationEngine;

session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/RecommendationEngine.php';
require_once __DIR__ . '/../utils/pagination_helper.php';

$title = "Consigliati per te";

if (!isset($_SESSION['id_utente'])) {
    header("Location: ../auth/login.php");
    exit;
}

$pdo = Database::getInstance()->getConnection();
$engine = new RecommendationEngine($pdo);

/* ============================================================
   PAGINAZIONE
   ============================================================ */
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$itemsPerPage = 10;

// Prova a ottenere raccomandazioni dalla cache
$raccomandazioni_cached = $engine->getCachedRecommendations($_SESSION['id_utente'], 100);

// Se non ci sono in cache o sono vecchie, genera nuove
if (empty($raccomandazioni_cached)) {
    $raccomandazioni_raw = $engine->generateRecommendations($_SESSION['id_utente'], 100);

    // Trasforma il formato per la visualizzazione
    $raccomandazioni_all = [];
    foreach ($raccomandazioni_raw as $rec) {
        $libro = $rec['libro'];
        $libro['motivo_raccomandazione'] = implode('; ', $rec['motivi']);
        $libro['score'] = $rec['score'];
        $raccomandazioni_all[] = $libro;
    }
} else {
    $raccomandazioni_all = $raccomandazioni_cached;
}

// Calcola paginazione
$totalItems = count($raccomandazioni_all);
$pagination = new PaginationHelper($totalItems, $itemsPerPage, $page);

// Estrai solo gli item della pagina corrente
$raccomandazioni = array_slice($raccomandazioni_all, $pagination->getOffset(), $pagination->getLimit());

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

// Recupera info copie per ogni libro
foreach ($raccomandazioni as &$libro) {
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
    <link rel="stylesheet" href="../../public/assets/css/raccomandationStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/paginationStyle.css">
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="catalogo-container">
    <div class="catalogo-header">
        <h1>Consigliati per te</h1>
        <p class="subtitle">Abbiamo selezionato questi libri in base ai tuoi interessi e alle tue letture</p>
    </div>

    <?php if (!empty($raccomandazioni)): ?>
        <div class="catalogo-grid raccomandazioni-section">
            <?php foreach ($raccomandazioni as $libro):
                $disponibilita = getDisponibilita(
                        $libro['copie_disponibili'],
                        $libro['totale_copie'],
                        $libro['copie_smarrite']
                );
                ?>
                <div class="libro-card" data-book-id="<?= $libro['id_libro'] ?>">
                    <a href="../catalog/dettaglio_libro.php?id=<?= $libro['id_libro'] ?>&from=raccomandazioni" class="card-link">
                        <div class="libro-copertina">
                            <?php if ($libro['immagine_copertina_url']): ?>
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

                    <?php if (isset($libro['motivo_raccomandazione'])): ?>
                        <div class="libro-motivo">
                            <strong>Perché questo libro:</strong>
                            <?= htmlspecialchars($libro['motivo_raccomandazione']) ?>
                        </div>
                    <?php endif; ?>

                    <div class="feedback-buttons">
                        <button class="feedback-btn" data-feedback="thumbs_up" onclick="sendFeedback(<?= $libro['id_libro'] ?>, 'thumbs_up', this)">
                            👍 Mi piace
                        </button>
                        <button class="feedback-btn" data-feedback="thumbs_down" onclick="sendFeedback(<?= $libro['id_libro'] ?>, 'thumbs_down', this)">
                            👎 Non mi interessa
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- PAGINAZIONE -->
        <?php echo $pagination->render('raccomandazioni.php'); ?>

        <div class="refresh-recommendations">
            <a href="?refresh=1" class="refresh-btn">Aggiorna raccomandazioni</a>
        </div>
    <?php else: ?>
        <div class="empty-recommendations">
            <h2>Inizia a esplorare!</h2>
            <p>Non abbiamo ancora abbastanza informazioni per consigliarti libri personalizzati.</p>
            <p>Prendi in prestito qualche libro o naviga il catalogo per ricevere raccomandazioni su misura per te!</p>
            <a href="homepage.php">Esplora il catalogo</a>
        </div>
    <?php endif; ?>
</div>

<!-- Tracking gestito da trackInteraction.js incluso in navigation.php -->

<script>
    function sendFeedback(bookId, feedback, button) {
        fetch('../api/save_feedback.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id_libro: bookId,
                feedback: feedback
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Aggiorna UI
                    const card = button.closest('.libro-card');
                    const buttons = card.querySelectorAll('.feedback-btn');

                    buttons.forEach(btn => {
                        btn.classList.remove('active-up', 'active-down');
                    });

                    if (feedback === 'thumbs_up') {
                        button.classList.add('active-up');
                    } else {
                        button.classList.add('active-down');
                        // Opzionale: nascondi la card dopo feedback negativo
                        setTimeout(() => {
                            card.style.opacity = '0.5';
                            card.style.pointerEvents = 'none';
                        }, 500);
                    }
                }
            })
            .catch(error => {
                console.error('Errore invio feedback:', error);
                alert('Errore nell\'invio del feedback');
            });
    }

    // Forza refresh se richiesto
    <?php if (isset($_GET['refresh'])): ?>
    fetch('../api/refresh_recommendations.php', {
        method: 'POST'
    })
        .then(() => {
            window.location.href = 'raccomandazioni.php';
        });
    <?php endif; ?>
</script>

</body>
</html>