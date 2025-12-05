<?php
use Proprietario\SudoMakers\Database;
require '../vendor/autoload.php';

session_start();
require_once "Database.php";

if(!isset($_SESSION['id_utente'])) {
    header("location: login.php");
    exit;
}

// Recupera i dati dell'utente
$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->prepare("SELECT * FROM utente WHERE id_utente = :id");
$stmt->execute(['id' => $_SESSION['id_utente']]);
$utente = $stmt->fetch();

if(!$utente) {
    header("location: homepage.php");
    exit;
}

// Usa TCPDF per generare il PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Informazioni documento
$pdf->SetCreator('Biblioteca Digitale');
$pdf->SetAuthor('Sistema Biblioteca');
$pdf->SetTitle('Tessera Biblioteca - ' . $utente['nome'] . ' ' . $utente['cognome']);
$pdf->SetSubject('Tessera Biblioteca Digitale');

// Rimuovi header e footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Aggiungi una pagina
$pdf->AddPage();

// Formatta le date
$data_registrazione = new DateTime($utente['data_registrazione']);
$data_scadenza = clone $data_registrazione;
$data_scadenza->modify('+1 year');

// Colori
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(31, 31, 33);

// Titolo
$pdf->SetFont('helvetica', 'B', 24);
$pdf->Cell(0, 15, 'TESSERA BIBLIOTECA DIGITALE', 0, 1, 'C');
$pdf->Ln(5);

// Sottotitolo
$pdf->SetFont('helvetica', '', 12);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 8, 'Biblioteca Scolastica - Sistema di Gestione Prestiti', 0, 1, 'C');
$pdf->Ln(10);

// Reset colore testo
$pdf->SetTextColor(0, 0, 0);

// Box principale
$pdf->SetFillColor(240, 240, 240);
$pdf->Rect(15, $pdf->GetY(), 180, 100, 'F');

$startY = $pdf->GetY() + 10;
$pdf->SetY($startY);

// Informazioni utente
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(50, 8, 'Nome Completo:', 0, 0);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 8, $utente['nome'] . ' ' . $utente['cognome'], 0, 1);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(50, 8, 'ID Utente:', 0, 0);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 8, '#' . str_pad($utente['id_utente'], 6, '0', STR_PAD_LEFT), 0, 1);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(50, 8, 'Email:', 0, 0);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 8, $utente['email'], 0, 1);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(50, 8, 'Data di Nascita:', 0, 0);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 8, date('d/m/Y', strtotime($utente['data_nascita'])), 0, 1);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(50, 8, 'Data Emissione:', 0, 0);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 8, $data_registrazione->format('d/m/Y'), 0, 1);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(50, 8, 'Scadenza:', 0, 0);
$pdf->SetFont('helvetica', '', 11);
$pdf->SetTextColor(12, 138, 31);
$pdf->Cell(0, 8, $data_scadenza->format('d/m/Y') . ' - ATTIVA', 0, 1);
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(15);

// Codice a barre (usando Code128)
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'CODICE FISCALE', 0, 1, 'C');
$pdf->Ln(5);

// Genera il codice a barre
$style = array(
    'border' => 2,
    'vpadding' => 'auto',
    'hpadding' => 'auto',
    'fgcolor' => array(0,0,0),
    'bgcolor' => array(255,255,255),
    'module_width' => 1,
    'module_height' => 1
);

$pdf->write1DBarcode($utente['codice_fiscale'], 'C128', '', '', '', 30, 0.4, $style, 'N');

$pdf->Ln(5);

// Codice fiscale in testo
$pdf->SetFont('courier', 'B', 16);
$pdf->Cell(0, 10, $utente['codice_fiscale'], 0, 1, 'C');

$pdf->Ln(10);

// Note
$pdf->SetFont('helvetica', 'I', 9);
$pdf->SetTextColor(100, 100, 100);
$pdf->MultiCell(0, 5, 'Questa tessera è valida per l\'identificazione presso la biblioteca scolastica. ' .
    'Presenta questa tessera ad ogni prestito o restituzione di libri. ' .
    'In caso di smarrimento, contatta immediatamente l\'amministrazione.', 0, 'C');

// Output PDF
$filename = 'Tessera_Biblioteca_' . $utente['cognome'] . '_' . $utente['nome'] . '.pdf';
$pdf->Output($filename, 'D'); // 'D' = Download
?>