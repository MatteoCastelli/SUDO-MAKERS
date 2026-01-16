<?php

namespace Proprietario\SudoMakers\core;

use PDO;
use Exception;

class NotificationEngine
{
    private $pdo;

    // Tipi di notifica
    const TYPE_PRESTITO_CONFERMA = 'prestito_conferma';
    const TYPE_SCADENZA_PROMEMORIA = 'scadenza_promemoria';
    const TYPE_RITARDO_LIEVE = 'ritardo_lieve';
    const TYPE_RITARDO_MEDIO = 'ritardo_medio';
    const TYPE_RITARDO_GRAVE = 'ritardo_grave';
    const TYPE_RITARDO_CRITICO = 'ritardo_critico';
    const TYPE_PRENOTAZIONE_DISPONIBILE = 'prenotazione_disponibile';
    const TYPE_PRENOTAZIONE_PROMEMORIA = 'prenotazione_promemoria';
    const TYPE_PRENOTAZIONE_SCADUTA = 'prenotazione_scaduta';

    // Priorità
    const PRIORITY_LOW = 'bassa';
    const PRIORITY_MEDIUM = 'media';
    const PRIORITY_HIGH = 'alta';
    const PRIORITY_URGENT = 'urgente';

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Crea una notifica nel database e la invia via email se necessario
     */
    public function creaNotifica(
        int $id_utente,
        string $tipo,
        string $titolo,
        string $messaggio,
        string $priorita = self::PRIORITY_MEDIUM,
        bool $invia_email = true,
        array $dati_extra = []
    ) {
        try {
            // Verifica preferenze utente
            $preferenze = $this->getPreferenzeNotifiche($id_utente);

            // Se l'utente ha disattivato questo tipo di notifica, esci
            if (!$this->shouldSendNotification($preferenze, $tipo)) {
                return false;
            }

            // Verifica quiet hours
            if ($this->isQuietHours($preferenze) && $priorita !== self::PRIORITY_URGENT) {
                // Ritarda l'invio dopo le quiet hours
                $dati_extra['ritardata'] = true;
            }

            // Inserisci notifica in-app
            $stmt = $this->pdo->prepare("
                INSERT INTO notifica 
                (id_utente, tipo, titolo, messaggio, priorita, dati_extra)
                VALUES (:id_utente, :tipo, :titolo, :messaggio, :priorita, :dati_extra)
            ");

            $stmt->execute([
                'id_utente' => $id_utente,
                'tipo' => $tipo,
                'titolo' => $titolo,
                'messaggio' => $messaggio,
                'priorita' => $priorita,
                'dati_extra' => json_encode($dati_extra)
            ]);

            $id_notifica = $this->pdo->lastInsertId();

            // Invia email se richiesto e utente ha email attive
            if ($invia_email && $preferenze['email_attive']) {
                if (!isset($dati_extra['ritardata']) || !$dati_extra['ritardata']) {
                    $this->inviaEmailNotifica($id_utente, $tipo, $titolo, $messaggio, $dati_extra);
                }
            }

            return $id_notifica;

        } catch (Exception $e) {
            error_log("Errore creazione notifica: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Ottieni preferenze notifiche utente
     */
    private function getPreferenzeNotifiche(int $id_utente): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM preferenze_notifiche 
            WHERE id_utente = :id_utente
        ");
        $stmt->execute(['id_utente' => $id_utente]);
        $pref = $stmt->fetch(PDO::FETCH_ASSOC);

        // Default se non esistono preferenze
        if (!$pref) {
            return [
                'email_attive' => true,
                'promemoria_scadenza' => true,
                'notifiche_ritardo' => true,
                'notifiche_prenotazioni' => true,
                'quiet_hours_inizio' => '22:00:00',
                'quiet_hours_fine' => '08:00:00',
                'quiet_hours_attive' => false
            ];
        }

        return $pref;
    }

    /**
     * Verifica se deve inviare la notifica in base alle preferenze
     */
    private function shouldSendNotification(array $preferenze, string $tipo): bool
    {
        // Controlla tipo specifico
        if (strpos($tipo, 'scadenza') !== false && !$preferenze['promemoria_scadenza']) {
            return false;
        }

        if (strpos($tipo, 'ritardo') !== false && !$preferenze['notifiche_ritardo']) {
            return false;
        }

        if (strpos($tipo, 'prenotazione') !== false && !$preferenze['notifiche_prenotazioni']) {
            return false;
        }

        return true;
    }

    /**
     * Verifica se siamo in quiet hours
     */
    private function isQuietHours(array $preferenze): bool
    {
        if (!$preferenze['quiet_hours_attive']) {
            return false;
        }

        $ora_corrente = date('H:i:s');
        $inizio = $preferenze['quiet_hours_inizio'];
        $fine = $preferenze['quiet_hours_fine'];

        // Caso normale (es. 22:00 - 08:00)
        if ($inizio > $fine) {
            return ($ora_corrente >= $inizio || $ora_corrente <= $fine);
        }

        // Caso inverso (es. 08:00 - 22:00)
        return ($ora_corrente >= $inizio && $ora_corrente <= $fine);
    }

    /**
     * Invia email notifica
     */
    private function inviaEmailNotifica(
        int $id_utente,
        string $tipo,
        string $titolo,
        string $messaggio,
        array $dati_extra
    ) {
        // Recupera dati utente
        $stmt = $this->pdo->prepare("SELECT nome, email FROM utente WHERE id_utente = :id");
        $stmt->execute(['id' => $id_utente]);
        $utente = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$utente || !$utente['email']) {
            return false;
        }

        // Seleziona template in base al tipo
        switch ($tipo) {
            case self::TYPE_PRESTITO_CONFERMA:
                return $this->sendPrestitoConfermaEmail($utente, $dati_extra);

            case self::TYPE_SCADENZA_PROMEMORIA:
                return $this->sendScadenzaPromemoriaEmail($utente, $dati_extra);

            case self::TYPE_RITARDO_LIEVE:
            case self::TYPE_RITARDO_MEDIO:
            case self::TYPE_RITARDO_GRAVE:
            case self::TYPE_RITARDO_CRITICO:
                return $this->sendRitardoEmail($utente, $tipo, $dati_extra);

            case self::TYPE_PRENOTAZIONE_DISPONIBILE:
                return $this->sendPrenotazioneDisponibileEmail($utente, $dati_extra);

            case self::TYPE_PRENOTAZIONE_PROMEMORIA:
                return $this->sendPrenotazionePromemoriaEmail($utente, $dati_extra);

            default:
                return $this->sendGenericEmail($utente, $titolo, $messaggio);
        }
    }

    /**
     * Email conferma prestito con countdown e dettagli
     */
    private function sendPrestitoConfermaEmail(array $utente, array $dati): bool
    {
        require_once __DIR__ . '/../utils/email_sender.php';

        $titolo_libro = $dati['titolo_libro'] ?? 'Libro';
        $data_scadenza = $dati['data_scadenza'] ?? date('Y-m-d');
        $id_prestito = $dati['id_prestito'] ?? 0;
        $immagine_copertina = $dati['immagine_copertina'] ?? '';

        $giorni_rimasti = floor((strtotime($data_scadenza) - time()) / 86400);

        $html = $this->loadEmailTemplate('prestito_conferma', [
            'nome' => $utente['nome'],
            'titolo_libro' => $titolo_libro,
            'data_scadenza' => date('d/m/Y', strtotime($data_scadenza)),
            'giorni_rimasti' => $giorni_rimasti,
            'id_prestito' => $id_prestito,
            'immagine_copertina' => $immagine_copertina,
            'link_dashboard' => 'http://localhost/SudoMakers/src/user/le_mie_prenotazioni.php'
        ]);

        return sendEmail(
            $utente['email'],
            $utente['nome'],
            'Prestito confermato - ' . $titolo_libro,
            $html
        );
    }

    /**
     * Email promemoria scadenza (3 giorni prima)
     */
    private function sendScadenzaPromemoriaEmail(array $utente, array $dati): bool
    {
        require_once __DIR__ . '/../utils/email_sender.php';

        $titolo_libro = $dati['titolo_libro'] ?? 'Libro';
        $data_scadenza = $dati['data_scadenza'] ?? date('Y-m-d');

        $giorni_rimasti = floor((strtotime($data_scadenza) - time()) / 86400);

        $html = $this->loadEmailTemplate('scadenza_promemoria', [
            'nome' => $utente['nome'],
            'titolo_libro' => $titolo_libro,
            'data_scadenza' => date('d/m/Y', strtotime($data_scadenza)),
            'giorni_rimasti' => $giorni_rimasti,
            'link_dashboard' => 'http://localhost/SudoMakers/src/user/le_mie_prenotazioni.php',
            'link_disattiva_promemoria' => 'http://localhost/SudoMakers/src/user/preferenze_notifiche.php'
        ]);

        return sendEmail(
            $utente['email'],
            $utente['nome'],
            'Promemoria: Restituzione libro tra ' . $giorni_rimasti . ' giorni',
            $html
        );
    }

    /**
     * Email ritardo con escalation in base al livello
     */
    private function sendRitardoEmail(array $utente, string $tipo, array $dati): bool
    {
        require_once __DIR__ . '/../utils/email_sender.php';

        $titolo_libro = $dati['titolo_libro'] ?? 'Libro';
        $giorni_ritardo = $dati['giorni_ritardo'] ?? 0;
        $multa_attuale = $dati['multa_attuale'] ?? 0;

        $template_data = [
            'nome' => $utente['nome'],
            'titolo_libro' => $titolo_libro,
            'giorni_ritardo' => $giorni_ritardo,
            'multa_attuale' => number_format($multa_attuale, 2, ',', '.'),
            'link_dashboard' => 'http://localhost/SudoMakers/src/user/le_mie_prenotazioni.php'
        ];

        switch ($tipo) {
            case self::TYPE_RITARDO_LIEVE: // 1-3 giorni
                $subject = 'Libro in ritardo - ' . $titolo_libro;
                $template = 'ritardo_lieve';
                $template_data['messaggio_principale'] = 'Il tuo prestito è scaduto ' . $giorni_ritardo . ' giorni fa.';
                $template_data['azione_richiesta'] = 'I nuovi prestiti sono temporaneamente bloccati fino alla restituzione.';
                break;

            case self::TYPE_RITARDO_MEDIO: // 4-7 giorni
                $subject = 'Ritardo significativo - Multa in accumulo';
                $template = 'ritardo_medio';
                $template_data['messaggio_principale'] = 'Il prestito è in ritardo da ' . $giorni_ritardo . ' giorni.';
                $template_data['azione_richiesta'] = 'Multa accumulata: €' . $template_data['multa_attuale'] . ' (€0,50/giorno)';
                break;

            case self::TYPE_RITARDO_GRAVE: // 8-14 giorni
                $subject = 'Ritardo grave - Segnalazione al bibliotecario';
                $template = 'ritardo_grave';
                $template_data['messaggio_principale'] = 'Il prestito è in grave ritardo (' . $giorni_ritardo . ' giorni).';
                $template_data['azione_richiesta'] = 'Il tuo account è stato segnalato al bibliotecario per follow-up.';
                break;

            case self::TYPE_RITARDO_CRITICO: // >14 giorni
                $subject = 'Comunicazione formale - Ritardo critico';
                $template = 'ritardo_critico';
                $template_data['messaggio_principale'] = 'Il prestito è in ritardo da oltre 14 giorni.';
                $template_data['azione_richiesta'] = 'È necessario un intervento immediato. Contatta la biblioteca.';
                $template_data['link_pdf'] = 'http://localhost/SudoMakers/src/utils/genera_pdf_comunicazione.php?id=' . $dati['id_prestito'];
                break;

            default:
                return false;
        }

        $html = $this->loadEmailTemplate($template, $template_data);

        return sendEmail(
            $utente['email'],
            $utente['nome'],
            $subject,
            $html
        );
    }

    /**
     * Email libro disponibile per prenotazione (urgente)
     */
    private function sendPrenotazioneDisponibileEmail(array $utente, array $dati): bool
    {
        require_once __DIR__ . '/../utils/email_sender.php';

        $titolo_libro = $dati['titolo_libro'] ?? 'Libro';
        $data_scadenza_ritiro = $dati['data_scadenza_ritiro'] ?? date('Y-m-d H:i:s', strtotime('+48 hours'));

        $ore_rimaste = floor((strtotime($data_scadenza_ritiro) - time()) / 3600);

        $html = $this->loadEmailTemplate('prenotazione_disponibile', [
            'nome' => $utente['nome'],
            'titolo_libro' => $titolo_libro,
            'data_scadenza' => date('d/m/Y H:i', strtotime($data_scadenza_ritiro)),
            'ore_rimaste' => $ore_rimaste,
            'link_dashboard' => 'http://localhost/SudoMakers/src/user/le_mie_prenotazioni.php'
        ]);

        return sendEmail(
            $utente['email'],
            $utente['nome'],
            'Il tuo libro è disponibile! - ' . $titolo_libro,
            $html,
            true // Priority email
        );
    }

    /**
     * Carica template email
     */
    private function loadEmailTemplate(string $template_name, array $data): string
    {
        $template_path = __DIR__ . "/../templates/email/{$template_name}.html";

        if (!file_exists($template_path)) {
            // Fallback: genera HTML base
            return $this->generateBasicEmailHTML($data);
        }

        $html = file_get_contents($template_path);

        // Sostituisci variabili
        foreach ($data as $key => $value) {
            $html = str_replace('{{' . $key . '}}', $value, $html);
        }

        return $html;
    }

    /**
     * Genera HTML base per email
     */
    private function generateBasicEmailHTML(array $data): string
    {
        $nome = $data['nome'] ?? 'Utente';
        $messaggio = $data['messaggio_principale'] ?? '';
        $azione = $data['azione_richiesta'] ?? '';
        $link = $data['link_dashboard'] ?? '';

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0c8a1f; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .button { display: inline-block; padding: 12px 24px; background: #0c8a1f; color: white; text-decoration: none; border-radius: 5px; margin: 15px 0; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #888; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Biblioteca Digitale</h1>
                </div>
                <div class='content'>
                    <p>Ciao <strong>{$nome}</strong>,</p>
                    <p>{$messaggio}</p>
                    <p><strong>{$azione}</strong></p>
                    <a href='{$link}' class='button'>Vai alla Dashboard</a>
                </div>
                <div class='footer'>
                    <p>Biblioteca Digitale - Sistema automatico di notifiche</p>
                    <p><a href='http://localhost/SudoMakers/src/user/preferenze_notifiche.php'>Gestisci preferenze</a> | 
                       <a href='http://localhost/SudoMakers/src/user/disattiva_notifiche.php'>Disattiva email</a></p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Processa notifiche ritardate (quiet hours finite)
     */
    public function processaNotificheRitardate()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT n.*, u.email, u.nome,
                       pn.quiet_hours_fine, pn.quiet_hours_attive
                FROM notifica n
                JOIN utente u ON n.id_utente = u.id_utente
                LEFT JOIN preferenze_notifiche pn ON u.id_utente = pn.id_utente
                WHERE n.data_invio_email IS NULL
                AND JSON_EXTRACT(n.dati_extra, '$.ritardata') = true
            ");

            $notifiche = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($notifiche as $notifica) {
                // Verifica se quiet hours sono finite
                if (!$this->isQuietHours([
                    'quiet_hours_attive' => $notifica['quiet_hours_attive'],
                    'quiet_hours_inizio' => '22:00:00',
                    'quiet_hours_fine' => $notifica['quiet_hours_fine']
                ])) {
                    // Invia email ora
                    $dati_extra = json_decode($notifica['dati_extra'], true);
                    unset($dati_extra['ritardata']);

                    $this->inviaEmailNotifica(
                        $notifica['id_utente'],
                        $notifica['tipo'],
                        $notifica['titolo'],
                        $notifica['messaggio'],
                        $dati_extra
                    );

                    // Aggiorna record
                    $update = $this->pdo->prepare("
                        UPDATE notifica 
                        SET data_invio_email = NOW(),
                            dati_extra = :dati_extra
                        WHERE id_notifica = :id
                    ");
                    $update->execute([
                        'dati_extra' => json_encode($dati_extra),
                        'id' => $notifica['id_notifica']
                    ]);
                }
            }

        } catch (Exception $e) {
            error_log("Errore processing notifiche ritardate: " . $e->getMessage());
        }
    }
}