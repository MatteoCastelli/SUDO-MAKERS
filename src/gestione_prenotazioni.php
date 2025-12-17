<?php
use Proprietario\SudoMakers\Database;

session_start();
require_once "Database.php";
require_once "check_permissions.php";
require_once "functions.php";

requireAnyRole(['bibliotecario', 'amministratore']);

$pdo = Database::getInstance()->getConnection();

/**
 * Funzione per assegnare libro al primo in coda quando rientra
 */
function assegnaLibroAlPrimoInCoda($id_libro, $pdo) {
    // Trova prima prenotazione attiva
    $stmt = $pdo->prepare("
        SELECT p.*, u.email, u.nome, l.titolo
        FROM prenotazione p
        JOIN utente u ON p.id_utente = u.id_utente
        JOIN libro l ON p.id_libro = l.id_libro
        WHERE p.id_libro = :id_libro
        AND p.stato = 'attiva'
        ORDER BY p.posizione_coda ASC
        LIMIT 1
    ");
    $stmt->execute(['id_libro' => $id_libro]);
    $prenotazione = $stmt->fetch();

    if(!$prenotazione) {
        return false; // Nessuna prenotazione in coda
    }

    // Aggiorna prenotazione a "disponibile"
    $data_disponibilita = date('Y-m-d H:i:s');
    $data_scadenza = date('Y-m-d H:i:s', strtotime('+48 hours'));

    $stmt = $pdo->prepare("
        UPDATE prenotazione 
        SET stato = 'disponibile',
            data_disponibilita = :data_disp,
            data_scadenza_ritiro = :data_scad
        WHERE id_prenotazione = :id
    ");
    $stmt->execute([
        'data_disp' => $data_disponibilita,
        'data_scad' => $data_scadenza,
        'id' => $prenotazione['id_prenotazione']
    ]);

    // Riserva una copia disponibile
    $stmt = $pdo->prepare("
        UPDATE copia 
        SET disponibile = 0 
        WHERE id_libro = :id_libro 
        AND disponibile = 1 
        AND stato_fisico != 'smarrito'
        LIMIT 1
    ");
    $stmt->execute(['id_libro' => $id_libro]);

    // Invia notifica via email
    $ore_rimaste = 48;
    sendNotificaLibroDisponibile(
        $prenotazione['email'],
        $prenotazione['nome'],
        $prenotazione['titolo'],
        $data_scadenza
    );

    // Crea notifica in-app
    $stmt = $pdo->prepare("
        INSERT INTO notifica 
        (id_utente, tipo, titolo, messaggio) 
        VALUES 
        (:id_utente, 'prenotazione', 'Libro disponibile per il ritiro!', 
         :messaggio)
    ");
    $messaggio = "Il libro '{$prenotazione['titolo']}' è disponibile! Ritiralo entro 48 ore (scadenza: " . date('d/m/Y H:i', strtotime($data_scadenza)) . ")";
    $stmt->execute([
        'id_utente' => $prenotazione['id_utente'],
        'messaggio' => $messaggio
    ]);

    return true;
}

/**
 * Funzione per annullare prenotazioni scadute e passare al successivo
 */
function verificaPrenotazioniScadute($pdo) {
    // Trova prenotazioni scadute
    $stmt = $pdo->query("
        SELECT p.*, l.titolo
        FROM prenotazione p
        JOIN libro l ON p.id_libro = l.id_libro
        WHERE p.stato = 'disponibile'
        AND p.data_scadenza_ritiro < NOW()
    ");
    $prenotazioni_scadute = $stmt->fetchAll();

    foreach($prenotazioni_scadute as $pren) {
        // Annulla prenotazione
        $stmt = $pdo->prepare("
            UPDATE prenotazione 
            SET stato = 'scaduta' 
            WHERE id_prenotazione = :id
        ");
        $stmt->execute(['id' => $pren['id_prenotazione']]);

        // Libera la copia riservata
        $stmt = $pdo->prepare("
            UPDATE copia 
            SET disponibile = 1 
            WHERE id_libro = :id_libro 
            AND disponibile = 0
            LIMIT 1
        ");
        $stmt->execute(['id_libro' => $pren['id_libro']]);

        // Notifica utente
        $stmt = $pdo->prepare("
            INSERT INTO notifica 
            (id_utente, tipo, titolo, messaggio) 
            VALUES 
            (:id_utente, 'prenotazione', 'Prenotazione scaduta', 
             :messaggio)
        ");
        $messaggio = "La tua prenotazione per '{$pren['titolo']}' è scaduta. Il libro è stato assegnato al successivo in lista.";
        $stmt->execute([
            'id_utente' => $pren['id_utente'],
            'messaggio' => $messaggio
        ]);

        // Ricalcola posizioni in coda
        ricalcolaPosizioniCoda($pren['id_libro'], $pdo);

        // Assegna al prossimo
        assegnaLibroAlPrimoInCoda($pren['id_libro'], $pdo);
    }
}

/**
 * Invia promemoria 12 ore prima della scadenza
 */
function inviaPromemoria($pdo) {
    $stmt = $pdo->query("
        SELECT p.*, u.email, u.nome, l.titolo
        FROM prenotazione p
        JOIN utente u ON p.id_utente = u.id_utente
        JOIN libro l ON p.id_libro = l.id_libro
        WHERE p.stato = 'disponibile'
        AND p.data_scadenza_ritiro BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 12 HOUR)
        AND p.id_prenotazione NOT IN (
            SELECT id_prenotazione FROM notifica 
            WHERE tipo = 'prenotazione' 
            AND titolo LIKE '%Promemoria%'
        )
    ");
    $prenotazioni = $stmt->fetchAll();

    foreach($prenotazioni as $pren) {
        // Email promemoria
        sendPromemoriaRitiro(
            $pren['email'],
            $pren['nome'],
            $pren['titolo'],
            $pren['data_scadenza_ritiro']
        );

        // Notifica in-app
        $stmt = $pdo->prepare("
            INSERT INTO notifica 
            (id_utente, tipo, titolo, messaggio) 
            VALUES 
            (:id_utente, 'prenotazione', 'Promemoria: ritira il libro!', 
             :messaggio)
        ");
        $ore_rimaste = round((strtotime($pren['data_scadenza_ritiro']) - time()) / 3600);
        $messaggio = "PROMEMORIA: Ritira '{$pren['titolo']}' entro {$ore_rimaste} ore o la prenotazione scadrà!";
        $stmt->execute([
            'id_utente' => $pren['id_utente'],
            'messaggio' => $messaggio
        ]);
    }
}

/**
 * Ricalcola posizioni in coda dopo annullamento
 */
function ricalcolaPosizioniCoda($id_libro, $pdo) {
    $stmt = $pdo->prepare("
        SELECT id_prenotazione 
        FROM prenotazione 
        WHERE id_libro = :id_libro 
        AND stato = 'attiva'
        ORDER BY posizione_coda ASC
    ");
    $stmt->execute(['id_libro' => $id_libro]);
    $prenotazioni = $stmt->fetchAll();

    $posizione = 1;
    foreach($prenotazioni as $pren) {
        $stmt = $pdo->prepare("
            UPDATE prenotazione 
            SET posizione_coda = :pos 
            WHERE id_prenotazione = :id
        ");
        $stmt->execute([
            'pos' => $posizione,
            'id' => $pren['id_prenotazione']
        ]);
        $posizione++;
    }
}

// Esecuzione automatica delle verifiche
verificaPrenotazioniScadute($pdo);
inviaPromemoria($pdo);

// Interfaccia per bibliotecario
$title = "Gestione Prenotazioni";

// Lista prenotazioni attive
$stmt = $pdo->query("
    SELECT p.*, u.nome, u.cognome, u.email, l.titolo, l.id_libro
    FROM prenotazione p
    JOIN utente u ON p.id_utente = u.id_utente
    JOIN libro l ON p.id_libro = l.id_libro
    WHERE p.stato IN ('attiva', 'disponibile')
    ORDER BY l.titolo, p.posizione_coda
");
$prenotazioni = $stmt->fetchAll();

// Raggruppa per libro
$prenotazioni_per_libro = [];
foreach($prenotazioni as $pren) {
    $prenotazioni_per_libro[$pren['titolo']][] = $pren;
}
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../public/assets/css/privateAreaStyle.css">
    <link rel="stylesheet" href="../public/assets/css/dashboardStyle.css">
</head>
<body>
<?php require_once 'navigation.php'; ?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1>Gestione Code Prenotazioni</h1>
    </div>

    <?php if(empty($prenotazioni)): ?>
        <div class="section-card">
            <p style="text-align: center; color: #888;">Nessuna prenotazione attiva al momento</p>
        </div>
    <?php else: ?>
        <?php foreach($prenotazioni_per_libro as $titolo => $lista): ?>
            <div class="section-card" style="margin-bottom: 30px;">
                <h2>📚 <?= htmlspecialchars($titolo) ?></h2>
                <p style="color: #888; margin-bottom: 20px;">Persone in coda: <?= count($lista) ?></p>

                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Posizione</th>
                        <th>Utente</th>
                        <th>Email</th>
                        <th>Data Prenotazione</th>
                        <th>Stato</th>
                        <th>Scadenza Ritiro</th>
                        <th>Azioni</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach($lista as $pren): ?>
                        <tr>
                            <td><strong>#<?= $pren['posizione_coda'] ?></strong></td>
                            <td><?= htmlspecialchars($pren['nome'] . ' ' . $pren['cognome']) ?></td>
                            <td><?= htmlspecialchars($pren['email']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($pren['data_prenotazione'])) ?></td>
                            <td>
                                <?php if($pren['stato'] === 'disponibile'): ?>
                                    <span class="badge badge-success">Disponibile</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">In Coda</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($pren['data_scadenza_ritiro']): ?>
                                    <?php
                                    $scadenza = strtotime($pren['data_scadenza_ritiro']);
                                    $ore_rimaste = round(($scadenza - time()) / 3600);
                                    ?>
                                    <span style="color: <?= $ore_rimaste < 12 ? '#b30000' : '#ff9800' ?>">
                                            <?= date('d/m/Y H:i', $scadenza) ?>
                                            <br><small>(<?= $ore_rimaste ?>h rimaste)</small>
                                        </span>
                                <?php else: ?>
                                    <span style="color: #888;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="dettaglio_libro.php?id=<?= $pren['id_libro'] ?>" class="btn-small btn-info">Vedi Libro</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
