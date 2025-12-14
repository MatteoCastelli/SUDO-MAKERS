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
$title = "Le Mie Prenotazioni";

// Gestione annullamento prenotazione
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annulla_prenotazione'])) {
    $id_prenotazione = (int)$_POST['id_prenotazione'];

    $stmt = $pdo->prepare("
        UPDATE prenotazione 
        SET stato = 'annullata' 
        WHERE id_prenotazione = :id 
        AND id_utente = :id_utente 
        AND stato = 'attiva'
    ");
    $stmt->execute(['id' => $id_prenotazione, 'id_utente' => $id_utente]);

    if($stmt->rowCount() > 0) {
        // Ricalcola posizioni
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

// Recupera prenotazioni attive
$stmt = $pdo->prepare("
    SELECT p.*, l.titolo, l.id_libro, l.immagine_copertina_url,
           GROUP_CONCAT(CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori
    FROM prenotazione p
    JOIN libro l ON p.id_libro = l.id_libro
    LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
    LEFT JOIN autore a ON la.id_autore = a.id_autore
    WHERE p.id_utente = :id_utente
    AND p.stato IN ('attiva', 'disponibile')
    GROUP BY p.id_prenotazione
    ORDER BY 
        CASE p.stato 
            WHEN 'disponibile' THEN 1 
            WHEN 'attiva' THEN 2 
        END,
        p.posizione_coda
");
$stmt->execute(['id_utente' => $id_utente]);
$prenotazioni = $stmt->fetchAll();

// Recupera storico prenotazioni
$stmt = $pdo->prepare("
    SELECT p.*, l.titolo
    FROM prenotazione p
    JOIN libro l ON p.id_libro = l.id_libro
    WHERE p.id_utente = :id_utente
    AND p.stato IN ('ritirata', 'scaduta', 'annullata')
    ORDER BY p.data_prenotazione DESC
    LIMIT 10
");
$stmt->execute(['id_utente' => $id_utente]);
$storico = $stmt->fetchAll();

$num_prenotazioni_attive = count(array_filter($prenotazioni, fn($p) => $p['stato'] === 'attiva' || $p['stato'] === 'disponibile'));
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../public/assets/css/privateAreaStyle.css">
    <link rel="stylesheet" href="../public/assets/css/dashboardStyle.css">
    <style>
        .countdown-timer {
            font-size: 24px;
            font-weight: bold;
            color: #ff9800;
            margin: 10px 0;
        }

        .countdown-timer.urgent {
            color: #b30000;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .prenotazione-card {
            background: #1f1f21;
            border: 2px solid #303033;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 20px;
        }

        .prenotazione-card.disponibile {
            border-color: #0c8a1f;
            box-shadow: 0 0 15px rgba(12, 138, 31, 0.3);
        }

        .prenotazione-card.urgente {
            border-color: #b30000;
            animation: glow 2s infinite;
        }

        @keyframes glow {
            0%, 100% { box-shadow: 0 0 10px rgba(179, 0, 0, 0.3); }
            50% { box-shadow: 0 0 20px rgba(179, 0, 0, 0.6); }
        }

        .libro-mini-cover {
            width: 150px;
            height: 200px;
            border-radius: 8px;
            overflow: hidden;
            background: #2a2a2c;
        }

        .libro-mini-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .posizione-badge {
            display: inline-block;
            padding: 8px 16px;
            background: #ff9800;
            color: white;
            border-radius: 20px;
            font-weight: bold;
            font-size: 16px;
        }

        .limite-prenotazioni {
            background: #2a2a2c;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .limite-prenotazioni strong {
            color: <?= $num_prenotazioni_attive >= 5 ? '#b30000' : '#0c8a1f' ?>;
            font-size: 24px;
        }
    </style>
</head>
<body>
<?php require_once 'navigation.php'; ?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>📚 Le Mie Prenotazioni</h1>
        <a href="profile.php" class="btn-back">← Torna al Profilo</a>
    </div>

    <?php if(isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="limite-prenotazioni">
        <p style="margin: 0; color: #888;">Prenotazioni attive</p>
        <strong><?= $num_prenotazioni_attive ?> / 5</strong>
    </div>

    <?php if(empty($prenotazioni)): ?>
        <div class="section-card">
            <p style="text-align: center; color: #888; padding: 40px;">
                Non hai prenotazioni attive.<br>
                <a href="homepage.php" style="color: #0c8a1f;">Esplora il catalogo</a>
            </p>
        </div>
    <?php else: ?>
        <div class="section-card">
            <h2>🔖 Prenotazioni Attive</h2>

            <?php foreach($prenotazioni as $pren): ?>
                <?php
                $ore_rimaste = null;
                $urgente = false;
                if($pren['data_scadenza_ritiro']) {
                    $ore_rimaste = (strtotime($pren['data_scadenza_ritiro']) - time()) / 3600;
                    $urgente = $ore_rimaste <= 12;
                }
                ?>

                <div class="prenotazione-card <?= $pren['stato'] === 'disponibile' ? ($urgente ? 'urgente' : 'disponibile') : '' ?>">
                    <div class="libro-mini-cover">
                        <?php if($pren['immagine_copertina_url']): ?>
                            <img src="<?= htmlspecialchars($pren['immagine_copertina_url']) ?>" alt="Copertina">
                        <?php else: ?>
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; font-size: 60px;">📖</div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <h3 style="margin: 0 0 5px 0; color: #ebebed;">
                            <a href="dettaglio_libro.php?id=<?= $pren['id_libro'] ?>" style="color: #ebebed; text-decoration: none;">
                                <?= htmlspecialchars($pren['titolo']) ?>
                            </a>
                        </h3>
                        <p style="color: #888; margin: 0 0 15px 0;"><?= htmlspecialchars($pren['autori'] ?? 'Autore sconosciuto') ?></p>

                        <?php if($pren['stato'] === 'disponibile'): ?>
                            <div style="background: #0c8a1f; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                <p style="margin: 0 0 10px 0; font-size: 18px; color: white; font-weight: bold;">
                                    ✅ LIBRO DISPONIBILE PER IL RITIRO!
                                </p>
                                <?php if($ore_rimaste !== null): ?>
                                    <div class="countdown-timer <?= $urgente ? 'urgent' : '' ?>"
                                         data-scadenza="<?= strtotime($pren['data_scadenza_ritiro']) ?>">
                                        Calcolo countdown...
                                    </div>
                                    <p style="margin: 5px 0 0 0; color: white; font-size: 14px;">
                                        Scadenza: <?= date('d/m/Y alle H:i', strtotime($pren['data_scadenza_ritiro'])) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div style="margin-bottom: 15px;">
                                <span class="posizione-badge">Posizione in coda: #<?= $pren['posizione_coda'] ?></span>
                            </div>
                            <p style="color: #888; font-size: 14px;">
                                📅 Prenotato il: <?= date('d/m/Y', strtotime($pren['data_prenotazione'])) ?>
                            </p>
                        <?php endif; ?>

                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <a href="dettaglio_libro.php?id=<?= $pren['id_libro'] ?>" class="btn-primary">
                                Vedi Dettagli
                            </a>
                            <?php if($pren['stato'] === 'attiva'): ?>
                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Sei sicuro di voler annullare questa prenotazione?');">
                                    <input type="hidden" name="id_prenotazione" value="<?= $pren['id_prenotazione'] ?>">
                                    <button type="submit" name="annulla_prenotazione" class="btn-secondary">
                                        Annulla Prenotazione
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if(!empty($storico)): ?>
        <div class="section-card">
            <h2>📋 Storico Prenotazioni</h2>
            <table class="data-table">
                <thead>
                <tr>
                    <th>Libro</th>
                    <th>Data Prenotazione</th>
                    <th>Stato</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach($storico as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['titolo']) ?></td>
                        <td><?= date('d/m/Y', strtotime($s['data_prenotazione'])) ?></td>
                        <td>
                            <?php
                            $badge_class = match($s['stato']) {
                                'ritirata' => 'badge-success',
                                'scaduta' => 'badge-danger',
                                'annullata' => 'badge-warning',
                                default => 'badge-info'
                            };
                            $stato_text = match($s['stato']) {
                                'ritirata' => 'Ritirata',
                                'scaduta' => 'Scaduta',
                                'annullata' => 'Annullata',
                                default => 'Sconosciuto'
                            };
                            ?>
                            <span class="badge <?= $badge_class ?>"><?= $stato_text ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
    // Countdown timer in tempo reale
    function aggiornaCountdown() {
        const timers = document.querySelectorAll('.countdown-timer');

        timers.forEach(timer => {
            const scadenza = parseInt(timer.dataset.scadenza) * 1000;
            const now = Date.now();
            const diff = scadenza - now;

            if(diff <= 0) {
                timer.textContent = 'SCADUTO';
                timer.classList.add('urgent');
                return;
            }

            const ore = Math.floor(diff / (1000 * 60 * 60));
            const minuti = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const secondi = Math.floor((diff % (1000 * 60)) / 1000);

            timer.textContent = `Ritira entro: ${ore}h ${minuti}m ${secondi}s`;

            if(ore < 12) {
                timer.classList.add('urgent');
            }
        });
    }

    // Aggiorna ogni secondo
    aggiornaCountdown();
    setInterval(aggiornaCountdown, 1000);
</script>

</body>
</html>
