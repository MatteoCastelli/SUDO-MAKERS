# Sistema di Gestione Biblioteca Scolastica

Sistema web completo per la gestione di una biblioteca scolastica con funzionalità avanzate di catalogazione, prestito libri, prenotazioni e gamification.

## Autori

- Christian Tibaldo
- Matteo Castelli
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
- PHP 8.5
- MariaDB
- Composer per la gestione delle dipendenze

### Librerie e Dipendenze
- PHPMailer: invio email e notifiche
- TCPDF: generazione PDF per tessere e ricevute
- Dotenv: gestione configurazioni ambiente

### Frontend
- HTML5, CSS
- JavaScript
- Design responsive

### Database
- MySQL con struttura normalizzata
- Stored procedures per logica complessa

## Requisiti di Sistema

- PHP 7.4 o superiore
- MySQL o MariaDB
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
3. Importare il file SQL:
   ```
   data/biblioteca.sql
   ```

### 5. Configurazione Ambiente

1. Modificare il file `.env` nella root del progetto
2. Configurare le credenziali seguendo questo template:

```env
USERNAME=[username mailtrap]
PASSWORD=[password mailtrap]
MTP_HOST=sandbox.smtp.mailtrap.io
SMTP_PORT=2525
FROM_EMAIL=biblioteca@noreply.com
FROM_NAME=Biblioteca_Digitale
REPLY_TO_EMAIL=reply@noreply.com
REPLY_TO_NAME=Biblioteca_Digitale
```

## Accesso al Sistema

Una volta completata l'installazione, accedere al sistema:

```
http://localhost/SudoMakers/src/user/homepage.php
```

### Account Predefiniti

- **Utente Standard**: registrarsi tramite interfaccia web
- **Bibliotecario**: verificare nel database la tabella `utenti` per le credenziali admin

## Struttura del Progetto

```
SudoMakers/
├── data/                    # Database e file dati
│   ├── biblioteca.sql       # Schema database
│   └── comuni.csv           # Dati comuni italiani
├── public/                  # File pubblici
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
├── .env                     # Configurazione
├── .gitignore              
├── composer.json            # Dipendenze PHP
└── README.md                # Questa documentazione
```

## Schema Database

Il database è strutturato per supportare tutte le funzionalità del sistema bibliotecario. Di seguito le tabelle principali e le loro relazioni.

### Tabelle Principali

#### Gestione Libri e Catalogo
- **libro**: Informazioni bibliografiche (titolo, ISBN, editore, categoria, descrizione)
- **autore**: Anagrafica autori con biografia
- **libro_autore**: Relazione N-N tra libri e autori
- **copia**: Copie fisiche dei libri con stato e barcode

#### Gestione Utenti
- **utente**: Anagrafica utenti (email, password hash, dati personali, verificato)
- **ruolo**: Definizione ruoli sistema (bibliotecario, utente, admin)
- **utente_ruolo**: Assegnazione ruoli agli utenti

#### Sistema Prestiti e Prenotazioni
- **prestito**: Registrazione prestiti (date, stato, restituzione)
- **prenotazione**: Gestione code prenotazione per libri non disponibili
- **condizione_restituzione**: Valutazione stato fisico alla restituzione

#### Sistema Multe e Pagamenti
- **multa**: Registrazione multe per ritardi (importo, stato, gravità)
- **pagamento**: Tracciamento pagamenti multe

#### Sistema Gamification
- **livello_utente**: Livello, esperienza e progressione utenti
- **badge**: Definizione badge e achievement disponibili
- **utente_badge**: Badge ottenuti dagli utenti con timestamp
- **obiettivo**: Obiettivi periodici (giornalieri, settimanali, mensili)
- **progresso_obiettivo**: Avanzamento utenti verso obiettivi
- **streak_utente**: Serie consecutive di letture
- **storico_xp**: Log completo acquisizioni esperienza
- **classifica**: Classifiche per periodo e tipologia

#### Sistema Raccomandazioni
- **cache_raccomandazioni**: Raccomandazioni pre-calcolate per utenti
- **interazione_utente**: Tracciamento click, visualizzazioni, ricerche
- **feedback_raccomandazione**: Feedback su raccomandazioni (like/dislike)
- **profilo_preferenze**: Generi e autori preferiti utenti
- **trend_libri**: Statistiche trending settimanali/mensili

#### Sistema Notifiche
- **notifica**: Notifiche in-app per utenti
- **preferenze_notifiche**: Configurazione tipi notifiche per utente
- **log_email**: Tracciamento email inviate
- **template_email**: Template HTML per email automatiche

#### Recensioni e Feedback
- **recensione**: Recensioni e valutazioni libri

#### Sistema di Sicurezza
- **password_reset_tokens**: Token temporanei per reset password
- **log_attivita**: Audit log azioni importanti sistema

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
- Testare connessione SMTP manualmente


### Problemi con Composer
```bash
# Aggiornare Composer
composer self-update

# Reinstallare dipendenze
rm -rf vendor/
composer install
```

## TODO a priorità alta

- [ ] Sistema di routing
- [ ] Pannello admin avanzato
- [ ] Implementare sistema di rinnovo prestiti
- [ ] Implementare pulsante di richiesta libri non disponibili
- [ ] Sistema di log più dettagliato
- [ ] Implementare wishlist personale
- [ ] Tema chiaro
- [ ] Bacheca achievement pubblica

### Bug Noti

#### Critici
- Nessuno attualmente

#### Minori
- [ ] Ricerca avanzata non mantiene filtri dopo reload
- [ ] Responsive da migliorare

#### Codice legacy da refactorare:
- [ ] `src/utils/functions.php` (troppo grande, separare in classi)
