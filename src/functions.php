<?php
require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

function generateEmailVerificationToken(int $bytes = 32, string $ttlSpec = "+24 hours"): array
{
    $raw = random_bytes($bytes);
    $token = bin2hex($raw);
    $expiresAt = new DateTimeImmutable($ttlSpec);
    return [$token, $expiresAt];
}

function sendVerificationEmail(string $email, string $username, string $url, string $token ): void{

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = "sandbox.smtp.mailtrap.io"; // o live.smtp.mailtrap.io per stream reali
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV["USERNAME"]; // username fornito da Mailtrap
        $mail->Password   = $_ENV["PASSWORD"]; // password fornita da Mailtrap
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // o 'ssl' su porta 465
        $mail->Port       = 587; // 25, 465, 587 o 2525 sono possibili

        // Mittente e destinatario
        $mail->setFrom('registrazione@noreply.com', 'Registrazione');
        $mail->addAddress($email, $username);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Registration Email Verification";
        $mail->Body = "<h1>Ciao $username</h1> <p>Clicca il link per verificare la tua identità</p> <a href='$url'>Clicca qui</a>";
        $mail->send();

        header('Location: index.php?registered=1');
    } catch (Exception) {
        echo "Mailer Error: {$mail->ErrorInfo}";
    }
}

function sendLoginMail($toAddress, $toName) {

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = "sandbox.smtp.mailtrap.io"; // o live.smtp.mailtrap.io per stream reali
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV["USERNAME"]; // username fornito da Mailtrap
        $mail->Password   = $_ENV["PASSWORD"]; // password fornita da Mailtrap
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // o 'ssl' su porta 465
        $mail->Port       = 587; // 25, 465, 587 o 2525 sono possibili

        // Mittente e destinatario
        $mail->setFrom('biblioteca@noreply.com', 'Biblioteca Digitale');
        $mail->addAddress($toAddress, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "New access to Biblioteca";
        $mail->Body    = "<h1>Hello $toName</h1><p>New access to yuor account.</p>";
        $mail->AltBody = "New access to yuor account.";

        $mail->send();
    } catch (Exception $e) {
        echo "Mailer Error: {$mail->ErrorInfo}";
    }
}

function getCodiceCatastale($comune)
{
    $csvPath = __DIR__ . '/../assets/data/comuni.csv';
    
    if (!file_exists($csvPath)) {
        return false;
    }
    
    $handle = fopen($csvPath, 'r');
    if (!$handle) {
        return false;
    }
    
    // Salta la riga di intestazione
    fgetcsv($handle);
    
    $comuneCercato = strtolower(trim($comune));
    $risultati = [];
    
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) >= 2) {
            $denominazione = trim($row[0]);
            $codiceCatastale = trim($row[1]);
            
            if (strtolower($denominazione) === $comuneCercato) {
                $risultati[] = $codiceCatastale;
            }
        }
    }
    
    fclose($handle);
    
    // Restituisce il codice solo se c'è un match esatto e unico
    if (count($risultati) === 1) {
        return $risultati[0];
    }
    
    return false;
}

function sendAccountSuspensionEmail($email, $nome) {

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = "sandbox.smtp.mailtrap.io";
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV["USERNAME"];
        $mail->Password   = $_ENV["PASSWORD"];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Mittente e destinatario
        $mail->setFrom('sicurezza@biblioteca.com', 'Biblioteca Digitale - Sicurezza');
        $mail->addAddress($email, $nome);

        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Account temporaneamente sospeso - Biblioteca Digitale';

        $durata = 30;
        $data_ora = date('d/m/Y H:i:s');

        $mail->Body = "
                <h1>Account Sospeso</h1>
                <p>
                Ciao $nome,<br>
                Il tuo account è stato temporaneamente sospeso per motivi di sicurezza a causa di troppi tentativi di accesso falliti.<br>
                Data sospensione: $data_ora<br>
                Durata sospensione: $durata minuti
                </p>
        ";

        $mail->AltBody = "Ciao $nome, il tuo account è stato temporaneamente sospeso per troppi tentativi di accesso falliti. Attendi $durata minuti prima di riprovare.";

        $mail->send();
    } catch (Exception) {
        echo "Mailer Error: {$mail->ErrorInfo}";
    }
}


// Aggiungi queste funzioni al tuo functions.php (dopo le funzioni esistenti)

