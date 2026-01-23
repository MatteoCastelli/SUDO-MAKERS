# Sistema di Gestione Biblioteca Scolastica

Sistema web completo per la gestione di una biblioteca scolastica con funzionalità avanzate di catalogazione, prestito libri, prenotazioni e gamification.

## Autori

- Christian Tibaldo (Scrum Master)
- Matteo Castelli (Vice Scrum Master)
- Giovanni Montagna
- Alex Gogonea
- Adam Moustakim

## Caratteristiche Principali

### Per gli Utenti
- Ricerca e consultazione del catalogo libri con filtri avanzati
- Sistema di prenotazione e gestione prestiti personali
- Raccomandazioni personalizzate basate sulle letture
- Sistema di gamification con achievement e livelli
- Notifiche email automatiche per scadenze e promemoria
- Visualizzazione trending e statistiche di lettura
- Gestione profilo personale e preferenze notifiche
- Sistema di multe automatico per ritardi

### Per i Bibliotecari
- Dashboard centralizzata per la gestione biblioteca
- Catalogazione rapida libri con supporto ISBN
- Gestione copie e disponibilità
- Sistema di prestito e restituzione rapida con barcode
- Gestione prenotazioni con notifiche automatiche
- Monitoraggio prestiti scaduti e multe
- Generazione ricevute e report PDF
- Gestione utenti e permessi

## Tecnologie Utilizzate

### Backend
- PHP 7.4+
- MySQL/MariaDB
- Composer per la gestione delle dipendenze

### Librerie e Dipendenze
- PHPMailer: invio email e notifiche
- TCPDF: generazione PDF per tessere e ricevute
- Dotenv: gestione configurazioni ambiente
- Comuni Italiani: autocompletamento comuni italiani

### Frontend
- HTML5, CSS3
- JavaScript vanilla
- Design responsive

### Database
- MySQL con struttura normalizzata
- Stored procedures per logica complessa
- Trigger per automazioni

## Requisiti di Sistema

- PHP 7.4 o superiore
- MySQL 5.7+ o MariaDB 10.2+
- XAMPP (o stack LAMP/WAMP equivalente)
- Composer
- Account Mailtrap.io per l'invio email (sviluppo)

## Installazione

### 1. Clonazione Repository

```bash
git clone <repository-url>
cd SudoMakers
```

### 2. Configurazione XAMPP

1. Copiare la cartella del progetto in `C:\xampp\htdocs\SudoMakers`
2. Avviare Apache e MySQL dal pannello di controllo XAMPP

### 3. Installazione Dipendenze

Aprire il terminale nella cartella del progetto ed eseguire:

```bash
composer install
```

### 4. Configurazione Database

