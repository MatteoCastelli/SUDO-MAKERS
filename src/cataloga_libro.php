<?php
use Proprietario\SudoMakers\Database;

session_start();
require_once "Database.php";
require_once "check_permissions.php";

requireAnyRole(['bibliotecario', 'amministratore']);

$pdo = Database::getInstance()->getConnection();
$title = "Cataloga Nuovo Libro";

// Recupera categorie esistenti
$stmt = $pdo->query("SELECT DISTINCT categoria FROM libro WHERE categoria IS NOT NULL ORDER BY categoria");
$categorie = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Recupera autori esistenti
$stmt = $pdo->query("SELECT id_autore, CONCAT(nome, ' ', cognome) as nome_completo FROM autore ORDER BY cognome, nome");
$autori = $stmt->fetchAll();

$success = false;
$error = null;

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Inserisci il libro
        $stmt = $pdo->prepare("
            INSERT INTO libro (titolo, editore, anno_pubblicazione, isbn, ean, categoria, descrizione, immagine_copertina_url, collocazione)
            VALUES (:titolo, :editore, :anno, :isbn, :ean, :categoria, :descrizione, :immagine, :collocazione)
        ");

        $stmt->execute([
            'titolo' => trim($_POST['titolo']),
            'editore' => trim($_POST['editore']) ?: null,
            'anno' => $_POST['anno_pubblicazione'] ?: null,
            'isbn' => trim($_POST['isbn']) ?: null,
            'ean' => trim($_POST['ean']) ?: null,
            'categoria' => trim($_POST['categoria']) ?: null,
            'descrizione' => trim($_POST['descrizione']) ?: null,
            'immagine' => trim($_POST['immagine_copertina_url']) ?: null,
            'collocazione' => trim($_POST['collocazione']) ?: null
        ]);

        $id_libro = $pdo->lastInsertId();

        // Gestisci autori
        if(!empty($_POST['autori'])) {
            $autori_selezionati = $_POST['autori'];
            foreach($autori_selezionati as $id_autore) {
                $stmt = $pdo->prepare("INSERT INTO libro_autore (id_libro, id_autore) VALUES (:libro, :autore)");
                $stmt->execute(['libro' => $id_libro, 'autore' => $id_autore]);
            }
        }

        // Nuovo autore se specificato
        if(!empty($_POST['nuovo_autore_nome']) && !empty($_POST['nuovo_autore_cognome'])) {
            $stmt = $pdo->prepare("INSERT INTO autore (nome, cognome) VALUES (:nome, :cognome)");
            $stmt->execute([
                'nome' => trim($_POST['nuovo_autore_nome']),
                'cognome' => trim($_POST['nuovo_autore_cognome'])
            ]);
            $nuovo_id_autore = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO libro_autore (id_libro, id_autore) VALUES (:libro, :autore)");
            $stmt->execute(['libro' => $id_libro, 'autore' => $nuovo_id_autore]);
        }

        // Crea copie fisiche
        $num_copie = (int)$_POST['num_copie'];
        for($i = 1; $i <= $num_copie; $i++) {
            // Genera codice a barre univoco (usando id_libro + timestamp + counter)
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
        $success = true;

        // Redirect dopo successo
        header("Location: cataloga_libro.php?success=1&id_libro=$id_libro");
        exit;

    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "Errore durante la catalogazione: " . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../style/privateAreaStyle.css">
    <link rel="stylesheet" href="../style/dashboardStyle.css">
</head>
<body>
<?php require_once 'navigation.php'; ?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>📖 Cataloga Nuovo Libro</h1>
        <a href="dashboard_bibliotecario.php" class="btn-back">← Torna alla Dashboard</a>
    </div>

    <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success">
            ✓ Libro catalogato con successo! <a href="dettaglio_libro.php?id=<?= $_GET['id_libro'] ?>">Visualizza libro</a>
        </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="alert alert-danger">
            ⚠ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="section-card">
        <!-- Ricerca tramite EAN/ISBN -->
        <div class="search-api-section">
            <h3>🔍 Ricerca Rapida tramite Codice</h3>
            <p class="help-text">Scansiona il codice EAN o inserisci l'ISBN per importare automaticamente i dati</p>

            <div class="api-search-form">
                <input type="text" id="api_search_code" placeholder="Inserisci ISBN o EAN..." class="form-input">
                <button type="button" onclick="searchBookAPI()" class="btn-primary">🔍 Cerca</button>
            </div>

            <div id="api_results" class="api-results"></div>
        </div>

        <hr style="margin: 30px 0; border-color: #303033;">

        <!-- Form manuale -->
        <form method="POST" id="catalogaForm">
            <h3>📝 Inserimento Manuale</h3>

            <div class="form-grid">
                <div class="form-group">
                    <label for="titolo">Titolo *</label>
                    <input type="text" id="titolo" name="titolo" required>
                </div>

                <div class="form-group">
                    <label for="editore">Editore</label>
                    <input type="text" id="editore" name="editore">
                </div>

                <div class="form-group">
                    <label for="anno_pubblicazione">Anno Pubblicazione</label>
                    <input type="number" id="anno_pubblicazione" name="anno_pubblicazione" min="1000" max="<?= date('Y') ?>">
                </div>

                <div class="form-group">
                    <label for="isbn">ISBN</label>
                    <input type="text" id="isbn" name="isbn" maxlength="17">
                </div>

                <div class="form-group">
                    <label for="ean">EAN</label>
                    <input type="text" id="ean" name="ean" maxlength="13">
                </div>

                <div class="form-group">
                    <label for="categoria">Categoria</label>
                    <input type="text" id="categoria" name="categoria" list="categorie_list">
                    <datalist id="categorie_list">
                        <?php foreach($categorie as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>">
                            <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="form-group">
                    <label for="collocazione">Collocazione Scaffale</label>
                    <input type="text" id="collocazione" name="collocazione" placeholder="es. A1-23">
                </div>

                <div class="form-group">
                    <label for="num_copie">Numero Copie *</label>
                    <input type="number" id="num_copie" name="num_copie" value="1" min="1" max="50" required>
                </div>
            </div>

            <div class="form-group">
                <label for="descrizione">Descrizione/Trama</label>
                <textarea id="descrizione" name="descrizione" rows="4"></textarea>
            </div>

            <div class="form-group">
                <label for="immagine_copertina_url">URL Immagine Copertina</label>
                <input type="url" id="immagine_copertina_url" name="immagine_copertina_url">
                <small>Lascia vuoto per usare un placeholder</small>
            </div>

            <div class="form-group">
                <label>Autori</label>
                <div class="autori-selection">
                    <select name="autori[]" multiple size="6" style="width: 100%; padding: 10px;">
                        <?php foreach($autori as $autore): ?>
                            <option value="<?= $autore['id_autore'] ?>"><?= htmlspecialchars($autore['nome_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>Tieni premuto Ctrl (Windows) o Cmd (Mac) per selezionare più autori</small>
                </div>
            </div>

            <div class="form-group">
                <label>Oppure Aggiungi Nuovo Autore</label>
                <div class="form-inline">
                    <input type="text" name="nuovo_autore_nome" placeholder="Nome" style="flex: 1;">
                    <input type="text" name="nuovo_autore_cognome" placeholder="Cognome" style="flex: 1;">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-success">✓ Cataloga Libro</button>
                <a href="dashboard_bibliotecario.php" class="btn-secondary">Annulla</a>
            </div>
        </form>
    </div>
</div>

<script>
    async function searchBookAPI() {
        const code = document.getElementById('api_search_code').value.trim();
        const resultsDiv = document.getElementById('api_results');

        if(!code) {
            alert('Inserisci un codice ISBN o EAN');
            return;
        }

        resultsDiv.innerHTML = '<p>🔄 Ricerca in corso...</p>';

        try {
            // Cerca su Google Books API
            const response = await fetch(`https://www.googleapis.com/books/v1/volumes?q=isbn:${code}`);
            const data = await response.json();

            if(data.totalItems > 0) {
                const book = data.items[0].volumeInfo;

                // Popola il form
                document.getElementById('titolo').value = book.title || '';
                document.getElementById('editore').value = book.publisher || '';
                document.getElementById('anno_pubblicazione').value = book.publishedDate ? book.publishedDate.split('-')[0] : '';
                document.getElementById('isbn').value = code;
                document.getElementById('descrizione').value = book.description || '';
                document.getElementById('categoria').value = book.categories ? book.categories[0] : '';

                if(book.imageLinks && book.imageLinks.thumbnail) {
                    document.getElementById('immagine_copertina_url').value = book.imageLinks.thumbnail;
                }

                resultsDiv.innerHTML = `
                <div class="alert alert-success">
                    ✓ Libro trovato! Dati importati automaticamente nel form.<br>
                    <strong>${book.title}</strong> - ${book.authors ? book.authors.join(', ') : 'Autore sconosciuto'}
                </div>
            `;
            } else {
                resultsDiv.innerHTML = '<div class="alert alert-warning">⚠ Nessun libro trovato. Compila manualmente il form.</div>';
            }
        } catch(error) {
            resultsDiv.innerHTML = '<div class="alert alert-danger">❌ Errore nella ricerca. Compila manualmente il form.</div>';
            console.error('Errore API:', error);
        }
    }

    // Permetti ricerca premendo Invio
    document.getElementById('api_search_code').addEventListener('keypress', function(e) {
        if(e.key === 'Enter') {
            e.preventDefault();
            searchBookAPI();
        }
    });
</script>

</body>
</html>