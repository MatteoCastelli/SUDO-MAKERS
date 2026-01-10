<?php
/**
 * Preferenze Notifiche - Gestione preferenze utente
 * Permette agli utenti di configurare quando e come ricevere le notifiche
 */

use Proprietario\SudoMakers\core\Database;

session_start();
require_once __DIR__ . '/../core/Database.php';

if(!isset($_SESSION['id_utente'])) {
    header("Location: ../auth/login.php");
    exit;
}

$pdo = Database::getInstance()->getConnection();
$id_utente = $_SESSION['id_utente'];
$title = "Preferenze Notifiche";

$success = '';
$error = '';

// Carica preferenze attuali
$stmt = $pdo->prepare("SELECT * FROM preferenze_notifiche WHERE id_utente = :id");
$stmt->execute(['id' => $id_utente]);
$preferenze = $stmt->fetch();

// Se non esistono preferenze, crea con valori default
if(!$preferenze) {
    $stmt = $pdo->prepare("
        INSERT INTO preferenze_notifiche (id_utente) 
        VALUES (:id)
    ");
    $stmt->execute(['id' => $id_utente]);

    // Ricarica
    $stmt = $pdo->prepare("SELECT * FROM preferenze_notifiche WHERE id_utente = :id");
    $stmt->execute(['id' => $id_utente]);
    $preferenze = $stmt->fetch();
}

// Gestione salvataggio
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email_attive = isset($_POST['email_attive']) ? 1 : 0;
        $promemoria_scadenza = isset($_POST['promemoria_scadenza']) ? 1 : 0;
        $notifiche_ritardo = isset($_POST['notifiche_ritardo']) ? 1 : 0;
        $notifiche_prenotazioni = isset($_POST['notifiche_prenotazioni']) ? 1 : 0;
        $quiet_hours_attive = isset($_POST['quiet_hours_attive']) ? 1 : 0;
        $quiet_hours_inizio = $_POST['quiet_hours_inizio'] ?? '22:00:00';
        $quiet_hours_fine = $_POST['quiet_hours_fine'] ?? '08:00:00';

        $stmt = $pdo->prepare("
            UPDATE preferenze_notifiche 
            SET email_attive = :email_attive,
                promemoria_scadenza = :promemoria,
                notifiche_ritardo = :ritardo,
                notifiche_prenotazioni = :prenotazioni,
                quiet_hours_attive = :quiet_attive,
                quiet_hours_inizio = :quiet_inizio,
                quiet_hours_fine = :quiet_fine
            WHERE id_utente = :id_utente
        ");

        $stmt->execute([
            'email_attive' => $email_attive,
            'promemoria' => $promemoria_scadenza,
            'ritardo' => $notifiche_ritardo,
            'prenotazioni' => $notifiche_prenotazioni,
            'quiet_attive' => $quiet_hours_attive,
            'quiet_inizio' => $quiet_hours_inizio,
            'quiet_fine' => $quiet_hours_fine,
            'id_utente' => $id_utente
        ]);

        $success = "Preferenze salvate con successo!";

        // Ricarica preferenze aggiornate
        $stmt = $pdo->prepare("SELECT * FROM preferenze_notifiche WHERE id_utente = :id");
        $stmt->execute(['id' => $id_utente]);
        $preferenze = $stmt->fetch();

    } catch(Exception $e) {
        $error = "Errore nel salvataggio: " . $e->getMessage();
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
    <link rel="stylesheet" href="../../public/assets/css/preferenze_notifiche.css">
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="preferenze-container">
    <h1>⚙️ Preferenze Notifiche</h1>

    <?php if($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="info-box">
        <p><strong>ℹ️ Come funzionano le notifiche:</strong></p>
        <p>• Ricevi sempre notifiche in-app nella tua dashboard</p>
        <p>• Le email sono opzionali e puoi configurarle qui</p>
        <p>• Le notifiche urgenti (es. libro disponibile) vengono sempre inviate</p>
    </div>

    <form method="POST">
        <!-- Notifiche Email -->
        <div class="preference-section">
            <h3>📧 Notifiche Email</h3>

            <div class="preference-item">
                <div class="preference-label">
                    <strong>Abilita notifiche email</strong>
                    <small>Ricevi email in aggiunta alle notifiche in-app</small>
                </div>
                <label class="switch">
                    <input type="checkbox" name="email_attive"
                        <?= $preferenze['email_attive'] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <!-- Tipi di Notifica -->
        <div class="preference-section">
            <h3>🔔 Tipi di Notifica</h3>

            <div class="preference-item">
                <div class="preference-label">
                    <strong>Promemoria scadenza</strong>
                    <small>Email 3 giorni prima della scadenza prestito</small>
                </div>
                <label class="switch">
                    <input type="checkbox" name="promemoria_scadenza"
                        <?= $preferenze['promemoria_scadenza'] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="preference-item">
                <div class="preference-label">
                    <strong>Avvisi ritardo</strong>
                    <small>Email quando un libro è in ritardo</small>
                </div>
                <label class="switch">
                    <input type="checkbox" name="notifiche_ritardo"
                        <?= $preferenze['notifiche_ritardo'] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="preference-item">
                <div class="preference-label">
                    <strong>Notifiche prenotazioni</strong>
                    <small>Email quando un libro prenotato diventa disponibile</small>
                </div>
                <label class="switch">
                    <input type="checkbox" name="notifiche_prenotazioni"
                        <?= $preferenze['notifiche_prenotazioni'] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <!-- Quiet Hours -->
        <div class="preference-section">
            <h3>🌙 Quiet Hours (Ore Silenziose)</h3>

            <div class="preference-item">
                <div class="preference-label">
                    <strong>Abilita quiet hours</strong>
                    <small>Non ricevere email in determinate fasce orarie</small>
                </div>
                <label class="switch">
                    <input type="checkbox" name="quiet_hours_attive" id="quiet_hours_toggle"
                        <?= $preferenze['quiet_hours_attive'] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="quiet-hours-container" id="quiet_hours_settings">
                <div>
                    <label for="quiet_hours_inizio" style="display: block; margin-bottom: 5px; color: #888;">
                        Inizio:
                    </label>
                    <input type="time" id="quiet_hours_inizio" name="quiet_hours_inizio"
                           class="time-input"
                           value="<?= substr($preferenze['quiet_hours_inizio'], 0, 5) ?>">
                </div>

                <div style="color: #888; font-size: 24px;">→</div>

                <div>
                    <label for="quiet_hours_fine" style="display: block; margin-bottom: 5px; color: #888;">
                        Fine:
                    </label>
                    <input type="time" id="quiet_hours_fine" name="quiet_hours_fine"
                           class="time-input"
                           value="<?= substr($preferenze['quiet_hours_fine'], 0, 5) ?>">
                </div>

                <div style="margin-left: auto; color: #888; font-size: 13px;">
                    <p style="margin: 0;">Le email urgenti verranno comunque inviate</p>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-primary" style="width: 100%; padding: 15px; font-size: 16px;">
            💾 Salva Preferenze
        </button>

        <a href="profile.php" class="btn-secondary" style="width: 100%; display: block; text-align: center; margin-top: 10px; padding: 15px;">
            ← Torna al Profilo
        </a>
    </form>
</div>

<script>
    // Mostra/nascondi impostazioni quiet hours
    const quietToggle = document.getElementById('quiet_hours_toggle');
    const quietSettings = document.getElementById('quiet_hours_settings');

    function toggleQuietSettings() {
        if(quietToggle.checked) {
            quietSettings.style.display = 'flex';
        } else {
            quietSettings.style.display = 'none';
        }
    }

    quietToggle.addEventListener('change', toggleQuietSettings);

    // Imposta stato iniziale
    toggleQuietSettings();
</script>

</body>
</html>