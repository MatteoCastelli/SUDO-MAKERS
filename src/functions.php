<?php
use MLocati\ComuniItaliani\Finder;
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
        $mail->setFrom('calcetto@noreply.com', 'Calcetto Website');
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

function checkAndGenerateCF($cognome, $data_nascita, $sesso, $codice_catastale)
{

    //return $cf_calcolato;
}

function getCodiceCatastale($comune)
{
    $finder = new Finder();
    $municipalities = $finder->findMunicipalitiesByName($comune, false);
    if (count($municipalities) != 1) {
        return false;
    }
    $comune = reset($municipalities);
    return $comune->getCadastralCode();
}