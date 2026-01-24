<?php
/**
 * Restituzione Rapida - Pagina per bibliotecari
 * Permette di gestire restituzioni veloci tramite scanner
 */

use Proprietario\SudoMakers\core\Database;

session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/check_permissions.php';

// SOLO BIBLIOTECARI
requireAnyRole(['bibliotecario', 'amministratore']);

$pdo = Database::getInstance()->getConnection();
$title = "Restituzione Rapida";

$errore = '';
$success = '';
$prestito_info = null;

// Recupera codice da scanner
$codice = $_GET['codice'] ?? '';

if(!empty($codice)) {
    // Cerca prestito attivo per questa copia
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            c.codice_barcode,
            c.stato_fisico,
            l.titolo,
            l.id_libro,
            GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori,
            u.nome as utente_nome,
            u.cognome as utente_cognome,
            u.email as utente_email,
            DATEDIFF(p.data_scadenza, NOW()) as giorni_rimanenti
        FROM prestito p
        JOIN copia c ON p.id_copia = c.id_copia
        JOIN libro l ON c.id_libro = l.id_libro
        LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
        LEFT JOIN autore a ON la.id_autore = a.id_autore
        JOIN utente u ON p.id_utente = u.id_utente
        WHERE c.codice_barcode = :codice
        AND p.data_restituzione_effettiva IS NULL
        GROUP BY p.id_prestito
    ");
    $stmt->execute(['codice' => $codice]);
    $prestito_info = $stmt->fetch();

    if(!$prestito_info) {
        $errore = "Nessun prestito attivo trovato per questa copia";
    }
}

