<?php

use Proprietario\SudoMakers\core\Database;
use Proprietario\SudoMakers\core\RecommendationEngine;

session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/RecommendationEngine.php';
require_once __DIR__ . '/../utils/check_permissions.php';
require_once __DIR__ . '/../utils/pagination_helper.php';

$pdo = Database::getInstance()->getConnection();
$title = "Raccomandazioni per te";

// Redirect se non autenticato
if (!isset($_SESSION['id_utente'])) {
    header("Location: ../auth/login.php");
    exit;
}

/* ============================================================
   PAGINAZIONE
   ============================================================ */
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$itemsPerPage = 10;

$engine = new RecommendationEngine($pdo);

// Genera raccomandazioni complete
$recs_raw = $engine->generateRecommendations($_SESSION['id_utente'], 50);

$raccomandazioni = [];
foreach ($recs_raw as $rec) {
    $libro = $rec['libro'];
    $libro['motivo_raccomandazione'] = implode('; ', $rec['motivi']);
    $libro['score'] = $rec['score'];
    $raccomandazioni[] = $libro;
}

// Arricchisci con info copie
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

// Calcola paginazione
$totalItems = count($raccomandazioni);
$pagination = new PaginationHelper($totalItems, $itemsPerPage, $page);

// Estrai solo gli item della pagina corrente
$raccomandazioni_paginate = array_slice($raccomandazioni, $pagination->getOffset(), $pagination->getLimit());

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
    <link rel="stylesheet" href="../../public/assets/css/recommendationStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/paginationStyle.css">
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="catalogo-container">
    <div class="raccomandazioni-header">
        <h1>📚 Raccomandazioni per te</h1>
        <p>Libri selezionati in base alle tue preferenze e alla tua cronologia di lettura</p>
    </div>

    <?php if (!empty($raccomandazioni_paginate)): ?>
        <div class="catalogo-grid">
            <?php foreach($raccomandazioni_paginate as $libro):
                $disponibilita = getDisponibilita($libro['copie_disponibili'], $libro['totale_copie'], $libro['copie_smarrite']);
                ?>
                <div class="libro-card">
                    <a href="../catalog/dettaglio_libro.php?id=<?= $libro['id_libro'] ?>&from=raccomandazioni" class="card-link" data-libro-id="<?= $libro['id_libro'] ?>">
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

                            <?php if (!empty($libro['motivo_raccomandazione'])): ?>
                                <div class="libro-motivo">
                                    <strong>Perché te lo consigliamo:</strong>
                                    <?= htmlspecialchars($libro['motivo_raccomandazione']) ?>
                                </div>
                            <?php endif; ?>

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
        <?php echo $pagination->render('raccomandazioni.php'); ?>

    <?php else: ?>
        <div class="empty-recommendations">
            <h2>📚 Ancora nessuna raccomandazione</h2>
            <p>Inizia a leggere qualche libro per ricevere raccomandazioni personalizzate!</p>
            <a href="homepage.php">Esplora il catalogo</a>
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
                    fonte: 'raccomandazioni',
                })
            }).catch(console.error);
        });
    });
</script>

</body>
</html>