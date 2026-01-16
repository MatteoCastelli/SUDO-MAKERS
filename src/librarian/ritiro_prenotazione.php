<?php
/**
 * RITIRO PRENOTAZIONE - Pagina per confermare il ritiro del libro prenotato
 * Solo per bibliotecari
 */

use Proprietario\SudoMakers\core\Database;

session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/check_permissions.php';

requireAnyRole(['bibliotecario', 'amministratore']);

$pdo = Database::getInstance()->getConnection();
$title = "Ritiro Prenotazione";

$success = '';
$error = '';

// Gestione conferma ritiro
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['conferma_ritiro'])) {
    $id_prenotazione = (int)$_POST['id_prenotazione'];

    try {
        $pdo->beginTransaction();

        // Recupera prenotazione
        $stmt = $pdo->prepare("
            SELECT p.*, l.titolo, u.nome, u.cognome, c.id_copia
            FROM prenotazione p
            JOIN libro l ON p.id_libro = l.id_libro
            JOIN utente u ON p.id_utente = u.id_utente
            LEFT JOIN copia c ON p.id_copia_assegnata = c.id_copia
            WHERE p.id_prenotazione = :id
            AND p.stato = 'disponibile'
        ");
        $stmt->execute(['id' => $id_prenotazione]);
        $prenotazione = $stmt->fetch();

        if(!$prenotazione) {
            throw new Exception("Prenotazione non trovata o non disponibile");
        }

        // Crea prestito
        $data_scadenza = date('Y-m-d H:i:s', strtotime('+1 month'));

        $stmt = $pdo->prepare("
            INSERT INTO prestito (id_utente, id_copia, id_bibliotecario, data_scadenza, note)
            VALUES (:id_utente, :id_copia, :id_biblio, :scadenza, :note)
        ");
        $stmt->execute([
            'id_utente' => $prenotazione['id_utente'],
            'id_copia' => $prenotazione['id_copia'],
            'id_biblio' => $_SESSION['id_utente'],
            'scadenza' => $data_scadenza,
            'note' => 'Prestito da prenotazione - Bibliotecario: ' . $_SESSION['username']
        ]);

        // Aggiorna prenotazione
        $stmt = $pdo->prepare("UPDATE prenotazione SET stato = 'ritirata' WHERE id_prenotazione = :id");
        $stmt->execute(['id' => $id_prenotazione]);

        // Notifica utente
        $stmt = $pdo->prepare("
            INSERT INTO notifica (id_utente, tipo, titolo, messaggio, priorita)
            VALUES (:id_utente, 'prestito', 'Prestito Attivato', :msg, 'media')
        ");
        $stmt->execute([
            'id_utente' => $prenotazione['id_utente'],
            'msg' => "Hai ritirato '{$prenotazione['titolo']}'. Data restituzione: " . date('d/m/Y', strtotime($data_scadenza))
        ]);

        $pdo->commit();

        $success = "Prestito confermato per {$prenotazione['nome']} {$prenotazione['cognome']}!";

    } catch(Exception $e) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Errore: " . $e->getMessage();
    }
}

// Recupera prenotazioni disponibili per ritiro
$stmt = $pdo->query("
    SELECT p.*, 
           u.nome, u.cognome, u.email, u.codice_tessera,
           l.titolo, l.immagine_copertina_url,
           c.codice_barcode,
           TIMESTAMPDIFF(HOUR, NOW(), p.data_scadenza_ritiro) as ore_rimaste
    FROM prenotazione p
    JOIN utente u ON p.id_utente = u.id_utente
    JOIN libro l ON p.id_libro = l.id_libro
    LEFT JOIN copia c ON p.id_copia_assegnata = c.id_copia
    WHERE p.stato = 'disponibile'
    ORDER BY p.data_scadenza_ritiro ASC
");
$prenotazioni = $stmt->fetchAll();
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
        <h1>📦 Ritiro Prenotazioni</h1>
        <a href="dashboard_bibliotecario.php" class="btn-back">← Dashboard</a>
    </div>

    <?php if($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if(empty($prenotazioni)): ?>
        <div class="section-card">
            <p style="text-align: center; color: #888; padding: 40px;">
                Nessuna prenotazione disponibile per il ritiro
            </p>
        </div>
    <?php else: ?>
        <div class="section-card">
            <h2>Prenotazioni Disponibili (<?= count($prenotazioni) ?>)</h2>

            <?php foreach($prenotazioni as $pren):
                $in_scadenza = $pren['ore_rimaste'] < 12;
                ?>
                <div class="prestito-card" style="border-left: 4px solid <?= $in_scadenza ? '#b30000' : '#0c8a1f' ?>">
                    <div style="display: flex; gap: 20px; align-items: start;">
                        <!-- Copertina -->
                        <div style="width: 100px; flex-shrink: 0;">
                            <?php if($pren['immagine_copertina_url']): ?>
                                <img src="<?= htmlspecialchars($pren['immagine_copertina_url']) ?>"
                                     alt="Copertina"
                                     style="width: 100%; border-radius: 6px;">
                            <?php else: ?>
                                <div style="width: 100px; height: 150px; background: #333; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #888;">
                                    📖
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Info -->
                        <div style="flex: 1;">
                            <h3 style="margin: 0 0 10px 0; color: #ebebed;">
                                <?= htmlspecialchars($pren['titolo']) ?>
                            </h3>

                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 15px;">
                                <div>
                                    <strong style="color: #888;">Utente:</strong><br>
                                    <?= htmlspecialchars($pren['nome'] . ' ' . $pren['cognome']) ?>
                                    <div style="font-size: 13px; color: #888; margin-top: 3px;">
                                        📧 <?= htmlspecialchars($pren['email']) ?><br>
                                        🆔 Tessera: <?= htmlspecialchars($pren['codice_tessera']) ?>
                                    </div>
                                </div>

                                <div>
                                    <strong style="color: #888;">Codice a Barre:</strong><br>
                                    <code style="background: rgba(0,0,0,0.3); padding: 5px 10px; border-radius: 4px; display: inline-block;">
                                        <?= htmlspecialchars($pren['codice_barcode']) ?>
                                    </code>
                                </div>
                            </div>

                            <div style="background: rgba(<?= $in_scadenza ? '179, 0, 0' : '12, 138, 31' ?>, 0.1); padding: 12px; border-radius: 6px; margin-bottom: 15px;">
                                <strong style="color: <?= $in_scadenza ? '#ff9800' : '#0c8a1f' ?>;">
                                    ⏰ Scadenza ritiro: <?= date('d/m/Y H:i', strtotime($pren['data_scadenza_ritiro'])) ?>
                                    (<?= abs($pren['ore_rimaste']) ?>h rimaste)
                                </strong>
                            </div>

                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="id_prenotazione" value="<?= $pren['id_prenotazione'] ?>">
                                <button type="submit" name="conferma_ritiro" class="btn-success"
                                        onclick="return confirm('Confermi il ritiro del libro?')">
                                    ✅ Conferma Ritiro e Crea Prestito
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
