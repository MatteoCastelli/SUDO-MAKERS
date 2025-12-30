<?php
use Proprietario\SudoMakers\core\Database;

session_start();
require_once __DIR__ . '/../core/Database.php';

// Verifica login
if(!isset($_SESSION['id_utente'])){
    header("Location: ../auth/login.php");
    exit;
}

$pdo = Database::getInstance()->getConnection();
$title = "Scansiona Libro";
$errore = '';
$libro_trovato = null;

// ================== GESTIONE SCANSIONE ==================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codice_barre'])){
    $codice = trim($_POST['codice_barre']);

    if(!empty($codice)){
        // Cerca il libro tramite ISBN o altro identificativo
        $stmt = $pdo->prepare("
            SELECT id_libro, titolo, isbn
            FROM libro
            WHERE isbn = :codice 
            OR id_libro = :codice_int
            LIMIT 1
        ");

        $codice_int = is_numeric($codice) ? (int)$codice : 0;
        $stmt->execute([
            'codice' => $codice,
            'codice_int' => $codice_int
        ]);

        $libro_trovato = $stmt->fetch();

        if($libro_trovato){
            // Redirect alla pagina dettaglio
            header("Location: ../catalog/dettaglio_libro.php?id=" . $libro_trovato['id_libro']);
            exit;
        } else {
            $errore = "Nessun libro trovato con codice: " . htmlspecialchars($codice);
        }
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
    <link rel="stylesheet" href="../../public/assets/css/catalogoStyle.css">
    <link href="https://fonts.googleapis.com/css2?family=Zalando+Sans+SemiExpanded:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <style>
        body {
            background: #151517;
            font-family: "Zalando Sans SemiExpanded", sans-serif;
            font-weight: 300;
        }

        .scansione-container {
            max-width: 500px;
            margin: 200px auto 40px;
            padding: 30px 35px;
            background: #1f1f21;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
        }

        .scansione-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .scansione-header h1 {
            color: #ebebed;
            margin: 5px 0 20px;
            font-weight: 600;
            font-size: 22px;
        }

        .form-scansione {
            margin-top: 20px;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 15px;
        }

        .input-group label {
            width: 100%;
            font-size: 14px;
            text-align: left;
            margin-bottom: 5px;
            color: #ebebed;
            font-weight: 400;
        }

        .input-barcode {
            width: 100%;
            padding: 8px 10px;
            font-size: 16px;
            border: 2px solid #888;
            border-radius: 4px;
            box-sizing: border-box;
            font-family: "Zalando Sans SemiExpanded", sans-serif;
            font-weight: 300;
            background: #1f1f21;
            color: #ebebed;
            transition: border-color 0.2s, background-color 0.2s;
        }

        .input-barcode:focus {
            outline: none;
            border-color: #0c8a1f;
        }

        .input-barcode:hover {
            border-color: #888;
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            margin-top: 15px;
            font-size: 15px;
            background: #1f1f21;
            color: #ebebed;
            border: 2px solid #303033;
            border-radius: 4px;
            cursor: pointer;
            font-family: "Zalando Sans SemiExpanded", sans-serif;
            font-weight: 300;
            transition: background 0.2s, border-color 0.2s;
        }

        .btn-submit:hover {
            background: #3b3b3d;
            border-color: #3b3b3d;
        }

        .btn-cancel {
            width: 100%;
            padding: 12px;
            margin-top: 10px;
            font-size: 15px;
            background: #1f1f21;
            color: #ebebed;
            border: 2px solid #303033;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: block;
            text-align: center;
            box-sizing: border-box;
            font-family: "Zalando Sans SemiExpanded", sans-serif;
            font-weight: 300;
            transition: background 0.2s, border-color 0.2s;
        }

        .btn-cancel:hover {
            background: #3b3b3d;
            border-color: #3b3b3d;
        }

        .errore {
            background: rgba(179, 0, 0, 0.2);
            color: #ff6b6b;
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 3px solid #b30000;
            font-size: 14px;
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="scansione-container">
    <div class="scansione-header">
        <h1>Scansiona codice a barre</h1>
    </div>

    <?php if($errore): ?>
        <div class="errore">
            ⚠️ <?= $errore ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" class="form-scansione" id="scanForm">
        <div class="input-group">
            <label for="codice_barre">
                Codice a barre / ISBN
            </label>
            <input
                type="text"
                id="codice_barre"
                name="codice_barre"
                class="input-barcode"
                placeholder="Scansiona o digita il codice..."
                autocomplete="off"
                autofocus
                required
            >
        </div>

        <button type="submit" class="btn-submit">
            Cerca libro
        </button>

        <a href="homepage.php" class="btn-cancel">
            Torna al catalogo
        </a>
    </form>
</div>

<script>
    // Auto-focus sul campo quando la pagina si carica
    window.addEventListener('load', function() {
        document.getElementById('codice_barre').focus();
    });

    // Mantieni sempre il focus sul campo
    document.addEventListener('click', function(e) {
        if(e.target.id !== 'codice_barre' && !e.target.closest('button') && !e.target.closest('a')) {
            document.getElementById('codice_barre').focus();
        }
    });
</script>

</body>
</html>