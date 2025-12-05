<?php
use Proprietario\SudoMakers\Database;

$title = "Tessera Utente";
session_start();
require_once "Database.php";

if(!isset($_SESSION['id_utente'])) {
    echo "<script>alert('Non autenticato');</script>";
    header("location: login.php");
    exit;
}

// Recupera i dati dell'utente
$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->prepare("SELECT * FROM utente WHERE id_utente = :id");
$stmt->execute(['id' => $_SESSION['id_utente']]);
$utente = $stmt->fetch();

if(!$utente) {
    echo "<script>alert('Errore nel recupero dati utente');</script>";
    header("location: homepage.php");
    exit;
}

// Formatta la data di registrazione
$data_registrazione = new DateTime($utente['data_registrazione']);
$data_scadenza = clone $data_registrazione;
$data_scadenza->modify('+1 year'); // Tessera valida 1 anno

?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../style/privateAreaStyle.css">
</head>
<body>
<?php require_once 'navigation.php'; ?>

<div class="tessera-container">
    <div class="tessera-card">
        <div class="tessera-header">
            <h1>Tessera Biblioteca Digitale</h1>
            <p>Biblioteca Scolastica - Sistema di Gestione Prestiti</p>
        </div>

        <div class="tessera-body" id="tessera-content">
            <div class="tessera-info">
                <div class="info-item">
                    <div class="info-label">Nome Completo</div>
                    <div class="info-value"><?= htmlspecialchars($utente['nome'] . ' ' . $utente['cognome']) ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">ID Utente</div>
                    <div class="info-value">#<?= str_pad($utente['id_utente'], 6, '0', STR_PAD_LEFT) ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?= htmlspecialchars($utente['email']) ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Data Nascita</div>
                    <div class="info-value"><?= date('d/m/Y', strtotime($utente['data_nascita'])) ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Data Emissione</div>
                    <div class="info-value"><?= $data_registrazione->format('d/m/Y') ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Scadenza Tessera</div>
                    <div class="info-value">
                        <?= $data_scadenza->format('d/m/Y') ?>
                        <span class="validity-badge">Attiva</span>
                    </div>
                </div>
            </div>

            <div class="barcode-section">
                <svg id="barcode"></svg>
                <div class="cf-display"><?= htmlspecialchars($utente['codice_fiscale']) ?></div>
            </div>
        </div>

        <div class="tessera-actions">
            <a href="generaPdfTessera.php" class="btn-download">Scarica PDF</a>
            <button onclick="window.print()" class="btn-secondary">Stampa</button>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script>
    // Genera il codice a barre dal codice fiscale
    const codiceFiscale = '<?= htmlspecialchars($utente['codice_fiscale']) ?>';

    JsBarcode("#barcode", codiceFiscale, {
        format: "CODE128",
        width: 2,
        height: 100,
        displayValue: false,
        margin: 10,
        background: "#ffffff",
        lineColor: "#000000"
    });
</script>
</body>
</html>