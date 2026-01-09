<?php
/**
 * Scanner Barcode Automatico - SOLO BIBLIOTECARI
 * Riconosce automaticamente ISBN, codici copia e tessere utente
 */

use Proprietario\SudoMakers\core\Database;

session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/check_permissions.php';
require_once __DIR__ . '/../utils/barcode_utils.php';

// ⚠️ SOLO BIBLIOTECARI E AMMINISTRATORI
requireAnyRole(['bibliotecario', 'amministratore']);

$pdo = Database::getInstance()->getConnection();
$title = "Scanner Barcode";
$errore = '';
$info_codice = null;
$risultato = null;

// ================== GESTIONE SCANSIONE ==================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codice_barre'])){
    $codice = trim($_POST['codice_barre']);

    if(!empty($codice)){
        // Riconosci tipo codice
        $info_codice = riconosciTipoCodice($codice);

        // Processa il codice
        $risultato = processaCodice($codice, $pdo);

        if($risultato['success'] && $risultato['redirect']) {
            // Redirect automatico
            header("Location: " . $risultato['redirect']);
            exit;
        } else {
            // Mostra errore
            $errore = $risultato['message'];
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
        <h1>Scanner Automatico</h1>
    </div>

    <?php if($errore): ?>
        <div class="errore">
            Errore <?= htmlspecialchars($errore) ?>
        </div>
    <?php endif; ?>

    <?php if($info_codice && !$risultato['success']): ?>
        <div class="info-box">
            <h3>Codice riconosciuto</h3>
            <p><strong>Tipo:</strong>
                <span class="tipo-badge tipo-<?= $info_codice['tipo'] ?>">
                    <?= strtoupper($info_codice['tipo']) ?>
                </span>
            </p>
            <p><strong>Valore:</strong> <?= htmlspecialchars($info_codice['valore']) ?></p>
            <p><strong>Descrizione:</strong> <?= htmlspecialchars($info_codice['descrizione']) ?></p>
        </div>
    <?php endif; ?>

    <form method="POST" action="" class="form-scansione" id="scanForm">
        <div class="input-group">
            <label for="codice_barre">
                Punta e scansiona il codice
            </label>
            <input type="text"
                   id="codice_barre"
                   name="codice_barre"
                   class="input-barcode"
                   autocomplete="off"
                   autofocus
                   required
                   placeholder="Scansiona qui...">
        </div>

        <button type="submit" class="btn-submit">
            Riconosci e Processa
        </button>

        <a href="dashboard_bibliotecario.php" class="btn-cancel">
            Torna alla Dashboard
        </a>
    </form>

    <div class="help-section">
        <h3>💡 Tipi di codice supportati</h3>
        <div style="display: grid; gap: 15px; margin-top: 20px;">
            <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; border-left: 4px solid #0c8a1f;">
                <h4 style="margin: 0 0 5px 0; color: #ebebed;">EAN-13 / ISBN (13 cifre)</h4>
                <p style="margin: 0; color: #888; font-size: 14px;">
                    Libro da catalogare o cercare nel sistema<br>
                    <strong>Esempio:</strong> 9788804668879
                </p>
            </div>

            <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; border-left: 4px solid #0c8a1f;">
                <h4 style="margin: 0 0 5px 0; color: #ebebed;">Codice Copia (LIBxxxxxx)</h4>
                <p style="margin: 0; color: #888; font-size: 14px;">
                    Identifica una copia specifica per prestito/restituzione<br>
                    <strong>Esempio:</strong> LIB000001
                </p>
            </div>

            <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; border-left: 4px solid #0c8a1f;">
                <h4 style="margin: 0 0 5px 0; color: #ebebed;">Tessera Utente (USERxxxxxx)</h4>
                <p style="margin: 0; color: #888; font-size: 14px;">
                    Identifica un utente registrato<br>
                    <strong>Esempio:</strong> USER000001
                </p>
            </div>
        </div>

    </div>
</div>

<script>
    // Auto-focus sul campo quando la pagina si carica
    window.addEventListener('load', function() {
        document.getElementById('codice_barre').focus();
    });

    // Mantieni sempre il focus sul campo (simula scanner hardware)
    document.addEventListener('click', function(e) {
        if(e.target.id !== 'codice_barre' &&
            !e.target.closest('button') &&
            !e.target.closest('a')) {
            document.getElementById('codice_barre').focus();
        }
    });

    // Feedback visivo durante digitazione
    const input = document.getElementById('codice_barre');
    input.addEventListener('input', function() {
        if(this.value.length > 0) {
            this.style.borderColor = '#0c8a1f';
            this.style.boxShadow = '0 0 0 3px rgba(12, 138, 31, 0.2)';
        } else {
            this.style.borderColor = '#888';
            this.style.boxShadow = 'none';
        }
    });

    // Auto-submit dopo aver scansionato (opzionale)
    // Alcuni scanner aggiungono "Enter" automaticamente
    input.addEventListener('keypress', function(e) {
        if(e.key === 'Enter' && this.value.length >= 10) {
            document.getElementById('scanForm').submit();
        }
    });
</script>

</body>
</html>