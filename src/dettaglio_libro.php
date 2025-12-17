<?php
use Proprietario\SudoMakers\Database;

session_start();
require_once "Database.php";
require_once "check_permissions.php";
require_once "functions.php";

$pdo = Database::getInstance()->getConnection();

// Verifica ID libro
if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    header("Location: homepage.php");
    exit;
}

$id_libro = (int)$_GET['id'];

// ================== INVIO / MODIFICA RECENSIONE ==================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['id_utente'])){
    $voto = (int)$_POST['voto'];
    $testo = trim($_POST['testo']);

    if($voto >= 1 && $voto <= 5){
        try {
            $stmt = $pdo->prepare("
                INSERT INTO recensione (id_libro, id_utente, voto, testo)
                VALUES (:id_libro, :id_utente, :voto, :testo)
                ON DUPLICATE KEY UPDATE voto = :voto, testo = :testo, data_recensione = NOW()
            ");
            $stmt->execute([
                    'id_libro' => $id_libro,
                    'id_utente' => $_SESSION['id_utente'],
                    'voto'      => $voto,
                    'testo'     => $testo
            ]);

            header("Location: dettaglio_libro.php?id=$id_libro&success=1");
            exit;
        } catch(Exception $e){
            $errore = "Errore nell'invio della recensione.";
        }
    }
}

// ================== DETTAGLI LIBRO ==================
$query = "
    SELECT 
        l.*,
        GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') AS autori,
        COUNT(DISTINCT c.id_copia) AS totale_copie,
        SUM(CASE WHEN c.disponibile = 1 AND c.stato_fisico != 'smarrito' THEN 1 ELSE 0 END) AS copie_disponibili,
        SUM(CASE WHEN c.stato_fisico = 'smarrito' THEN 1 ELSE 0 END) AS copie_smarrite,
        AVG(r.voto) AS media_voti,
        COUNT(DISTINCT r.id_recensione) AS numero_recensioni
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

// ================== DISPONIBILITÀ ==================
function getDisponibilita($copie_disponibili, $totale_copie, $copie_smarrite){
    $copie_attive = $totale_copie - $copie_smarrite;

    if($copie_attive <= 0){
        return ['stato'=>'non_disponibile','testo'=>'Non disponibile','classe'=>'badge-red'];
    } elseif($copie_disponibili > 0){
        return ['stato'=>'disponibile','testo'=>'Disponibile','classe'=>'badge-green'];
    }
    return ['stato'=>'prenotabile','testo'=>'Prenotabile','classe'=>'badge-orange'];
}

$disponibilita = getDisponibilita(
        $libro['copie_disponibili'],
        $libro['totale_copie'],
        $libro['copie_smarrite']
);

