<?php
require_once __DIR__ . '/email_sender.php';  // o il path corretto al file con la funzione sendTestEmail

// Sostituisci con la tua email reale per il test
$testEmail = 'tuoindirizzo@email.com';
$testName = 'Adam'; // o qualsiasi nome

if (sendTestEmail($testEmail, $testName)) {
    echo "Email di test inviata con successo a $testEmail";
} else {
    echo "Errore nell'invio dell'email di test.";
}
