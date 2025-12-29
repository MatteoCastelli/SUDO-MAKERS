<?php
use Proprietario\SudoMakers\Database;

session_start();
require_once "Database.php";

if(!isset($_SESSION['id_utente'])) {
    header("Location: login.php");
    exit;
}

$pdo = Database::getInstance()->getConnection();
$id_utente = $_SESSION['id_utente'];
$title = "I Miei Libri";

$tab = $_GET['tab'] ?? 'prestiti';

// --- GESTIONE ANNULLAMENTO PRENOTAZIONE ---
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annulla_prenotazione'])) {
    $id_prenotazione = (int)$_POST['id_prenotazione'];
    $stmt = $pdo->prepare("UPDATE prenotazione SET stato = 'annullata' WHERE id_prenotazione = :id AND id_utente = :id_utente AND stato = 'attiva'");
    $stmt->execute(['id' => $id_prenotazione, 'id_utente' => $id_utente]);

    if($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("SELECT id_libro FROM prenotazione WHERE id_prenotazione = :id");
        $stmt->execute(['id' => $id_prenotazione]);
        $libro = $stmt->fetch();
        if($libro) {
            require_once 'gestione_prenotazioni.php';
            ricalcolaPosizioniCoda($libro['id_libro'], $pdo);
        }
        $success = "Prenotazione annullata con successo";
    }
}

