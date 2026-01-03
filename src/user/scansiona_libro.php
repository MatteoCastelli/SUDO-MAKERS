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
    <link rel="stylesheet" href="../../public/assets/css/ScansionaLibro.css">
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="scansione-container">
    <div class="scansione-header">
        <h1>Scansiona codice a barre</h1>
    </div>

    <?php if($errore): ?>
        <div class="errore">
            <?= $errore ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" class="form-scansione" id="scanForm">
        <div class="input-group">
            <label for="codice_barre">
                Codice a barre / ISBN
            </label>
            <input
                type="text" id="codice_barre" name="codice_barre" class="input-barcode" placeholder="Scansiona o digita il codice..." autocomplete="off" autofocus required
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