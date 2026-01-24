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
    <style>
        /* Stili specifici per le icone SVG */
        .action-icon svg, .stat-icon svg {
            width: 42px;
            height: 42px;
            transition: all 0.3s ease;
        }

        .stat-icon svg {
            width: 32px;
            height: 32px;
        }

        /* Hover effect sulle card */
        .action-card:hover .action-icon svg {
            transform: scale(1.15);
            filter: drop-shadow(0 0 8px rgba(255,255,255,0.2));
        }

        /* Colorazione specifica in base al tipo di card */
        .action-primary .action-icon svg { fill: #0c8a1f; filter: drop-shadow(0 0 5px rgba(12, 138, 31, 0.3)); }
        .action-warning .action-icon svg { fill: #ffc107; filter: drop-shadow(0 0 5px rgba(255, 193, 7, 0.3)); }
        .action-success .action-icon svg { fill: #28a745; filter: drop-shadow(0 0 5px rgba(40, 167, 69, 0.3)); }
        .action-info .action-icon svg { fill: #17a2b8; filter: drop-shadow(0 0 5px rgba(23, 162, 184, 0.3)); }
        .action-danger .action-icon svg { fill: #dc3545; filter: drop-shadow(0 0 5px rgba(220, 53, 69, 0.3)); }

        .stat-valid .stat-icon svg { fill: #0c8a1f; }
        .stat-danger .stat-icon svg { fill: #dc3545; }
        .stat-warning .stat-icon svg { fill: #ffc107; }
        .stat-info .stat-icon svg { fill: #17a2b8; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Dashboard Bibliotecario</h1>
        <p>Benvenuto, <?= htmlspecialchars($_SESSION['nome']) ?> <?= htmlspecialchars($_SESSION['cognome']) ?></p>
    </div>

    <a href="scansiona_libro.php" class="action-card action-primary" style="margin-bottom: 40px">
        <span class="action-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
        </span>
        <h3>Scansiona</h3>
        <p>Scansiona un libro/utente per vedere le informazioni</p>
    </a>

    <!-- Azioni Rapide -->
    <div class="quick-actions">
        <a href="../librarian/prestito_rapido.php" class="action-card action-warning">
            <span class="action-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M21 4H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 16H7V6h14zM3 8v12c0 1.1.9 2 2 2h14v-2H5V8z"/></svg>
            </span>
            <h3>Prestito</h3>
            <p>Prendi in prestito il libro</p>
        </a>

        <a href="ritiro_prenotazione.php" class="action-card action-success">
            <span class="action-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/></svg>
            </span>
            <h3>Ritiro Prenotazioni</h3>
            <p>Conferma ritiro libri prenotati</p>
        </a>

        <a href="restituzione_rapida.php" class="action-card action-success">
            <span class="action-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>
            </span>
            <h3>Restituzione</h3>
            <p>Gestisci una restituzione</p>
        </a>

        <a href="cataloga_libro.php" class="action-card action-info">
            <span class="action-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            </span>
            <h3>Cataloga Libro</h3>
            <p>Aggiungi un nuovo libro</p>
        </a>
        <a href="../librarian/gestione_copie.php" class="action-card action-warning">
            <span class="action-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
            </span>
            <h3>Gestione Copie</h3>
            <p>Gestisci copie fisiche</p>
        </a>

        <a href="gestion_multe.php" class="action-card action-danger">
            <span class="action-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
            </span>
            <h3>Gestione Multe</h3>
            <p>Visualizza e gestisci multe</p>
        </a>
    </div>

    <!-- Statistiche -->
    <div class="stats-grid">
        <div class="stat-card stat-valid">
            <div class="stat-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/></svg>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?= $prestiti_attivi ?></div>
                <div class="stat-label">Prestiti Attivi</div>
            </div>
        </div>
        <a href="prestiti_scaduti.php" style="text-decoration: none">
            <div class="stat-card stat-danger">
                <div class="stat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $prestiti_scaduti ?></div>
                    <div class="stat-label">Prestiti Scaduti</div>
                </div>
            </div>
        </a>
        <a href="../librarian/gestione_prenotazioni.php" style="text-decoration: none">
            <div class="stat-card stat-warning">
                <div class="stat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2zm-2 1H8v-6c0-2.48 1.51-4.5 4-4.5s4 2.02 4 4.5v6z"/></svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $prenotazioni_attive ?></div>
                    <div class="stat-label">Prenotazioni Attive</div>
                </div>
            </div>
        </a>
        <a href="../user/homepage.php" style="text-decoration: none">
            <div class="stat-card stat-info">
                <div class="stat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h10v2zm-4 4H9v-2h6v2zm4-8H9V5h10v2z"/></svg>
                </div>
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
                        <td data-label="ID">#<?= $prestito['id_prestito'] ?></td>
                        <td data-label="Utente"><?= htmlspecialchars($prestito['nome'] . ' ' . $prestito['cognome']) ?></td>
                        <td data-label="Libro"><?= htmlspecialchars($prestito['titolo']) ?></td>
                        <td data-label="Data Prestito"><?= date('d/m/Y', strtotime($prestito['data_prestito'])) ?></td>
                        <td data-label="Scadenza"><?= date('d/m/Y H:i', strtotime($prestito['data_scadenza'])) ?></td>
                        <td data-label="Stato"><span class="badge badge-info" style="background-color: #ff9900">In scadenza</span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Ultimi Prestiti -->
    <div class="section-card">
        <h2>Ultimi 10 Prestiti</h2>
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
                    <td data-label="ID">#<?= $prestito['id_prestito'] ?></td>
                    <td data-label="Utente"><?= htmlspecialchars($prestito['nome'] . ' ' . $prestito['cognome']) ?></td>
                    <td data-label="Libro"><?= htmlspecialchars($prestito['titolo']) ?></td>
                    <td data-label="Data Prestito"><?= date('d/m/Y', strtotime($prestito['data_prestito'])) ?></td>
                    <td data-label="Scadenza"><?= date('d/m/Y', strtotime($prestito['data_scadenza'])) ?></td>
                    <td data-label="Stato">
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