function getThreeCFChars($string, $isNome = false)
{
    $s = strtoupper($string);
    $s = preg_replace('/[^A-Z]/', '', $s);

    $vocali = ['A', 'E', 'I', 'O', 'U'];

    $consonanti = array_values(array_filter(str_split($s), fn($c) => !in_array($c, $vocali)));
    $vocaliSolo = array_values(array_filter(str_split($s), fn($c) => in_array($c, $vocali)));

    // Regola speciale per il nome: se ci sono più di 3 consonanti, prendi 1a, 3a e 4a
    if ($isNome && count($consonanti) > 3) {
        $consonanti = [$consonanti[0], $consonanti[2], $consonanti[3]];
    }

    $result = array_merge($consonanti, $vocaliSolo);

    return str_pad(implode('', array_slice($result, 0, 3)), 3, 'X');
}

function calcolaCarattereControllo($cf15)
{
    $cf15 = strtoupper($cf15);

    $valoriPari = [
        '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4,
        '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9,
        'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4,
        'F' => 5, 'G' => 6, 'H' => 7, 'I' => 8, 'J' => 9,
        'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14,
        'P' => 15, 'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19,
        'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23, 'Y' => 24, 'Z' => 25
    ];

    $valoriDispari = [
        '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9,
        '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
        'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9,
        'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
        'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11,
        'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
        'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23
    ];

    $somma = 0;

    for ($i = 0; $i < 15; $i++) {
        $char = $cf15[$i];

        if ($i % 2 === 0) {
            $somma += $valoriDispari[$char];
        } else {
            $somma += $valoriPari[$char];
        }
    }

    $resto = $somma % 26;
    $caratteriControllo = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";

    return $caratteriControllo[$resto];
}

function verificaChecksumCF($cf)
{
    $cf = strtoupper($cf);
    if (!preg_match('/^[A-Z0-9]{16}$/', $cf)) return false;

    $cf15 = substr($cf, 0, 15);
    $carattereAtteso = calcolaCarattereControllo($cf15);

    return $cf[15] === $carattereAtteso;
}

function verificaCFvsDati($cf, $nome, $cognome, $data, $sesso, $comune_catastale)
{
    $cf = strtoupper($cf);

    // 3 lettere cognome
    if (substr($cf, 0, 3) !== getThreeCFChars($cognome)) return false;

    // 3 lettere nome
    if (substr($cf, 3, 3) !== getThreeCFChars($nome, true)) return false;

    // 2 cifre anno
    if (substr($cf, 6, 2) !== substr($data, 2, 2)) return false;

    // lettera mese
    $mesi = "ABCDEHLMPRST";
    if ($cf[8] !== $mesi[(int)substr($data, 5, 2) - 1]) return false;

    // giorno (aggiungi 40 se femmina)
    $giornoCF = (int)substr($cf, 9, 2);
    $giornoReal = (int)substr($data, 8, 2);
    if ($sesso == "F") $giornoReal += 40;
    if ($giornoCF !== $giornoReal) return false;

    // codice catastale
    if (substr($cf, 11, 4) !== $comune_catastale) return false;

    return true;
}

// Sostituisci la funzione vuota checkAndGenerateCF con questa:
function checkAndGenerateCF($nome, $cognome, $data_nascita, $sesso, $comune_nascita)
{
    $codice_catastale = getCodiceCatastale($comune_nascita);
    if (!$codice_catastale) {
        return false;
    }

    $cf = '';

    // 1. Cognome (3 caratteri)
    $cf .= getThreeCFChars($cognome);

    // 2. Nome (3 caratteri)
    $cf .= getThreeCFChars($nome, true);

    // 3. Anno (2 cifre)
    $cf .= substr($data_nascita, 2, 2);

    // 4. Mese (1 lettera)
    $mesi = "ABCDEHLMPRST";
    $mese = (int)substr($data_nascita, 5, 2);
    $cf .= $mesi[$mese - 1];

    // 5. Giorno (2 cifre, +40 per le femmine)
    $giorno = (int)substr($data_nascita, 8, 2);
    if ($sesso === 'F') {
        $giorno += 40;
    }
    $cf .= str_pad($giorno, 2, '0', STR_PAD_LEFT);

    // 6. Codice catastale (4 caratteri)
    $cf .= $codice_catastale;

    // 7. Carattere di controllo
    $cf .= calcolaCarattereControllo($cf);

    return strtoupper($cf);
}
