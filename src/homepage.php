<?php
use Proprietario\SudoMakers\Database;

session_start();
require_once "Database.php";

$title = "Catalogo Biblioteca";
$pdo = Database::getInstance()->getConnection();

// Query per ottenere tutti i libri con informazioni sugli autori e disponibilità
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

// Funzione per determinare lo stato di disponibilità
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
</head>
<body>
<?php require_once 'navigation.php'; ?>

<div class="catalogo-container">
    <div class="catalogo-header">
        <h1>Catalogo Biblioteca</h1>
        <p class="subtitle">Esplora la nostra collezione di libri</p>
    </div>

    <div class="catalogo-grid">
        <?php foreach($libri as $libro):
            $disponibilita = getDisponibilita($libro['copie_disponibili'], $libro['totale_copie'], $libro['copie_smarrite']);
            ?>
            <div class="libro-card">
                <a href="dettaglio_libro.php?id=<?= $libro['id_libro'] ?>" class="card-link" data-libro-id="<?= $libro['id_libro'] ?>">
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
            </div>
        <?php endforeach; ?>
    </div>

    <?php if(empty($libri)): ?>
        <div class="empty-state">
            <p>Nessun libro presente nel catalogo</p>
        </div>
    <?php endif; ?>
</div>

<!-- SCRIPT PER TRACCIARE I CLICK -->
<script>
    document.querySelectorAll('.card-link').forEach(link => {
        link.addEventListener('click', function(event) {
            const libroId = this.dataset.libroId;
            const idUtente = <?= json_encode($_SESSION['id_utente'] ?? null) ?>;

            if (!idUtente) return;

            fetch('/track_interaction.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_libro: libroId,
                    tipo: 'click',
                    fonte: 'catalogo',   // esempio di fonte contestuale
                })
            }).catch(console.error);
        });
    });
</script>

</body>
</html>
