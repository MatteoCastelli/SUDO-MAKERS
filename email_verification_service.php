<?php

require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
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

        header('Location: verifica_email.php');
    } catch (Exception) {
        echo "Mailer Error: {$mail->ErrorInfo}";
    }
}