<?php
/**
 * Report Amministrativo Multe
 * Genera report dettagliati sulle multe incassate, pendenti e comportamenti critici
 */

use Proprietario\SudoMakers\core\Database;

session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/check_permissions.php';

// Solo bibliotecari e amministratori
requireAnyRole(['bibliotecario', 'amministratore']);

$pdo = Database::getInstance()->getConnection();
$title = "Report Amministrativo Multe";

// Parametri report
$data_inizio = $_GET['data_inizio'] ?? date('Y-m-01'); // Primo giorno mese corrente
$data_fine = $_GET['data_fine'] ?? date('Y-m-t'); // Ultimo giorno mese corrente
$tipo_report = $_GET['tipo'] ?? 'generale';

// ========================================
// STATISTICHE GENERALI
// ========================================
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as totale_multe,
        SUM(CASE WHEN stato = 'non_pagata' THEN 1 ELSE 0 END) as multe_pendenti,
        SUM(CASE WHEN stato = 'pagata' THEN 1 ELSE 0 END) as multe_pagate,
        SUM(CASE WHEN stato = 'non_pagata' THEN importo ELSE 0 END) as importo_pendente,
        SUM(CASE WHEN stato = 'pagata' THEN importo ELSE 0 END) as importo_incassato,
        AVG(CASE WHEN stato = 'pagata' THEN importo ELSE NULL END) as media_multa
    FROM multa
    WHERE DATE(data_creazione) BETWEEN :data_inizio AND :data_fine
");
$stmt->execute([
    'data_inizio' => $data_inizio,
    'data_fine' => $data_fine
]);
$stats_generali = $stmt->fetch(PDO::FETCH_ASSOC);

