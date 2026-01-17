<?php
/**
 * TRIGGER MANUALE CRON JOBS - Solo per sviluppo/test
 * 
 * Questo file permette di eseguire manualmente i cron jobs da browser
 * IMPORTANTE: Rimuovere o proteggere in produzione!
 */

// Timeout lungo per permettere l'esecuzione
set_time_limit(300);
ini_set('max_execution_time', 300);

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trigger Cron Jobs - Sviluppo</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #1a1a1a;
            color: #ebebed;
        }
        h1 {
            color: #0c8a1f;
            border-bottom: 3px solid #0c8a1f;
            padding-bottom: 10px;
        }
        .warning {
            background: rgba(255, 152, 0, 0.1);
            border-left: 4px solid #ff9800;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .cron-section {
            background: #2d2d2d;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .btn {
            background: #0c8a1f;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin: 5px;
        }
        .btn:hover {
            background: #0a7018;
        }
        .output {
            background: #1a1a1a;
            border: 1px solid #444;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .success {
            color: #0c8a1f;
        }
        .error {
            color: #b30000;
        }
    </style>
</head>
<body>
    <h1>⚙️ Trigger Cron Jobs - Modalità Sviluppo</h1>

    <div class="warning">
        <strong>⚠️ ATTENZIONE:</strong> Questo strumento è solo per sviluppo/test.
        In produzione, configura i cron jobs reali nel sistema operativo.
        <br><br>
        <strong>Nota:</strong> Questo file dovrebbe essere rimosso o protetto con autenticazione in produzione.
    </div>

    <div class="cron-section">
        <h2>Cron Notifiche (Scadenze, Ritardi, Promemoria)</h2>
        <p>Gestisce l'invio automatico di notifiche email per scadenze prestiti, ritardi e prenotazioni.</p>
        <button class="btn" onclick="runCron('notifiche')">▶️ Esegui Cron Notifiche</button>
        <div id="output-notifiche" class="output" style="display: none;"></div>
    </div>

    <div class="cron-section">
        <h2>Cron Prenotazioni (Gestione automatica)</h2>
        <p>Gestisce annullamento prenotazioni scadute e notifiche disponibilità.</p>
        <button class="btn" onclick="runCron('prenotazioni')">▶️ Esegui Cron Prenotazioni</button>
        <div id="output-prenotazioni" class="output" style="display: none;"></div>
    </div>

    <div class="cron-section">
        <h2>Cron Trends (Aggiornamento statistiche)</h2>
        <p>Aggiorna le statistiche di tendenza e popolarità dei libri.</p>
        <button class="btn" onclick="runCron('trends')">▶️ Esegui Cron Trends</button>
        <div id="output-trends" class="output" style="display: none;"></div>
    </div>

    <div class="cron-section">
        <h2>Cleanup Token (Pulizia token scaduti)</h2>
        <p>Rimuove token di verifica e reset password scaduti dal database.</p>
        <button class="btn" onclick="runCron('cleanup')">▶️ Esegui Cleanup Token</button>
        <div id="output-cleanup" class="output" style="display: none;"></div>
    </div>

    <div class="cron-section">
        <h2>▶️ Esegui Tutti</h2>
        <button class="btn" onclick="runAllCrons()" style="background: #ff9800;">▶️ Esegui Tutti i Cron Jobs</button>
        <p style="font-size: 12px; color: #888; margin-top: 10px;">
            Nota: L'esecuzione di tutti i cron potrebbe richiedere alcuni secondi
        </p>
    </div>

    <script>
        function runCron(type) {
            const outputDiv = document.getElementById('output-' + type);
            outputDiv.style.display = 'block';
            outputDiv.innerHTML = '<span style="color: #888;">Esecuzione in corso...</span>';

            fetch('run_cron.php?type=' + type)
                .then(res => res.text())
                .then(data => {
                    outputDiv.innerHTML = data;
                })
                .catch(err => {
                    outputDiv.innerHTML = '<span class="error">Errore: ' + err + '</span>';
                });
        }

        function runAllCrons() {
            runCron('notifiche');
            setTimeout(() => runCron('prenotazioni'), 1000);
            setTimeout(() => runCron('trends'), 2000);
            setTimeout(() => runCron('cleanup'), 3000);
        }

        // Log viewer
        function viewLog(type) {
            window.open('view_log.php?type=' + type, '_blank', 'width=800,height=600');
        }
    </script>
</body>
</html>
