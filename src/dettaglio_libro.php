<?php
use Proprietario\SudoMakers\Database;

session_start();
require_once "Database.php";
require_once "check_permissions.php";

$pdo = Database::getInstance()->getConnection();

// Verifica che ci sia un ID libro
if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    header("Location: homepage.php");
    exit;
}

$id_libro = (int)$_GET['id'];

// Gestione invio recensione
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['id_utente'])){
    $voto = (int)$_POST['voto'];
    $testo = trim($_POST['testo']);

    if($voto >= 1 && $voto <= 5){
        try {
            $stmt = $pdo->prepare("INSERT INTO recensione (id_libro, id_utente, voto, testo) 
                                   VALUES (:id_libro, :id_utente, :voto, :testo)
                                   ON DUPLICATE KEY UPDATE voto = :voto, testo = :testo, data_recensione = NOW()");
            $stmt->execute([
                'id_libro' => $id_libro,
                'id_utente' => $_SESSION['id_utente'],
                'voto' => $voto,
                'testo' => $testo
            ]);
            header("Location: dettaglio_libro.php?id=$id_libro&success=1");
            exit;
        } catch(Exception $e) {
            $errore = "Errore nell'invio della recensione.";
        }
    }
}

// Query per ottenere dettagli libro
$query = "
    SELECT 
        l.*,
        GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori,
        COUNT(DISTINCT c.id_copia) as totale_copie,
        SUM(CASE WHEN c.disponibile = 1 AND c.stato_fisico != 'smarrito' THEN 1 ELSE 0 END) as copie_disponibili,
        SUM(CASE WHEN c.stato_fisico = 'smarrito' THEN 1 ELSE 0 END) as copie_smarrite,
        AVG(r.voto) as media_voti,
        COUNT(DISTINCT r.id_recensione) as numero_recensioni
    FROM libro l
    LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
    LEFT JOIN autore a ON la.id_autore = a.id_autore
    LEFT JOIN copia c ON l.id_libro = c.id_libro
    LEFT JOIN recensione r ON l.id_libro = r.id_libro
    WHERE l.id_libro = :id_libro
    GROUP BY l.id_libro
";

$stmt = $pdo->prepare($query);
$stmt->execute(['id_libro' => $id_libro]);
$libro = $stmt->fetch();

if(!$libro){
    header("Location: homepage.php");
    exit;
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

$disponibilita = getDisponibilita($libro['copie_disponibili'], $libro['totale_copie'], $libro['copie_smarrite']);

// Recupera recensioni
$stmt = $pdo->prepare("
    SELECT r.*, u.nome, u.cognome, u.foto 
    FROM recensione r
    JOIN utente u ON r.id_utente = u.id_utente
    WHERE r.id_libro = :id_libro
    ORDER BY r.data_recensione DESC
");
$stmt->execute(['id_libro' => $id_libro]);
$recensioni = $stmt->fetchAll();

// Verifica se l'utente ha già recensito
$ha_recensito = false;
if(isset($_SESSION['id_utente'])){
    $stmt = $pdo->prepare("SELECT * FROM recensione WHERE id_libro = :id_libro AND id_utente = :id_utente");
    $stmt->execute(['id_libro' => $id_libro, 'id_utente' => $_SESSION['id_utente']]);
    $ha_recensito = $stmt->fetch();
}

// Libri correlati (stesso genere)
$stmt = $pdo->prepare("
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
    WHERE l.categoria = :categoria AND l.id_libro != :id_libro
    GROUP BY l.id_libro
    ORDER BY RAND()
    LIMIT 5
");
$stmt->execute(['categoria' => $libro['categoria'], 'id_libro' => $id_libro]);
$libri_correlati = $stmt->fetchAll();

$title = $libro['titolo'];
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="../style/privateAreaStyle.css">
    <link rel="stylesheet" href="../style/catalogoStyle.css">
    <link rel="stylesheet" href="../style/dettaglioLibroStyle.css">
</head>
<body>
<?php require_once 'navigation.php'; ?>

<div class="dettaglio-container">
    <!-- Sezione principale libro -->
    <div class="libro-dettaglio">
        <div class="libro-copertina-grande">
            <?php if($libro['immagine_copertina_url']): ?>
                <img src="<?= htmlspecialchars($libro['immagine_copertina_url']) ?>"
                     alt="Copertina di <?= htmlspecialchars($libro['titolo']) ?>">
            <?php else: ?>
                <div class="copertina-placeholder-grande">📖</div>
            <?php endif; ?>
        </div>

        <div class="libro-informazioni">
            <div class="disponibilita-badge <?= $disponibilita['classe'] ?>">
                <?= $disponibilita['testo'] ?>
            </div>

            <h1><?= htmlspecialchars($libro['titolo']) ?></h1>
            <p class="autore-grande"><?= htmlspecialchars($libro['autori'] ?? 'Autore sconosciuto') ?></p>

            <?php if($libro['media_voti']): ?>
                <div class="rating-display">
                    <?php
                    $media = round($libro['media_voti'], 1);
                    for($i = 1; $i <= 5; $i++):
                        if($i <= floor($media)): ?>
                            <span class="star filled">★</span>
                        <?php elseif($i == ceil($media) && $media - floor($media) >= 0.5): ?>
                            <span class="star half">★</span>
                        <?php else: ?>
                            <span class="star">☆</span>
                        <?php endif;
                    endfor; ?>
                    <span class="rating-text"><?= $media ?> (<?= $libro['numero_recensioni'] ?> recensioni)</span>
                </div>
            <?php endif; ?>

            <div class="info-grid">
                <div class="info-item">
                    <strong>Editore:</strong> <?= htmlspecialchars($libro['editore'] ?? 'N/D') ?>
                </div>
                <div class="info-item">
                    <strong>Anno:</strong> <?= $libro['anno_pubblicazione'] ?? 'N/D' ?>
                </div>
                <div class="info-item">
                    <strong>ISBN:</strong> <?= htmlspecialchars($libro['isbn'] ?? 'N/D') ?>
                </div>
                <div class="info-item">
                    <strong>Categoria:</strong> <?= htmlspecialchars($libro['categoria'] ?? 'N/D') ?>
                </div>
                <div class="info-item">
                    <strong>Copie disponibili:</strong>
                    <span class="copie-count"><?= $libro['copie_disponibili'] ?> di <?= $libro['totale_copie'] - $libro['copie_smarrite'] ?></span>
                </div>
            </div>

            <?php if($libro['descrizione']): ?>
                <div class="descrizione">
                    <h3>Descrizione</h3>
                    <p><?= nl2br(htmlspecialchars($libro['descrizione'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sezione Recensioni -->
    <div class="recensioni-section">
        <h2>📝 Recensioni (<?= count($recensioni) ?>)</h2>

        <?php if(isset($_SESSION['id_utente'])): ?>
            <div class="aggiungi-recensione">
                <h3><?= $ha_recensito ? 'Modifica la tua recensione' : 'Aggiungi una recensione' ?></h3>
                <form method="POST" class="recensione-form">
                    <div class="voto-selector">
                        <label>Voto:</label>
                        <div class="stars-input">
                            <?php for($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="voto" value="<?= $i ?>" id="star<?= $i ?>"
                                    <?= ($ha_recensito && $ha_recensito['voto'] == $i) ? 'checked' : '' ?> required>
                                <label for="star<?= $i ?>">★</label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <textarea name="testo" placeholder="Scrivi la tua recensione..." rows="4"><?= $ha_recensito ? htmlspecialchars($ha_recensito['testo']) : '' ?></textarea>
                    <button type="submit" class="btn-invia"><?= $ha_recensito ? 'Aggiorna recensione' : 'Pubblica recensione' ?></button>
                </form>
            </div>
        <?php else: ?>
            <p class="login-prompt">🔒 <a href="login.php">Accedi</a> per lasciare una recensione</p>
        <?php endif; ?>

        <div class="lista-recensioni">
            <?php foreach($recensioni as $rec): ?>
                <div class="recensione-card">
                    <div class="recensione-header">
                        <img src="<?= htmlspecialchars($rec['foto']) ?>" alt="Foto profilo" class="recensione-avatar">
                        <div class="recensione-info">
                            <strong><?= htmlspecialchars($rec['nome'] . ' ' . $rec['cognome']) ?></strong>
                            <div class="recensione-stars">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <span class="star <?= $i <= $rec['voto'] ? 'filled' : '' ?>">★</span>
                                <?php endfor; ?>
                            </div>
                            <span class="recensione-data"><?= date('d/m/Y', strtotime($rec['data_recensione'])) ?></span>
                        </div>
                    </div>
                    <?php if($rec['testo']): ?>
                        <p class="recensione-testo"><?= nl2br(htmlspecialchars($rec['testo'])) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if(empty($recensioni)): ?>
                <p class="no-recensioni">Nessuna recensione ancora. Sii il primo a recensire questo libro!</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Libri Correlati -->
    <?php if(!empty($libri_correlati)): ?>
        <div class="correlati-section">
            <h2>📚 Altri libri di <?= htmlspecialchars($libro['categoria']) ?></h2>
            <div class="correlati-grid">
                <?php foreach($libri_correlati as $correlato):
                    $disp_cor = getDisponibilita($correlato['copie_disponibili'], $correlato['totale_copie'], $correlato['copie_smarrite']);
                    ?>
                    <div class="libro-card-mini">
                        <a href="dettaglio_libro.php?id=<?= $correlato['id_libro'] ?>">
                            <div class="copertina-mini">
                                <?php if($correlato['immagine_copertina_url']): ?>
                                    <img src="<?= htmlspecialchars($correlato['immagine_copertina_url']) ?>"
                                         alt="<?= htmlspecialchars($correlato['titolo']) ?>">
                                <?php else: ?>
                                    <div class="placeholder-mini">📖</div>
                                <?php endif; ?>
                                <div class="badge-mini <?= $disp_cor['classe'] ?>">
                                    <?= $disp_cor['testo'] ?>
                                </div>
                            </div>
                            <h4><?= htmlspecialchars($correlato['titolo']) ?></h4>
                            <p><?= htmlspecialchars($correlato['autori'] ?? 'Autore sconosciuto') ?></p>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
