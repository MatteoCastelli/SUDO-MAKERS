<?php

use Proprietario\SudoMakers\core\Database;

session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/check_permissions.php';

requireAnyRole(['bibliotecario', 'amministratore']);

$pdo = Database::getInstance()->getConnection();
$title = "Prestiti Scaduti";

// Filtri
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'giorni_scaduto';
$order = isset($_GET['order']) && $_GET['order'] === 'ASC' ? 'ASC' : 'DESC';

// Query base
$query = "
    SELECT 
        p.*,
        u.nome as utente_nome,
        u.cognome as utente_cognome,
        u.email as utente_email,
        l.titolo,
        l.immagine_copertina_url,
        c.codice_barcode,
        ABS(DATEDIFF(NOW(), p.data_scadenza)) as giorni_scaduto,
        COALESCE(
            (SELECT SUM(importo) 
             FROM multa m 
             WHERE m.id_prestito = p.id_prestito 
             AND m.stato != 'pagata'),
            0
        ) as multa_pendente
    FROM prestito p
    JOIN utente u ON p.id_utente = u.id_utente
    JOIN copia c ON p.id_copia = c.id_copia
    JOIN libro l ON c.id_libro = l.id_libro
    WHERE p.data_restituzione_effettiva IS NULL
    AND p.data_scadenza < NOW()
";

$params = [];

// Filtro ricerca
if (!empty($search)) {
    $query .= " AND (
        l.titolo LIKE :search OR
        u.nome LIKE :search OR
        u.cognome LIKE :search OR
        u.email LIKE :search OR
        c.codice_barcode LIKE :search
    )";
    $params['search'] = "%$search%";
}

