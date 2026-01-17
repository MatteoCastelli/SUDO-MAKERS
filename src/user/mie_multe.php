<?php
/**
 * Le Mie Multe - Visualizzazione multe utente
 * Permette agli utenti di visualizzare le proprie multe attive e lo storico
 */

use Proprietario\SudoMakers\core\Database;

session_start();
require_once __DIR__ . '/../core/Database.php';

if(!isset($_SESSION['id_utente'])) {
    header("Location: ../auth/login.php");
    exit;
}

$pdo = Database::getInstance()->getConnection();
$id_utente = $_SESSION['id_utente'];
$title = "Le Mie Multe";

// Recupera multe attive
$stmt = $pdo->prepare("
    SELECT 
        m.*,
        p.id_prestito,
        l.titolo as libro_titolo,
        l.immagine_copertina_url,
        DATEDIFF(NOW(), m.data_creazione) as giorni_multa_aperta
    FROM multa m
    LEFT JOIN prestito p ON m.id_prestito = p.id_prestito
    LEFT JOIN copia c ON p.id_copia = c.id_copia
    LEFT JOIN libro l ON c.id_libro = l.id_libro
    WHERE m.id_utente = :id_utente
    AND m.stato = 'non_pagata'
    ORDER BY m.data_creazione DESC
");
$stmt->execute(['id_utente' => $id_utente]);
$multe_attive = $stmt->fetchAll();

// Recupera storico multe pagate
$stmt = $pdo->prepare("
    SELECT 
        m.*,
        p.id_prestito,
        l.titolo as libro_titolo,
        pag.metodo_pagamento,
        pag.data_pagamento as data_effettiva_pagamento
    FROM multa m
    LEFT JOIN prestito p ON m.id_prestito = p.id_prestito
    LEFT JOIN copia c ON p.id_copia = c.id_copia
    LEFT JOIN libro l ON c.id_libro = l.id_libro
    LEFT JOIN pagamento pag ON m.id_multa = pag.id_multa
    WHERE m.id_utente = :id_utente
    AND m.stato = 'pagata'
    ORDER BY m.data_pagamento DESC
    LIMIT 20
");
$stmt->execute(['id_utente' => $id_utente]);
$multe_pagate = $stmt->fetchAll();

// Calcola totale da pagare
$totale_da_pagare = array_sum(array_column($multe_attive, 'importo'));

// Verifica se utente è bloccato
$stmt = $pdo->prepare("SELECT prestiti_bloccati, motivo_blocco FROM utente WHERE id_utente = :id");
$stmt->execute(['id' => $id_utente]);
$stato_utente = $stmt->fetch();
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="../../public/assets/css/privateAreaStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/dashboardStyle.css">
    <link rel="stylesheet" href="../../public/assets/css/mie_multe.css">

</head>
<body>
<?php require_once __DIR__ . '/../utils/navigation.php'; ?>

<div class="multe-container">
    <div class="multe-header">
        <div>
            <h1>Le Mie Multe</h1>
            <p style="color: #888;">Visualizza e gestisci le tue multe</p>
        </div>

        <div class="totale-multe">
            <div class="label">Totale da Pagare</div>
            <div class="importo">€<?= number_format($totale_da_pagare, 2, ',', '.') ?></div>
        </div>
    </div>

    <?php if($stato_utente['prestiti_bloccati']): ?>
        <div class="avviso-blocco">
            <h3 style="margin-top: 0; color: #b30000;">Account Bloccato</h3>
            <p style="margin: 10px 0;">
                I tuoi prestiti sono temporaneamente bloccati.
            </p>
            <p style="margin: 5px 0; color: #888;">
                <strong>Motivo:</strong> <?= htmlspecialchars($stato_utente['motivo_blocco'] ?? 'Multe non pagate') ?>
            </p>
            <p style="margin: 15px 0 0 0; font-size: 14px;">
                <strong>Come sbloccare:</strong> Restituisci i libri in ritardo e contatta la biblioteca per saldare le multe.
            </p>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab active" onclick="switchTab('attive')">
            Multe Attive (<?= count($multe_attive) ?>)
        </button>
        <button class="tab" onclick="switchTab('storico')">
            Storico (<?= count($multe_pagate) ?>)
        </button>
    </div>

    <!-- Tab Multe Attive -->
    <div id="tab-attive" class="tab-content active">
        <?php if(empty($multe_attive)): ?>
            <div style="text-align: center; padding: 60px 20px;">
                <h2 style="color: #0c8a1f; margin: 0;">Nessuna Multa Attiva!</h2>
                <p style="color: #888; margin-top: 10px;">
                    Ottimo lavoro! Continua a restituire i libri in tempo.
                </p>
            </div>
        <?php else: ?>
            <?php foreach($multe_attive as $multa):
                $classe_gravita = '';
                if($multa['importo'] > 10 || $multa['giorni_ritardo'] > 14) {
                    $classe_gravita = 'grave';
                }
                ?>
                <div class="multa-card <?= $classe_gravita ?>">
                    <?php if($multa['immagine_copertina_url']): ?>
                        <div class="multa-cover">
                            <img src="<?= htmlspecialchars($multa['immagine_copertina_url']) ?>"
                                 alt="Copertina">
                        </div>
                    <?php else: ?>
                        <div class="multa-cover">
                            <span style="font-size: 32px;">📖</span>
                        </div>
                    <?php endif; ?>

                    <div class="multa-details">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <h3 style="margin: 0 0 10px 0; color: #ebebed;">
                                    <?= htmlspecialchars($multa['causale']) ?>
                                </h3>

                                <?php if($multa['libro_titolo']): ?>
                                    <div style="color: #888; margin-bottom: 10px;">
                                        <?= htmlspecialchars($multa['libro_titolo']) ?>
                                    </div>
                                <?php endif; ?>

                                <div style="display: flex; gap: 20px; margin-top: 15px;">
                                    <div>
                                        <div style="color: #888; font-size: 12px;">Tipo</div>
                                        <strong style="text-transform: capitalize;">
                                            <?= htmlspecialchars($multa['tipo_multa']) ?>
                                        </strong>
                                    </div>

                                    <?php if($multa['giorni_ritardo'] > 0): ?>
                                        <div>
                                            <div style="color: #888; font-size: 12px;">Giorni Ritardo</div>
                                            <strong style="color: #ff9800;">
                                                <?= $multa['giorni_ritardo'] ?> giorni
                                            </strong>
                                        </div>
                                    <?php endif; ?>

                                    <div>
                                        <div style="color: #888; font-size: 12px;">Data Multa</div>
                                        <strong><?= date('d/m/Y', strtotime($multa['data_creazione'])) ?></strong>
                                    </div>
                                </div>
                            </div>

                            <div class="multa-importo-grande">
                                €<?= number_format($multa['importo'], 2, ',', '.') ?>
                            </div>
                        </div>

                        <?php if($multa['note']): ?>
                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #444; color: #888; font-size: 13px;">
                                <strong>Note:</strong> <?= nl2br(htmlspecialchars($multa['note'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="info-pagamento">
                <h3 style="margin-top: 0;">Come Pagare</h3>
                <p style="margin: 10px 0; color: #ebebed;">
                    Per saldare le tue multe, recati in biblioteca con un documento di identità.
                </p>
                <p style="margin: 5px 0; color: #888;">
                    <strong>Metodi accettati:</strong> Contanti, Carta di credito/debito, Bonifico bancario
                </p>
                <p style="margin: 15px 0 0 0; color: #888; font-size: 14px;">
                    <strong>Suggerimento:</strong> Restituisci i libri in ritardo il prima possibile per evitare l'accumulo di multe (€0,50/giorno dopo 3 giorni di tolleranza).
                </p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tab Storico -->
    <div id="tab-storico" class="tab-content">
        <?php if(empty($multe_pagate)): ?>
            <div style="text-align: center; padding: 60px 20px; color: #888;">
                <p>Nessuna multa pagata nello storico</p>
            </div>
        <?php else: ?>
            <?php foreach($multe_pagate as $multa): ?>
                <div class="multa-card pagata">
                    <div class="multa-details" style="width: 100%;">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <h3 style="margin: 0 0 10px 0; color: #ebebed;">
                                    <?= htmlspecialchars($multa['causale']) ?>
                                </h3>

                                <?php if($multa['libro_titolo']): ?>
                                    <div style="color: #888; margin-bottom: 10px;">
                                        <?= htmlspecialchars($multa['libro_titolo']) ?>
                                    </div>
                                <?php endif; ?>

                                <div style="display: flex; gap: 20px; margin-top: 15px;">
                                    <div>
                                        <div style="color: #888; font-size: 12px;">Tipo</div>
                                        <strong style="text-transform: capitalize;">
                                            <?= htmlspecialchars($multa['tipo_multa']) ?>
                                        </strong>
                                    </div>

                                    <div>
                                        <div style="color: #888; font-size: 12px;">Data Pagamento</div>
                                        <strong style="color: #0c8a1f;">
                                            <?= date('d/m/Y', strtotime($multa['data_effettiva_pagamento'] ?? $multa['data_pagamento'])) ?>
                                        </strong>
                                    </div>

                                    <?php if($multa['metodo_pagamento']): ?>
                                        <div>
                                            <div style="color: #888; font-size: 12px;">Metodo</div>
                                            <strong style="text-transform: capitalize;">
                                                <?= htmlspecialchars($multa['metodo_pagamento']) ?>
                                            </strong>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div style="text-align: right;">
                                <div style="font-size: 24px; font-weight: bold; color: #0c8a1f;">
                                    €<?= number_format($multa['importo'], 2, ',', '.') ?>
                                </div>
                                <div style="color: #0c8a1f; font-size: 14px; margin-top: 5px;">
                                    ✓ Pagata
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <a href="le_mie_prenotazioni.php" class="btn-secondary" style="display: block; text-align: center; margin-top: 30px; padding: 15px;">
        ← Torna ai Miei Libri
    </a>
</div>

<script>
    function switchTab(tabName) {
        // Nascondi tutti i tab content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });

        // Disattiva tutti i tab button
        document.querySelectorAll('.tab').forEach(button => {
            button.classList.remove('active');
        });

        // Attiva il tab selezionato
        document.getElementById('tab-' + tabName).classList.add('active');
        event.currentTarget.classList.add('active');
    }
</script>

</body>
</html>