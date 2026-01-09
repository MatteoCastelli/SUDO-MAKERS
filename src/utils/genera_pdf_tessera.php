<?php

use Proprietario\SudoMakers\core\Database;

require_once __DIR__ . '/../../vendor/autoload.php';

session_start();
require_once __DIR__ . '/../core/Database.php';

if(!isset($_SESSION['id_utente'])) {
    header("location: login.php");
    exit;
}

$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->prepare("SELECT * FROM utente WHERE id_utente = :id");
$stmt->execute(['id' => $_SESSION['id_utente']]);
$utente = $stmt->fetch();

if(!$utente) {
    header("location: homepage.php");
    exit;
}

// configurazione pdf
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('Biblioteca Scolastica');
$pdf->SetTitle($utente['nome'] . "_" . $utente['cognome']);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 5, 15);
$pdf->SetAutoPageBreak(TRUE, 10);
$pdf->AddPage();

// calcolo date
$data_registrazione = new DateTime($utente['data_registrazione']);
$data_scadenza = clone $data_registrazione;
$data_scadenza->modify('+1 year');

// titolo
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', 'B', 24);
$pdf->Cell(0, 15, 'TESSERA BIBLIOTECA SCOLASTICA', 0, 1, 'C');

// spaziatura superiore
$pdf->Ln(5);

// riquadro grigio
$pdf->SetFillColor(245, 245, 245);
$boxHeight = 60;
$boxY = $pdf->GetY();
$pdf->Rect(15, $boxY, 180, $boxHeight, 'F');

// posizionamento inizio testo
$pdf->SetY($boxY + 5);

// funzione ausiliaria righe
function printCenteredRow($pdf, $label, $value, $isExpired = false) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(88, 8, $label, 0, 0, 'R');
    $pdf->Cell(4, 8, '', 0, 0);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor($isExpired ? 12 : 0, $isExpired ? 138 : 0, $isExpired ? 31 : 0);
    $pdf->Cell(88, 8, $value, 0, 1, 'L');
}

// stampa dati
printCenteredRow($pdf, 'Nome Completo:', $utente['nome'] . ' ' . $utente['cognome']);
printCenteredRow($pdf, 'Data di Nascita:', date('d/m/Y', strtotime($utente['data_nascita'])));
printCenteredRow($pdf, 'Username:', $utente['username']);
printCenteredRow($pdf, 'Tessera Utente:', $utente['codice_tessera']);
printCenteredRow($pdf, 'Email:', $utente['email']);
printCenteredRow($pdf, 'Data Emissione:', $data_registrazione->format('d/m/Y'));
printCenteredRow($pdf, 'Scadenza:', $data_scadenza->format('d/m/Y') . ' - ATTIVA', true);

// spaziatura inferiore (dopo il box)
$pdf->SetY($boxY + $boxHeight + 5);

// titolo sezione barcode
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'CODICE FISCALE', 0, 1, 'C');

// calcolo posizione barcode
$barcodeHeight = 20;
$barcodeWidth = 110;
$centerX = ($pdf->getPageWidth() - $barcodeWidth) / 2;
$currentY = $pdf->GetY();

// stile barcode
$style = array(
    'border' => false,
    'hpadding' => 'auto',
    'vpadding' => 'auto',
    'fgcolor' => array(0,0,0),
    'bgcolor' => false, // Trasparente
    'module_width' => 1,
    'module_height' => 1,
    'position' => 'C' // Forza allineamento centro nel blocco
);

// generazione barcode
$pdf->write1DBarcode($utente['codice_fiscale'], 'C128', $centerX, $currentY, $barcodeWidth, $barcodeHeight, 0.4, $style, 'N');

$pdf->SetY($currentY + $barcodeHeight);

// codice fiscale testuale
$pdf->SetFont('courier', 'B', 14);
$pdf->Cell(0, 10, $utente['codice_fiscale'], 0, 1, 'C');

// output
$filename = $utente['nome'] . "_" . $utente['cognome'] . '.pdf';
$pdf->Output($filename, 'D');
?>