// ================== PRENOTAZIONI ==================
$prenotazione_utente = null;
if(isset($_SESSION['id_utente'])){
    $stmt = $pdo->prepare("
        SELECT *
        FROM prenotazione
        WHERE id_utente = :id_utente
          AND id_libro = :id_libro
          AND stato IN ('attiva','disponibile')
    ");
    $stmt->execute([
            'id_utente' => $_SESSION['id_utente'],
            'id_libro'  => $id_libro
    ]);
    $prenotazione_utente = $stmt->fetch();
}

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM prenotazione
    WHERE id_libro = :id_libro AND stato = 'attiva'
");
$stmt->execute(['id_libro' => $id_libro]);
$persone_in_coda = $stmt->fetchColumn();

// ================== RECENSIONI ==================
$stmt = $pdo->prepare("
    SELECT r.*, u.nome, u.cognome, u.foto
    FROM recensione r
    JOIN utente u ON r.id_utente = u.id_utente
    WHERE r.id_libro = :id_libro
    ORDER BY r.data_recensione DESC
");
$stmt->execute(['id_libro'=>$id_libro]);
$recensioni = $stmt->fetchAll();

$ha_recensito = false;
if(isset($_SESSION['id_utente'])){
    $stmt = $pdo->prepare("
        SELECT *
        FROM recensione
        WHERE id_libro = :id_libro AND id_utente = :id_utente
    ");
    $stmt->execute([
            'id_libro'=>$id_libro,
            'id_utente'=>$_SESSION['id_utente']
    ]);
    $ha_recensito = $stmt->fetch();
}

// ================== LIBRI CORRELATI ==================
$stmt = $pdo->prepare("
    SELECT 
        l.*,
        GROUP_CONCAT(CONCAT(a.nome,' ',a.cognome) SEPARATOR ', ') AS autori,
        COUNT(c.id_copia) AS totale_copie,
        SUM(CASE WHEN c.disponibile=1 AND c.stato_fisico!='smarrito' THEN 1 ELSE 0 END) AS copie_disponibili,
        SUM(CASE WHEN c.stato_fisico='smarrito' THEN 1 ELSE 0 END) AS copie_smarrite
    FROM libro l
    LEFT JOIN libro_autore la ON l.id_libro=la.id_libro
    LEFT JOIN autore a ON la.id_autore=a.id_autore
    LEFT JOIN copia c ON l.id_libro=c.id_libro
    WHERE l.categoria=:categoria AND l.id_libro!=:id_libro
    GROUP BY l.id_libro
    ORDER BY RAND()
    LIMIT 5
");
$stmt->execute([
        'categoria'=>$libro['categoria'],
        'id_libro'=>$id_libro
]);
$libri_correlati = $stmt->fetchAll();

$title = $libro['titolo'];
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="../public/assets/css/privateAreaStyle.css">
    <link rel="stylesheet" href="../public/assets/css/catalogoStyle.css">
    <link rel="stylesheet" href="../public/assets/css/dettaglioLibroStyle.css">
    <style>
        .disponibilita-azioni {
            background: #1f1f21;
            border: 2px solid #303033;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 50px;
        }

        .disponibilita-azioni h2 {
            margin: 0 0 20px 0;
            color: #ebebed;
        }

        .stato-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .stato-box {
            background: #2a2a2c;
            padding: 20px;
            border-radius: 8px;
        }

        .stato-box p {
            margin: 0 0 10px 0;
            color: #888;
            font-size: 14px;
        }

        .stato-box .valore {
            font-size: 32px;
            font-weight: bold;
            margin: 0;
        }

        .coda-alert {
            background: #ff9800;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .coda-alert p {
            margin: 0;
            color: white;
            font-weight: bold;
        }

        .prenotazione-box {
            padding: 25px;
            border-radius: 8px;
            text-align: center;
        }

        .prenotazione-box.disponibile {
            background: #0c8a1f;
        }

        .prenotazione-box.in-coda {
            background: #2a2a2c;
        }

        .prenotazione-box h3 {
            margin: 0 0 15px 0;
            font-size: 24px;
        }

        .prenotazione-box p {
            margin: 0 0 20px 0;
            font-size: 16px;
        }

        .btn-prenota {
            padding: 15px 40px;
            background: #ff9800;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.3s;
        }

        .btn-prenota:hover {
            background: #ff7700;
            transform: scale(1.05);
        }

        .azioni-disponibile {
            text-align: center;
        }

        .azioni-disponibile p.success {
            color: #0c8a1f;
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 20px 0;
        }

        .azioni-non-auth {
            text-align: center;
            background: #2a2a2c;
            padding: 30px;
            border-radius: 8px;
        }

        .azioni-non-auth p {
            color: #ebebed;
            font-size: 16px;
            margin: 0 0 20px 0;
        }

        .btn-link {
            display: inline-block;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            transition: all 0.3s;
        }

        .btn-green {
            background: #0c8a1f;
            color: white;
        }

        .btn-green:hover {
            background: #0a6f18;
        }

        .btn-border {
            background: transparent;
            color: #ebebed;
            border: 2px solid #303033;
            margin-left: 10px;
        }

        .btn-border:hover {
            background: #3b3b3d;
        }

        @media (max-width: 768px) {
            .stato-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php require_once 'navigation.php'; ?>

<div class="dettaglio-container">
    <!-- GESTIONE MESSAGGI -->
    <?php if(isset($_GET['prenotazione']) && $_GET['prenotazione'] === 'success'): ?>
        <div class="alert alert-success">
            ✅ <strong>Prenotazione confermata!</strong><br>
            Posizione in coda: <strong>#<?= $_GET['posizione'] ?? '?' ?></strong><br>
            Tempo di attesa stimato: circa <strong><?= $_GET['stima'] ?? '?' ?> giorni</strong><br>
            Riceverai una notifica quando il libro sarà disponibile!
        </div>
    <?php endif; ?>

    <?php if(isset($_GET['error'])): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>

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
                <?php if(hasAnyRole(["bibliotecario", "amministratore"])){ ?>
                    <a href="gestione_copie.php?id_libro=<?= $id_libro ?>" class="btn-gestione-copie">
                        Gestisci Copie
                    </a>
                <?php } ?>
            </div>

            <?php if($libro['descrizione']): ?>
                <div class="descrizione">
                    <h3>Descrizione</h3>
                    <p><?= nl2br(htmlspecialchars($libro['descrizione'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- SEZIONE DISPONIBILITÀ E PRENOTAZIONE -->
    <div class="disponibilita-azioni">
        <h2>📍 Disponibilità e Azioni</h2>

        <div class="stato-grid">
            <div class="stato-box">
                <p>Stato Disponibilità</p>
                <div class="disponibilita-badge <?= $disponibilita['classe'] ?>" style="position: unset; font-size: 18px; padding: 10px 20px;">
                    <?= $disponibilita['testo'] ?>
                </div>
            </div>

            <div class="stato-box">
                <p>Copie Disponibili</p>
                <p class="valore" style="color: <?= $libro['copie_disponibili'] > 0 ? '#0c8a1f' : '#b30000' ?>;">
                    <?= $libro['copie_disponibili'] ?> / <?= $libro['totale_copie'] - $libro['copie_smarrite'] ?>
                </p>
            </div>
        </div>

        <?php if($persone_in_coda > 0): ?>
            <div class="coda-alert">
                <p>⏳ <?= $persone_in_coda ?> person<?= $persone_in_coda > 1 ? 'e' : 'a' ?> in coda per questo libro</p>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['id_utente'])): ?>
            <?php if($prenotazione_utente): ?>
                <!-- Utente ha già una prenotazione -->
                <?php if($prenotazione_utente['stato'] === 'disponibile'): ?>
                    <div class="prenotazione-box disponibile">
                        <h3 style="color: white;">✅ IL TUO LIBRO È PRONTO!</h3>
                        <p style="color: white;">
                            Ritiralo entro il <?= date('d/m/Y alle H:i', strtotime($prenotazione_utente['data_scadenza_ritiro'])) ?>
                        </p>
                        <a href="le_mie_prenotazioni.php" class="btn-link btn-green" style="background: white; color: #0c8a1f;">
                            Vedi Dettagli Prenotazione
                        </a>
                    </div>
                <?php else: ?>
                    <div class="prenotazione-box in-coda">
                        <h3 style="color: #ebebed;">🔖 Hai già prenotato questo libro</h3>
                        <p style="color: #ff9800; font-size: 24px; font-weight: bold;">
                            Posizione in coda: #<?= $prenotazione_utente['posizione_coda'] ?>
                        </p>
                        <p style="color: #888;">
                            Prenotato il <?= date('d/m/Y', strtotime($prenotazione_utente['data_prenotazione'])) ?>
                        </p>
                        <a href="le_mie_prenotazioni.php" class="btn-link btn-border">
                            Gestisci Prenotazioni
                        </a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Mostra azioni disponibili -->
                <?php if($disponibilita['stato'] === 'disponibile'): ?>
                    <div class="azioni-disponibile">
                        <p class="success">
                            ✓ Copie disponibili! Puoi prendere il libro in prestito direttamente
                        </p>
                        <?php if(hasAnyRole(['bibliotecario', 'amministratore'])): ?>
                            <a href="nuovo_prestito.php?libro=<?= $id_libro ?>" class="btn-link btn-green">
                                Crea Prestito
                            </a>
                        <?php else: ?>
                            <p style="color: #888; font-size: 14px;">
                                Rivolgiti al bancone per ritirare il libro
                            </p>
                        <?php endif; ?>
                    </div>
                <?php elseif($disponibilita['stato'] === 'prenotabile'): ?>
                    <div style="text-align: center;">
                        <p style="color: #ff9800; font-size: 16px; margin: 0 0 20px 0;">
                            Tutte le copie sono in prestito, ma puoi prenotare il libro!
                        </p>
                        <form method="POST" action="prenota_libro.php">
                            <input type="hidden" name="id_libro" value="<?= $id_libro ?>">
                            <button type="submit" class="btn-prenota">
                                🔖 Prenota questo libro
                            </button>
                        </form>
                        <p style="color: #888; font-size: 13px; margin: 15px 0 0 0;">
                            Sarai inserito in coda e riceverai una notifica quando sarà disponibile
                        </p>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 30px;">
                        <p style="color: #b30000; font-size: 18px; font-weight: bold; margin: 0;">
                            ❌ Libro non disponibile al momento
                        </p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php else: ?>
            <!-- Utente non autenticato -->
            <div class="azioni-non-auth">
                <p>
                    🔒 Effettua l'accesso per prenotare o prendere in prestito questo libro
                </p>
                <a href="login.php" class="btn-link btn-green">
                    Accedi
                </a>
                <a href="register.php" class="btn-link btn-border">
                    Registrati
                </a>
            </div>
        <?php endif; ?>
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