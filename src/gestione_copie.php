<?php
use Proprietario\SudoMakers\Database;

session_start();
require_once "Database.php";
require_once "check_permissions.php";

requireAnyRole(['bibliotecario', 'amministratore']);

$pdo = Database::getInstance()->getConnection();
$title = "Gestione Copie";

// Verifica che sia stato passato l'ID del libro
if(!isset($_GET['id_libro'])) {
    header("Location: dashboard_bibliotecario.php");
    exit;
}

$id_libro = (int)$_GET['id_libro'];

// Recupera informazioni del libro
$stmt = $pdo->prepare("
    SELECT l.*, 
           GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori
    FROM libro l
    LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
    LEFT JOIN autore a ON la.id_autore = a.id_autore
    WHERE l.id_libro = :id
    GROUP BY l.id_libro
");
$stmt->execute(['id' => $id_libro]);
$libro = $stmt->fetch();

if(!$libro) {
    header("Location: dashboard_bibliotecario.php");
    exit;
}

// Recupera tutte le copie del libro
$stmt = $pdo->prepare("
        SELECT c.*, 
           CASE 
               WHEN c.disponibile = 1 THEN 'Disponibile'
               ELSE 'In prestito'
           END as stato_disponibilita,
           u.nome AS utente_nome,
           u.cognome AS utente_cognome
    FROM copia c
    LEFT JOIN prestito p ON c.id_copia = p.id_copia AND p.data_restituzione_effettiva IS NULL
    LEFT JOIN utente u ON p.id_utente = u.id_utente
    WHERE c.id_libro = :id
    ORDER BY c.id_copia
");
$stmt->execute(['id' => $id_libro]);
$copie = $stmt->fetchAll();

$success = false;
$error = null;

// Gestione aggiunta nuove copie
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if($_POST['action'] === 'add_copies') {
            $num_copie = (int)$_POST['num_copie'];

            if($num_copie < 1 || $num_copie > 50) {
                throw new Exception("Numero di copie non valido (1-50)");
            }

            $pdo->beginTransaction();

            for($i = 1; $i <= $num_copie; $i++) {
                // Genera codice a barre univoco
                $codice_barcode = 'LIB' . str_pad($id_libro, 6, '0', STR_PAD_LEFT) . time() . str_pad($i, 3, '0', STR_PAD_LEFT);

                $stmt = $pdo->prepare("
                    INSERT INTO copia (id_libro, codice_barcode, stato_fisico, disponibile)
                    VALUES (:id_libro, :codice, 'ottimo', 1)
                ");
                $stmt->execute([
                        'id_libro' => $id_libro,
                        'codice' => $codice_barcode
                ]);
            }

            $pdo->commit();
            header("Location: gestione_copie.php?id_libro=$id_libro&success=add");
            exit;
        }
        elseif($_POST['action'] === 'delete_copy') {
            $id_copia = (int)$_POST['id_copia'];

            // Verifica che la copia sia disponibile
            $stmt = $pdo->prepare("SELECT disponibile FROM copia WHERE id_copia = :id AND id_libro = :libro");
            $stmt->execute(['id' => $id_copia, 'libro' => $id_libro]);
            $copia = $stmt->fetch();

            if(!$copia) {
                throw new Exception("Copia non trovata");
            }

            if($copia['disponibile'] == 0) {
                throw new Exception("Impossibile eliminare: la copia è attualmente in prestito");
            }

            $stmt = $pdo->prepare("DELETE FROM copia WHERE id_copia = :id");
            $stmt->execute(['id' => $id_copia]);

            header("Location: gestione_copie.php?id_libro=$id_libro&success=delete");
            exit;
        }
        elseif($_POST['action'] === 'update_state') {
            $id_copia = (int)$_POST['id_copia'];
            $stato_fisico = $_POST['stato_fisico'];

            $stati_validi = ['ottimo', 'buono', 'discreto', 'danneggiato'];
            if(!in_array($stato_fisico, $stati_validi)) {
                throw new Exception("Stato fisico non valido");
            }

            $stmt = $pdo->prepare("UPDATE copia SET stato_fisico = :stato WHERE id_copia = :id AND id_libro = :libro");
            $stmt->execute(['stato' => $stato_fisico, 'id' => $id_copia, 'libro' => $id_libro]);

            header("Location: gestione_copie.php?id_libro=$id_libro&success=update");
            exit;
        }
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}

// Messaggio se arriva da cataloga_libro
$da_cataloga = isset($_GET['nuovo']);
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> - <?= htmlspecialchars($libro['titolo']) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <link rel="stylesheet" href="../public/assets/css/privateAreaStyle.css">
    <link rel="stylesheet" href="../public/assets/css/dashboardStyle.css">
    <style>
        .copies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .copy-card {
            background: #1f1f21;
            border: 2px solid #303033;
            border-radius: 8px;
            padding: 20px;
            transition: border-color 0.2s;
        }

        .copy-card:hover {
            border-color: #888;
        }

        .copy-card.disponibile {
            border-left: 4px solid #0c8a1f;
        }

        .copy-card.prestito {
            border-left: 4px solid #b30000;
        }

        .copy-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .copy-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-disponibile {
            background: #0c8a1f;
            color: white;
        }

        .badge-prestito {
            background: #b30000;
            color: white;
        }

        .copy-info {
            margin-bottom: 10px;
        }

        .copy-info label {
            display: block;
            font-size: 12px;
            color: #888;
            margin-bottom: 5px;
        }

        .copy-info strong {
            font-size: 14px;
        }

        .copy-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-small {
            padding: 8px 12px;
            font-size: 13px;
            flex: 1;
        }

        .add-copies-section {
            background: #1f1f21;
            border: 2px solid #303033;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .form-inline {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }

        .form-inline .form-group {
            flex: 1;
        }
    </style>
</head>
<body>
<?php require_once 'navigation.php'; ?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Gestione Copie</h1>
        <a href="dettaglio_libro.php?id=<?= $id_libro ?>" class="btn-back">← Torna al Libro</a>
    </div>

    <?php if($da_cataloga): ?>
        <div class="alert alert-info">
            Questo libro è già presente nel catalogo. Puoi aggiungere nuove copie qui sotto.
        </div>
    <?php endif; ?>

    <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php
            switch($_GET['success']) {
                case 'add': echo '✓ Copie aggiunte con successo!'; break;
                case 'delete': echo '✓ Copia eliminata con successo!'; break;
                case 'update': echo '✓ Stato aggiornato con successo!'; break;
            }
            ?>
        </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Info Libro -->
    <div class="section-card">
        <h2><?= htmlspecialchars($libro['titolo']) ?></h2>
        <?php if($libro['autori']): ?>
            <p style="color: #888; margin-top: 5px;">di <?= htmlspecialchars($libro['autori']) ?></p>
        <?php endif; ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
            <?php if($libro['isbn']): ?>
                <div><strong>ISBN:</strong> <?= htmlspecialchars($libro['isbn']) ?></div>
            <?php endif; ?>
            <?php if($libro['ean']): ?>
                <div><strong>EAN:</strong> <?= htmlspecialchars($libro['ean']) ?></div>
            <?php endif; ?>
            <?php if($libro['editore']): ?>
                <div><strong>Editore:</strong> <?= htmlspecialchars($libro['editore']) ?></div>
            <?php endif; ?>
            <div><strong>Copie totali:</strong> <?= count($copie) ?></div>
            <div><strong>Disponibili:</strong> <?= count(array_filter($copie, fn($c) => $c['disponibile'] == 1)) ?></div>
        </div>
    </div>

    <!-- Aggiungi Copie -->
    <div class="add-copies-section">
        <h3>Aggiungi Nuove Copie</h3>
        <form method="POST" class="form-inline" style="margin-top: 20px;">
            <input type="hidden" name="action" value="add_copies">
            <div class="form-group">
                <label for="num_copie">Numero di copie da aggiungere</label>
                <input type="number" id="num_copie" name="num_copie" value="1" min="1" max="50" required>
            </div>
            <button type="submit" class="btn-success">✓ Aggiungi Copie</button>
        </form>
    </div>

    <!-- Lista Copie -->
    <div class="section-card">
        <h3>Copie Esistenti (<?= count($copie) ?>)</h3>

        <?php if(empty($copie)): ?>
            <p style="color: #888; text-align: center; padding: 40px;">Nessuna copia presente</p>
        <?php else: ?>
            <div class="copies-grid">
                <?php foreach($copie as $copia): ?>
                    <div class="copy-card <?= $copia['disponibile'] ? 'disponibile' : 'prestito' ?>">
                        <div class="copy-header">
                            <!--                            <strong>#--><?php //= $copia['id_copia'] ?><!--</strong>-->
                            <span class="copy-badge <?= $copia['disponibile'] ? 'badge-disponibile' : 'badge-prestito' ?>">
                                <?= $copia['stato_disponibilita'] ?>
                            </span>
                        </div>

                        <div class="copy-info">
                            <label>Codice a Barre</label>
                            <!--                            <strong>--><?php //= htmlspecialchars($copia['codice_barcode']) ?><!--</strong>-->
                            <svg class="barcode" jsbarcode-format="CODE128" jsbarcode-value="<?= htmlspecialchars($copia['codice_barcode']) ?>" jsbarcode-textmargin="0" jsbarcode-height="40"></svg>
                        </div>

                        <div class="copy-info">
                            <label>Stato Fisico</label>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="update_state">
                                <input type="hidden" name="id_copia" value="<?= $copia['id_copia'] ?>">
                                <select name="stato_fisico" onchange="this.form.submit()" style="padding: 5px;">
                                    <option value="ottimo" <?= $copia['stato_fisico'] === 'ottimo' ? 'selected' : '' ?>>Ottimo</option>
                                    <option value="buono" <?= $copia['stato_fisico'] === 'buono' ? 'selected' : '' ?>>Buono</option>
                                    <option value="discreto" <?= $copia['stato_fisico'] === 'discreto' ? 'selected' : '' ?>>Discreto</option>
                                    <option value="danneggiato" <?= $copia['stato_fisico'] === 'danneggiato' ? 'selected' : '' ?>>Danneggiato</option>
                                </select>
                            </form>
                        </div>

                        <div class="copy-actions">
                            <?php if($copia['disponibile']): ?>
                                <form method="POST" onsubmit="return confirm('Sei sicuro di voler eliminare questa copia?');" style="flex: 1;">
                                    <input type="hidden" name="action" value="delete_copy">
                                    <input type="hidden" name="id_copia" value="<?= $copia['id_copia'] ?>">
                                    <button type="submit" class="btn-danger btn-small">Elimina</button>
                                </form>
                            <?php else: ?>
                                <button class="btn-secondary btn-small" disabled>In prestito a <?= htmlspecialchars($copia['utente_nome'] . ' ' . $copia['utente_cognome']) ?></button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
    JsBarcode(".barcode").init();
</script>
</body>
</html>
