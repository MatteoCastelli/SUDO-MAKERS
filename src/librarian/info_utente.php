<?php
/**
 * Info Utente - Pagina per bibliotecari
 * Mostra dettagli completi utente quando si scansiona la tessera
 */

use Proprietario\SudoMakers\core\Database;

session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/check_permissions.php';

// SOLO BIBLIOTECARI
requireAnyRole(['bibliotecario', 'amministratore']);

$pdo = Database::getInstance()->getConnection();
$title = "Info Utente";

$id_utente = (int)($_GET['id'] ?? 0);

if(!$id_utente) {
    header("Location: dashboard_bibliotecario.php");
    exit;
}

// Recupera dati utente
$stmt = $pdo->prepare("
    SELECT * 
    FROM utente 
    WHERE id_utente = :id
");
$stmt->execute(['id' => $id_utente]);
$utente = $stmt->fetch();

if(!$utente) {
    $errore = "Utente non trovato";
}

// Prestiti attivi
$stmt = $pdo->prepare("
    SELECT p.*, l.titolo, c.codice_barcode,
           DATEDIFF(p.data_scadenza, NOW()) as giorni_rimanenti
    FROM prestito p
    JOIN copia c ON p.id_copia = c.id_copia
    JOIN libro l ON c.id_libro = l.id_libro
    WHERE p.id_utente = :id
    AND p.data_restituzione_effettiva IS NULL
    ORDER BY p.data_scadenza ASC
");
$stmt->execute(['id' => $id_utente]);
$prestiti_attivi = $stmt->fetchAll();

// Prenotazioni attive
$stmt = $pdo->prepare("
    SELECT pr.*, l.titolo
    FROM prenotazione pr
    JOIN libro l ON pr.id_libro = l.id_libro
    WHERE pr.id_utente = :id
    AND pr.stato IN ('attiva', 'disponibile')
    ORDER BY pr.posizione_coda ASC
");
$stmt->execute(['id' => $id_utente]);
$prenotazioni_attive = $stmt->fetchAll();

// Statistiche
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as totale_prestiti,
        SUM(CASE WHEN data_restituzione_effettiva IS NULL THEN 1 ELSE 0 END) as prestiti_attivi,
        SUM(CASE WHEN data_scadenza < NOW() AND data_restituzione_effettiva IS NULL THEN 1 ELSE 0 END) as prestiti_scaduti
    FROM prestito
    WHERE id_utente = :id
");
$stmt->execute(['id' => $id_utente]);
$stats = $stmt->fetch();
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../../public/assets/css/privateAreaStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/dashboardStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/profileStyle.css">
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="dashboard-container">
    <?php if(isset($errore)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($errore) ?></div>
        <a href="dashboard_bibliotecario.php" class="btn-secondary">← Torna alla Dashboard</a>
    <?php else: ?>

        <div class="dashboard-header">
            <div>
                <h1>Info Utente</h1>
                <p><?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?></p>
            </div>
            <a href="scansiona_libro.php" class="btn-secondary">Torna allo Scanner</a>
        </div>

        <!-- Statistiche rapide -->
        <div class="stats-grid" style="margin-bottom: 30px;">
            <div class="stat-card stat-info">
                <div class="stat-icon">📚</div>
                <div class="stat-content">
                    <div class="stat-value"><?= $stats['prestiti_attivi'] ?>/10</div>
                    <div class="stat-label">Prestiti Attivi</div>
                </div>
            </div>
            <div class="stat-card <?= $stats['prestiti_scaduti'] > 0 ? 'stat-danger' : 'stat-valid' ?>">
                <div class="stat-icon"><?= $stats['prestiti_scaduti'] > 0 ? '⚠️' : '✅' ?></div>
                <div class="stat-content">
                    <div class="stat-value"><?= $stats['prestiti_scaduti'] ?></div>
                    <div class="stat-label">Prestiti Scaduti</div>
                </div>
            </div>
            <div class="stat-card stat-warning">
                <div class="stat-icon">🔖</div>
                <div class="stat-content">
                    <div class="stat-value"><?= count($prenotazioni_attive) ?>/5</div>
                    <div class="stat-label">Prenotazioni</div>
                </div>
            </div>
            <div class="stat-card stat-info">
                <div class="stat-icon">📖</div>
                <div class="stat-content">
                    <div class="stat-value"><?= $stats['totale_prestiti'] ?></div>
                    <div class="stat-label">Totale Storico</div>
                </div>
            </div>
        </div>

        <!-- Dati Anagrafici -->
        <div class="section-card">
            <h2>Dati Anagrafici</h2>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 20px;">
                <div class="profile-field">
                    <span style="color: #888;">Nome Completo:</span>
                    <strong><?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?></strong>
                </div>
                <div class="profile-field">
                    <span style="color: #888;">Codice Tessera:</span>
                    <strong><?= htmlspecialchars($utente['codice_tessera']) ?></strong>
                </div>
                <div class="profile-field">
                    <span style="color: #888;">Email:</span>
                    <strong><?= htmlspecialchars($utente['email']) ?></strong>
                </div>
                <div class="profile-field">
                    <span style="color: #888;">Data di Nascita:</span>
                    <strong><?= date('d/m/Y', strtotime($utente['data_nascita'])) ?></strong>
                </div>
                <div class="profile-field">
                    <span style="color: #888;">Codice Fiscale:</span>
                    <strong><?= htmlspecialchars($utente['codice_fiscale']) ?></strong>
                </div>
                <?php if(isset($utente['data_iscrizione']) && $utente['data_iscrizione']): ?>
                    <div class="profile-field">
                        <span style="color: #888;">Iscritto dal:</span>
                        <strong><?= date('d/m/Y', strtotime($utente['data_iscrizione'])) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Prestiti Attivi -->
        <?php if(!empty($prestiti_attivi)): ?>
            <div class="section-card">
                <h2>📚 Prestiti Attivi (<?= count($prestiti_attivi) ?>)</h2>
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Libro</th>
                        <th>Codice Copia</th>
                        <th>Data Prestito</th>
                        <th>Scadenza</th>
                        <th>Stato</th>
                        <th>Azioni</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach($prestiti_attivi as $p):
                        $scaduto = $p['giorni_rimanenti'] < 0;
                        $in_scadenza = $p['giorni_rimanenti'] <= 3 && $p['giorni_rimanenti'] >= 0;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($p['titolo']) ?></td>
                            <td><code><?= htmlspecialchars($p['codice_barcode']) ?></code></td>
                            <td><?= date('d/m/Y', strtotime($p['data_prestito'])) ?></td>
                            <td style="color: <?= $scaduto ? '#b30000' : ($in_scadenza ? '#ff9800' : '#0c8a1f') ?>;">
                                <?= date('d/m/Y', strtotime($p['data_scadenza'])) ?>
                                <?php if($scaduto): ?>
                                    <br><small>(Scaduto da <?= abs($p['giorni_rimanenti']) ?> giorni)</small>
                                <?php elseif($in_scadenza): ?>
                                    <br><small>(<?= $p['giorni_rimanenti'] ?> giorni rimasti)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($scaduto): ?>
                                    <span class="badge badge-danger">Scaduto</span>
                                <?php elseif($in_scadenza): ?>
                                    <span class="badge badge-warning">In Scadenza</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Attivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="restituzione_rapida.php?codice=<?= urlencode($p['codice_barcode']) ?>"
                                   class="btn-small btn-info">
                                    Restituzione
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="section-card">
                <p style="text-align: center; color: #888;">Nessun prestito attivo</p>
            </div>
        <?php endif; ?>

        <!-- Prenotazioni Attive -->
        <?php if(!empty($prenotazioni_attive)): ?>
            <div class="section-card">
                <h2>🔖 Prenotazioni Attive (<?= count($prenotazioni_attive) ?>)</h2>
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Libro</th>
                        <th>Data Prenotazione</th>
                        <th>Posizione Coda</th>
                        <th>Stato</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach($prenotazioni_attive as $pr): ?>
                        <tr>
                            <td><?= htmlspecialchars($pr['titolo']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($pr['data_prenotazione'])) ?></td>
                            <td>
                                <?php if($pr['stato'] === 'disponibile'): ?>
                                    <span class="badge badge-success">Pronto al Ritiro</span>
                                <?php else: ?>
                                    #<?= $pr['posizione_coda'] ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($pr['stato'] === 'disponibile'): ?>
                                    <span class="badge badge-success">Disponibile</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">In Coda</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Azioni Rapide -->
        <div class="quick-actions" style="margin-top: 30px;">
            <a href="prestito_rapido.php" class="action-card action-primary">
                <span class="action-icon">📚</span>
                <h3>Nuovo Prestito</h3>
                <p>Crea prestito per questo utente</p>
            </a>
            <a href="gestione_prenotazioni.php" class="action-card action-info">
                <span class="action-icon">🔖</span>
                <h3>Gestisci Prenotazioni</h3>
                <p>Vedi tutte le prenotazioni</p>
            </a>
            <a href="scansiona_libro.php" class="action-card action-success">
                <span class="action-icon">🔍</span>
                <h3>Torna allo Scanner</h3>
                <p>Scansiona altro codice</p>
            </a>
        </div>

    <?php endif; ?>
</div>

</body>
</html>