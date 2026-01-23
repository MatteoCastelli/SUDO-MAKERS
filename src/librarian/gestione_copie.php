<?php

use Proprietario\SudoMakers\core\Database;

session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/check_permissions.php';

requireAnyRole(['bibliotecario', 'amministratore']);

$pdo = Database::getInstance()->getConnection();
$title = "Gestione Copie";

// Se non c'è l'ID del libro, mostra l'elenco dei libri
if(!isset($_GET['id_libro'])) {
    $stmt = $pdo->query(
            "SELECT l.*, 
                   GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori,
                   COUNT(DISTINCT c.id_copia) as num_copie
                   FROM libro l
                   LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
                   LEFT JOIN autore a ON la.id_autore = a.id_autore
                   LEFT JOIN copia c ON l.id_libro = c.id_libro
                   GROUP BY l.id_libro
                   ORDER BY l.titolo ASC
            ");
    $libri = $stmt->fetchAll();

    // Mostra l'elenco dei libri
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
            <h1>Gestione Copie</h1>
            <a href="../librarian/dashboard_bibliotecario.php" class="btn-back">← Torna alla Dashboard</a>
        </div>

        <!-- Elenco Libri -->
        <div class="section-card">
            <h2>Catalogo Libri</h2>

            <?php if(empty($libri)): ?>
                <p style="color: #888; text-align: center; padding: 40px;">Nessun libro presente nel catalogo</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                    <tr>
                        <th style="width: 80px;">Copertina</th>
                        <th>Titolo</th>
                        <th>Autori</th>
                        <th style="width: 120px;">Copie Totali</th>
                        <th style="width: 120px;">Azioni</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach($libri as $libro): ?>
                        <tr>
                            <td data-label="Copertina">
                                <?php if($libro['immagine_copertina_url']): ?>
                                    <img src="<?= htmlspecialchars($libro['immagine_copertina_url']) ?>"
                                         alt="Copertina"
                                         style="width: 60px; height: 90px; object-fit: cover; border-radius: 4px;">
                                <?php else: ?>
                                    <div style="width: 60px; height: 90px; background: #e0e0e0; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #999; font-size: 12px;">
                                        No cover
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Titolo">
                                <strong><?= htmlspecialchars($libro['titolo']) ?></strong>
                                <?php if($libro['isbn']): ?>
                                    <div style="color: #888; font-size: 0.9em; margin-top: 4px;">
                                        ISBN: <?= htmlspecialchars($libro['isbn']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Autori"><?= htmlspecialchars($libro['autori'] ?? 'Autore sconosciuto') ?></td>
                            <td data-label="Copie Totali" style="text-align: center;">
                            <span class="badge" style="font-size: 1em;">
                                <?= $libro['num_copie'] ?>
                            </span>
                            </td>
                            <td data-label="Azioni">
                                <a href="../librarian/gestione_copie.php?id_libro=<?= $libro['id_libro'] ?>"
                                   class="btn-small btn-primary">
                                    Dettagli
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    </body>
    </html>
    <?php
    exit;
}

// Se c'è l'ID del libro, mostra la gestione delle copie
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
    header("Location: gestione_copie.php");
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
        elseif($_POST['action'] === 'update_collocazione') {
            // In realtà la collocazione è sul libro, non sulla copia, ma l'utente vuole modificarla dalla card
            // Quindi aggiorniamo il libro
            $nuova_collocazione = trim($_POST['collocazione']);
            
            $stmt = $pdo->prepare("UPDATE libro SET collocazione = :collocazione WHERE id_libro = :id");
            $stmt->execute(['collocazione' => $nuova_collocazione, 'id' => $id_libro]);
            
            header("Location: gestione_copie.php?id_libro=$id_libro&success=update_collocazione");
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
    <link rel="stylesheet" href="../../public/assets/css/privateAreaStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/dashboardStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/gestioneCopieStyle.css">
</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Gestione Copie</h1>
        <a href="gestione_copie.php" class="btn-back">← Torna all'Elenco Libri</a>
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
                case 'update_collocazione': echo '✓ Collocazione aggiornata con successo!'; break;
            }
            ?>
        </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Info Libro e Aggiungi Copie affiancati -->
    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
        <!-- Info Libro -->
        <a href="../catalog/dettaglio_libro.php?id=<?= $id_libro ?>" style="text-decoration: none; color: white; flex: 1;">
            <div class="section-card">
                <h2><?= htmlspecialchars($libro['titolo']) ?></h2>
                <?php if($libro['autori']): ?>
                    <p style="color: #888; margin-top: 5px;">di <?= htmlspecialchars($libro['autori']) ?></p>
                <?php endif; ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px; text-decoration: none">
                    <?php if($libro['isbn']): ?>
                        <div><strong>ISBN:</strong> <?= htmlspecialchars($libro['isbn']) ?></div>
                    <?php endif; ?>
                    <div><strong>Copie totali:</strong> <?= count($copie) ?></div>
                    <div><strong>Disponibili:</strong> <?= count(array_filter($copie, fn($c) => $c['disponibile'] == 1)) ?></div>
                    <div><strong>Collocazione:</strong> <?= htmlspecialchars($libro['collocazione'] ?? 'N/D') ?></div>
                </div>
            </div>
        </a>

        <!-- Aggiungi Copie -->
        <div class="add-copies-section" style="flex: 1;">
            <h3>Aggiungi Nuove Copie</h3>
            <form method="POST" class="form-inline" style="margin-top: 20px;">
                <input type="hidden" name="action" value="add_copies">
                <div class="form-group">
                    <label for="num_copie">Numero di copie da aggiungere</label>
                    <input type="number" id="num_copie" name="num_copie" value="1" min="1" max="50" required>
                </div>
                <button type="submit" class="btn-success" style="margin-bottom: 20px;">Aggiungi Copie</button>
            </form>
        </div>
    </div>

    <!-- Lista Copie -->
    <div class="section-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="margin: 0;">Copie Esistenti (<?= count($copie) ?>)</h3>
            <?php if(!empty($copie)): ?>
                <button type="button" class="btn-primary" onclick="printAllLabels()">
                    Stampa tutte
                </button>
            <?php endif; ?>
        </div>

        <?php if(empty($copie)): ?>
            <p style="color: #888; text-align: center; padding: 40px;">Nessuna copia presente</p>
        <?php else: ?>
            <div class="copies-grid">
                <?php foreach($copie as $copia): ?>
                    <div class="copy-card <?= $copia['disponibile'] ? 'disponibile' : 'prestito' ?>">
                        <div class="copy-header">
                            <span class="copy-badge <?= $copia['disponibile'] ? 'badge-disponibile' : 'badge-prestito' ?>">
                                <?= $copia['stato_disponibilita'] ?>
                            </span>
                        </div>

                        <div class="copy-info">
                            <label>Codice a Barre</label>
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

                        <div class="copy-info">
                            <label>Collocazione</label>
                            <form method="POST" style="display: flex; gap: 5px;">
                                <input type="hidden" name="action" value="update_collocazione">
                                <input type="text" name="collocazione" 
                                       value="<?= htmlspecialchars($libro['collocazione'] ?? '') ?>" 
                                       placeholder="es. A1-23"
                                       style="padding: 5px; border-radius: 4px; border: 1px solid #555; background: #333; color: white; width: 100px;">
                                <button type="submit" class="btn-small btn-primary" title="Aggiorna per tutte le copie">💾</button>
                            </form>
                        </div>

                        <div class="copy-actions">
                            <button type="button" class="btn-primary btn-small" onclick="printLabel('<?= htmlspecialchars($copia['codice_barcode']) ?>', '<?= htmlspecialchars(addslashes($libro['titolo'])) ?>', '<?= htmlspecialchars($libro['isbn'] ?? 'N/A') ?>', '<?= htmlspecialchars($libro['collocazione'] ?? 'N/A') ?>', '<?= htmlspecialchars($copia['stato_fisico']) ?>')">
                                Stampa
                            </button>
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

    // Dati per la stampa di tutte le etichette
    const copiesData = <?= json_encode(array_map(function($c) use ($libro) {
        return [
            'barcode' => $c['codice_barcode'],
            'titolo' => $libro['titolo'],
            'isbn' => $libro['isbn'] ?? 'N/A',
            'scaffale' => $libro['collocazione'] ?? 'N/A',
            'stato' => $c['stato_fisico']
        ];
    }, $copie)) ?>;

    // Funzione per generare l'HTML di una singola etichetta
    function generateLabelHTML(barcode, titolo, isbn, scaffale, stato) {
        // Crea un elemento SVG temporaneo per generare il barcode
        const svgNode = document.createElementNS("http://www.w3.org/2000/svg", "svg");
        JsBarcode(svgNode, barcode, {
            format: "CODE128",
            width: 2,
            height: 50,
            displayValue: true,
            margin: 0
        });
        
        const barcodeSVG = svgNode.outerHTML;

        return `
            <div class="print-label">
                <h3>${titolo}</h3>
                <p><strong>ISBN:</strong> ${isbn}</p>
                <p><strong>Scaffale:</strong> ${scaffale}</p>
                <p><strong>Stato:</strong> ${stato}</p>
                <div class="barcode-container">
                    ${barcodeSVG}
                </div>
            </div>
        `;
    }

    // Funzione per stampare usando un iframe nascosto
    function printContent(contentHTML) {
        // Rimuovi iframe esistente se presente
        let existingFrame = document.getElementById('print-frame');
        if (existingFrame) {
            document.body.removeChild(existingFrame);
        }

        // Crea nuovo iframe
        let printFrame = document.createElement('iframe');
        printFrame.id = 'print-frame';
        printFrame.style.position = 'fixed';
        printFrame.style.right = '0';
        printFrame.style.bottom = '0';
        printFrame.style.width = '0';
        printFrame.style.height = '0';
        printFrame.style.border = '0';
        document.body.appendChild(printFrame);

        const doc = printFrame.contentWindow.document;
        doc.open();
        doc.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Stampa Etichette</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        margin: 0;
                        padding: 20px;
                    }
                    .print-label {
                        border: 2px solid #000;
                        padding: 15px;
                        margin: 0 auto 20px auto;
                        text-align: center;
                        width: 300px;
                        page-break-inside: avoid;
                        break-inside: avoid;
                    }
                    .print-label h3 {
                        margin: 5px 0;
                        font-size: 16px;
                    }
                    .print-label p {
                        margin: 5px 0;
                        font-size: 14px;
                    }
                    .barcode-container svg {
                        width: 100%;
                        height: auto;
                        max-height: 80px;
                    }
                    @media print {
                        @page { margin: 1cm; size: auto; }
                        body { margin: 0; }
                    }
                </style>
            </head>
            <body>
                ${contentHTML}
            </body>
            </html>
        `);
        doc.close();

        // Attendi il caricamento e poi stampa
        setTimeout(() => {
            printFrame.contentWindow.focus();
            printFrame.contentWindow.print();
        }, 500);
    }

    function printLabel(barcode, titolo, isbn, scaffale, stato) {
        const html = generateLabelHTML(barcode, titolo, isbn, scaffale, stato);
        printContent(html);
    }

    function printAllLabels() {
        if (!copiesData || copiesData.length === 0) return;
        
        let allHtml = '';
        copiesData.forEach(copia => {
            allHtml += generateLabelHTML(copia.barcode, copia.titolo, copia.isbn, copia.scaffale, copia.stato);
        });
        
        printContent(allHtml);
    }
</script>
</body>
</html>