// Gestione conferma restituzione
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['conferma_restituzione'])) {
    $id_prestito = (int)$_POST['id_prestito'];
    $id_copia = (int)$_POST['id_copia'];
    $id_libro = (int)$_POST['id_libro'];
    $nuovo_stato = $_POST['nuovo_stato'] ?? 'buono';
    $note = trim($_POST['note'] ?? '');

    try {
        $pdo->beginTransaction();

        // Chiudi prestito
        $stmt = $pdo->prepare("
            UPDATE prestito 
            SET data_restituzione_effettiva = NOW() 
            WHERE id_prestito = :id
        ");
        $stmt->execute(['id' => $id_prestito]);

        // Aggiorna stato copia e rendila disponibile
        $stmt = $pdo->prepare("
            UPDATE copia 
            SET disponibile = 1, 
                stato_fisico = :stato
            WHERE id_copia = :id
        ");
        $stmt->execute([
                'id' => $id_copia,
                'stato' => $nuovo_stato
        ]);

        // Log operazione
        if(!empty($note)) {
            $stmt = $pdo->prepare("
                UPDATE prestito 
                SET note = CONCAT(COALESCE(note, ''), '\n[Restituzione] ', :note)
                WHERE id_prestito = :id
            ");
            $stmt->execute([
                    'id' => $id_prestito,
                    'note' => $note . ' - Bibliotecario: ' . $_SESSION['username']
            ]);
        }

        // Controlla se ci sono prenotazioni in attesa
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM prenotazione 
            WHERE id_libro = :id_libro 
            AND stato = 'attiva'
        ");
        $stmt->execute(['id_libro' => $id_libro]);
        $has_queue = $stmt->fetchColumn() > 0;

        if($has_queue) {
            // Assegna al primo in coda
            require_once __DIR__ . '/gestione_prenotazioni_functions.php';
            assegnaLibroAlPrimoInCoda($id_libro, $pdo);
            $queue_message = " Il libro è stato assegnato al primo utente in coda.";
        } else {
            $queue_message = "";
        }
        // ===== HOOK GAMIFICATION - Badge Restituzione =====
        require_once __DIR__ . '/../core/GamificationEngine.php';
        $gamification = new \Proprietario\SudoMakers\core\GamificationEngine($pdo);

// Recupera info libro e utente dal prestito
        $stmt = $pdo->prepare("
    SELECT p.id_utente, l.id_libro, l.categoria, p.data_scadenza, p.data_restituzione_effettiva
    FROM prestito p
    JOIN copia c ON p.id_copia = c.id_copia
    JOIN libro l ON c.id_libro = l.id_libro
    WHERE p.id_prestito = :id_prestito
");
        $stmt->execute(['id_prestito' => $id_prestito]); // usa l'ID del prestito che hai appena aggiornato
        $prestito_info = $stmt->fetch();

        if($prestito_info) {
            // Check badge LETTURE (questo si sblocca alla restituzione!)
            $badges = $gamification->checkAndAwardBadges($prestito_info['id_utente'], 'prestito_completato');

            // Check badge GENERE
            if($prestito_info['categoria']) {
                $badges_genere = $gamification->checkAndAwardBadges(
                        $prestito_info['id_utente'],
                        'genere_esplorato',
                        ['categoria' => $prestito_info['categoria']]
                );
            }

            // Check badge VELOCITÀ (se restituito in anticipo)
            if(strtotime($prestito_info['data_restituzione_effettiva']) < strtotime($prestito_info['data_scadenza'])) {
                $badges_velocita = $gamification->checkAndAwardBadges(
                        $prestito_info['id_utente'],
                        'restituzione_anticipata'
                );
            }

            // Aggiorna obiettivi
            $gamification->updateObjectiveProgress($prestito_info['id_utente']);
        }
        $pdo->commit();

        $success = "Restituzione completata con successo!{$queue_message}";

        // Reset per nuova restituzione
        $prestito_info = null;
        $codice = ''; // ← IMPORTANTE: Resetta anche il codice

    } catch(Exception $e) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errore = "Errore: " . $e->getMessage();
    }
}
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
        .scan-container {
            text-align: center;
            padding: 20px;
        }
        .scan-input {
            font-size: 18px;
            padding: 15px;
            text-align: center;
            letter-spacing: 2px;
            max-width: 400px;
            margin: 0 auto;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .info-box {
            background: #2a2a2c;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #303033;
        }
        .info-box h3 {
            margin-top: 0;
            color: #0c8a1f;
            font-size: 18px;
            border-bottom: 1px solid #444;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            border-bottom: 1px solid #333;
            padding-bottom: 5px;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #888;
        }
        .info-value {
            font-weight: bold;
            color: #ebebed;
            text-align: right;
        }
        .stato-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .stato-option {
            border: 1px solid #444;
            padding: 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            background: #2a2a2c;
            position: relative;
        }
        .stato-option:hover {
            border-color: #666;
            background: #333;
        }
        .stato-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }
        .stato-option.selected {
            border-color: #0c8a1f;
            background: rgba(12, 138, 31, 0.1);
        }
        .stato-label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 16px;
        }
        .stato-desc {
            font-size: 12px;
            color: #888;
        }
        .btn-large {
            width: 100%;
            padding: 15px;
            font-size: 18px;
        }
        .actions-container {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        .actions-container .btn {
            flex: 1;
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Restituzione Rapida</h1>
        <a href="dashboard_bibliotecario.php" class="btn-back">← Torna alla Dashboard</a>
    </div>

    <?php if($errore): ?>
        <div class="alert alert-error">
            <span>⚠️</span> <?= htmlspecialchars($errore) ?>
        </div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="alert alert-success">
            <span>✅</span> <?= $success ?>
        </div>
        <div style="text-align: center; margin-top: 30px;">
            <a href="restituzione_rapida.php" class="btn-primary btn-large" style="max-width: 300px;">Nuova Restituzione</a>
            <br><br>
        </div>
    <?php endif; ?>

    <?php if(!$success && !$prestito_info): ?>
        <!-- Scansiona codice -->
        <div class="section-card">
            <h2>Scansiona Codice</h2>
            <div class="scan-container">
                <p style="color: #888; margin-bottom: 20px;">Usa lo scanner per leggere il codice barcode della copia.</p>
                <form method="GET">
                    <div class="form-group">
                        <input type="text" name="codice" placeholder="Codice copia (es: LIB00000001)"
                               class="form-input scan-input" autofocus required>
                    </div>
                    <button type="submit" class="btn-primary btn-large" style="max-width: 400px;">
                        Cerca Prestito
                    </button>
                </form>
            </div>
        </div>
    <?php elseif($prestito_info && !$success): ?>
        <!-- Info prestito e conferma restituzione -->
        <div class="info-grid">
            <div class="info-box">
                <h3>Libro</h3>
                <div class="info-row">
                    <span class="info-label">Titolo</span>
                    <span class="info-value"><?= htmlspecialchars($prestito_info['titolo']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Autori</span>
                    <span class="info-value"><?= htmlspecialchars($prestito_info['autori']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Codice</span>
                    <span class="info-value"><?= htmlspecialchars($prestito_info['codice_barcode']) ?></span>
                </div>
            </div>

            <div class="info-box">
                <h3>Utente</h3>
                <div class="info-row">
                    <span class="info-label">Nome</span>
                    <span class="info-value"><?= htmlspecialchars($prestito_info['utente_nome'] . ' ' . $prestito_info['utente_cognome']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($prestito_info['utente_email']) ?></span>
                </div>
            </div>

            <div class="info-box">
                <h3>Scadenza</h3>
                <div class="info-row">
                    <span class="info-label">Data Prestito</span>
                    <span class="info-value"><?= date('d/m/Y', strtotime($prestito_info['data_prestito'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Scadenza</span>
                    <span class="info-value"><?= date('d/m/Y', strtotime($prestito_info['data_scadenza'])) ?></span>
                </div>
                <div style="margin-top: 15px; text-align: center;">
                    <?php if($prestito_info['giorni_rimanenti'] < 0): ?>
                        <span class="badge badge-danger" style="background: #dc3545; padding: 5px 10px; border-radius: 4px; color: white;">
                            SCADUTO da <?= abs($prestito_info['giorni_rimanenti']) ?> giorni
                        </span>
                    <?php elseif($prestito_info['giorni_rimanenti'] <= 3): ?>
                        <span class="badge badge-warning" style="background: #ffc107; padding: 5px 10px; border-radius: 4px; color: black;">
                            In scadenza (<?= $prestito_info['giorni_rimanenti'] ?> giorni)
                        </span>
                    <?php else: ?>
                        <span class="badge badge-success" style="background: #28a745; padding: 5px 10px; border-radius: 4px; color: white;">
                            In regola (<?= $prestito_info['giorni_rimanenti'] ?> giorni)
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Form conferma restituzione -->
        <div class="section-card">
            <h2>Conferma Restituzione</h2>
            <form method="POST">
                <input type="hidden" name="id_prestito" value="<?= $prestito_info['id_prestito'] ?>">
                <input type="hidden" name="id_copia" value="<?= $prestito_info['id_copia'] ?>">
                <input type="hidden" name="id_libro" value="<?= $prestito_info['id_libro'] ?>">

                <div class="form-group">
                    <label>Valuta lo stato fisico del libro:</label>
                    <p style="color: #888; font-size: 14px; margin-top: 0;">
                        Stato precedente: <strong style="color: #0c8a1f;"><?= ucfirst($prestito_info['stato_fisico']) ?></strong>
                    </p>
                    
                    <div class="stato-grid">
                        <?php
                        $stati = [
                            'ottimo' => ['Ottimo', 'Come nuovo'],
                            'buono' => ['Buono', 'Normale usura'],
                            'discreto' => ['Discreto', 'Segni evidenti'],
                            'danneggiato' => ['Danneggiato', 'Richiede riparazione']
                        ];
                        $current_stato = $prestito_info['stato_fisico'] ?? 'buono';
                        ?>
                        <?php foreach($stati as $value => $label): ?>
                            <div class="stato-option <?= $current_stato === $value ? 'selected' : '' ?>" onclick="selectStato(this)">
                                <input type="radio" name="nuovo_stato" value="<?= $value ?>" <?= $current_stato === $value ? 'checked' : '' ?>>
                                <span class="stato-label"><?= $label[0] ?></span>
                                <span class="stato-desc"><?= $label[1] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="note">Note aggiuntive (opzionale):</label>
                    <textarea name="note" id="note" rows="3"
                              placeholder="Es: Pagine piegate, copertina rovinata..."
                              class="form-textarea"></textarea>
                </div>

                <div class="actions-container">
                    <a href="restituzione_rapida.php" class="btn-secondary btn" style="text-align: center; padding-top: 15px;">Annulla</a>
                    <button type="submit" name="conferma_restituzione" class="btn-primary btn">
                        Conferma Restituzione
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
    function selectStato(element) {
        // Rimuovi selezione da tutti
        document.querySelectorAll('.stato-option').forEach(el => el.classList.remove('selected'));
        // Aggiungi a quello cliccato
        element.classList.add('selected');
        // Seleziona il radio button
        const radio = element.querySelector('input[type="radio"]');
        radio.checked = true;
    }
</script>

</body>
</html>