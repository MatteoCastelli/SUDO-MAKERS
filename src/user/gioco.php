<?php
use Proprietario\SudoMakers\core\Database;

session_start();
require_once __DIR__ . '/../core/Database.php';

if(!isset($_SESSION['id_utente'])) {
    header("Location: ../auth/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../public/assets/css/impiccatoStyle.css" />
    <link rel="stylesheet" href="../../public/assets/css/privateAreaStyle.css" />
    <title>Impiccato</title>
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>
<h1>Gioco dell'Impiccato</h1>
<p>Indovina il titolo del libro!</p>
<div class="game-container">
    <svg height="250" width="200" class="figure-container">
        <!-- rod -->
        <line x1="60" y1="20" x2="140" y2="20" />
        <line x1="140" y1="20" x2="140" y2="50" />
        <line x1="60" y1="20" x2="60" y2="230" />
        <line x1="20" y1="230" x2="100" y2="230" />
        <!-- head -->
        <circle cx="140" cy="70" r="20" class="figure-part" />
        <!-- body -->
        <line x1="140" y1="90" x2="140" y2="150" class="figure-part" />
        <!-- arms -->
        <line x1="140" y1="120" x2="120" y2="100" class="figure-part" />
        <line x1="140" y1="120" x2="160" y2="100" class="figure-part" />
        <!-- legs -->
        <line x1="140" y1 ="150" x2="120" y2="180" class="figure-part" />
        <line x1="140" y1="150" x2="160" y2="180" class="figure-part" />
    </svg>
    <div class="wrong-letters-container">
        <div id="wrong-letters"></div>
    </div>
    <div class="word" id="word"></div>
</div>
<!-- Popup -->
<div class="popup-container" id="popup-container">
    <div class="popup">
        <h2 id="final-message"></h2>
        <h3 id="final-message-reveal-word"></h3>
        <button id="play-button">Play Again</button>
    </div>
</div>

<div class="section-card">
    <h3>Istruzioni</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
        <div style="background: #2a2a2c; padding: 20px; border-radius: 8px; border-left: 4px solid #0c8a1f;">
            <h4 style="margin: 0 0 10px 0; color: #ebebed;"> Indoovina il titolo del libro</h4>
            <p style="margin: 0; color: #888; font-size: 14px;">
                Prova a indovinare il libro<br>
                <strong>Esempio:</strong> La Bibbia
            </p>
        </div>

        <div style="background: #2a2a2c; padding: 20px; border-radius: 8px; border-left: 4px solid #0c8a1f;">
            <h4 style="margin: 0 0 10px 0; color: #ebebed;">Codice Copia (LIBxxxxxx)</h4>
            <p style="margin: 0; color: #888; font-size: 14px;">
                Identifica una copia specifica per prestito/restituzione<br>
                <strong>Esempio:</strong> LIB000001
            </p>
        </div>

        <div style="background: #2a2a2c; padding: 20px; border-radius: 8px; border-left: 4px solid #0c8a1f;">
            <h4 style="margin: 0 0 10px 0; color: #ebebed;">Tessera Utente (USERxxxxxx)</h4>
            <p style="margin: 0; color: #888; font-size: 14px;">
                Identifica un utente registrato<br>
                <strong>Esempio:</strong> USER000001
            </p>
        </div>
    </div>
</div>
</div>

<!-- Notification -->
<div class="notification-container" id="notification-container">
    <p>Hai già usato questa lettera</p>
</div>
<script src="../../public/assets/js/impiccato.js"></script>
</body>
</html>