<?php
/**
 * Visualizza Ricevuta Pagamento Multa
 * Mostra ricevuta dettagliata stampabile dopo pagamento
 */

use Proprietario\SudoMakers\core\Database;

session_start();
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/check_permissions.php';

requireAnyRole(['bibliotecario', 'amministratore']);

$pdo = Database::getInstance()->getConnection();

$id_pagamento = (int)($_GET['id'] ?? 0);

if (!$id_pagamento) {
    die("ID pagamento non valido");
}

// Recupera dati completi pagamento
$stmt = $pdo->prepare("
    SELECT 
        pag.*,
        m.id_multa,
        m.importo as importo_multa,
        m.tipo_multa,
        m.data_creazione as data_multa,
        m.note as note_multa,
        u.id_utente,
        u.nome,
        u.cognome,
        u.codice_tessera,
        u.codice_fiscale,
        u.email,
        b.nome as nome_bibliotecario,
        b.cognome as cognome_bibliotecario,
        l.titolo as libro_titolo,
        pr.data_scadenza,
        pr.data_restituzione_effettiva,
        DATEDIFF(
            COALESCE(pr.data_restituzione_effettiva, NOW()), 
            pr.data_scadenza
        ) as giorni_ritardo
    FROM pagamento pag
    JOIN multa m ON pag.id_multa = m.id_multa
    JOIN utente u ON m.id_utente = u.id_utente
    JOIN utente b ON pag.id_bibliotecario = b.id_utente
    LEFT JOIN prestito pr ON m.id_prestito = pr.id_prestito
    LEFT JOIN copia c ON pr.id_copia = c.id_copia
    LEFT JOIN libro l ON c.id_libro = l.id_libro
    WHERE pag.id_pagamento = :id
");

$stmt->execute(['id' => $id_pagamento]);
$dati = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dati) {
    die("Pagamento non trovato");
}

// Formattazione dati
$data_pagamento = date('d/m/Y H:i', strtotime($dati['data_pagamento']));
$data_multa = date('d/m/Y', strtotime($dati['data_multa']));
$importo_formattato = number_format($dati['importo'], 2, ',', '.');

