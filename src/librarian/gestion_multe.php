<?php
/**
 * Gestione Multe - Pagina per bibliotecari
 * Visualizza e gestisce multe, pagamenti e genera ricevute
 */

use Proprietario\SudoMakers\core\Database;

session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/check_permissions.php';

// SOLO BIBLIOTECARI E AMMINISTRATORI
requireAnyRole(['bibliotecario', 'amministratore']);

$pdo = Database::getInstance()->getConnection();
$title = "Gestione Multe";

$success = '';
$error = '';

// Gestione registrazione pagamento
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registra_pagamento'])) {
    $id_multa = (int)$_POST['id_multa'];
    $metodo = $_POST['metodo_pagamento'];
    $note = trim($_POST['note_pagamento'] ?? '');

    try {
        $pdo->beginTransaction();

        // Recupera info multa
        $stmt = $pdo->prepare("SELECT * FROM multa WHERE id_multa = :id");
        $stmt->execute(['id' => $id_multa]);
        $multa = $stmt->fetch();

        if(!$multa) {
            throw new Exception("Multa non trovata");
        }

        // Aggiorna multa
        $stmt = $pdo->prepare("
            UPDATE multa 
            SET stato = 'pagata', 
                data_pagamento = NOW(),
                note = CONCAT(COALESCE(note, ''), '\n[Pagamento] ', :note)
            WHERE id_multa = :id
        ");
        $stmt->execute([
            'id' => $id_multa,
            'note' => $note . ' - Bibliotecario: ' . $_SESSION['username']
        ]);

        // Registra pagamento
        $stmt = $pdo->prepare("
            INSERT INTO pagamento 
            (id_multa, importo, metodo_pagamento, id_bibliotecario, note_pagamento)
            VALUES (:id_multa, :importo, :metodo, :id_biblio, :note)
        ");
        $stmt->execute([
            'id_multa' => $id_multa,
            'importo' => $multa['importo'],
            'metodo' => $metodo,
            'id_biblio' => $_SESSION['id_utente'],
            'note' => $note
        ]);

        $id_pagamento = $pdo->lastInsertId();

        // Sblocca utente se non ha altre multe
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM multa 
            WHERE id_utente = :id_utente AND stato = 'non_pagata'
        ");
        $stmt->execute(['id_utente' => $multa['id_utente']]);
        $altre_multe = $stmt->fetchColumn();

        if($altre_multe == 0) {
            $stmt = $pdo->prepare("
                UPDATE utente 
                SET prestiti_bloccati = FALSE, motivo_blocco = NULL 
                WHERE id_utente = :id
            ");
            $stmt->execute(['id' => $multa['id_utente']]);
        }

        // Genera ricevuta PDF (opzionale - placeholder)
        // require_once __DIR__ . '/../utils/genera_ricevuta_pdf.php';
        // $pdf_path = generaRicevuta($id_pagamento);

        $pdo->commit();

        $success = "Pagamento registrato con successo! ID Pagamento: #$id_pagamento";

    } catch(Exception $e) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Errore: " . $e->getMessage();
    }
}

// Gestione annullamento multa (solo amministratori)
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annulla_multa']) && isAdmin()) {
    $id_multa = (int)$_POST['id_multa'];
    $motivo = trim($_POST['motivo_annullamento']);

    try {
        $stmt = $pdo->prepare("
            UPDATE multa 
            SET stato = 'pagata',
                importo = 0,
                note = CONCAT(COALESCE(note, ''), '\n[ANNULLATA] Motivo: ', :motivo, ' - Admin: ', :admin)
            WHERE id_multa = :id
        ");
        $stmt->execute([
            'id' => $id_multa,
            'motivo' => $motivo,
            'admin' => $_SESSION['username']
        ]);

        $success = "Multa annullata con successo";

    } catch(Exception $e) {
        $error = "Errore: " . $e->getMessage();
    }
}

// Filtri
$filtro_stato = $_GET['stato'] ?? 'non_pagata';
$filtro_tipo = $_GET['tipo'] ?? '';
$search = trim($_GET['search'] ?? '');

// Query multe
$sql = "
    SELECT 
        m.*,
        u.nome,
        u.cognome,
        u.email,
        u.codice_tessera,
        u.prestiti_bloccati,
        p.id_prestito,
        l.titolo as libro_titolo,
        DATEDIFF(NOW(), m.data_creazione) as giorni_multa_aperta
    FROM multa m
    JOIN utente u ON m.id_utente = u.id_utente
    LEFT JOIN prestito p ON m.id_prestito = p.id_prestito
    LEFT JOIN copia c ON p.id_copia = c.id_copia
    LEFT JOIN libro l ON c.id_libro = l.id_libro
    WHERE 1=1
";

$params = [];

if($filtro_stato) {
    $sql .= " AND m.stato = :stato";
    $params['stato'] = $filtro_stato;
}

if($filtro_tipo) {
    $sql .= " AND m.tipo_multa = :tipo";
    $params['tipo'] = $filtro_tipo;
}

if($search) {
    $sql .= " AND (u.nome LIKE :search OR u.cognome LIKE :search OR u.email LIKE :search OR u.codice_tessera LIKE :search)";
    $params['search'] = "%$search%";
}

$sql .= " ORDER BY m.data_creazione DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$multe = $stmt->fetchAll();

// Statistiche
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as totale,
        SUM(CASE WHEN stato = 'non_pagata' THEN 1 ELSE 0 END) as non_pagate,
        SUM(CASE WHEN stato = 'non_pagata' THEN importo ELSE 0 END) as importo_non_pagato,
        SUM(CASE WHEN stato = 'pagata' THEN importo ELSE 0 END) as importo_incassato_totale,
        SUM(CASE WHEN stato = 'pagata' AND DATE(data_pagamento) = CURDATE() THEN importo ELSE 0 END) as incassato_oggi
    FROM multa
");
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
    <link rel="stylesheet" href="../../public/assets/css/gestione_multe.css">

</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="dashboard-container">
    <div class="multe-header">
        <div>
            <h1>💰 Gestione Multe</h1>
            <p>Visualizza e gestisci multe utenti</p>
        </div>
        <a href="dashboard_bibliotecario.php" class="btn-secondary">← Dashboard</a>
    </div>

    <?php if($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Statistiche -->
    <div class="stats-multe">
        <div class="stat-multa danger">
            <h3>Multe Non Pagate</h3>
            <div class="value"><?= $stats['non_pagate'] ?></div>
        </div>

        <div class="stat-multa danger">
            <h3>Importo da Incassare</h3>
            <div class="value">€<?= number_format($stats['importo_non_pagato'], 2, ',', '.') ?></div>
        </div>

        <div class="stat-multa">
            <h3>Incassato Oggi</h3>
            <div class="value">€<?= number_format($stats['incassato_oggi'], 2, ',', '.') ?></div>
        </div>

        <div class="stat-multa">
            <h3>Totale Incassato</h3>
            <div class="value">€<?= number_format($stats['importo_incassato_totale'], 2, ',', '.') ?></div>
        </div>
    </div>

    <!-- Filtri -->
    <div class="filtri-multe">
        <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; width: 100%;">
            <select name="stato" onchange="this.form.submit()">
                <option value="">Tutti gli stati</option>
                <option value="non_pagata" <?= $filtro_stato === 'non_pagata' ? 'selected' : '' ?>>Non Pagate</option>
                <option value="pagata" <?= $filtro_stato === 'pagata' ? 'selected' : '' ?>>Pagate</option>
            </select>

            <select name="tipo" onchange="this.form.submit()">
                <option value="">Tutti i tipi</option>
                <option value="ritardo" <?= $filtro_tipo === 'ritardo' ? 'selected' : '' ?>>Ritardo</option>
                <option value="danno" <?= $filtro_tipo === 'danno' ? 'selected' : '' ?>>Danno</option>
                <option value="smarrimento" <?= $filtro_tipo === 'smarrimento' ? 'selected' : '' ?>>Smarrimento</option>
            </select>

            <input type="text" name="search" placeholder="Cerca utente..."
                   value="<?= htmlspecialchars($search) ?>" style="flex: 1; min-width: 200px;">

            <button type="submit" class="btn-primary">Filtra</button>
            <a href="gestione_multe.php" class="btn-secondary">Reset</a>
        </form>
    </div>

    <!-- Lista Multe -->
    <div class="section-card">
        <h2>Multe (<?= count($multe) ?>)</h2>

        <?php if(empty($multe)): ?>
            <p style="text-align: center; color: #888; padding: 40px;">
                Nessuna multa trovata con i filtri selezionati
            </p>
        <?php else: ?>
            <?php foreach($multe as $multa):
                $classe_gravita = '';
                if($multa['stato'] === 'pagata') {
                    $classe_gravita = 'pagata';
                } elseif($multa['importo'] > 10 || $multa['giorni_ritardo'] > 14) {
                    $classe_gravita = 'grave';
                }
                ?>
                <div class="multa-row <?= $classe_gravita ?>">
                    <div class="multa-info">
                        <!-- Utente -->
                        <div class="multa-utente">
                            <div>
                                <strong style="font-size: 16px;">
                                    <?= htmlspecialchars($multa['nome'] . ' ' . $multa['cognome']) ?>
                                </strong>
                                <?php if($multa['prestiti_bloccati']): ?>
                                    <span class="blocco-badge">BLOCCATO</span>
                                <?php endif; ?>
                                <div style="color: #888; font-size: 13px; margin-top: 5px;">
                                    <?= htmlspecialchars($multa['email']) ?> •
                                    Tessera: <?= htmlspecialchars($multa['codice_tessera']) ?>
                                </div>
                            </div>
                        </div>

                        <!-- Causale -->
                        <div>
                            <div style="color: #888; font-size: 12px;">Causale</div>
                            <strong><?= htmlspecialchars($multa['causale']) ?></strong>
                            <?php if($multa['libro_titolo']): ?>
                                <div style="color: #888; font-size: 13px;">
                                    <?= htmlspecialchars($multa['libro_titolo']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Tipo e Dettagli -->
                        <div>
                            <div style="color: #888; font-size: 12px;">Tipo</div>
                            <strong style="text-transform: capitalize;">
                                <?= htmlspecialchars($multa['tipo_multa']) ?>
                            </strong>
                            <?php if($multa['giorni_ritardo'] > 0): ?>
                                <div style="color: #ff9800; font-size: 13px;">
                                    <?= $multa['giorni_ritardo'] ?> giorni
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Importo -->
                        <div style="text-align: right;">
                            <div class="importo-grande">
                                €<?= number_format($multa['importo'], 2, ',', '.') ?>
                            </div>
                            <div style="color: #888; font-size: 12px;">
                                <?= date('d/m/Y', strtotime($multa['data_creazione'])) ?>
                            </div>
                            <?php if($multa['stato'] === 'pagata' && $multa['data_pagamento']): ?>
                                <div style="color: #0c8a1f; font-size: 12px;">
                                    Pagata il <?= date('d/m/Y', strtotime($multa['data_pagamento'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Azioni -->
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <?php if($multa['stato'] === 'non_pagata'): ?>
                                <button onclick="apriModalPagamento(<?= $multa['id_multa'] ?>, '<?= htmlspecialchars($multa['nome'] . ' ' . $multa['cognome']) ?>', <?= $multa['importo'] ?>)"
                                        class="btn-small btn-success">
                                    Registra Pagamento
                                </button>

                                <?php if(isAdmin()): ?>
                                    <button onclick="apriModalAnnullamento(<?= $multa['id_multa'] ?>)"
                                            class="btn-small btn-danger">
                                        Annulla
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="dettaglio_multa.php?id=<?= $multa['id_multa'] ?>"
                                   class="btn-small btn-info">
                                    Dettagli
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if($multa['note']): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #444; color: #888; font-size: 13px;">
                            <strong>Note:</strong> <?= nl2br(htmlspecialchars($multa['note'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Pagamento -->
<div id="modalPagamento" class="modal-pagamento">
    <div class="modal-content-pagamento">
        <h2>Registra Pagamento Multa</h2>

        <form method="POST" class="form-pagamento">
            <input type="hidden" name="id_multa" id="multa_id">

            <div style="background: rgba(12, 138, 31, 0.1); padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <div style="font-size: 14px; color: #888;">Utente:</div>
                <div style="font-size: 18px; font-weight: bold; color: #ebebed;" id="multa_utente"></div>
                <div style="font-size: 24px; font-weight: bold; color: #0c8a1f; margin-top: 10px;">
                    Importo: €<span id="multa_importo"></span>
                </div>
            </div>

            <div class="form-group">
                <label for="metodo_pagamento">Metodo di Pagamento</label>
                <select name="metodo_pagamento" id="metodo_pagamento" required>
                    <option value="">Seleziona metodo...</option>
                    <option value="contanti">Contanti</option>
                    <option value="carta">Carta</option>
                    <option value="bonifico">Bonifico</option>
                </select>
            </div>

            <div class="form-group">
                <label for="note_pagamento">Note (opzionale)</label>
                <textarea name="note_pagamento" id="note_pagamento" rows="3"
                          placeholder="Es: Ricevuta #123, riferimento transazione..."></textarea>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="registra_pagamento" class="btn-success" style="flex: 1;">
                    Conferma Pagamento
                </button>
                <button type="button" onclick="chiudiModalPagamento()" class="btn-secondary" style="flex: 1;">
                    Annulla
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Annullamento (Solo Admin) -->
<?php if(isAdmin()): ?>
    <div id="modalAnnullamento" class="modal-pagamento">
        <div class="modal-content-pagamento">
            <h2>Annulla Multa</h2>

            <div style="background: rgba(179, 0, 0, 0.1); padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #b30000;">
                <strong style="color: #b30000;">⚠Attenzione:</strong>
                <p style="margin: 5px 0 0 0; color: #888;">
                    Questa azione annullerà definitivamente la multa. L'operazione verrà registrata nel log.
                </p>
            </div>

            <form method="POST" class="form-pagamento">
                <input type="hidden" name="id_multa" id="annulla_multa_id">

                <div class="form-group">
                    <label for="motivo_annullamento">Motivo Annullamento *</label>
                    <textarea name="motivo_annullamento" id="motivo_annullamento" rows="4" required
                              placeholder="Es: Errore di sistema, libro restituito in tempo, ecc."></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="annulla_multa" class="btn-danger" style="flex: 1;">
                        Conferma Annullamento
                    </button>
                    <button type="button" onclick="chiudiModalAnnullamento()" class="btn-secondary" style="flex: 1;">
                        Annulla
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
    function apriModalPagamento(idMulta, nomeUtente, importo) {
        document.getElementById('multa_id').value = idMulta;
        document.getElementById('multa_utente').textContent = nomeUtente;
        document.getElementById('multa_importo').textContent = importo.toFixed(2).replace('.', ',');
        document.getElementById('modalPagamento').style.display = 'block';
    }

    function chiudiModalPagamento() {
        document.getElementById('modalPagamento').style.display = 'none';
    }

    function apriModalAnnullamento(idMulta) {
        document.getElementById('annulla_multa_id').value = idMulta;
        document.getElementById('modalAnnullamento').style.display = 'block';
    }

    function chiudiModalAnnullamento() {
        document.getElementById('modalAnnullamento').style.display = 'none';
    }

    // Chiudi modal cliccando fuori
    window.onclick = function(event) {
        const modalPagamento = document.getElementById('modalPagamento');
        const modalAnnullamento = document.getElementById('modalAnnullamento');

        if (event.target === modalPagamento) {
            chiudiModalPagamento();
        }
        if (event.target === modalAnnullamento) {
            chiudiModalAnnullamento();
        }
    }
</script>

</body>
</html>