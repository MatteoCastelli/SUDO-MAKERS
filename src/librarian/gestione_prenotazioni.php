<?php

use Proprietario\SudoMakers\core\Database;

session_start();

// Inclusioni necessarie
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/check_permissions.php';
require_once __DIR__ . '/../utils/functions.php';

// Funzioni di gestione prenotazioni integrate direttamente
require_once __DIR__ . '/gestione_prenotazioni_functions.php';

// Controllo permessi: solo bibliotecario o amministratore
requireAnyRole(['bibliotecario', 'amministratore']);

// Connessione al DB
$pdo = Database::getInstance()->getConnection();

// Esecuzione automatica delle verifiche
scadenzaPrenotazioniNonRitirate($pdo);
inviaPromemoria($pdo);

// Titolo della pagina
$title = "Gestione Prenotazioni";

// Lista prenotazioni attive (attiva + disponibile)
$stmt = $pdo->query("
    SELECT p.*, u.nome, u.cognome, u.email, l.titolo, l.id_libro
    FROM prenotazione p
    JOIN utente u ON p.id_utente = u.id_utente
    JOIN libro l ON p.id_libro = l.id_libro
    WHERE p.stato IN ('attiva', 'disponibile')
    ORDER BY l.titolo, p.posizione_coda
");
$prenotazioni = $stmt->fetchAll();

// Raggruppa prenotazioni per libro
$prenotazioni_per_libro = [];
foreach ($prenotazioni as $pren) {
    $prenotazioni_per_libro[$pren['titolo']][] = $pren;
}

?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="../../public/assets/css/privateAreaStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/dashboardStyle.css">
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Gestione Code Prenotazioni</h1>
        <a href="dashboard_bibliotecario.php" class="btn-back">← Torna alla Dashboard</a>
    </div>

    <?php if (empty($prenotazioni)): ?>
        <div class="section-card">
            <p style="text-align: center; color: #888;">Nessuna prenotazione attiva al momento</p>
        </div>
    <?php else: ?>
        <?php foreach ($prenotazioni_per_libro as $titolo => $lista): ?>
            <div class="section-card" style="margin-bottom: 30px;">
                <h2>📚 <?= htmlspecialchars($titolo) ?></h2>
                <p style="color: #888; margin-bottom: 20px;">Persone in coda: <?= count($lista) ?></p>

                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Posizione</th>
                        <th>Utente</th>
                        <th>Email</th>
                        <th>Data Prenotazione</th>
                        <th>Stato</th>
                        <th>Scadenza Ritiro</th>
                        <th>Azioni</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lista as $pren): ?>
                        <tr>
                            <td><strong>#<?= $pren['posizione_coda'] ?></strong></td>
                            <td><?= htmlspecialchars($pren['nome'] . ' ' . $pren['cognome']) ?></td>
                            <td><?= htmlspecialchars($pren['email']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($pren['data_prenotazione'])) ?></td>
                            <td>
                                <?php if ($pren['stato'] === 'disponibile'): ?>
                                    <span class="badge badge-success">Disponibile</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">In Coda</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($pren['data_scadenza_ritiro']): ?>
                                    <?php
                                    $scadenza = strtotime($pren['data_scadenza_ritiro']);
                                    $ore_rimaste = round(($scadenza - time()) / 3600);
                                    ?>
                                    <span style="color: <?= $ore_rimaste < 12 ? '#b30000' : '#ff9800' ?>">
                                        <?= date('d/m/Y H:i', $scadenza) ?>
                                        <br><small>(<?= $ore_rimaste ?>h rimaste)</small>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #888;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="../catalog/dettaglio_libro.php?id=<?= $pren['id_libro'] ?>" class="btn-small btn-info">Vedi Libro</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