// Determina causale in base al tipo
$causale = match($dati['tipo_multa']) {
    'ritardo' => "Multa per ritardo restituzione libro",
    'danneggiamento' => "Multa per danneggiamento libro",
    'smarrimento' => "Multa per smarrimento libro",
    'altra' => "Altra multa",
    default => "Multa biblioteca"
};

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ricevuta Pagamento #<?= $id_pagamento ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #1a1a1a;
            color: #ebebed;
            padding: 20px;
        }
        
        @media print {
            body {
                background: white;
                color: black;
            }
            .no-print {
                display: none !important;
            }
        }
        
        .ricevuta-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            color: #333;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .ricevuta-header {
            background: linear-gradient(135deg, #0c8a1f 0%, #0a7018 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .ricevuta-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 700;
            letter-spacing: 2px;
        }
        
        .ricevuta-numero {
            font-size: 20px;
            opacity: 0.95;
            font-weight: 500;
        }
        
        .stato-pagato {
            display: inline-block;
            background: rgba(255,255,255,0.3);
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            border: 2px solid white;
        }
        
        .ricevuta-body {
            padding: 40px 30px;
        }
        
        .sezione {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .sezione:last-of-type {
            border-bottom: none;
        }
        
        .sezione h2 {
            color: #0c8a1f;
            font-size: 18px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-row .label {
            color: #666;
            font-weight: 500;
        }
        
        .info-row .value {
            color: #333;
            font-weight: 600;
            text-align: right;
        }
        
        .importo-box {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 3px solid #0c8a1f;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
        }
        
        .importo-box .label {
            color: #666;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        
        .importo-box .importo {
            color: #0c8a1f;
            font-size: 48px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }
        
        .firma-box {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 50px;
            padding-top: 30px;
            border-top: 2px solid #e0e0e0;
        }
        
        .firma {
            text-align: center;
        }
        
        .firma .linea {
            height: 60px;
            border-bottom: 2px solid #333;
            margin-bottom: 10px;
        }
        
        .firma strong {
            display: block;
            color: #0c8a1f;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .firma .ruolo {
            color: #666;
            font-size: 12px;
        }
        
        .footer {
            background: #2d2d2d;
            color: #aaa;
            padding: 25px 30px;
            text-align: center;
            font-size: 12px;
        }
        
        .footer p {
            margin: 5px 0;
        }
        
        .footer strong {
            color: #0c8a1f;
        }
        
        .btn-print {
            background: #0c8a1f;
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            margin: 10px 5px;
        }
        
        .btn-print:hover {
            background: #0a7018;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
            transition: background 0.3s;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .actions-bar {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: #2d2d2d;
            border-radius: 8px;
        }
        
        @media print {
            .ricevuta-container {
                box-shadow: none;
            }
            .firma .linea {
                height: 80px;
            }
        }
    </style>
</head>
<body>

<!-- Barra azioni (non stampata) -->
<div class="actions-bar no-print">
    <button class="btn-print" onclick="window.print()">🖨️ Stampa Ricevuta</button>
    <a href="gestion_multe.php" class="btn-secondary">← Torna a Gestione Multe</a>
    <button class="btn-print" onclick="scaricaPDF()">📄 Scarica PDF</button>
</div>

<!-- Ricevuta -->
<div class="ricevuta-container">
    <!-- Header -->
    <div class="ricevuta-header">
        <h1>RICEVUTA DI PAGAMENTO</h1>
        <div class="ricevuta-numero">
            Ricevuta N° <?= str_pad($id_pagamento, 6, '0', STR_PAD_LEFT) ?>
        </div>
        <div style="margin-top: 20px;">
            <span class="stato-pagato">✓ PAGATA</span>
        </div>
    </div>
    
    <!-- Body -->
    <div class="ricevuta-body">
        <!-- Dati Pagamento -->
        <div class="sezione">
            <h2>📅 Dati Pagamento</h2>
            <div class="info-row">
                <span class="label">Data e Ora Pagamento:</span>
                <span class="value"><?= $data_pagamento ?></span>
            </div>
            <div class="info-row">
                <span class="label">Metodo di Pagamento:</span>
                <span class="value" style="text-transform: capitalize;"><?= htmlspecialchars($dati['metodo_pagamento']) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Operatore:</span>
                <span class="value"><?= htmlspecialchars($dati['nome_bibliotecario'] . ' ' . $dati['cognome_bibliotecario']) ?></span>
            </div>
        </div>
        
        <!-- Importo -->
        <div class="importo-box">
            <div class="label">IMPORTO PAGATO</div>
            <div class="importo">€ <?= $importo_formattato ?></div>
        </div>
        
        <!-- Dati Utente -->
        <div class="sezione">
            <h2>👤 Dati Utente</h2>
            <div class="info-row">
                <span class="label">Nome e Cognome:</span>
                <span class="value"><?= htmlspecialchars($dati['nome'] . ' ' . $dati['cognome']) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Codice Tessera:</span>
                <span class="value"><?= htmlspecialchars($dati['codice_tessera']) ?></span>
            </div>
            <?php if (!empty($dati['codice_fiscale'])): ?>
            <div class="info-row">
                <span class="label">Codice Fiscale:</span>
                <span class="value"><?= htmlspecialchars($dati['codice_fiscale']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="label">Email:</span>
                <span class="value"><?= htmlspecialchars($dati['email']) ?></span>
            </div>
        </div>
        
        <!-- Dettagli Multa -->
        <div class="sezione">
            <h2>📋 Dettagli Multa</h2>
            <div class="info-row">
                <span class="label">ID Multa:</span>
                <span class="value">#<?= $dati['id_multa'] ?></span>
            </div>
            <div class="info-row">
                <span class="label">Tipo Multa:</span>
                <span class="value" style="text-transform: capitalize;"><?= htmlspecialchars($dati['tipo_multa']) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Causale:</span>
                <span class="value"><?= htmlspecialchars($causale) ?></span>
            </div>
            
            <?php if (!empty($dati['libro_titolo'])): ?>
            <div class="info-row">
                <span class="label">Libro:</span>
                <span class="value"><?= htmlspecialchars($dati['libro_titolo']) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($dati['giorni_ritardo']) && $dati['giorni_ritardo'] > 0): ?>
            <div class="info-row">
                <span class="label">Giorni di Ritardo:</span>
                <span class="value"><?= $dati['giorni_ritardo'] ?> giorni</span>
            </div>
            <?php endif; ?>
            
            <div class="info-row">
                <span class="label">Data Multa:</span>
                <span class="value"><?= $data_multa ?></span>
            </div>
        </div>
        
        <!-- Note -->
        <?php if (!empty($dati['note_pagamento'])): ?>
        <div class="sezione">
            <h2>📝 Note</h2>
            <p style="color: #666; line-height: 1.6;"><?= nl2br(htmlspecialchars($dati['note_pagamento'])) ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Firme -->
        <div class="firma-box">
            <div class="firma">
                <div class="linea"></div>
                <strong>Firma Bibliotecario</strong>
                <div class="ruolo"><?= htmlspecialchars($dati['nome_bibliotecario'] . ' ' . $dati['cognome_bibliotecario']) ?></div>
            </div>
            <div class="firma">
                <div class="linea"></div>
                <strong>Firma Utente</strong>
                <div class="ruolo"><?= htmlspecialchars($dati['nome'] . ' ' . $dati['cognome']) ?></div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="footer">
        <p><strong>Biblioteca Digitale SudoMakers - Sistema di Gestione Bibliotecaria</strong></p>
        <p>Via Example 123, 20100 Milano, IT • Tel: +39 123 456 7890</p>
        <p style="margin-top: 15px;">Documento generato automaticamente il <?= $data_pagamento ?></p>
        <p>Ricevuta N° <?= str_pad($id_pagamento, 6, '0', STR_PAD_LEFT) ?> - Valida a tutti gli effetti di legge</p>
    </div>
</div>

<!-- Barra azioni bottom (non stampata) -->
<div class="actions-bar no-print">
    <button class="btn-print" onclick="window.print()">🖨️ Stampa Ricevuta</button>
    <a href="gestion_multe.php" class="btn-secondary">← Torna a Gestione Multe</a>
</div>

<script>
function scaricaPDF() {
    // Per scaricare come PDF, usa la funzione di stampa e scegli "Salva come PDF"
    alert('Usa la funzione "Stampa" del browser e seleziona "Salva come PDF" come destinazione.');
    window.print();
}
</script>

</body>
</html>