// Ordinamento
$allowed_sorts = ['giorni_scaduto', 'data_prestito', 'data_scadenza', 'titolo', 'utente_cognome', 'multa_pendente'];
if (in_array($sort, $allowed_sorts)) {
    $query .= " ORDER BY $sort $order";
} else {
    $query .= " ORDER BY giorni_scaduto DESC";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$prestiti = $stmt->fetchAll();

// Statistiche
$totale_scaduti = count($prestiti);
$totale_multe = array_sum(array_column($prestiti, 'multa_pendente'));
$prestiti_critico = count(array_filter($prestiti, fn($p) => $p['giorni_scaduto'] > 30));
$prestiti_gravi = count(array_filter($prestiti, fn($p) => $p['giorni_scaduto'] > 7 && $p['giorni_scaduto'] <= 30));
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../../public/assets/css/privateAreaStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/dashboardStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/tableStyle.css">
    <style>
        .filters-bar {
            background: #323232;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
        }
        
        .search-box input {
            width: 80%;
            padding: 10px 15px;
            border: 1px solid #646464;
            border-radius: 6px;
            font-size: 14px;
            background-color: #2a2a2c;
            color: white;
        }
        
        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .filter-group label {
            font-weight: 500;
            color: #f0f0f0;
        }
        
        .filter-group select {
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
            background-color: #2d2d2d;
            color: white;
            border: 1px solid #646464;"
        }
        
        .stats-mini {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .stat-mini {
            background: #323232;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            flex: 1;
            min-width: 150px;
            text-align: center;
        }
        
        .stat-mini-value {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-mini-label {
            font-size: 13px;
            color: #666;
        }
        
        .alert-box {
            background: #282828;
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-box.danger {
            background: #282828;
            border-left-color: #dc3545;
        }
        
        .prestito-row {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .prestito-row:hover {
            background-color: #323232;
        }
        
        .prestito-row.critico {
            background-color: #f8d7da;
        }
        
        .prestito-row.grave {
            background-color: #fff3cd;
        }
        
        .libro-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .libro-thumb {
            width: 40px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .libro-placeholder {
            width: 40px;
            height: 60px;
            background: #323232;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .utente-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        
        .utente-nome {
            font-weight: 500;
        }
        
        .utente-contatto {
            font-size: 12px;
            color: #666;
        }
        
        .giorni-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .giorni-badge.critico {
            background: #f8d7da;
            color: #721c24;
        }
        
        .giorni-badge.grave {
            background: #fff3cd;
            color: #856404;
        }
        
        .giorni-badge.recente {
            background: #ffeaa7;
            color: #d63031;
        }
        
        .multa-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            background: #d4edda;
            color: #155724;
        }
        
        .multa-badge.pendente {
            background: #f8d7da;
            color: #721c24;
        }
        
        .actions-cell {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        
        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background: #138496;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Prestiti Scaduti</h1>
        <p>Gestione prestiti oltre la scadenza</p>
    </div>

    <?php if ($totale_scaduti > 0): ?>
        <div class="alert-box danger">
            <div>
                <strong>Attenzione!</strong> Ci sono <strong><?= $totale_scaduti ?></strong> prestiti scaduti che richiedono attenzione immediata.
            </div>
        </div>
    <?php endif; ?>

    <!-- Statistiche Mini -->
    <div class="stats-mini">
        <div class="stat-mini">
            <div class="stat-mini-value" style="color: #dc3545;"><?= $totale_scaduti ?></div>
            <div class="stat-mini-label">Totale Scaduti</div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-value" style="color: #dc3545;"><?= $prestiti_critico ?></div>
            <div class="stat-mini-label">Critici (>30gg)</div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-value" style="color: #ffc107;"><?= $prestiti_gravi ?></div>
            <div class="stat-mini-label">Gravi (7-30gg)</div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-value" style="color: #e74c3c;">€ <?= number_format($totale_multe, 2) ?></div>
            <div class="stat-mini-label">Multe Pendenti</div>
        </div>
    </div>

    <!-- Filtri -->
    <form method="GET" class="filters-bar">
        <div class="search-box">
            <input type="text" 
                   name="search" 
                   placeholder="Cerca per titolo, utente, email o codice..." 
                   value="<?= htmlspecialchars($search) ?>">
        </div>
        
        <div class="filter-group">
            <label>Ordina per:</label>
            <select name="sort" onchange="this.form.submit()">
                <option value="giorni_scaduto" <?= $sort === 'giorni_scaduto' ? 'selected' : '' ?>>Giorni Scaduto</option>
                <option value="data_scadenza" <?= $sort === 'data_scadenza' ? 'selected' : '' ?>>Data Scadenza</option>
                <option value="data_prestito" <?= $sort === 'data_prestito' ? 'selected' : '' ?>>Data Prestito</option>
                <option value="multa_pendente" <?= $sort === 'multa_pendente' ? 'selected' : '' ?>>Importo Multa</option>
                <option value="titolo" <?= $sort === 'titolo' ? 'selected' : '' ?>>Titolo Libro</option>
                <option value="utente_cognome" <?= $sort === 'utente_cognome' ? 'selected' : '' ?>>Utente</option>
            </select>
        </div>
        
        <div class="filter-group">
            <select name="order" onchange="this.form.submit()">
                <option value="DESC" <?= $order === 'DESC' ? 'selected' : '' ?>>Decrescente</option>
                <option value="ASC" <?= $order === 'ASC' ? 'selected' : '' ?>>Crescente</option>
            </select>
        </div>
        
        <button type="submit" class="btn-action btn-info">Cerca</button>
        <a href="prestiti_scaduti.php" class="btn-action" style="background: #6c757d; color: white;">Reset</a>
    </form>

    <!-- Tabella Prestiti Scaduti -->
    <div class="section-card">
        <?php if (empty($prestiti)): ?>
            <p style="text-align: center; padding: 40px; color: #666;">
                🎉 Nessun prestito scaduto! Ottimo lavoro!
            </p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Libro</th>
                            <th>Utente</th>
                            <th>Barcode</th>
                            <th>Scaduto il</th>
                            <th>Giorni Scaduto</th>
                            <th>Multa</th>
                            <th>Gravità</th>
                            <th style="text-align: right;">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prestiti as $prestito): ?>
                            <?php
                            $giorni = $prestito['giorni_scaduto'];
                            $classe_row = $giorni > 30 ? 'critico' : ($giorni > 7 ? 'grave' : '');
                            $classe_badge = $giorni > 30 ? 'critico' : ($giorni > 7 ? 'grave' : 'recente');
                            ?>
                            <tr class="prestito-row <?= $classe_row ?>">
                                <td><strong>#<?= $prestito['id_prestito'] ?></strong></td>
                                
                                <td>
                                    <div class="libro-info">
                                        <?php if ($prestito['immagine_copertina_url']): ?>
                                            <img src="<?= htmlspecialchars($prestito['immagine_copertina_url']) ?>" 
                                                 alt="Copertina" 
                                                 class="libro-thumb">
                                        <?php else: ?>
                                            <div class="libro-placeholder">📖</div>
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars($prestito['titolo']) ?></span>
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="utente-info">
                                        <span class="utente-nome">
                                            <?= htmlspecialchars($prestito['utente_nome'] . ' ' . $prestito['utente_cognome']) ?>
                                        </span>
                                        <span class="utente-contatto">
                                            <?= htmlspecialchars($prestito['utente_email']) ?>
                                        </span>
                                    </div>
                                </td>
                                
                                <td>
                                    <code style="background: #646464; padding: 4px 8px; border-radius: 4px;">
                                        <?= htmlspecialchars($prestito['codice_barcode']) ?>
                                    </code>
                                </td>

                                <td>
                                    <strong style="color: #dc3545;">
                                        <?= date('d/m/Y H:i', strtotime($prestito['data_scadenza'])) ?>
                                    </strong>
                                </td>
                                
                                <td>
                                    <span class="giorni-badge <?= $classe_badge ?>">
                                        <strong><?= $giorni ?></strong> giorni
                                    </span>
                                </td>
                                
                                <td>
                                    <?php if ($prestito['multa_pendente'] > 0): ?>
                                        <span class="multa-badge pendente">
                                             € <?= number_format($prestito['multa_pendente'], 2) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="multa-badge">
                                            Nessuna
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <?php if ($giorni > 30): ?>
                                        <span class="badge badge-danger">CRITICO</span>
                                    <?php elseif ($giorni > 7): ?>
                                        <span class="badge badge-warning">GRAVE</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #ffeaa7; color: #d63031;">Recente</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <div class="actions-cell">
                                        <a href="info_utente.php?id=<?= $prestito['id_utente'] ?>" 
                                           class="btn-action btn-info"
                                           title="Info Utente">
                                            Info
                                        </a>
                                        <a href="restituzione_rapida.php?codice=<?= urlencode($prestito['codice_barcode']) ?>"
                                           class="btn-action btn-success"
                                           title="Gestisci Restituzione">
                                            Restituisci
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
