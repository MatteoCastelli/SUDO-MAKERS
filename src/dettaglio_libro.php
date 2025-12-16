<?php
use Proprietario\SudoMakers\Database;
use Proprietario\SudoMakers\RecommendationEngine;

session_start();
require_once "Database.php";
require_once "check_permissions.php";
require_once "RecommendationEngine.php";

$pdo = Database::getInstance()->getConnection();

// Verifica ID libro
if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    header("Location: homepage.php");
    exit;
}

$id_libro = (int)$_GET['id'];

// ================== INVIO / MODIFICA RECENSIONE ==================
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['id_utente'])){
    $voto = (int)$_POST['voto'];
    $testo = trim($_POST['testo']);

    if($voto >= 1 && $voto <= 5){
        try {
            $stmt = $pdo->prepare("
                INSERT INTO recensione (id_libro, id_utente, voto, testo)
                VALUES (:id_libro, :id_utente, :voto, :testo)
                ON DUPLICATE KEY UPDATE voto = :voto, testo = :testo, data_recensione = NOW()
            ");
            $stmt->execute([
                    'id_libro' => $id_libro,
                    'id_utente' => $_SESSION['id_utente'],
                    'voto'      => $voto,
                    'testo'     => $testo
            ]);

            header("Location: dettaglio_libro.php?id=$id_libro&success=1");
            exit;
        } catch(Exception $e){
            $errore = "Errore nell'invio della recensione.";
        }
    }
}

// ================== DETTAGLI LIBRO ==================
$query = "
    SELECT 
        l.*,
        GROUP_CONCAT(DISTINCT CONCAT(a.nome, ' ', a.cognome) SEPARATOR ', ') AS autori,
        COUNT(DISTINCT c.id_copia) AS totale_copie,
        SUM(CASE WHEN c.disponibile = 1 AND c.stato_fisico != 'smarrito' THEN 1 ELSE 0 END) AS copie_disponibili,
        SUM(CASE WHEN c.stato_fisico = 'smarrito' THEN 1 ELSE 0 END) AS copie_smarrite,
        AVG(r.voto) AS media_voti,
        COUNT(DISTINCT r.id_recensione) AS numero_recensioni
    FROM libro l
    LEFT JOIN libro_autore la ON l.id_libro = la.id_libro
    LEFT JOIN autore a ON la.id_autore = a.id_autore
    LEFT JOIN copia c ON l.id_libro = c.id_libro
    LEFT JOIN recensione r ON l.id_libro = r.id_libro
    WHERE l.id_libro = :id_libro
    GROUP BY l.id_libro
";

$stmt = $pdo->prepare($query);
$stmt->execute(['id_libro' => $id_libro]);
$libro = $stmt->fetch();

if(!$libro){
    header("Location: homepage.php");
    exit;
}

// ================== DISPONIBILITÀ ==================
function getDisponibilita($copie_disponibili, $totale_copie, $copie_smarrite){
    $copie_attive = $totale_copie - $copie_smarrite;

    if($copie_attive <= 0){
        return ['stato'=>'non_disponibile','testo'=>'Non disponibile','classe'=>'badge-red'];
    } elseif($copie_disponibili > 0){
        return ['stato'=>'disponibile','testo'=>'Disponibile','classe'=>'badge-green'];
    }
    return ['stato'=>'prenotabile','testo'=>'Prenotabile','classe'=>'badge-orange'];
}

$disponibilita = getDisponibilita(
        $libro['copie_disponibili'],
        $libro['totale_copie'],
        $libro['copie_smarrite']
);

// ================== PRENOTAZIONI ==================
$prenotazione_utente = null;
if(isset($_SESSION['id_utente'])){
    $stmt = $pdo->prepare("
        SELECT *
        FROM prenotazione
        WHERE id_utente = :id_utente
          AND id_libro = :id_libro
          AND stato IN ('attiva','disponibile')
    ");
    $stmt->execute([
            'id_utente' => $_SESSION['id_utente'],
            'id_libro'  => $id_libro
    ]);
    $prenotazione_utente = $stmt->fetch();
}

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM prenotazione
    WHERE id_libro = :id_libro AND stato = 'attiva'
");
$stmt->execute(['id_libro' => $id_libro]);
$persone_in_coda = $stmt->fetchColumn();

// ================== RECENSIONI ==================
$stmt = $pdo->prepare("
    SELECT r.*, u.nome, u.cognome, u.foto
    FROM recensione r
    JOIN utente u ON r.id_utente = u.id_utente
    WHERE r.id_libro = :id_libro
    ORDER BY r.data_recensione DESC
");
$stmt->execute(['id_libro'=>$id_libro]);
$recensioni = $stmt->fetchAll();

$ha_recensito = false;
if(isset($_SESSION['id_utente'])){
    $stmt = $pdo->prepare("
        SELECT *
        FROM recensione
        WHERE id_libro = :id_libro AND id_utente = :id_utente
    ");
    $stmt->execute([
            'id_libro'=>$id_libro,
            'id_utente'=>$_SESSION['id_utente']
    ]);
    $ha_recensito = $stmt->fetch();
}

// ================== LIBRI CORRELATI ==================
$stmt = $pdo->prepare("
    SELECT 
        l.*,
        GROUP_CONCAT(CONCAT(a.nome,' ',a.cognome) SEPARATOR ', ') AS autori,
        COUNT(c.id_copia) AS totale_copie,
        SUM(CASE WHEN c.disponibile=1 AND c.stato_fisico!='smarrito' THEN 1 ELSE 0 END) AS copie_disponibili,
        SUM(CASE WHEN c.stato_fisico='smarrito' THEN 1 ELSE 0 END) AS copie_smarrite
    FROM libro l
    LEFT JOIN libro_autore la ON l.id_libro=la.id_libro
    LEFT JOIN autore a ON la.id_autore=a.id_autore
    LEFT JOIN copia c ON l.id_libro=c.id_libro
    WHERE l.categoria=:categoria AND l.id_libro!=:id_libro
    GROUP BY l.id_libro
    ORDER BY RAND()
    LIMIT 5
");
$stmt->execute([
        'categoria'=>$libro['categoria'],
        'id_libro'=>$id_libro
]);
$libri_correlati = $stmt->fetchAll();

// ================== RECOMMENDATION ENGINE ==================
$engine = new RecommendationEngine($pdo);
$libri_also_read = $engine->getBooksAlsoRead($id_libro, 6);

$title = $libro['titolo'];
?>
