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
    <style>
        .tessera-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 100px);
            padding: 20px;
        }

        .tessera-card {
            background: linear-gradient(135deg, #1f1f21 0%, #2a2a2c 100%);
            border-radius: 20px;
            padding: 40px;
            max-width: 800px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
            border: 2px solid #3b3b3d;
        }

        .tessera-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #3b3b3d;
        }

        .tessera-header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
            color: #ebebed;
        }

        .tessera-header p {
            margin: 0;
            color: #888;
            font-size: 14px;
        }

        .tessera-body {
            background: #1a1a1c;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid #3b3b3d;
        }

        .tessera-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-item {
            padding: 15px;
            background: #1f1f21;
            border-radius: 8px;
            border-left: 3px solid #0c8a1f;
        }

        .info-label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 18px;
            color: #ebebed;
            font-weight: 500;
        }

        .barcode-section {
            text-align: center;
            padding: 30px;
            background: white;
            border-radius: 10px;
            margin: 20px 0;
        }

        .barcode-section svg {
            max-width: 100%;
            height: auto;
        }

        .cf-display {
            font-size: 24px;
            font-weight: bold;
            color: #000;
            margin-top: 10px;
            letter-spacing: 2px;
        }

        .tessera-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-download {
            padding: 15px 30px;
            background: #0c8a1f;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-download:hover {
            background: #0a6f18;
        }

        .btn-secondary {
            padding: 15px 30px;
            background: transparent;
            color: #ebebed;
            border: 2px solid #3b3b3d;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary:hover {
            background: #3b3b3d;
            border-color: #555;
        }

        .validity-badge {
            display: inline-block;
            padding: 5px 15px;
            background: #0c8a1f;
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        @media print {
            body * {
                visibility: hidden;
            }
            .tessera-body, .tessera-body * {
                visibility: visible;
            }
            .tessera-body {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .tessera-actions {
                display: none;
            }
        }
    </style>
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