// ========================================
// MULTE PER TIPO
// ========================================
$stmt = $pdo->prepare("
    SELECT 
        tipo_multa,
        COUNT(*) as numero,
        SUM(importo) as importo_totale,
        AVG(importo) as importo_medio
    FROM multa
    WHERE DATE(data_creazione) BETWEEN :data_inizio AND :data_fine
    GROUP BY tipo_multa
    ORDER BY numero DESC
");
$stmt->execute([
    'data_inizio' => $data_inizio,
    'data_fine' => $data_fine
]);
$multe_per_tipo = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========================================
// UTENTI CON PIÙ MULTE
// ========================================
$stmt = $pdo->prepare("
    SELECT 
        u.id_utente,
        u.nome,
        u.cognome,
        u.email,
        u.codice_tessera,
        u.prestiti_bloccati,
        COUNT(m.id_multa) as numero_multe,
        SUM(m.importo) as importo_totale,
        SUM(CASE WHEN m.stato = 'non_pagata' THEN m.importo ELSE 0 END) as importo_pendente
    FROM utente u
    JOIN multa m ON u.id_utente = m.id_utente
    WHERE DATE(m.data_creazione) BETWEEN :data_inizio AND :data_fine
    GROUP BY u.id_utente
    HAVING numero_multe >= 2
    ORDER BY numero_multe DESC, importo_totale DESC
    LIMIT 20
");
$stmt->execute([
    'data_inizio' => $data_inizio,
    'data_fine' => $data_fine
]);
$utenti_problematici = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========================================
// LIBRI PIÙ SOGGETTI A MULTE
// ========================================
$stmt = $pdo->prepare("
    SELECT 
        l.id_libro,
        l.titolo,
        GROUP_CONCAT(DISTINCT a.nome SEPARATOR ', ') as autori,
        COUNT(DISTINCT m.id_multa) as numero_multe,
        COUNT(DISTINCT m.id_utente) as utenti_diversi,
        SUM(m.importo) as importo_totale
    FROM libro l
    LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
    LEFT JOIN autore a ON la.id_autore = a.id_autore
    JOIN copia c ON l.id_libro = c.id_libro
    JOIN prestito p ON c.id_copia = p.id_copia
    JOIN multa m ON p.id_prestito = m.id_prestito
    WHERE DATE(m.data_creazione) BETWEEN :data_inizio AND :data_fine
    GROUP BY l.id_libro, l.titolo
    HAVING numero_multe >= 2
    ORDER BY numero_multe DESC
    LIMIT 15
");
$stmt->execute([
    'data_inizio' => $data_inizio,
    'data_fine' => $data_fine
]);
$libri_problematici = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========================================
// ANDAMENTO INCASSI GIORNALIERI
// ========================================
$stmt = $pdo->prepare("
    SELECT 
        DATE(data_pagamento) as data,
        COUNT(*) as numero_pagamenti,
        SUM(importo) as incasso_giornaliero
    FROM multa
    WHERE stato = 'pagata'
    AND DATE(data_pagamento) BETWEEN :data_inizio AND :data_fine
    GROUP BY DATE(data_pagamento)
    ORDER BY data ASC
");
$stmt->execute([
    'data_inizio' => $data_inizio,
    'data_fine' => $data_fine
]);
$andamento_incassi = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../../public/assets/css/privateAreaStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/dashboardStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/gestione_multe.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .report-container { padding: 20px !important; }
        }
        
        .report-header {
            background: linear-gradient(135deg, #323232 0%, #282828 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .filtri-report {
            background: #2d2d2d;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .stats-grid-report {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card-report {
            background: #2d2d2d;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-card-report .value {
            font-size: 32px;
            font-weight: bold;
            color: #0c8a1f;
            margin: 10px 0;
        }
        
        .stat-card-report .label {
            color: #888;
            font-size: 14px;
        }
        
        .report-section {
            background: #2d2d2d;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .report-section h2 {
            color: #0c8a1f;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #0c8a1f;
        }
        
        .tabella-report {
            width: 100%;
            border-collapse: collapse;
        }
        
        .tabella-report th {
            background: #1a1a1a;
            color: #0c8a1f;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        
        .tabella-report td {
            padding: 12px;
            border-bottom: 1px solid #444;
        }
        
        .tabella-report tr:hover {
            background: #333;
        }
        
        .badge-warning {
            background: rgba(255, 152, 0, 0.2);
            color: #ff9800;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
        }
        
        .badge-danger {
            background: rgba(179, 0, 0, 0.2);
            color: #b30000;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="dashboard-container">
    <div class="report-header no-print">
        <h1>Report Amministrativo Multe</h1>
        <p>Analisi dettagliata multe e comportamenti critici</p>
    </div>
    
    <!-- Filtri -->
    <div class="filtri-report no-print">
        <form method="GET" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
            <div>
                <label style="display: block; margin-bottom: 5px; color: #888;">Data Inizio</label>
                <input type="date" name="data_inizio" value="<?= $data_inizio ?>" 
                       style="padding: 10px; border-radius: 6px; border: 1px solid #444; background: #1a1a1a; color: #ebebed;">
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 5px; color: #888;">Data Fine</label>
                <input type="date" name="data_fine" value="<?= $data_fine ?>" 
                       style="padding: 10px; border-radius: 6px; border: 1px solid #444; background: #1a1a1a; color: #ebebed;">
            </div>
            
            <button type="submit" class="btn-primary">Filtra Report</button>
            <button type="button" onclick="window.print()" class="btn-success">Stampa Report</button>
            <a href="gestion_multe.php" class="btn-secondary">← Gestione Multe</a>
        </form>
    </div>
    
    <!-- Statistiche Generali -->
    <div class="stats-grid-report">
        <div class="stat-card-report">
            <div class="label">Totale Multe</div>
            <div class="value"><?= $stats_generali['totale_multe'] ?></div>
        </div>
        
        <div class="stat-card-report">
            <div class="label">Multe Pendenti</div>
            <div class="value" style="color: #ff9800;"><?= $stats_generali['multe_pendenti'] ?></div>
        </div>
        
        <div class="stat-card-report">
            <div class="label">Multe Pagate</div>
            <div class="value" style="color: #0c8a1f;"><?= $stats_generali['multe_pagate'] ?></div>
        </div>
        
        <div class="stat-card-report">
            <div class="label">Importo Pendente</div>
            <div class="value" style="color: #b30000;">
                €<?= number_format($stats_generali['importo_pendente'], 2, ',', '.') ?>
            </div>
        </div>
        
        <div class="stat-card-report">
            <div class="label">Importo Incassato</div>
            <div class="value" style="color: #0c8a1f;">
                €<?= number_format($stats_generali['importo_incassato'], 2, ',', '.') ?>
            </div>
        </div>
        
        <div class="stat-card-report">
            <div class="label">Media per Multa</div>
            <div class="value">
                €<?= number_format($stats_generali['media_multa'], 2, ',', '.') ?>
            </div>
        </div>
    </div>
    
    <!-- Multe per Tipo -->
    <div class="report-section">
        <h2>Distribuzione per Tipo di Multa</h2>
        <table class="tabella-report">
            <thead>
                <tr>
                    <th>Tipo Multa</th>
                    <th>Numero</th>
                    <th>Importo Totale</th>
                    <th>Importo Medio</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($multe_per_tipo as $tipo): ?>
                    <tr>
                        <td style="text-transform: capitalize;"><?= htmlspecialchars($tipo['tipo_multa']) ?></td>
                        <td><strong><?= $tipo['numero'] ?></strong></td>
                        <td>€<?= number_format($tipo['importo_totale'], 2, ',', '.') ?></td>
                        <td>€<?= number_format($tipo['importo_medio'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Utenti con Comportamenti Critici -->
    <?php if(!empty($utenti_problematici)): ?>
        <div class="report-section">
            <h2>Utenti con Comportamenti Critici (≥2 multe)</h2>
            <table class="tabella-report">
                <thead>
                    <tr>
                        <th>Utente</th>
                        <th>Codice Tessera</th>
                        <th>Email</th>
                        <th>N° Multe</th>
                        <th>Importo Totale</th>
                        <th>Importo Pendente</th>
                        <th>Stato</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($utenti_problematici as $utente): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($utente['codice_tessera']) ?></td>
                            <td style="font-size: 12px;"><?= htmlspecialchars($utente['email']) ?></td>
                            <td>
                                <strong style="color: #ff9800;"><?= $utente['numero_multe'] ?></strong>
                            </td>
                            <td>€<?= number_format($utente['importo_totale'], 2, ',', '.') ?></td>
                            <td>
                                <strong style="color: <?= $utente['importo_pendente'] > 0 ? '#b30000' : '#0c8a1f' ?>;">
                                    €<?= number_format($utente['importo_pendente'], 2, ',', '.') ?>
                                </strong>
                            </td>
                            <td>
                                <?php if($utente['prestiti_bloccati']): ?>
                                    <span class="badge-danger">BLOCCATO</span>
                                <?php else: ?>
                                    <span style="color: #0c8a1f;">✓ Attivo</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <!-- Libri Problematici -->
    <?php if(!empty($libri_problematici)): ?>
        <div class="report-section">
            <h2>Libri Soggetti a Danneggiamenti Frequenti</h2>
            <p style="color: #888; margin-bottom: 15px;">
                Libri con 2+ multe nel periodo selezionato - potrebbero richiedere controlli più frequenti o copie sostitutive
            </p>
            <table class="tabella-report">
                <thead>
                    <tr>
                        <th>Titolo</th>
                        <th>Autore</th>
                        <th>N° Multe</th>
                        <th>Utenti Diversi</th>
                        <th>Importo Totale</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($libri_problematici as $libro): ?>
                        <tr>
                            <td>
                                <strong>
                                    <a href="../catalog/dettaglio_libro.php?id=<?= $libro['id_libro'] ?>" 
                                       style="color: #0c8a1f; text-decoration: none;">
                                        <?= htmlspecialchars($libro['titolo']) ?>
                                    </a>
                                </strong>
                            </td>
                            <td><?= htmlspecialchars($libro['autori'] ?? 'N/D') ?></td>
                            <td>
                                <span class="badge-warning"><?= $libro['numero_multe'] ?> multe</span>
                            </td>
                            <td><?= $libro['utenti_diversi'] ?> utenti</td>
                            <td>€<?= number_format($libro['importo_totale'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <!-- Andamento Incassi -->
    <?php if(!empty($andamento_incassi)): ?>
        <div class="report-section">
            <h2>Andamento Incassi Giornalieri</h2>
            <table class="tabella-report">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>N° Pagamenti</th>
                        <th>Incasso Giornaliero</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($andamento_incassi as $giorno): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($giorno['data'])) ?></td>
                            <td><?= $giorno['numero_pagamenti'] ?></td>
                            <td style="font-weight: 600;">€<?= number_format($giorno['incasso_giornaliero'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <div style="text-align: center; margin: 40px 0; padding: 20px; background: #2d2d2d; border-radius: 8px;">
        <p style="color: #888; font-size: 12px;">
            Report generato il <?= date('d/m/Y H:i') ?> • Periodo: <?= date('d/m/Y', strtotime($data_inizio)) ?> - <?= date('d/m/Y', strtotime($data_fine)) ?>
        </p>
    </div>
</div>

</body>
</html>
