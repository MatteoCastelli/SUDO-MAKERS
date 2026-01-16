<?php

use Proprietario\SudoMakers\core\Database;

session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/check_permissions.php';

requireAnyRole(['bibliotecario', 'amministratore']);

$pdo = Database::getInstance()->getConnection();
$title = "Dashboard Bibliotecario";

// Statistiche rapide
$stmt = $pdo->query("SELECT COUNT(*) FROM prestito WHERE data_restituzione_effettiva IS NULL");
$prestiti_attivi = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM prestito WHERE data_scadenza < NOW() AND data_restituzione_effettiva IS NULL");
$prestiti_scaduti = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM prenotazione WHERE stato = 'attiva'");
$prenotazioni_attive = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM libro");
$totale_libri = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM copia");
$totale_copie = $stmt->fetchColumn();

// Ultimi prestiti
$stmt = $pdo->query("
    SELECT p.*, u.nome, u.cognome, l.titolo, c.codice_barcode
    FROM prestito p
    JOIN utente u ON p.id_utente = u.id_utente
    JOIN copia c ON p.id_copia = c.id_copia
    JOIN libro l ON c.id_libro = l.id_libro
    ORDER BY p.data_prestito DESC
    LIMIT 10
");
$ultimi_prestiti = $stmt->fetchAll();

// Prestiti in scadenza oggi/domani
$stmt = $pdo->query("
    SELECT p.*, u.nome, u.cognome, l.titolo
    FROM prestito p
    JOIN utente u ON p.id_utente = u.id_utente
    JOIN copia c ON p.id_copia = c.id_copia
    JOIN libro l ON c.id_libro = l.id_libro
    WHERE p.data_restituzione_effettiva IS NULL
    AND p.data_scadenza BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 1 DAY)
    ORDER BY p.data_scadenza ASC
");
$prestiti_in_scadenza = $stmt->fetchAll();
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../../public/assets/css/privateAreaStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/dashboardStyle.css">
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Dashboard Bibliotecario</h1>
        <p>Benvenuto, <?= htmlspecialchars($_SESSION['nome']) ?> <?= htmlspecialchars($_SESSION['cognome']) ?></p>
    </div>

    <a href="scansiona_libro.php" class="action-card action-primary" style="margin-bottom: 40px">
        <span class="action-icon">📚</span>
        <h3>Scansiona</h3>
        <p>Scansiona un libro/utente per vedere le informazioni</p>
    </a>

    <!-- Azioni Rapide -->
    <div class="quick-actions">
        <a href="../librarian/prestito_rapido.php" class="action-card action-warning">
            <span class="action-icon">📖</span>
            <h3>Prestito</h3>
            <p>Prendi in prestito il libro</p>
        </a>

        <a href="ritiro_prenotazione.php" class="action-card action-success">
            <span class="action-icon">📦</span>
            <h3>Ritiro Prenotazioni</h3>
            <p>Conferma ritiro libri prenotati</p>
        </a>

        <a href="restituzione_rapida.php" class="action-card action-success">
            <span class="action-icon">✅</span>
            <h3>Restituzione</h3>
            <p>Gestisci una restituzione</p>
        </a>

        <a href="cataloga_libro.php" class="action-card action-info">
            <span class="action-icon">➕</span>
            <h3>Cataloga Libro</h3>
            <p>Aggiungi un nuovo libro</p>
        </a>
        <a href="../librarian/gestione_copie.php" class="action-card action-warning">
            <span class="action-icon">📖</span>
            <h3>Gestione Copie</h3>
            <p>Gestisci copie fisiche</p>
        </a>
    </div>

    <!-- Statistiche -->
    <div class="stats-grid">
        <div class="stat-card stat-valid">
            <div class="stat-icon">📚</div>
            <div class="stat-content">
                <div class="stat-value"><?= $prestiti_attivi ?></div>
                <div class="stat-label">Prestiti Attivi</div>
            </div>
        </div>
        <div class="stat-card stat-danger">
            <div class="stat-icon">⚠️</div>
            <div class="stat-content">
                <div class="stat-value"><?= $prestiti_scaduti ?></div>
                <div class="stat-label">Prestiti Scaduti</div>
            </div>
        </div>
        <a href="../librarian/gestione_prenotazioni.php" style="text-decoration: none">
            <div class="stat-card stat-warning">
                <div class="stat-icon">🔔</div>
                <div class="stat-content">
                    <div class="stat-value"><?= $prenotazioni_attive ?></div>
                    <div class="stat-label">Prenotazioni Attive</div>
                </div>
            </div>
        </a>
        <a href="../user/homepage.php" style="text-decoration: none">
        <div class="stat-card stat-info">
            <div class="stat-icon">📖</div>
            <div class="stat-content">
                <div class="stat-value"><?= $totale_libri ?></div>
                <div class="stat-label">Libri in Catalogo</div>
            </div>
        </div>
        </a>
    </div>

    <!-- Prestiti in Scadenza -->
    <?php if(!empty($prestiti_in_scadenza)): ?>
        <div class="section-card alert-warning">
            <h2>Prestiti in Scadenza (oggi/domani)</h2>
            <table class="data-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Utente</th>
                    <th>Libro</th>
                    <th>Data Prestito</th>
                    <th>Scadenza</th>
                    <th>Stato</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach($prestiti_in_scadenza as $prestito): ?>
                    <tr>
                        <td>#<?= $prestito['id_prestito'] ?></td>
                        <td><?= htmlspecialchars($prestito['nome'] . ' ' . $prestito['cognome']) ?></td>
                        <td><?= htmlspecialchars($prestito['titolo']) ?></td>
                        <td><?= date('d/m/Y', strtotime($prestito['data_prestito'])) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($prestito['data_scadenza'])) ?></td>
                        <td><span class="badge badge-info" style="background-color: #ff9900">In scadenza</span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Ultimi Prestiti -->
    <div class="section-card">
        <h2>Ultimi Prestiti</h2>
        <table class="data-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Utente</th>
                <th>Libro</th>
                <th>Data Prestito</th>
                <th>Scadenza</th>
                <th>Stato</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach($ultimi_prestiti as $prestito): ?>
                <tr>
                    <td>#<?= $prestito['id_prestito'] ?></td>
                    <td><?= htmlspecialchars($prestito['nome'] . ' ' . $prestito['cognome']) ?></td>
                    <td><?= htmlspecialchars($prestito['titolo']) ?></td>
                    <td><?= date('d/m/Y', strtotime($prestito['data_prestito'])) ?></td>
                    <td><?= date('d/m/Y', strtotime($prestito['data_scadenza'])) ?></td>
                    <td>
                        <?php if($prestito['data_restituzione_effettiva']): ?>
                            <span class="badge badge-success">Restituito</span>
                        <?php elseif($prestito['data_scadenza'] < date('Y-m-d H:i:s')): ?>
                            <span class="badge badge-danger">Scaduto</span>
                        <?php else: ?>
                            <span class="badge badge-info">Attivo</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>