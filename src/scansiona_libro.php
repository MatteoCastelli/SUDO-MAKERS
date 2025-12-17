<?php
use Proprietario\SudoMakers\Database;

session_start();
require_once "Database.php";

// Verifica login
if(!isset($_SESSION['id_utente'])){
    header("Location: login.php");
    exit;
}//

$pdo = Database::getInstance()->getConnection();
$title = "Scansiona Libro";
$errore = '';
$libro_trovato = null;

//GESTIONE SCANSIONE
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
            header("Location: dettaglio_libro.php?id=" . $libro_trovato['id_libro']);
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
    <link rel="stylesheet" href="../public/assets/css/privateAreaStyle.css">
    <link rel="stylesheet" href="../public/assets/css/catalogoStyle.css">
    <style>
        .scansione-container {
            max-width: 800px;
            margin: 80px auto 40px;
            padding: 40px;
            background: white;
            border-radius: 12px;
        }

        .scansione-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .scansione-header h1 {
            color: black;
            margin-bottom: 10px;
        }


        .form-scansione {
            margin-top: 30px;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: black;
            font-weight: 500;
        }

        .input-barcode {
            width: 100%;
            padding: 15px;
            font-size: 18px;
            border: 3px solid Blue;
            border-radius: 8px;
            box-sizing: border-box;
            font-family: Arial;
            background: white;
        }

        .input-barcode:focus {
            outline: none;
            border-color: Blue;
            background: white;
        }

        .btn-submit {
            width: 100%;
            padding: 15px;
            font-size: 18px;
            background: Green;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;

        }

        .btn-submit:hover {
            background: green;
        }

        .btn-cancel {
            width: 97%;
            padding: 12px;
            margin-top: 10px;
            font-size: 16px;
            background: lightgray;
            color: black;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: block;
            text-align: center;

        }

        .btn-cancel:hover {
            background: #e0e0e0;
        }

        .errore {
            background: #ffebee;
            color: Red;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }


    </style>
</head>
<body>
<?php require_once 'navigation.php'; ?>

<div class="scansione-container">
    <div class="scansione-header">
        <h1>Scansiona codice a barre</h1>
    </div>

    <?php if($errore): ?>
        <div class="errore">
            <?= $errore ?>
        </div>
    <?php endif; ?>

    <form method="post" class="form-scansione" id="scanForm">
        <div class="input-group">
            <label for="codice_barre">Codice a barre / ISBN</label>
            <input type="text" id="codice_barre" name="codice_barre" class="input-barcode" placeholder="Scansiona o digita il codice" autofocus required>
        </div>

        <button type="submit" class="btn-submit">Cerca libro</button>

        <a href="homepage.php" class="btn-cancel">Torna al catalogo</a>
    </form>

</div>

<script>
    // Auto-focus sul campo quando la pagina si carica
    window.addEventListener('load', function() {
        document.getElementById('codice_barre').focus();
    });

    // Feedback visivo durante la digitazione
    const input = document.getElementById('codice_barre');
    input.addEventListener('input', function() {
        if(this.value.length > 0) {
            this.style.borderColor = 'Blue';
        } else {
            this.style.borderColor = 'Green';
        }
    });

    // Auto-submit dopo la scansione (opzionale)
    // Molti lettori barcode inviano ENTER automaticamente
    let scanTimeout;
    input.addEventListener('input', function() {
        clearTimeout(scanTimeout);

        // Se il codice è lungo abbastanza (es. ISBN-13 = 13 cifre)
        if(this.value.length >= 10) {
            scanTimeout = setTimeout(() => {
                // Auto-submit dopo 500ms di inattività
                // document.getElementById('scanForm').submit();
            }, 500);
        }
    });

    // Mantieni sempre il focus sul campo
    document.addEventListener('click', function(e) {
        if(e.target.id !== 'codice_barre' && !e.target.closest('button') && !e.target.closest('a')) {
            input.focus();
        }
    });
</script>

</body>
</html>