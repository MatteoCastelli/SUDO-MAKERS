<?php
/**
 * Prestito Rapido - Pagina per bibliotecari
 * Permette di creare un prestito veloce tramite scanner
 */

use Proprietario\SudoMakers\core\Database;

session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/check_permissions.php';

// SOLO BIBLIOTECARI
requireAnyRole(['bibliotecario', 'amministratore']);

$pdo = Database::getInstance()->getConnection();
$title = "Prestito Rapido";

$errore = '';
$success = '';
$copia_info = null;
$utente_info = null;

// Recupera parametri da scanner
$codice_copia = $_GET['copia'] ?? '';
$codice_tessera = $_GET['tessera'] ?? '';

// Se arriva solo codice copia, mostra form per tessera
if(!empty($codice_copia)) {
    // Recupera info copia
    $stmt = $pdo->prepare("
        SELECT c.*, l.titolo, l.id_libro,
               GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori
        FROM copia c
        JOIN libro l ON c.id_libro = l.id_libro
        LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
        LEFT JOIN autore a ON la.id_autore = a.id_autore
        WHERE c.codice_barcode = :codice
        GROUP BY c.id_copia
    ");
    $stmt->execute(['codice' => $codice_copia]);
    $copia_info = $stmt->fetch();

    if(!$copia_info) {
        $errore = "Copia non trovata nel database";
    } elseif($copia_info['disponibile'] == 0) {
        $errore = "Questa copia è già in prestito";
        header("Location: dashboard_bibliotecario.php");
    } elseif($copia_info['stato_fisico'] == 'smarrito') {
        $errore = "Questa copia risulta smarrita";
    }
}

// Gestione conferma prestito
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['conferma_prestito'])) {
    $id_copia = (int)$_POST['id_copia'];
    $id_utente = (int)$_POST['id_utente'];
    $durata_giorni = (int)$_POST['durata_giorni'];

    try {
        // Verifica limiti utente
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM prestito 
            WHERE id_utente = :id_utente 
            AND data_restituzione_effettiva IS NULL
        ");
        $stmt->execute(['id_utente' => $id_utente]);
        $num_prestiti = $stmt->fetchColumn();

        if($num_prestiti >= 10) {
            throw new Exception("L'utente ha raggiunto il limite di 10 prestiti attivi");
        }

        // Verifica che l'utente non abbia già questo libro
        $stmt = $pdo->prepare("
            SELECT id_libro FROM copia WHERE id_copia = :id_copia
        ");
        $stmt->execute(['id_copia' => $id_copia]);
        $id_libro = $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM prestito p
            JOIN copia c ON p.id_copia = c.id_copia
            WHERE p.id_utente = :id_utente 
            AND c.id_libro = :id_libro 
            AND p.data_restituzione_effettiva IS NULL
        ");
        $stmt->execute(['id_utente' => $id_utente, 'id_libro' => $id_libro]);

        if($stmt->fetchColumn() > 0) {
            throw new Exception("L'utente ha già questo libro in prestito");
        }

        $data_scadenza = date('Y-m-d H:i:s', strtotime("+{$durata_giorni} days"));

        $pdo->beginTransaction();

        // Crea prestito
        $stmt = $pdo->prepare("
            INSERT INTO prestito 
            (id_utente, id_copia, data_prestito, data_scadenza, note) 
            VALUES (:id_utente, :id_copia, NOW(), :data_scadenza, :note)
        ");
        $stmt->execute([
            'id_utente' => $id_utente,
            'id_copia' => $id_copia,
            'data_scadenza' => $data_scadenza,
            'note' => 'Prestito rapido tramite scanner'
        ]);

        // Marca copia come non disponibile
        $stmt = $pdo->prepare("UPDATE copia SET disponibile = 0 WHERE id_copia = :id");
        $stmt->execute(['id' => $id_copia]);

        // Notifica utente
        $stmt = $pdo->prepare("SELECT titolo FROM libro WHERE id_libro = :id");
        $stmt->execute(['id' => $id_libro]);
        $titolo = $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO notifica 
            (id_utente, tipo, titolo, messaggio) 
            VALUES (:id_utente, 'prestito', 'Prestito attivato', :messaggio)
        ");
        $messaggio = "Hai preso in prestito '{$titolo}'. Restituzione entro: " . date('d/m/Y', strtotime($data_scadenza));
        $stmt->execute(['id_utente' => $id_utente, 'messaggio' => $messaggio]);

        $pdo->commit();

        $success = "Prestito registrato con successo! Scadenza: " . date('d/m/Y', strtotime($data_scadenza));

        // Reset variabili per nuovo prestito
        $copia_info = null;
        $utente_info = null;

    } catch(Exception $e) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errore = $e->getMessage();
    }
}

// Ricerca utente da tessera
if(!empty($codice_tessera) && $copia_info) {
    $stmt = $pdo->prepare("
        SELECT id_utente, nome, cognome, email, codice_tessera
        FROM utente
        WHERE codice_tessera = :codice
    ");
    $stmt->execute(['codice' => $codice_tessera]);
    $utente_info = $stmt->fetch();

    if(!$utente_info) {
        $errore = "Tessera utente non trovata";
    }
}

// Ricerca manuale utente
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cerca_utente'])) {
    $search = trim($_POST['search_utente']);
    $id_copia = (int)$_POST['id_copia'];

    // Recupera info copia
    $stmt = $pdo->prepare("
        SELECT c.*, l.titolo, l.id_libro,
               GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') as autori
        FROM copia c
        JOIN libro l ON c.id_libro = l.id_libro
        LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
        LEFT JOIN autore a ON la.id_autore = a.id_autore
        WHERE c.id_copia = :id
        GROUP BY c.id_copia
    ");
    $stmt->execute(['id' => $id_copia]);
    $copia_info = $stmt->fetch();

    // Cerca utente
    $stmt = $pdo->prepare("
        SELECT id_utente, nome, cognome, email, codice_tessera
        FROM utente
        WHERE codice_tessera = :search
        OR email = :search
        OR CONCAT(nome, ' ', cognome) LIKE :search_like
        LIMIT 10
    ");
    $stmt->execute([
        'search' => $search,
        'search_like' => "%{$search}%"
    ]);
    $utenti_trovati = $stmt->fetchAll();
}
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
        <h1>Prestito Rapido</h1>
        <a href="dashboard_bibliotecario.php" class="btn-back">← Torna alla Dashboard</a>
    </div>

    <?php if($errore): ?>
        <div class="alert alert-error"><?= htmlspecialchars($errore) ?></div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
        <div style="text-align: center; margin-top: 20px;">
            <a href="prestito_rapido.php" class="btn-secondary">Nuovo Prestito</a>
            <a href="dashboard_bibliotecario.php" class="btn-secondary">Torna alla Dashboard</a>
        </div>
    <?php endif; ?>

    <?php if(!$success): ?>
        <!-- Indicatore step -->
        <div class="step-indicator" style="display: flex; justify-content: center; margin-bottom: 30px; gap: 10px;">
            <div class="step <?= $copia_info ? 'completed' : 'active' ?>" style="padding: 10px 20px; border-radius: 20px; background: <?= $copia_info ? '#0c8a1f' : '#2a2a2c' ?>; color: white; font-weight: bold;">
                1. Libro
            </div>
            <div class="step <?= $copia_info && !$utente_info ? 'active' : ($utente_info ? 'completed' : '') ?>" style="padding: 10px 20px; border-radius: 20px; background: <?= $utente_info ? '#0c8a1f' : ($copia_info ? '#2a2a2c' : '#1f1f21') ?>; color: <?= $copia_info ? 'white' : '#888' ?>; font-weight: bold;">
                2. Utente
            </div>
            <div class="step <?= $utente_info ? 'active' : '' ?>" style="padding: 10px 20px; border-radius: 20px; background: <?= $utente_info ? '#2a2a2c' : '#1f1f21' ?>; color: <?= $utente_info ? 'white' : '#888' ?>; font-weight: bold;">
                3. Conferma
            </div>
        </div>

        <?php if(!$copia_info): ?>
            <!-- STEP 1: Scansiona libro -->
            <div class="section-card">
                <h3>Scansiona il codice del libro</h3>
                <p style="color: #888; margin-bottom: 20px;">Usa lo scanner per leggere il codice barcode della copia da dare in prestito.</p>
                <form method="GET">
                    <input type="text" name="copia" placeholder="Codice copia (es: LIB000001)"
                           class="form-input" autofocus required style="font-size: 18px; padding: 15px;">
                    <button type="submit" class="btn-primary" style="margin-top: 15px; width: 100%; padding: 15px; font-size: 16px;">
                        Continua
                    </button>
                </form>
            </div>
        <?php elseif(!$utente_info && !$errore): ?>
            <!-- STEP 2: Info libro + Scansiona tessera -->
            <div class="section-card">
                <h3>Libro selezionato:</h3>
                <p style="font-size: 18px; margin: 10px 0;"><strong><?= htmlspecialchars($copia_info['titolo']) ?></strong></p>
                <p style="color: #888;"><?= htmlspecialchars($copia_info['autori']) ?></p>
                <p style="color: #888;">Codice: <?= htmlspecialchars($copia_info['codice_barcode']) ?></p>
            </div>

            <div class="section-card">
                <h3>Scansiona tessera utente</h3>
                <form method="GET">
                    <input type="hidden" name="copia" value="<?= htmlspecialchars($codice_copia) ?>">
                    <input type="text" name="tessera" placeholder="Codice tessera (es: USER000001)"
                           class="form-input" autofocus required style="font-size: 18px; padding: 15px;">
                    <button type="submit" class="btn-primary" style="margin-top: 15px; width: 100%; padding: 15px; font-size: 16px;">
                        Continua
                    </button>
                </form>

                <?php if(isset($utenti_trovati)): ?>
                    <?php if(empty($utenti_trovati)): ?>
                        <p style="text-align: center; color: #888; margin-top: 20px;">Nessun utente trovato</p>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; margin-top: 20px;">
                            <?php foreach($utenti_trovati as $u): ?>
                                <a href="?copia=<?= urlencode($codice_copia) ?>&tessera=<?= urlencode($u['codice_tessera']) ?>"
                                   style="display: flex; justify-content: space-between; align-items: center; background: #2a2a2c; padding: 15px; border-radius: 8px; text-decoration: none; color: #ebebed; border: 1px solid #444; transition: all 0.3s;">
                                    <div>
                                        <strong><?= htmlspecialchars($u['nome'] . ' ' . $u['cognome']) ?></strong><br>
                                        <small style="color: #888;"><?= htmlspecialchars($u['email']) ?></small><br>
                                        <small style="color: #888;">Tessera: <?= htmlspecialchars($u['codice_tessera']) ?></small>
                                    </div>
                                    <div style="font-size: 24px;">→</div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- STEP 3: Conferma prestito -->
            <div class="section-card">
                <h3>Libro:</h3>
                <p style="font-size: 18px; margin: 5px 0;"><strong><?= htmlspecialchars($copia_info['titolo']) ?></strong></p>
                <p style="color: #888;"><?= htmlspecialchars($copia_info['autori']) ?></p>
            </div>

            <div class="section-card">
                <h3>Utente:</h3>
                <p style="font-size: 18px; margin: 5px 0;">
                    <strong><?= htmlspecialchars($utente_info['nome'] . ' ' . $utente_info['cognome']) ?></strong>
                </p>
                <p style="color: #888;"><?= htmlspecialchars($utente_info['email']) ?></p>
                <p style="color: #888;">Tessera: <?= htmlspecialchars($utente_info['codice_tessera']) ?></p>
            </div>

            <form method="POST" class="section-card">
                <input type="hidden" name="id_copia" value="<?= $copia_info['id_copia'] ?>">
                <input type="hidden" name="id_utente" value="<?= $utente_info['id_utente'] ?>">

                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 10px; color: #ebebed;">
                        <strong>Durata prestito (giorni):</strong>
                    </label>
                    <select name="durata_giorni" class="form-select" style="padding: 15px; font-size: 16px;">
                        <option value="7">7 giorni</option>
                        <option value="14">14 giorni</option>
                        <option value="21">21 giorni</option>
                        <option value="30" selected>30 giorni (standard)</option>
                        <option value="60">60 giorni</option>
                    </select>
                </div>

                <button type="submit" name="conferma_prestito" class="btn-primary"
                        style="width: 100%; padding: 20px; font-size: 18px; margin-top: 10px;">
                    Conferma Prestito
                </button>

                <a href="prestito_rapido.php" class="btn-secondary" style="width: 100%; display: block; text-align: center; margin-top: 15px; padding: 15px; box-sizing: border-box;">
                    Annulla
                </a>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>