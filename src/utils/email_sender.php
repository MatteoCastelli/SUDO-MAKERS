<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();


/**
 * Invia email HTML con PHPMailer
 *
 * @param string $to_email Email destinatario
 * @param string $to_name Nome destinatario
 * @param string $subject Oggetto email
 * @param string $html_body Corpo HTML
 * @param bool $is_priority Email ad alta priorità
 * @return bool Successo/Fallimento
 */
function sendEmail(
    string $to_email,
    string $to_name,
    string $subject,
    string $html_body,
    bool $is_priority = false
): bool {

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'] ?? 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['USERNAME'];
        $mail->Password   = $_ENV['PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)($_ENV['SMTP_PORT'] ?? 587);
        $mail->CharSet    = 'UTF-8';

        // Priorità
        if ($is_priority) {
            $mail->Priority = 1; // High priority
            $mail->addCustomHeader('X-Priority', '1');
            $mail->addCustomHeader('Importance', 'High');
        }

        // Mittente
        $mail->setFrom(
            $_ENV['FROM_EMAIL'] ?? 'biblioteca@noreply.com',
            $_ENV['FROM_NAME'] ?? 'Biblioteca Digitale'
        );

        // Destinatario
        $mail->addAddress($to_email, $to_name);

        // Reply-To (opzionale)
        if (!empty($_ENV['REPLY_TO_EMAIL'])) {
            $mail->addReplyTo($_ENV['REPLY_TO_EMAIL'], $_ENV['REPLY_TO_NAME'] ?? 'Biblioteca');
        }

        // Contenuto
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;

        // Versione testo alternativa
        $mail->AltBody = strip_tags($html_body);

        $mail->send();

        // Log successo
        error_log("Email inviata con successo a: $to_email - Oggetto: $subject");

        return true;

    } catch (Exception $e) {
        // Log errore
        error_log("Errore invio email a $to_email: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Invia email di test
 */
function sendTestEmail(string $to_email, string $to_name): bool
{
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0c8a1f; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { padding: 30px; background: #f9f9f9; border-radius: 0 0 8px 8px; }
            .success { color: #0c8a1f; font-size: 48px; text-align: center; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>✓ Test Email Sistema Notifiche</h1>
            </div>
            <div class='content'>
                <div class='success'>✓</div>
                <h2>Ciao {$to_name}!</h2>
                <p>Questa è un'email di test per verificare che il sistema di notifiche funzioni correttamente.</p>
                <p>Se ricevi questo messaggio, significa che tutto è configurato correttamente!</p>
                <p><strong>Data/Ora:</strong> " . date('d/m/Y H:i:s') . "</p>
                <hr style='margin: 20px 0; border: none; border-top: 1px solid #ddd;'>
                <p style='font-size: 12px; color: #888;'>
                    Questa email è stata generata automaticamente dal sistema di notifiche della Biblioteca Digitale.
                </p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendEmail(
        $to_email,
        $to_name,
        'Test Sistema Notifiche - Biblioteca Digitale',
        $html
    );
}