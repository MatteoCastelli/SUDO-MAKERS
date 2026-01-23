-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Creato il: Gen 23, 2026 alle 22:32
-- Versione del server: 10.4.32-MariaDB
-- Versione PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `biblioteca`
--
CREATE DATABASE IF NOT EXISTS `biblioteca` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `biblioteca`;

-- --------------------------------------------------------

--
-- Struttura della tabella `autore`
--

CREATE TABLE `autore` (
  `id_autore` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cognome` varchar(100) NOT NULL,
  `biografia` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `badge`
--

CREATE TABLE `badge` (
  `id_badge` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `icona` varchar(50) DEFAULT NULL,
  `criterio_sblocco` text DEFAULT NULL,
  `condizione_valore` int(11) DEFAULT NULL,
  `condizione_categoria` varchar(100) DEFAULT NULL,
  `tipo` enum('letture','genere','velocita','costanza','recensione','speciale','evento') DEFAULT 'letture',
  `rarita` enum('comune','raro','epico','leggendario') DEFAULT 'comune',
  `punti_esperienza` int(11) DEFAULT 10,
  `attivo` tinyint(1) DEFAULT 1,
  `data_inizio` date DEFAULT NULL,
  `data_fine` date DEFAULT NULL,
  `ordine_visualizzazione` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `cache_raccomandazioni`
--

CREATE TABLE `cache_raccomandazioni` (
  `id_cache` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `id_libro` int(11) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `motivo_raccomandazione` text DEFAULT NULL,
  `algoritmo` varchar(50) NOT NULL,
  `data_generazione` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `classifica`
--

CREATE TABLE `classifica` (
  `id_classifica` int(11) NOT NULL,
  `tipo` enum('generale','mensile','badge_rari','obiettivi','annuale') DEFAULT 'generale',
  `id_utente` int(11) NOT NULL,
  `posizione` int(11) NOT NULL,
  `punteggio` decimal(10,2) NOT NULL,
  `periodo_riferimento` varchar(7) DEFAULT NULL COMMENT 'Formato: YYYY-MM per mensile, YYYY per annuale',
  `ultimo_aggiornamento` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `condizione_restituzione`
--

CREATE TABLE `condizione_restituzione` (
  `id_condizione` int(11) NOT NULL,
  `id_prestito` int(11) NOT NULL,
  `stato_fisico_restituzione` enum('ottimo','buono','discreto','usurato','danneggiato') NOT NULL,
  `danni_rilevati` text DEFAULT NULL,
  `costo_riparazione` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `copia`
--

CREATE TABLE `copia` (
  `id_copia` int(11) NOT NULL,
  `id_libro` int(11) NOT NULL,
  `codice_barcode` varchar(50) NOT NULL,
  `stato_fisico` enum('ottimo','buono','discreto','usurato','danneggiato','smarrito') DEFAULT 'ottimo',
  `disponibile` tinyint(1) DEFAULT 1,
  `data_acquisizione` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `feedback_raccomandazione`
--

CREATE TABLE `feedback_raccomandazione` (
  `id_feedback` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `id_libro` int(11) NOT NULL,
  `feedback` enum('thumbs_up','thumbs_down','not_interested') NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `data_feedback` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `interazione_utente`
--

CREATE TABLE `interazione_utente` (
  `id_interazione` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `id_libro` int(11) NOT NULL,
  `tipo_interazione` enum('click','view_dettaglio','ricerca','prenotazione_tentata') NOT NULL,
  `data_interazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `durata_visualizzazione` int(11) DEFAULT NULL,
  `fonte` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `libro`
--

CREATE TABLE `libro` (
  `id_libro` int(11) NOT NULL,
  `titolo` varchar(500) NOT NULL,
  `editore` varchar(200) DEFAULT NULL,
  `anno_pubblicazione` int(11) DEFAULT NULL,
  `isbn` varchar(17) DEFAULT NULL,
  `ean` varchar(13) DEFAULT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `descrizione` text DEFAULT NULL,
  `immagine_copertina_url` varchar(500) DEFAULT NULL,
  `collocazione` varchar(50) DEFAULT NULL,
  `data_inserimento` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `libro_autore`
--

CREATE TABLE `libro_autore` (
  `id_libro` int(11) NOT NULL,
  `id_autore` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `livello_utente`
--

CREATE TABLE `livello_utente` (
  `id_utente` int(11) NOT NULL,
  `livello` int(11) DEFAULT 1,
  `esperienza_totale` int(11) DEFAULT 0,
  `esperienza_livello_corrente` int(11) DEFAULT 0,
  `esperienza_prossimo_livello` int(11) DEFAULT 100,
  `titolo` varchar(100) DEFAULT 'Lettore Novizio',
  `ultimo_aggiornamento` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `log_attivita`
--

CREATE TABLE `log_attivita` (
  `id_log` int(11) NOT NULL,
  `id_utente` int(11) DEFAULT NULL,
  `tipo_azione` enum('login','logout','prestito','restituzione','catalogazione','modifica','eliminazione') NOT NULL,
  `descrizione` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `log_email`
--

CREATE TABLE `log_email` (
  `id_log` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `id_notifica` int(11) DEFAULT NULL,
  `tipo_email` varchar(50) NOT NULL,
  `destinatario` varchar(255) NOT NULL,
  `oggetto` varchar(500) NOT NULL,
  `stato` enum('inviata','fallita','in_coda') DEFAULT 'in_coda',
  `errore` text DEFAULT NULL,
  `tentativo` int(11) DEFAULT 1,
  `data_invio` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `multa`
--

CREATE TABLE `multa` (
  `id_multa` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `id_prestito` int(11) DEFAULT NULL,
  `importo` decimal(10,2) NOT NULL,
  `causale` varchar(255) NOT NULL,
  `tipo_multa` enum('ritardo','danno','smarrimento') DEFAULT 'ritardo',
  `giorni_ritardo` int(11) DEFAULT 0,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `stato` enum('non_pagata','pagata') DEFAULT 'non_pagata',
  `note` text DEFAULT NULL,
  `data_pagamento` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Trigger `multa`
--
DELIMITER $$
CREATE TRIGGER `blocca_utente_multa` AFTER INSERT ON `multa` FOR EACH ROW BEGIN
    -- Blocca prestiti se multa > €5 o giorni_ritardo > 7
    IF NEW.importo > 5 OR NEW.giorni_ritardo > 7 THEN
        UPDATE utente 
        SET prestiti_bloccati = TRUE,
            motivo_blocco = 'Multe non pagate o ritardo grave'
        WHERE id_utente = NEW.id_utente;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `sblocca_utente_pagamento` AFTER UPDATE ON `multa` FOR EACH ROW BEGIN
    -- Se tutte le multe sono pagate, sblocca l'utente
    IF NEW.stato = 'pagata' AND OLD.stato = 'non_pagata' THEN
        -- Controlla se ci sono altre multe non pagate
        IF NOT EXISTS (
            SELECT 1 FROM multa 
            WHERE id_utente = NEW.id_utente 
            AND stato = 'non_pagata'
        ) THEN
            UPDATE utente 
            SET prestiti_bloccati = FALSE,
                motivo_blocco = NULL
            WHERE id_utente = NEW.id_utente;
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struttura della tabella `notifica`
--

CREATE TABLE `notifica` (
  `id_notifica` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `tipo` enum('prestito','scadenza','prenotazione','multa','sistema','badge','obiettivo') NOT NULL,
  `titolo` varchar(255) NOT NULL,
  `messaggio` text NOT NULL,
  `priorita` enum('bassa','media','alta','urgente') DEFAULT 'media',
  `dati_extra` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dati_extra`)),
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `letta` tinyint(1) DEFAULT 0,
  `data_invio_email` datetime DEFAULT NULL,
  `email_inviata` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `obiettivo`
--

CREATE TABLE `obiettivo` (
  `id_obiettivo` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `target` int(11) NOT NULL,
  `tipo` enum('libri_letti','generi_diversi','recensioni','custom') DEFAULT 'libri_letti',
  `anno_riferimento` int(11) DEFAULT NULL,
  `ricorrente` tinyint(1) DEFAULT 0,
  `punti_esperienza` int(11) DEFAULT 50,
  `icona` varchar(50) DEFAULT '?',
  `attivo` tinyint(1) DEFAULT 1,
  `ordine_visualizzazione` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `pagamento`
--

CREATE TABLE `pagamento` (
  `id_pagamento` int(11) NOT NULL,
  `id_multa` int(11) NOT NULL,
  `importo` decimal(10,2) NOT NULL,
  `data_pagamento` timestamp NOT NULL DEFAULT current_timestamp(),
  `metodo_pagamento` enum('contanti','carta','bonifico') DEFAULT 'contanti',
  `id_bibliotecario` int(11) DEFAULT NULL,
  `ricevuta_pdf` varchar(500) DEFAULT NULL,
  `note_pagamento` text DEFAULT NULL,
  `transazione_id` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `partecipazione_sfida`
--

CREATE TABLE `partecipazione_sfida` (
  `id_partecipazione` int(11) NOT NULL,
  `id_sfida` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `progresso` int(11) DEFAULT 0,
  `completata` tinyint(1) DEFAULT 0,
  `data_completamento` timestamp NULL DEFAULT NULL,
  `premio_ritirato` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `preferenze_notifiche`
--

CREATE TABLE `preferenze_notifiche` (
  `id_preferenza` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `email_attive` tinyint(1) DEFAULT 1,
  `promemoria_scadenza` tinyint(1) DEFAULT 1,
  `notifiche_ritardo` tinyint(1) DEFAULT 1,
  `notifiche_prenotazioni` tinyint(1) DEFAULT 1,
  `quiet_hours_attive` tinyint(1) DEFAULT 0,
  `quiet_hours_inizio` time DEFAULT '22:00:00',
  `quiet_hours_fine` time DEFAULT '08:00:00',
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_aggiornamento` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `prenotazione`
--

CREATE TABLE `prenotazione` (
  `id_prenotazione` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `id_libro` int(11) NOT NULL,
  `data_prenotazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `stato` enum('attiva','disponibile','ritirata','scaduta','annullata') DEFAULT 'attiva',
  `data_disponibilita` datetime DEFAULT NULL,
  `data_scadenza_ritiro` datetime DEFAULT NULL,
  `posizione_coda` int(11) DEFAULT NULL,
  `id_copia_assegnata` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `prestito`
--

CREATE TABLE `prestito` (
  `id_prestito` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `id_copia` int(11) NOT NULL,
  `id_bibliotecario` int(11) DEFAULT NULL,
  `data_prestito` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_scadenza` datetime NOT NULL,
  `data_restituzione_effettiva` datetime DEFAULT NULL,
  `rinnovato` tinyint(1) DEFAULT 0,
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `profilo_preferenze`
--

CREATE TABLE `profilo_preferenze` (
  `id_utente` int(11) NOT NULL,
  `categorie_preferite` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`categorie_preferite`)),
  `autori_preferiti` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`autori_preferiti`)),
  `pattern_lettura` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`pattern_lettura`)),
  `ultimo_aggiornamento` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `progresso_obiettivo`
--

CREATE TABLE `progresso_obiettivo` (
  `id_progresso` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `id_obiettivo` int(11) NOT NULL,
  `anno_riferimento` int(11) DEFAULT NULL,
  `progresso_attuale` int(11) DEFAULT 0,
  `completato` tinyint(1) DEFAULT 0,
  `notificato` tinyint(1) DEFAULT 0,
  `data_completamento` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `recensione`
--

CREATE TABLE `recensione` (
  `id_recensione` int(11) NOT NULL,
  `id_libro` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `voto` int(11) NOT NULL CHECK (`voto` between 1 and 5),
  `testo` text DEFAULT NULL,
  `data_recensione` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `ruolo`
--

CREATE TABLE `ruolo` (
  `id_ruolo` int(11) NOT NULL,
  `nome_ruolo` varchar(50) NOT NULL,
  `livello_permesso` int(11) NOT NULL DEFAULT 1,
  `descrizione` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `sfide_evento`
--

CREATE TABLE `sfide_evento` (
  `id_sfida` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descrizione` text DEFAULT NULL,
  `id_badge_premio` int(11) DEFAULT NULL COMMENT 'Badge ottenuto al completamento',
  `xp_premio` int(11) DEFAULT 0,
  `data_inizio` date NOT NULL,
  `data_fine` date NOT NULL,
  `requisiti` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Requisiti personalizzati per completare la sfida' CHECK (json_valid(`requisiti`)),
  `attiva` tinyint(1) DEFAULT 1,
  `creato_il` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `storico_xp`
--

CREATE TABLE `storico_xp` (
  `id_storico` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `punti_guadagnati` int(11) NOT NULL,
  `motivo` varchar(255) NOT NULL,
  `riferimento_tipo` enum('badge','obiettivo','prestito','recensione','altro') NOT NULL,
  `riferimento_id` int(11) DEFAULT NULL,
  `data_guadagno` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `streak_utente`
--

CREATE TABLE `streak_utente` (
  `id_utente` int(11) NOT NULL,
  `giorni_consecutivi` int(11) DEFAULT 0,
  `ultima_attivita` date NOT NULL,
  `record_personale` int(11) DEFAULT 0,
  `streak_attivo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `template_email`
--

CREATE TABLE `template_email` (
  `id_template` int(11) NOT NULL,
  `nome_template` varchar(100) NOT NULL,
  `tipo_notifica` varchar(50) NOT NULL,
  `oggetto` varchar(500) NOT NULL,
  `corpo_html` text NOT NULL,
  `corpo_testo` text DEFAULT NULL,
  `variabili` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Lista variabili disponibili nel template' CHECK (json_valid(`variabili`)),
  `attivo` tinyint(1) DEFAULT 1,
  `data_creazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_aggiornamento` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `trend_libri`
--

CREATE TABLE `trend_libri` (
  `id_libro` int(11) NOT NULL,
  `trend_score` decimal(8,2) NOT NULL DEFAULT 0.00,
  `prestiti_ultimi_7_giorni` int(11) DEFAULT 0,
  `prestiti_ultimi_30_giorni` int(11) DEFAULT 0,
  `click_ultimi_7_giorni` int(11) DEFAULT 0,
  `prenotazioni_attive` int(11) DEFAULT 0,
  `velocita_trend` decimal(5,2) DEFAULT 0.00,
  `ultimo_aggiornamento` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `utente`
--

CREATE TABLE `utente` (
  `id_utente` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cognome` varchar(100) NOT NULL,
  `data_nascita` date NOT NULL,
  `sesso` enum('M','F') NOT NULL,
  `comune_nascita` varchar(100) NOT NULL,
  `codice_catastale` varchar(4) NOT NULL,
  `codice_fiscale` varchar(16) NOT NULL,
  `codice_tessera` varchar(50) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `foto` varchar(500) DEFAULT '../../public/assets/img/default_avatar.png',
  `email_verificata` tinyint(1) DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `verification_expires` datetime DEFAULT NULL,
  `stato_account` enum('attivo','sospeso','bloccato') DEFAULT 'attivo',
  `prestiti_bloccati` tinyint(1) DEFAULT 0,
  `motivo_blocco` varchar(255) DEFAULT NULL,
  `tentativi_falliti` int(11) DEFAULT 0,
  `data_sospensione` datetime DEFAULT NULL,
  `data_registrazione` timestamp NOT NULL DEFAULT current_timestamp(),
  `data_ultimo_accesso` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Trigger `utente`
--
DELIMITER $$
CREATE TRIGGER `after_user_insert_create_level` AFTER INSERT ON `utente` FOR EACH ROW BEGIN
    INSERT INTO `livello_utente` (`id_utente`, `livello`, `esperienza_totale`, `titolo`)
    VALUES (NEW.id_utente, 1, 0, 'Lettore Novizio');
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struttura della tabella `utente_badge`
--

CREATE TABLE `utente_badge` (
  `id_utente_badge` int(11) NOT NULL,
  `id_utente` int(11) NOT NULL,
  `id_badge` int(11) NOT NULL,
  `data_ottenimento` timestamp NOT NULL DEFAULT current_timestamp(),
  `progressione_attuale` int(11) DEFAULT 0,
  `notificato` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `utente_ruolo`
--

CREATE TABLE `utente_ruolo` (
  `id_utente` int(11) NOT NULL,
  `id_ruolo` int(11) NOT NULL,
  `data_assegnazione` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `vista_multe_attive`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `vista_multe_attive` (
`id_multa` int(11)
,`id_utente` int(11)
,`nome` varchar(100)
,`cognome` varchar(100)
,`email` varchar(255)
,`importo` decimal(10,2)
,`causale` varchar(255)
,`tipo_multa` enum('ritardo','danno','smarrimento')
,`giorni_ritardo` int(11)
,`stato` enum('non_pagata','pagata')
,`data_creazione` timestamp
,`id_prestito` int(11)
,`libro_titolo` varchar(500)
,`giorni_multa_aperta` int(7)
);

-- --------------------------------------------------------

--
-- Struttura per vista `vista_multe_attive`
--
DROP TABLE IF EXISTS `vista_multe_attive`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_multe_attive`  AS SELECT `m`.`id_multa` AS `id_multa`, `m`.`id_utente` AS `id_utente`, `u`.`nome` AS `nome`, `u`.`cognome` AS `cognome`, `u`.`email` AS `email`, `m`.`importo` AS `importo`, `m`.`causale` AS `causale`, `m`.`tipo_multa` AS `tipo_multa`, `m`.`giorni_ritardo` AS `giorni_ritardo`, `m`.`stato` AS `stato`, `m`.`data_creazione` AS `data_creazione`, `p`.`id_prestito` AS `id_prestito`, `l`.`titolo` AS `libro_titolo`, to_days(current_timestamp()) - to_days(`m`.`data_creazione`) AS `giorni_multa_aperta` FROM ((((`multa` `m` join `utente` `u` on(`m`.`id_utente` = `u`.`id_utente`)) left join `prestito` `p` on(`m`.`id_prestito` = `p`.`id_prestito`)) left join `copia` `c` on(`p`.`id_copia` = `c`.`id_copia`)) left join `libro` `l` on(`c`.`id_libro` = `l`.`id_libro`)) WHERE `m`.`stato` = 'non_pagata' ORDER BY `m`.`data_creazione` DESC ;

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `autore`
--
ALTER TABLE `autore`
  ADD PRIMARY KEY (`id_autore`),
  ADD KEY `idx_cognome` (`cognome`);

--
-- Indici per le tabelle `badge`
--
ALTER TABLE `badge`
  ADD PRIMARY KEY (`id_badge`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Indici per le tabelle `cache_raccomandazioni`
--
ALTER TABLE `cache_raccomandazioni`
  ADD PRIMARY KEY (`id_cache`),
  ADD KEY `id_libro` (`id_libro`),
  ADD KEY `idx_utente_score` (`id_utente`,`score`),
  ADD KEY `idx_data` (`data_generazione`);

--
-- Indici per le tabelle `classifica`
--
ALTER TABLE `classifica`
  ADD PRIMARY KEY (`id_classifica`),
  ADD UNIQUE KEY `unique_classifica` (`tipo`,`id_utente`,`periodo_riferimento`),
  ADD KEY `id_utente` (`id_utente`),
  ADD KEY `idx_tipo_periodo` (`tipo`,`periodo_riferimento`),
  ADD KEY `idx_posizione` (`posizione`),
  ADD KEY `idx_punteggio` (`punteggio`);

--
-- Indici per le tabelle `condizione_restituzione`
--
ALTER TABLE `condizione_restituzione`
  ADD PRIMARY KEY (`id_condizione`),
  ADD KEY `id_prestito` (`id_prestito`);

--
-- Indici per le tabelle `copia`
--
ALTER TABLE `copia`
  ADD PRIMARY KEY (`id_copia`),
  ADD UNIQUE KEY `codice_barcode` (`codice_barcode`),
  ADD KEY `id_libro` (`id_libro`),
  ADD KEY `idx_barcode` (`codice_barcode`),
  ADD KEY `idx_disponibile` (`disponibile`);

--
-- Indici per le tabelle `feedback_raccomandazione`
--
ALTER TABLE `feedback_raccomandazione`
  ADD PRIMARY KEY (`id_feedback`),
  ADD UNIQUE KEY `unique_user_book` (`id_utente`,`id_libro`),
  ADD KEY `id_libro` (`id_libro`);

--
-- Indici per le tabelle `interazione_utente`
--
ALTER TABLE `interazione_utente`
  ADD PRIMARY KEY (`id_interazione`),
  ADD KEY `id_libro` (`id_libro`),
  ADD KEY `idx_utente_libro` (`id_utente`,`id_libro`),
  ADD KEY `idx_data` (`data_interazione`),
  ADD KEY `idx_tipo` (`tipo_interazione`);

--
-- Indici per le tabelle `libro`
--
ALTER TABLE `libro`
  ADD PRIMARY KEY (`id_libro`),
  ADD KEY `idx_titolo` (`titolo`(100)),
  ADD KEY `idx_isbn` (`isbn`),
  ADD KEY `idx_ean` (`ean`),
  ADD KEY `idx_categoria` (`categoria`);

--
-- Indici per le tabelle `libro_autore`
--
ALTER TABLE `libro_autore`
  ADD PRIMARY KEY (`id_libro`,`id_autore`),
  ADD KEY `id_autore` (`id_autore`);

--
-- Indici per le tabelle `livello_utente`
--
ALTER TABLE `livello_utente`
  ADD PRIMARY KEY (`id_utente`),
  ADD KEY `idx_livello` (`livello`),
  ADD KEY `idx_esperienza` (`esperienza_totale`);

--
-- Indici per le tabelle `log_attivita`
--
ALTER TABLE `log_attivita`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `id_utente` (`id_utente`),
  ADD KEY `idx_tipo` (`tipo_azione`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indici per le tabelle `log_email`
--
ALTER TABLE `log_email`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `id_utente` (`id_utente`),
  ADD KEY `id_notifica` (`id_notifica`),
  ADD KEY `idx_stato` (`stato`),
  ADD KEY `idx_data` (`data_invio`);

--
-- Indici per le tabelle `multa`
--
ALTER TABLE `multa`
  ADD PRIMARY KEY (`id_multa`),
  ADD KEY `id_prestito` (`id_prestito`),
  ADD KEY `idx_utente` (`id_utente`),
  ADD KEY `idx_stato` (`stato`),
  ADD KEY `idx_multa_tipo` (`tipo_multa`,`stato`);

--
-- Indici per le tabelle `notifica`
--
ALTER TABLE `notifica`
  ADD PRIMARY KEY (`id_notifica`),
  ADD KEY `idx_utente_letta` (`id_utente`,`letta`),
  ADD KEY `idx_notifica_priorita` (`priorita`,`data_creazione`),
  ADD KEY `idx_notifica_email` (`email_inviata`,`data_creazione`);

--
-- Indici per le tabelle `obiettivo`
--
ALTER TABLE `obiettivo`
  ADD PRIMARY KEY (`id_obiettivo`);

--
-- Indici per le tabelle `pagamento`
--
ALTER TABLE `pagamento`
  ADD PRIMARY KEY (`id_pagamento`),
  ADD KEY `id_multa` (`id_multa`),
  ADD KEY `id_bibliotecario` (`id_bibliotecario`);

--
-- Indici per le tabelle `partecipazione_sfida`
--
ALTER TABLE `partecipazione_sfida`
  ADD PRIMARY KEY (`id_partecipazione`),
  ADD UNIQUE KEY `unique_partecipazione` (`id_sfida`,`id_utente`),
  ADD KEY `id_utente` (`id_utente`),
  ADD KEY `idx_completata` (`completata`);

--
-- Indici per le tabelle `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `id_utente` (`id_utente`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indici per le tabelle `preferenze_notifiche`
--
ALTER TABLE `preferenze_notifiche`
  ADD PRIMARY KEY (`id_preferenza`),
  ADD UNIQUE KEY `unique_utente` (`id_utente`);

--
-- Indici per le tabelle `prenotazione`
--
ALTER TABLE `prenotazione`
  ADD PRIMARY KEY (`id_prenotazione`),
  ADD KEY `id_libro` (`id_libro`),
  ADD KEY `idx_stato` (`stato`),
  ADD KEY `idx_utente_libro` (`id_utente`,`id_libro`),
  ADD KEY `id_copia_assegnata` (`id_copia_assegnata`);

--
-- Indici per le tabelle `prestito`
--
ALTER TABLE `prestito`
  ADD PRIMARY KEY (`id_prestito`),
  ADD KEY `id_bibliotecario` (`id_bibliotecario`),
  ADD KEY `idx_utente` (`id_utente`),
  ADD KEY `idx_copia` (`id_copia`),
  ADD KEY `idx_scadenza` (`data_scadenza`),
  ADD KEY `idx_attivi` (`data_restituzione_effettiva`);

--
-- Indici per le tabelle `profilo_preferenze`
--
ALTER TABLE `profilo_preferenze`
  ADD PRIMARY KEY (`id_utente`);

--
-- Indici per le tabelle `progresso_obiettivo`
--
ALTER TABLE `progresso_obiettivo`
  ADD PRIMARY KEY (`id_progresso`),
  ADD UNIQUE KEY `unique_progresso` (`id_utente`,`id_obiettivo`),
  ADD KEY `id_obiettivo` (`id_obiettivo`);

--
-- Indici per le tabelle `recensione`
--
ALTER TABLE `recensione`
  ADD PRIMARY KEY (`id_recensione`),
  ADD UNIQUE KEY `unique_recensione` (`id_libro`,`id_utente`),
  ADD KEY `id_utente` (`id_utente`),
  ADD KEY `idx_libro` (`id_libro`),
  ADD KEY `idx_voto` (`voto`);

--
-- Indici per le tabelle `ruolo`
--
ALTER TABLE `ruolo`
  ADD PRIMARY KEY (`id_ruolo`),
  ADD UNIQUE KEY `nome_ruolo` (`nome_ruolo`),
  ADD KEY `idx_livello` (`livello_permesso`);

--
-- Indici per le tabelle `sfide_evento`
--
ALTER TABLE `sfide_evento`
  ADD PRIMARY KEY (`id_sfida`),
  ADD KEY `id_badge_premio` (`id_badge_premio`),
  ADD KEY `idx_date` (`data_inizio`,`data_fine`),
  ADD KEY `idx_attiva` (`attiva`);

--
-- Indici per le tabelle `storico_xp`
--
ALTER TABLE `storico_xp`
  ADD PRIMARY KEY (`id_storico`),
  ADD KEY `idx_utente_data` (`id_utente`,`data_guadagno`),
  ADD KEY `idx_riferimento` (`riferimento_tipo`,`riferimento_id`);

--
-- Indici per le tabelle `streak_utente`
--
ALTER TABLE `streak_utente`
  ADD PRIMARY KEY (`id_utente`);

--
-- Indici per le tabelle `template_email`
--
ALTER TABLE `template_email`
  ADD PRIMARY KEY (`id_template`),
  ADD UNIQUE KEY `nome_template` (`nome_template`);

--
-- Indici per le tabelle `trend_libri`
--
ALTER TABLE `trend_libri`
  ADD PRIMARY KEY (`id_libro`),
  ADD KEY `idx_trend_score` (`trend_score`);

--
-- Indici per le tabelle `utente`
--
ALTER TABLE `utente`
  ADD PRIMARY KEY (`id_utente`),
  ADD UNIQUE KEY `codice_fiscale` (`codice_fiscale`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `codice_tessera` (`codice_tessera`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_codice_fiscale` (`codice_fiscale`),
  ADD KEY `idx_stato` (`stato_account`),
  ADD KEY `idx_codice_tessera` (`codice_tessera`),
  ADD KEY `idx_utente_bloccato` (`prestiti_bloccati`);

--
-- Indici per le tabelle `utente_badge`
--
ALTER TABLE `utente_badge`
  ADD PRIMARY KEY (`id_utente_badge`),
  ADD UNIQUE KEY `unique_utente_badge` (`id_utente`,`id_badge`),
  ADD KEY `id_badge` (`id_badge`);

--
-- Indici per le tabelle `utente_ruolo`
--
ALTER TABLE `utente_ruolo`
  ADD PRIMARY KEY (`id_utente`,`id_ruolo`),
  ADD KEY `id_ruolo` (`id_ruolo`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `autore`
--
ALTER TABLE `autore`
  MODIFY `id_autore` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `badge`
--
ALTER TABLE `badge`
  MODIFY `id_badge` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `cache_raccomandazioni`
--
ALTER TABLE `cache_raccomandazioni`
  MODIFY `id_cache` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `classifica`
--
ALTER TABLE `classifica`
  MODIFY `id_classifica` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `condizione_restituzione`
--
ALTER TABLE `condizione_restituzione`
  MODIFY `id_condizione` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `copia`
--
ALTER TABLE `copia`
  MODIFY `id_copia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `feedback_raccomandazione`
--
ALTER TABLE `feedback_raccomandazione`
  MODIFY `id_feedback` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `interazione_utente`
--
ALTER TABLE `interazione_utente`
  MODIFY `id_interazione` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `libro`
--
ALTER TABLE `libro`
  MODIFY `id_libro` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `log_attivita`
--
ALTER TABLE `log_attivita`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `log_email`
--
ALTER TABLE `log_email`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `multa`
--
ALTER TABLE `multa`
  MODIFY `id_multa` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `notifica`
--
ALTER TABLE `notifica`
  MODIFY `id_notifica` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `obiettivo`
--
ALTER TABLE `obiettivo`
  MODIFY `id_obiettivo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `pagamento`
--
ALTER TABLE `pagamento`
  MODIFY `id_pagamento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `partecipazione_sfida`
--
ALTER TABLE `partecipazione_sfida`
  MODIFY `id_partecipazione` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `preferenze_notifiche`
--
ALTER TABLE `preferenze_notifiche`
  MODIFY `id_preferenza` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `prenotazione`
--
ALTER TABLE `prenotazione`
  MODIFY `id_prenotazione` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `prestito`
--
ALTER TABLE `prestito`
  MODIFY `id_prestito` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `progresso_obiettivo`
--
ALTER TABLE `progresso_obiettivo`
  MODIFY `id_progresso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `recensione`
--
ALTER TABLE `recensione`
  MODIFY `id_recensione` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `ruolo`
--
ALTER TABLE `ruolo`
  MODIFY `id_ruolo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `sfide_evento`
--
ALTER TABLE `sfide_evento`
  MODIFY `id_sfida` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `storico_xp`
--
ALTER TABLE `storico_xp`
  MODIFY `id_storico` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `template_email`
--
ALTER TABLE `template_email`
  MODIFY `id_template` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `utente`
--
ALTER TABLE `utente`
  MODIFY `id_utente` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `utente_badge`
--
ALTER TABLE `utente_badge`
  MODIFY `id_utente_badge` int(11) NOT NULL AUTO_INCREMENT;

--
-- Limiti per le tabelle scaricate
--

--
-- Limiti per la tabella `cache_raccomandazioni`
--
ALTER TABLE `cache_raccomandazioni`
  ADD CONSTRAINT `cache_raccomandazioni_ibfk_1` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE,
  ADD CONSTRAINT `cache_raccomandazioni_ibfk_2` FOREIGN KEY (`id_libro`) REFERENCES `libro` (`id_libro`) ON DELETE CASCADE;

--
-- Limiti per la tabella `classifica`
--
ALTER TABLE `classifica`
  ADD CONSTRAINT `classifica_ibfk_1` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE;

--
-- Limiti per la tabella `condizione_restituzione`
--
ALTER TABLE `condizione_restituzione`
  ADD CONSTRAINT `condizione_restituzione_ibfk_1` FOREIGN KEY (`id_prestito`) REFERENCES `prestito` (`id_prestito`) ON DELETE CASCADE;

--
-- Limiti per la tabella `copia`
--
ALTER TABLE `copia`
  ADD CONSTRAINT `copia_ibfk_1` FOREIGN KEY (`id_libro`) REFERENCES `libro` (`id_libro`) ON DELETE CASCADE;

--
-- Limiti per la tabella `feedback_raccomandazione`
--
ALTER TABLE `feedback_raccomandazione`
  ADD CONSTRAINT `feedback_raccomandazione_ibfk_1` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_raccomandazione_ibfk_2` FOREIGN KEY (`id_libro`) REFERENCES `libro` (`id_libro`) ON DELETE CASCADE;

--
-- Limiti per la tabella `interazione_utente`
--
ALTER TABLE `interazione_utente`
  ADD CONSTRAINT `interazione_utente_ibfk_1` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE,
  ADD CONSTRAINT `interazione_utente_ibfk_2` FOREIGN KEY (`id_libro`) REFERENCES `libro` (`id_libro`) ON DELETE CASCADE;

--
-- Limiti per la tabella `libro_autore`
--
ALTER TABLE `libro_autore`
  ADD CONSTRAINT `libro_autore_ibfk_1` FOREIGN KEY (`id_libro`) REFERENCES `libro` (`id_libro`) ON DELETE CASCADE,
  ADD CONSTRAINT `libro_autore_ibfk_2` FOREIGN KEY (`id_autore`) REFERENCES `autore` (`id_autore`) ON DELETE CASCADE;

--
-- Limiti per la tabella `livello_utente`
--
ALTER TABLE `livello_utente`
  ADD CONSTRAINT `livello_utente_ibfk_1` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE;

--
-- Limiti per la tabella `log_attivita`
--
ALTER TABLE `log_attivita`
  ADD CONSTRAINT `log_attivita_ibfk_1` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE SET NULL;

--
-- Limiti per la tabella `log_email`
--
ALTER TABLE `log_email`
  ADD CONSTRAINT `log_email_ibfk_1` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE,
  ADD CONSTRAINT `log_email_ibfk_2` FOREIGN KEY (`id_notifica`) REFERENCES `notifica` (`id_notifica`) ON DELETE SET NULL;

--
-- Limiti per la tabella `multa`
--
ALTER TABLE `multa`
  ADD CONSTRAINT `multa_ibfk_1` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE,
  ADD CONSTRAINT `multa_ibfk_2` FOREIGN KEY (`id_prestito`) REFERENCES `prestito` (`id_prestito`) ON DELETE SET NULL;

--
-- Limiti per la tabella `notifica`
--
ALTER TABLE `notifica`
  ADD CONSTRAINT `notifica_ibfk_1` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE;

--
-- Limiti per la tabella `pagamento`
--
ALTER TABLE `pagamento`
  ADD CONSTRAINT `pagamento_ibfk_1` FOREIGN KEY (`id_multa`) REFERENCES `multa` (`id_multa`) ON DELETE CASCADE,
  ADD CONSTRAINT `pagamento_ibfk_2` FOREIGN KEY (`id_bibliotecario`) REFERENCES `utente` (`id_utente`) ON DELETE SET NULL;

--
-- Limiti per la tabella `partecipazione_sfida`
--
ALTER TABLE `partecipazione_sfida`
  ADD CONSTRAINT `partecipazione_sfida_ibfk_1` FOREIGN KEY (`id_sfida`) REFERENCES `sfide_evento` (`id_sfida`) ON DELETE CASCADE,
  ADD CONSTRAINT `partecipazione_sfida_ibfk_2` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE;

--
-- Limiti per la tabella `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE;

--
-- Limiti per la tabella `preferenze_notifiche`
--
ALTER TABLE `preferenze_notifiche`
  ADD CONSTRAINT `preferenze_notifiche_ibfk_1` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE;

--
-- Limiti per la tabella `prenotazione`
--
ALTER TABLE `prenotazione`
  ADD CONSTRAINT `prenotazione_ibfk_1` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE,
  ADD CONSTRAINT `prenotazione_ibfk_2` FOREIGN KEY (`id_libro`) REFERENCES `libro` (`id_libro`) ON DELETE CASCADE,
  ADD CONSTRAINT `prenotazione_ibfk_3` FOREIGN KEY (`id_copia_assegnata`) REFERENCES `copia` (`id_copia`) ON DELETE SET NULL;

--
-- Limiti per la tabella `prestito`
--
ALTER TABLE `prestito`
  ADD CONSTRAINT `prestito_ibfk_1` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE,
  ADD CONSTRAINT `prestito_ibfk_2` FOREIGN KEY (`id_copia`) REFERENCES `copia` (`id_copia`) ON DELETE CASCADE,
  ADD CONSTRAINT `prestito_ibfk_3` FOREIGN KEY (`id_bibliotecario`) REFERENCES `utente` (`id_utente`) ON DELETE SET NULL;

--
-- Limiti per la tabella `profilo_preferenze`
--
ALTER TABLE `profilo_preferenze`
  ADD CONSTRAINT `profilo_preferenze_ibfk_1` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE;

--
-- Limiti per la tabella `progresso_obiettivo`
--
ALTER TABLE `progresso_obiettivo`
  ADD CONSTRAINT `progresso_obiettivo_ibfk_1` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE,
  ADD CONSTRAINT `progresso_obiettivo_ibfk_2` FOREIGN KEY (`id_obiettivo`) REFERENCES `obiettivo` (`id_obiettivo`) ON DELETE CASCADE;

--
-- Limiti per la tabella `recensione`
--
ALTER TABLE `recensione`
  ADD CONSTRAINT `recensione_ibfk_1` FOREIGN KEY (`id_libro`) REFERENCES `libro` (`id_libro`) ON DELETE CASCADE,
  ADD CONSTRAINT `recensione_ibfk_2` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE;

--
-- Limiti per la tabella `sfide_evento`
--
ALTER TABLE `sfide_evento`
  ADD CONSTRAINT `sfide_evento_ibfk_1` FOREIGN KEY (`id_badge_premio`) REFERENCES `badge` (`id_badge`) ON DELETE SET NULL;

--
-- Limiti per la tabella `storico_xp`
--
ALTER TABLE `storico_xp`
  ADD CONSTRAINT `storico_xp_ibfk_1` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE;

--
-- Limiti per la tabella `streak_utente`
--
ALTER TABLE `streak_utente`
  ADD CONSTRAINT `streak_utente_ibfk_1` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE;

--
-- Limiti per la tabella `trend_libri`
--
ALTER TABLE `trend_libri`
  ADD CONSTRAINT `trend_libri_ibfk_1` FOREIGN KEY (`id_libro`) REFERENCES `libro` (`id_libro`) ON DELETE CASCADE;

--
-- Limiti per la tabella `utente_badge`
--
ALTER TABLE `utente_badge`
  ADD CONSTRAINT `utente_badge_ibfk_1` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE,
  ADD CONSTRAINT `utente_badge_ibfk_2` FOREIGN KEY (`id_badge`) REFERENCES `badge` (`id_badge`) ON DELETE CASCADE;

--
-- Limiti per la tabella `utente_ruolo`
--
ALTER TABLE `utente_ruolo`
  ADD CONSTRAINT `utente_ruolo_ibfk_1` FOREIGN KEY (`id_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE,
  ADD CONSTRAINT `utente_ruolo_ibfk_2` FOREIGN KEY (`id_ruolo`) REFERENCES `ruolo` (`id_ruolo`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
