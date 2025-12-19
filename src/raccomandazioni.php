<?php
use Proprietario\SudoMakers\Database;
use Proprietario\SudoMakers\RecommendationEngine;

session_start();
require_once "Database.php";
require_once "RecommendationEngine.php";

$title = "Consigliati per te";

if (!isset($_SESSION['id_utente'])) {
    header("Location: login.php");
    exit;
}

$pdo = Database::getInstance()->getConnection();
$engine = new RecommendationEngine($pdo);

// Prova a ottenere raccomandazioni dalla cache
$raccomandazioni_cached = $engine->getCachedRecommendations($_SESSION['id_utente'], 12);

// Se non ci sono in cache o sono vecchie, genera nuove
if (empty($raccomandazioni_cached)) {
    $raccomandazioni_raw = $engine->generateRecommendations($_SESSION['id_utente'], 12);

    // Trasforma il formato per la visualizzazione
    $raccomandazioni = [];
    foreach ($raccomandazioni_raw as $rec) {
        $libro = $rec['libro'];
        $libro['motivo_raccomandazione'] = implode('; ', $rec['motivi']);
        $libro['score'] = $rec['score'];
        $raccomandazioni[] = $libro;
    }
} else {
    $raccomandazioni = $raccomandazioni_cached;
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
    <link rel="stylesheet" href="../public/assets/css/privateAreaStyle.css">
    <link rel="stylesheet" href="../public/assets/css/catalogoStyle.css">
    <link rel="stylesheet" href="../public/assets/css/ricercaStyle.css">
    <style>
        .raccomandazioni-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px 20px;
            background: #1f1f21;
            border: 2px solid #303033;
            border-radius: 10px;
        }

        .raccomandazioni-header h1 {
            font-size: 32px;
            margin: 0 0 15px;
            color: #ebebed;
        }

        .raccomandazioni-header p {
            color: #888;
            font-size: 16px;
            margin: 0;
        }

        .libro-motivo {
            background: #2a2a2c;
            padding: 10px 15px;
            border-radius: 6px;
            margin-top: 10px;
            font-size: 13px;
            color: #aaa;
            border-left: 3px solid #0c8a1f;
        }

        .libro-motivo strong {
            color: #0c8a1f;
            display: block;
            margin-bottom: 5px;
        }

        .feedback-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #303033;
        }

        .feedback-btn {
            flex: 1;
            padding: 8px 12px;
            border: 2px solid #303033;
            background: transparent;
            color: #ebebed;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .feedback-btn:hover {
            border-color: #0c8a1f;
            background: #2a2a2c;
        }

        .feedback-btn.active-up {
            border-color: #0c8a1f;
            background: #0c8a1f;
            color: white;
        }

        .feedback-btn.active-down {
            border-color: #b30000;
            background: #b30000;
            color: white;
        }

        .empty-recommendations {
            text-align: center;
            padding: 60px 20px;
            background: #1f1f21;
            border: 2px solid #303033;
            border-radius: 10px;
        }

        .empty-recommendations h2 {
            margin: 0 0 20px;
            font-size: 24px;
            color: #ebebed;
        }

        .empty-recommendations p {
            color: #888;
            margin-bottom: 30px;
        }

        .empty-recommendations a {
            display: inline-block;
            padding: 12px 25px;
            background: #0c8a1f;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: background 0.3s;
        }

        .empty-recommendations a:hover {
            background: #0a6f18;
        }

        .refresh-recommendations {
            text-align: center;
            margin-top: 30px;
        }

        .refresh-btn {
            padding: 12px 25px;
            background: transparent;
            color: #ebebed;
            border: 2px solid #303033;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .refresh-btn:hover {
            background: #2a2a2c;
            border-color: #0c8a1f;
        }
    </style>
</head>
<body>
<?php require_once 'navigation.php'; ?>

<div class="catalogo-container">
    <div class="raccomandazioni-header">
        <h1>📚 Consigliati per te</h1>
        <p>Abbiamo selezionato questi libri in base ai tuoi interessi e alle tue letture</p>
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
                    <a href="dettaglio_libro.php?id=<?= $libro['id_libro'] ?>&from=raccomandazioni" class="card-link">
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

                    <?php if (isset($libro['motivo_raccomandazione'])): ?>
                        <div class="libro-motivo">
                            <strong>💡 Perché questo libro:</strong>
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

        <div class="refresh-recommendations">
            <a href="?refresh=1" class="refresh-btn">🔄 Aggiorna raccomandazioni</a>
        </div>
    <?php else: ?>
        <div class="empty-recommendations">
            <h2>🔍 Inizia a esplorare!</h2>
            <p>Non abbiamo ancora abbastanza informazioni per consigliarti libri personalizzati.</p>
            <p>Prendi in prestito qualche libro o naviga il catalogo per ricevere raccomandazioni su misura per te!</p>
            <a href="homepage.php">Esplora il catalogo</a>
        </div>
    <?php endif; ?>
</div>

<script src="../scripts/trackInteraction.js"></script>
<script>
    function sendFeedback(bookId, feedback, button) {
        fetch('save_feedback.php', {
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
    fetch('refresh_recommendations.php', {
        method: 'POST'
    })
        .then(() => {
            window.location.href = 'raccomandazioni.php';
        });
    <?php endif; ?>
</script>

</body>
</html>