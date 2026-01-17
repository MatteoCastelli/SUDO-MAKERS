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
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="restituzione-container">
    <h1>Restituzione Rapida</h1>

    <?php if($errore): ?>
        <div class="alert alert-error"><?= htmlspecialchars($errore) ?></div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
        <div style="text-align: center; margin-top: 20px;">
            <a href="restituzione_rapida.php" class="btn-primary">Nuova Restituzione</a>
            <a href="dashboard_bibliotecario.php" class="btn-secondary">Torna alla Dashboard</a>
        </div>
    <?php endif; ?>

    <?php if(!$success && !$prestito_info): ?>
        <!-- Scansiona codice -->
        <div class="info-box">
            <h3>Scansiona il codice del libro da restituire</h3>
            <p>Usa lo scanner per leggere il codice barcode della copia.</p>
            <form method="GET" style="margin-top: 20px;">
                <input type="text" name="codice" placeholder="Codice copia (es: LIB00000001)"
                       class="input-barcode" autofocus required
                       style="width: 100%; padding: 15px; font-size: 18px;
                              background: rgba(0,0,0,0.3); border: 2px solid #444;
                              color: #ebebed; border-radius: 8px;">
                <button type="submit" class="btn-primary"
                        style="margin-top: 10px; width: 100%; padding: 15px; font-size: 16px;">
                    Continua
                </button>
            </form>
        </div>
    <?php elseif($prestito_info && !$success): ?>
        <!-- Info prestito e conferma restituzione -->
        <div class="info-box">
            <h3>Libro:</h3>
            <p style="font-size: 18px; margin: 5px 0;">
                <strong><?= htmlspecialchars($prestito_info['titolo']) ?></strong>
            </p>
            <p style="color: #888;"><?= htmlspecialchars($prestito_info['autori']) ?></p>
            <p style="color: #888;">Codice: <?= htmlspecialchars($prestito_info['codice_barcode']) ?></p>
        </div>

        <div class="info-box">
            <h3>Utente:</h3>
            <p style="font-size: 18px; margin: 5px 0;">
                <strong><?= htmlspecialchars($prestito_info['utente_nome'] . ' ' . $prestito_info['utente_cognome']) ?></strong>
            </p>
            <p style="color: #888;"><?= htmlspecialchars($prestito_info['utente_email']) ?></p>
        </div>

        <div class="info-box">
            <h3>Informazioni prestito:</h3>
            <p>Data prestito: <strong><?= date('d/m/Y', strtotime($prestito_info['data_prestito'])) ?></strong></p>
            <p>Data scadenza: <strong><?= date('d/m/Y', strtotime($prestito_info['data_scadenza'])) ?></strong></p>

            <?php if($prestito_info['giorni_rimanenti'] < 0): ?>
                <div class="danger-box">
                    <strong>PRESTITO SCADUTO</strong><br>
                    Scaduto da <?= abs($prestito_info['giorni_rimanenti']) ?> giorni
                </div>
            <?php elseif($prestito_info['giorni_rimanenti'] <= 3): ?>
                <div class="warning-box">
                    <strong>Prestito in scadenza</strong><br>
                    <?= $prestito_info['giorni_rimanenti'] ?> giorni rimanenti
                </div>
            <?php else: ?>
                <p style="color: #0c8a1f;">
                    In regola (<?= $prestito_info['giorni_rimanenti'] ?> giorni rimanenti)
                </p>
            <?php endif; ?>
        </div>

        <!-- Form conferma restituzione -->
        <form method="POST">
            <input type="hidden" name="id_prestito" value="<?= $prestito_info['id_prestito'] ?>">
            <input type="hidden" name="id_copia" value="<?= $prestito_info['id_copia'] ?>">
            <input type="hidden" name="id_libro" value="<?= $prestito_info['id_libro'] ?>">

            <div style="margin: 30px 0;">
                <h3>Valuta lo stato fisico del libro:</h3>
                <p style="color: #888; margin-bottom: 15px;">
                    Stato attuale nel sistema: <strong style="color: #0c8a1f;"><?= ucfirst($prestito_info['stato_fisico']) ?></strong>
                </p>
                <div class="stato-grid">
                    <div class="stato-option">
                        <input type="radio" name="nuovo_stato" value="ottimo" id="stato_ottimo"
                                <?= ($prestito_info['stato_fisico'] ?? 'buono') === 'ottimo' ? 'checked' : '' ?>>
                        <label for="stato_ottimo" style="cursor: pointer; display: block;">
                            <br><strong>Ottimo</strong><br>
                            <small style="color: #888;">Come nuovo</small>
                        </label>
                    </div>
                    <div class="stato-option">
                        <input type="radio" name="nuovo_stato" value="buono" id="stato_buono"
                                <?= ($prestito_info['stato_fisico'] ?? 'buono') === 'buono' ? 'checked' : '' ?>>
                        <label for="stato_buono" style="cursor: pointer; display: block;">
                            <br><strong>Buono</strong><br>
                            <small style="color: #888;">Normale usura</small>
                        </label>
                    </div>
                    <div class="stato-option">
                        <input type="radio" name="nuovo_stato" value="discreto" id="stato_discreto"
                                <?= ($prestito_info['stato_fisico'] ?? 'buono') === 'discreto' ? 'checked' : '' ?>>
                        <label for="stato_discreto" style="cursor: pointer; display: block;">
                            <br><strong>Discreto</strong><br>
                            <small style="color: #888;">Segni evidenti</small>
                        </label>
                    </div>
                    <div class="stato-option">
                        <input type="radio" name="nuovo_stato" value="danneggiato" id="stato_danneggiato"
                                <?= ($prestito_info['stato_fisico'] ?? 'buono') === 'danneggiato' ? 'checked' : '' ?>>
                        <label for="stato_danneggiato" style="cursor: pointer; display: block;">
                            <br><strong>Danneggiato</strong><br>
                            <small style="color: #888;">Richiede riparazione</small>
                        </label>
                    </div>
                </div>
            </div>

            <div style="margin: 20px 0;">
                <label style="display: block; margin-bottom: 10px; color: #ebebed;">
                    <strong>Note aggiuntive (opzionale):</strong>
                </label>
                <textarea name="note" rows="3"
                          placeholder="Es: Pagine piegate, copertina rovinata..."
                          style="width: 100%; padding: 15px; font-size: 14px;
                         background: rgba(0,0,0,0.3); border: 2px solid #444;
                         color: #ebebed; border-radius: 8px;
                         font-family: inherit; resize: vertical;"></textarea>
            </div>

            <button type="submit" name="conferma_restituzione" class="btn-primary"
                    style="width: 100%; padding: 20px; font-size: 18px; margin-top: 20px;">
                Conferma Restituzione
            </button>

            <a href="restituzione_rapida.php" class="btn-secondary"
               style="width: 96%; display: block; text-align: center; margin-top: 10px; padding: 15px;">
                Annulla
            </a>
        </form>
    <?php endif; ?>
</div>

<script>
    // Click su tutta l'area della card per selezionare lo stato
    document.querySelectorAll('.stato-option').forEach(option => {
        option.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            radio.checked = true;

            // Rimuovi highlight da tutte
            document.querySelectorAll('.stato-option').forEach(o => {
                o.style.borderColor = '#444';
                o.style.background = 'transparent';
            });

            // Aggiungi highlight alla selezionata
            this.style.borderColor = '#0c8a1f';
            this.style.background = 'rgba(12, 138, 31, 0.1)';
        });
    });

    // Imposta highlight iniziale su "buono"
    document.querySelector('input[name="nuovo_stato"]:checked')
        .closest('.stato-option').style.borderColor = '#0c8a1f';
    document.querySelector('input[name="nuovo_stato"]:checked')
        .closest('.stato-option').style.background = 'rgba(12, 138, 31, 0.1)';
</script>

</body>
</html>