1. Aprire phpMyAdmin (http://localhost/phpmyadmin)
2. Creare un nuovo database chiamato `biblioteca`
3. Importare il file SQL:
   ```
   data/biblioteca.sql
   ```

### 5. Configurazione Ambiente

1. Creare un file `.env` nella root del progetto
2. Configurare le credenziali seguendo questo template:

```env
# Database
DB_HOST=localhost
DB_NAME=biblioteca
DB_USER=root
DB_PASSWORD=

# Mailtrap (per sviluppo)
MAILTRAP_HOST=sandbox.smtp.mailtrap.io
MAILTRAP_PORT=2525
MAILTRAP_USER=your_mailtrap_username
MAILTRAP_PASSWORD=your_mailtrap_password
MAILTRAP_FROM_EMAIL=biblioteca@scuola.it
MAILTRAP_FROM_NAME=Biblioteca Scolastica

# Applicazione
APP_URL=http://localhost/SudoMakers
APP_ENV=development
```

### 6. Configurazione Mailtrap

1. Registrarsi su https://mailtrap.io
2. Creare un nuovo inbox
3. Copiare le credenziali SMTP nel file `.env`

### 7. Configurazione Cron Jobs (Opzionale)

Per attivare le funzionalità automatiche:

```bash
# Linux/Mac - aggiungere a crontab
*/30 * * * * php /path/to/SudoMakers/src/cron/run_cron.php

# Windows - creare Task Scheduler
# Eseguire ogni 30 minuti: php C:\xampp\htdocs\SudoMakers\src\cron\run_cron.php
```

## Accesso al Sistema

Una volta completata l'installazione, accedere al sistema:

```
http://localhost/SudoMakers/src/user/homepage.php
```

### Account Predefiniti

Gli account iniziali sono creati dall'importazione del database:

- **Bibliotecario**: verificare nel database la tabella `utenti` per le credenziali admin
- **Utente Standard**: registrarsi tramite interfaccia web

## Struttura del Progetto

```
SudoMakers/
├── data/                      # Database e file dati
│   ├── biblioteca.sql         # Schema database
│   └── comuni.csv            # Dati comuni italiani
├── public/                   # File pubblici
│   ├── assets/              
│   │   ├── css/             # Fogli di stile
│   │   ├── img/             # Immagini
│   │   └── js/              # JavaScript client
│   └── uploads/             # File caricati (avatar, etc)
├── src/                     # Codice sorgente
│   ├── api/                 # Endpoint API
│   ├── auth/                # Autenticazione
│   ├── catalog/             # Catalogo libri
│   ├── core/                # Classi core
│   ├── cron/                # Job automatici
│   ├── librarian/           # Area bibliotecario
│   ├── templates/           # Template email
│   ├── user/                # Area utente
│   └── utils/               # Utility e helper
├── vendor/                  # Dipendenze Composer
├── .env                     # Configurazione (non versionato)
├── .gitignore              
├── composer.json            # Dipendenze PHP
└── README.md               # Questa documentazione
```

## Funzionalità Dettagliate

### Sistema di Prenotazione
- Prenotazione libri non disponibili
- Notifica automatica quando il libro diventa disponibile
- Tempo limitato per il ritiro
- Cancellazione automatica prenotazioni scadute

### Sistema di Gamification
- Punti esperienza per letture completate
- Livelli progressivi
- Achievement sbloccabili
- Classifiche utenti
- Badge e riconoscimenti

### Sistema di Raccomandazioni
- Algoritmo basato su generi preferiti
- Raccomandazioni personalizzate
- Trending books
- Statistiche di lettura

### Sistema di Notifiche
- Email automatiche per:
  - Conferma prestito
  - Promemoria scadenza
  - Ritardo libro
  - Disponibilità prenotazione
  - Multe e sanzioni
- Preferenze personalizzabili per tipo di notifica

### Gestione Multe
- Calcolo automatico multe per ritardi
- Sistema a fasce progressive
- Tracciamento pagamenti
- Report e statistiche

## API Disponibili

Il sistema fornisce diverse API REST per integrazioni:

- `POST /api/track_interaction.php` - Traccia interazioni utente
- `GET /api/get_trending_stats.php` - Ottiene statistiche trending
- `POST /api/save_feedback.php` - Salva feedback raccomandazioni
- `POST /api/refresh_recommendations.php` - Aggiorna raccomandazioni

## Manutenzione

### Backup Database
```bash
mysqldump -u root biblioteca > backup_biblioteca_$(date +%Y%m%d).sql
```

### Pulizia Token Scaduti
```bash
php src/cron/cleanup_expired_tokens.php
```

### Aggiornamento Tendenze
```bash
php src/cron/cron_update_trends.php
```

## Risoluzione Problemi

### Errore Connessione Database
- Verificare che MySQL sia avviato
- Controllare credenziali in `.env`
- Verificare che il database `biblioteca` esista

### Email Non Inviate
- Verificare configurazione Mailtrap in `.env`
- Controllare log email in `data/logs/` (se abilitato)
- Testare connessione SMTP manualmente

### Errori di Permessi
```bash
# Linux/Mac
chmod -R 755 public/uploads
chmod 644 .env

# Windows
# Dare permessi di scrittura alla cartella uploads tramite Proprietà
```

### Problemi con Composer
```bash
# Aggiornare Composer
composer self-update

# Reinstallare dipendenze
rm -rf vendor/
composer install
```