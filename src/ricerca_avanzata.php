<?php
use Proprietario\SudoMakers\Database;

session_start();
require_once "Database.php";

$pdo = Database::getInstance()->getConnection();
$title = "Ricerca Avanzata";

// Recupera tutte le categorie disponibili
$stmt = $pdo->query("SELECT DISTINCT categoria FROM libro WHERE categoria IS NOT NULL ORDER BY categoria");
$categorie = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Recupera tutti gli autori
$stmt = $pdo->query("SELECT id_autore, CONCAT(nome, ' ', cognome) as nome_completo FROM autore ORDER BY cognome, nome");
$autori = $stmt->fetchAll();

// Recupera range anni
$stmt = $pdo->query("SELECT MIN(anno_pubblicazione) as min_anno, MAX(anno_pubblicazione) as max_anno FROM libro WHERE anno_pubblicazione IS NOT NULL");
$anni = $stmt->fetch();
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../style/privateAreaStyle.css">
    <link rel="stylesheet" href="../style/ricercaStyle.css">
</head>
<body>
<?php require_once 'navigation.php'; ?>

<div class="ricerca-avanzata-container">
    <h1>🔧 Ricerca Avanzata</h1>

    <form action="ricerca_avanzata_risultati.php" method="GET" class="form-avanzata">

        <div class="form-section">
            <h3>📖 Informazioni Libro</h3>

            <div class="form-group">
                <label for="titolo">Titolo</label>
                <input type="text" id="titolo" name="titolo" placeholder="Es: 1984">
            </div>

            <div class="form-group">
                <label for="autore">Autore</label>
                <select id="autore" name="autore">
                    <option value="">Tutti gli autori</option>
                    <?php foreach($autori as $autore): ?>
                        <option value="<?= $autore['id_autore'] ?>"><?= htmlspecialchars($autore['nome_completo']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="categoria">Categoria</label>
                <select id="categoria" name="categoria">
                    <option value="">Tutte le categorie</option>
                    <?php foreach($categorie as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="isbn">ISBN</label>
                <input type="text" id="isbn" name="isbn" placeholder="Es: 9788804668541">
            </div>

            <div class="form-group">
                <label for="editore">Editore</label>
                <input type="text" id="editore" name="editore" placeholder="Es: Mondadori">
            </div>
        </div>

        <div class="form-section">
            <h3>📅 Anno di Pubblicazione</h3>

            <div class="range-group">
                <div class="range-inputs">
                    <div class="range-input-group">
                        <label for="anno_min">Da</label>
                        <input type="number" id="anno_min" name="anno_min"
                               min="<?= $anni['min_anno'] ?>"
                               max="<?= $anni['max_anno'] ?>"
                               value="<?= $anni['min_anno'] ?>">
                    </div>
                    <div class="range-input-group">
                        <label for="anno_max">A</label>
                        <input type="number" id="anno_max" name="anno_max"
                               min="<?= $anni['min_anno'] ?>"
                               max="<?= $anni['max_anno'] ?>"
                               value="<?= $anni['max_anno'] ?>">
                    </div>
                </div>

                <div class="range-slider">
                    <input type="range" id="slider_min" class="slider-range"
                           min="<?= $anni['min_anno'] ?>"
                           max="<?= $anni['max_anno'] ?>"
                           value="<?= $anni['min_anno'] ?>"
                           step="1">
                    <input type="range" id="slider_max" class="slider-range"
                           min="<?= $anni['min_anno'] ?>"
                           max="<?= $anni['max_anno'] ?>"
                           value="<?= $anni['max_anno'] ?>"
                           step="1">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3>⭐ Valutazione e Disponibilità</h3>

            <div class="form-group">
                <label for="rating_min">Valutazione minima</label>
                <div class="rating-selector">
                    <select id="rating_min" name="rating_min">
                        <option value="">Qualsiasi</option>
                        <option value="1">⭐ 1+</option>
                        <option value="2">⭐ 2+</option>
                        <option value="3">⭐ 3+</option>
                        <option value="4">⭐ 4+</option>
                        <option value="5">⭐ 5</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Disponibilità</label>
                <div class="checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="disponibile" value="1" checked>
                        Solo libri disponibili immediatamente
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label>Condizione fisica</label>
                <div class="checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="condizione[]" value="ottimo" checked>
                        Ottimo
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="condizione[]" value="buono" checked>
                        Buono
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="condizione[]" value="discreto" checked>
                        Discreto
                    </label>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3>📊 Ordinamento</h3>

            <div class="form-group">
                <label for="ordinamento">Ordina risultati per</label>
                <select id="ordinamento" name="ordinamento">
                    <option value="rilevanza">Rilevanza</option>
                    <option value="alfabetico">Alfabetico (A-Z)</option>
                    <option value="anno_desc">Anno pubblicazione (più recente)</option>
                    <option value="anno_asc">Anno pubblicazione (più vecchio)</option>
                    <option value="popolarita">Popolarità (prestiti)</option>
                    <option value="rating">Valutazione media</option>
                </select>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-search">🔍 Cerca</button>
            <button type="reset" class="btn-reset">↺ Reset</button>
            <a href="homepage.php" class="btn-cancel">✕ Annulla</a>
        </div>
    </form>
</div>

<script>
    // Sincronizza slider con input
    const sliderMin = document.getElementById('slider_min');
    const sliderMax = document.getElementById('slider_max');
    const inputMin = document.getElementById('anno_min');
    const inputMax = document.getElementById('anno_max');

    sliderMin.addEventListener('input', function() {
        const val = parseInt(this.value);
        const maxVal = parseInt(sliderMax.value);
        if(val > maxVal) {
            sliderMax.value = val;
            inputMax.value = val;
        }
        inputMin.value = val;
    });

    sliderMax.addEventListener('input', function() {
        const val = parseInt(this.value);
        const minVal = parseInt(sliderMin.value);
        if(val < minVal) {
            sliderMin.value = val;
            inputMin.value = val;
        }
        inputMax.value = val;
    });

    inputMin.addEventListener('change', function() {
        sliderMin.value = this.value;
    });

    inputMax.addEventListener('change', function() {
        sliderMax.value = this.value;
    });
</script>

</body>
</html>