// --- QUERY DATI ---
$stmt = $pdo->prepare("
    SELECT p.*, l.titolo, l.id_libro, l.immagine_copertina_url,
           GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori
    FROM prenotazione p
    JOIN libro l ON p.id_libro = l.id_libro
    LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
    LEFT JOIN autore a ON la.id_autore = a.id_autore
    WHERE p.id_utente = :id_utente AND p.stato IN ('attiva', 'disponibile')
    GROUP BY p.id_prenotazione
    ORDER BY CASE p.stato WHEN 'disponibile' THEN 1 WHEN 'attiva' THEN 2 END, p.posizione_coda
");
$stmt->execute(['id_utente' => $id_utente]);
$prenotazioni = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT p.*, l.titolo, l.id_libro, l.immagine_copertina_url,
           GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori,
           DATEDIFF(p.data_scadenza, NOW()) as giorni_rimanenti
    FROM prestito p
    JOIN copia c ON p.id_copia = c.id_copia
    JOIN libro l ON c.id_libro = l.id_libro
    LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
    LEFT JOIN autore a ON la.id_autore = a.id_autore
    WHERE p.id_utente = :id_utente AND p.data_restituzione_effettiva IS NULL
    GROUP BY p.id_prestito
    ORDER BY p.data_scadenza ASC
");
$stmt->execute(['id_utente' => $id_utente]);
$prestiti = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT p.*, l.titolo FROM prenotazione p JOIN libro l ON p.id_libro = l.id_libro
    WHERE p.id_utente = :id_utente AND p.stato IN ('ritirata', 'scaduta', 'annullata')
    ORDER BY p.data_prenotazione DESC LIMIT 10
");
$stmt->execute(['id_utente' => $id_utente]);
$storico = $stmt->fetchAll();
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../public/assets/css/privateAreaStyle.css">
    <link rel="stylesheet" href="../public/assets/css/dashboardStyle.css">
    <link rel="stylesheet" href="../public/assets/css/prenotazioniStyle.css">
</head>
<body>
<?php require_once 'navigation.php'; ?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>I Tuoi Libri</h1>
    </div>

    <?php if(isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="tabs-navigation" style="margin-bottom: 20px">
        <button class="tab-button <?= $tab === 'prestiti' ? 'active' : '' ?>" onclick="switchTab('prestiti')">
            Prestiti <span class="tab-badge"><?= count($prestiti) ?>/10</span>
        </button>
        <button class="tab-button <?= $tab === 'prenotazioni' ? 'active' : '' ?>" onclick="switchTab('prenotazioni')">
            Prenotazioni <span class="tab-badge"><?= count($prenotazioni) ?>/5</span>
        </button>
    </div>

    <div id="tab-prestiti" class="tab-content <?= $tab === 'prestiti' ? 'active' : '' ?>">
        <?php if(empty($prestiti)): ?>
            <p style="text-align: center; color: #888; padding: 40px;">Nessun prestito attivo. <a href="homepage.php" style="color: #0c8a1f;">Vai al catalogo</a></p>
        <?php else: ?>
            <?php foreach($prestiti as $prest):
                $g = $prest['giorni_rimanenti'];
                $status_class = ($g < 0) ? 'scaduto' : (($g <= 3) ? 'in-scadenza' : '');
                $date_color = ($g < 0) ? '#b30000' : (($g <= 3) ? '#ff9800' : '#0c8a1f');
                ?>
                <div class="prestito-card <?= $status_class ?>">
                    <div class="libro-mini-cover">
                        <?php if($prest['immagine_copertina_url']): ?>
                            <img src="<?= htmlspecialchars($prest['immagine_copertina_url']) ?>" alt="Copertina">
                        <?php else: ?>
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; font-size: 50px;">📖</div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="card-content-top">
                            <h3 style="margin: 0;"><a href="dettaglio_libro.php?id=<?= $prest['id_libro'] ?>" style="color: #ebebed; text-decoration: none;"><?= htmlspecialchars($prest['titolo']) ?></a></h3>
                            <p style="color: #888; margin-top: 5px; margin-bottom: 5px;"><?= htmlspecialchars($prest['autori'] ?? 'Autore sconosciuto') ?></p>
                            <p style="color: #888; margin-top: 5px;"><?= htmlspecialchars($libro['descrizione'] ?? 'Nessuna descrizione.') ?></p>
                        </div>
                        <div class="card-details-bottom">
                            <p style="margin: 0; color: #888; font-size: 14px;">Preso il: <strong style="color: <?= $date_color ?>;"><?= date('d/m/Y', strtotime($prest['data_prestito'])) ?></strong></p>
                            <p style="margin: 5px 0 10px 0; color: #888; font-size: 14px;">Scadenza: <strong style="color: <?= $date_color ?>;"><?= date('d/m/Y', strtotime($prest['data_scadenza'])) ?></strong></p>
                            <a href="dettaglio_libro.php?id=<?= $prest['id_libro'] ?>" class="btn-primary">Vedi Dettagli Libro</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="tab-prenotazioni" class="tab-content <?= $tab === 'prenotazioni' ? 'active' : '' ?>">
        <?php if(empty($prenotazioni)): ?>
            <p style="text-align: center; color: #888; padding: 40px;">Nessuna prenotazione attiva. <a href="homepage.php" style="color: #0c8a1f;">Vai al catalogo</a></p></p>
        <?php else: ?>
            <?php foreach($prenotazioni as $pren):
                $urgente = false;
                if($pren['data_scadenza_ritiro']) {
                    $ore = (strtotime($pren['data_scadenza_ritiro']) - time()) / 3600;
                    $urgente = $ore <= 12;
                }
                ?>
                <div class="prenotazione-card <?= $pren['stato'] === 'disponibile' ? 'disponibile' : '' ?>">
                    <div class="libro-mini-cover">
                        <?php if($pren['immagine_copertina_url']): ?>
                            <img src="<?= htmlspecialchars($pren['immagine_copertina_url']) ?>" alt="Copertina">
                        <?php else: ?>
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; font-size: 50px;">📖</div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="card-content-top">
                            <h3 style="margin: 0;"><a href="dettaglio_libro.php?id=<?= $pren['id_libro'] ?>" style="color: #ebebed; text-decoration: none;"><?= htmlspecialchars($pren['titolo']) ?></a></h3>
                            <p style="color: #888; margin-top: 5px; margin-bottom: 5px;"><?= htmlspecialchars($pren['autori'] ?? 'Autore sconosciuto') ?></p>
                            <p style="color: #888; margin-top: 5px;"><?= htmlspecialchars($libro['descrizione'] ?? 'Nessuna descrizione.') ?></p>
                        </div>
                        <div class="card-details-bottom">
                            <?php if($pren['stato'] === 'disponibile'): ?>
                                <div style="background: rgba(12, 138, 31, 0.1); padding: 10px; border-radius: 6px; margin-bottom: 10px; border-left: 4px solid #0c8a1f;">
                                    <p style="margin: 0; color: #0c8a1f; font-weight: bold;">✅ PRONTO AL RITIRO</p>
                                    <div class="countdown-timer <?= $urgente ? 'urgent' : '' ?>" data-scadenza="<?= strtotime($pren['data_scadenza_ritiro']) ?>">...</div>
                                </div>
                            <?php else: ?>
                                <p style="margin-bottom: 20px;"><span class="posizione-badge">Posizione: #<?= $pren['posizione_coda'] ?></span></p>
                            <?php endif; ?>
                            <div style="display: flex; gap: 10px;">
                                <a href="dettaglio_libro.php?id=<?= $pren['id_libro'] ?>" class="btn-primary">Vedi Dettagli Libro</a>
                                <?php if($pren['stato'] === 'attiva'): ?>
                                    <form method="POST" style="margin: 0;"">
                                        <input type="hidden" name="id_prenotazione" value="<?= $pren['id_prenotazione'] ?>">
                                        <button type="submit" name="annulla_prenotazione" class="btn-secondary">Annulla</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="tab-storico" class="tab-content <?= $tab === 'storico' ? 'active' : '' ?>">
        <div class="section-card">
            <?php if(!empty($storico)): ?>
                <table class="data-table">
                    <thead><tr><th>Libro</th><th>Data</th><th>Stato</th></tr></thead>
                    <tbody>
                    <?php foreach($storico as $s): ?>
                        <tr>
                            <td><?= htmlspecialchars($s['titolo']) ?></td>
                            <td><?= date('d/m/Y', strtotime($s['data_prenotazione'])) ?></td>
                            <td><span class="badge badge-<?= $s['stato'] === 'ritirata' ? 'success' : 'warning' ?>"><?= ucfirst($s['stato']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #888;">Nessun dato nello storico.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function switchTab(tabName) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + tabName).classList.add('active');
        event.currentTarget.classList.add('active');
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.pushState({}, '', url);
    }

    function aggiornaCountdown() {
        document.querySelectorAll('.countdown-timer').forEach(t => {
            const diff = (parseInt(t.dataset.scadenza) * 1000) - Date.now();
            if(diff <= 0) { t.textContent = 'SCADUTO'; return; }
            const h = Math.floor(diff / 3600000);
            const m = Math.floor((diff % 3600000) / 60000);
            const s = Math.floor((diff % 60000) / 1000);
            t.textContent = `Ritira entro: ${h}h ${m}m ${s}s`;
            if(h < 12) t.classList.add('urgent');
        });
    }
    setInterval(aggiornaCountdown, 1000);
    aggiornaCountdown();
</script>
</body>
</html>