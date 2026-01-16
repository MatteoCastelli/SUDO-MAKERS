-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Creato il: Gen 16, 2026 alle 22:00
-- Versione del server: 10.4.32-MariaDB
-- Versione PHP: 8.0.30

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

DELIMITER $$
--
-- Procedure
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `calcola_multe_ritardo` ()   BEGIN
    -- Inserisce/aggiorna multe per prestiti in ritardo
    INSERT INTO multa (id_utente, id_prestito, importo, causale, tipo_multa, giorni_ritardo, stato)
    SELECT 
        p.id_utente,
        p.id_prestito,
        GREATEST(0, DATEDIFF(NOW(), p.data_scadenza) - 3) * 0.50 as importo,
        CONCAT('Ritardo restituzione - ', l.titolo) as causale,
        'ritardo' as tipo_multa,
        DATEDIFF(NOW(), p.data_scadenza) as giorni_ritardo,
        'non_pagata' as stato
    FROM prestito p
    JOIN copia c ON p.id_copia = c.id_copia
    JOIN libro l ON c.id_libro = l.id_libro
    WHERE p.data_restituzione_effettiva IS NULL
    AND p.data_scadenza < NOW()
    AND DATEDIFF(NOW(), p.data_scadenza) > 3
    ON DUPLICATE KEY UPDATE
        importo = GREATEST(0, DATEDIFF(NOW(), p.data_scadenza) - 3) * 0.50,
        giorni_ritardo = DATEDIFF(NOW(), p.data_scadenza);
END$$

DELIMITER ;

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

--
-- Dump dei dati per la tabella `autore`
--

INSERT INTO `autore` (`id_autore`, `nome`, `cognome`, `biografia`) VALUES
(1, 'George', 'Orwell', NULL),
(2, 'J.K.', 'Rowling', NULL),
(3, 'Italo', 'Calvino', NULL),
(4, 'Haruki', 'Murakami', NULL),
(5, 'Ken', 'Follett', NULL),
(6, 'Frances', 'Hardinge', NULL),
(7, 'Victor', 'Hugo', NULL),
(8, 'Gustave', 'Flaubert', NULL),
(9, 'James', 'Rollins', NULL),
(10, 'Oliver', 'Sacks', NULL),
(11, 'Hanya', 'Yanagihara', NULL),
(12, 'Azar', 'Nafisi', NULL),
(13, 'Manlio', 'Dinucci', NULL),
(14, 'Massimo Citro Della', 'Riva', NULL),
(15, 'Dan', 'Brown', NULL),
(16, 'Antonio', 'Manzini', NULL),
(17, 'Sally', 'Rooney', NULL),
(18, 'Mel', 'Robbins', NULL),
(19, 'Rutger', 'Bregman', NULL),
(20, 'Sarah', 'Wynn-Williams', NULL),
(21, 'Cristina Cassar', 'Scalia', NULL),
(22, 'Bibbiana', 'Cau', NULL),
(23, 'Alessandro', 'D\'Avenia', NULL),
(24, 'Peter', 'Cameron', NULL),
(25, 'Genki', 'Kawamura', NULL);

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

--
-- Dump dei dati per la tabella `badge`
--

INSERT INTO `badge` (`id_badge`, `nome`, `descrizione`, `icona`, `criterio_sblocco`, `condizione_valore`, `condizione_categoria`, `tipo`, `rarita`, `punti_esperienza`, `attivo`, `data_inizio`, `data_fine`, `ordine_visualizzazione`) VALUES
(1, 'Pagina Uno', 'Primo libro letto', '📖', 'Leggi 1 libro', 1, NULL, 'letture', 'comune', 10, 1, NULL, NULL, 1),
(2, 'Esploratore', '10 libri letti', '🗺️', 'Leggi 10 libri', 10, NULL, 'letture', 'raro', 50, 1, NULL, NULL, 2),
(3, 'Maratoneta', '100 libri letti', '🏆', 'Leggi 100 libri', 100, NULL, 'letture', 'leggendario', 500, 1, NULL, NULL, 3),
(4, 'Detective del Giallo', 'Amante del giallo', '🔍', 'Leggi 5 libri gialli', 5, 'Giallo', 'genere', 'raro', 50, 1, NULL, NULL, 10),
(5, 'Custode del Fantasy', 'Fan del fantasy', '🐉', 'Leggi 5 libri fantasy', 5, 'Fantasy', 'genere', 'raro', 50, 1, NULL, NULL, 11),
(6, 'Riconsegna Lampo', 'Restituzione veloce', '⚡', 'Restituisci 5 libri in anticipo', 5, NULL, 'velocita', 'raro', 30, 1, NULL, NULL, 20),
(7, 'Lettore Curioso', '5 libri letti', '📚', 'Leggi 5 libri', 5, NULL, 'letture', 'comune', 25, 1, NULL, NULL, 2),
(8, 'Divoratore', '25 libri letti', '📕', 'Leggi 25 libri', 25, NULL, 'letture', 'raro', 100, 1, NULL, NULL, 4),
(9, 'Bibliofilo', '50 libri letti', '📗', 'Leggi 50 libri', 50, NULL, 'letture', 'epico', 200, 1, NULL, NULL, 5),
(10, 'Viaggiatore della Storia', 'Appassionato di storia', '⏳', 'Leggi 5 libri storici', 5, NULL, 'genere', 'raro', 50, 1, NULL, NULL, 12),
(11, 'Scienziato Curioso', 'Lettore di divulgazione', '🔬', 'Leggi 5 libri scientifici', 5, NULL, 'genere', 'raro', 50, 1, NULL, NULL, 13),
(12, 'Puntuale', 'Mai in ritardo', '⏰', 'Restituisci 10 libri in anticipo', 10, NULL, 'velocita', 'epico', 100, 1, NULL, NULL, 21),
(13, 'Costante', '3 mesi consecutivi', '📅', 'Prendi almeno 1 libro al mese per 3 mesi', 3, NULL, 'costanza', 'raro', 75, 1, NULL, NULL, 30),
(14, 'Fedele Lettore', '6 mesi consecutivi', '🎖️', 'Prendi almeno 1 libro al mese per 6 mesi', 6, NULL, 'costanza', 'epico', 150, 1, NULL, NULL, 31),
(15, 'Anno di Lettura', '12 mesi consecutivi', '👑', 'Prendi almeno 1 libro al mese per 12 mesi', 12, NULL, 'costanza', 'leggendario', 300, 1, NULL, NULL, 32),
(16, 'Critico Novizio', 'Prima recensione', '✍️', 'Scrivi 1 recensione', 1, NULL, 'recensione', 'comune', 15, 1, NULL, NULL, 40),
(17, 'Critico Esperto', '10 recensioni scritte', '📝', 'Scrivi 10 recensioni', 10, NULL, 'recensione', 'raro', 75, 1, NULL, NULL, 41),
(18, 'Primo della Classe', 'Top 3 lettori del mese', '🥇', 'Raggiungi il podio mensile', 3, NULL, 'speciale', 'epico', 150, 1, NULL, NULL, 50),
(19, 'Collezionista', 'Leggi tutte le categorie', '🎨', 'Leggi almeno 1 libro per categoria', 1, NULL, 'speciale', 'leggendario', 250, 1, NULL, NULL, 51);

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

--
-- Dump dei dati per la tabella `cache_raccomandazioni`
--

INSERT INTO `cache_raccomandazioni` (`id_cache`, `id_utente`, `id_libro`, `score`, `motivo_raccomandazione`, `algoritmo`, `data_generazione`) VALUES
(44, 14, 11, 10.00, 'Molto richiesto recentemente', 'hybrid', '2025-12-30 14:13:49'),
(67, 18, 29, 21.34, 'Ti piace il genere Narrativa; Molto richiesto recentemente', 'hybrid', '2026-01-16 09:29:54'),
(68, 18, 30, 21.34, 'Ti piace il genere Narrativa; Molto richiesto recentemente', 'hybrid', '2026-01-16 09:29:54'),
(69, 18, 25, 11.34, 'Ti piace il genere Narrativa', 'hybrid', '2026-01-16 09:29:54'),
(70, 18, 26, 11.34, 'Ti piace il genere Narrativa', 'hybrid', '2026-01-16 09:29:54'),
(71, 18, 14, 10.00, 'Molto richiesto recentemente', 'hybrid', '2026-01-16 09:29:54'),
(72, 18, 11, 10.00, 'Molto richiesto recentemente', 'hybrid', '2026-01-16 09:29:54'),
(73, 17, 14, 10.00, 'Molto richiesto recentemente', 'hybrid', '2026-01-16 10:37:06'),
(74, 17, 19, 10.00, 'Molto richiesto recentemente', 'hybrid', '2026-01-16 10:37:06'),
(75, 17, 28, 10.00, 'Molto richiesto recentemente', 'hybrid', '2026-01-16 10:37:06'),
(76, 17, 11, 10.00, 'Molto richiesto recentemente', 'hybrid', '2026-01-16 10:37:06'),
(77, 17, 29, 10.00, 'Molto richiesto recentemente', 'hybrid', '2026-01-16 10:37:06'),
(78, 17, 30, 10.00, 'Molto richiesto recentemente', 'hybrid', '2026-01-16 10:37:06'),
(79, 4, 19, 10.00, 'Molto richiesto recentemente', 'hybrid', '2026-01-16 10:55:43'),
(80, 4, 28, 10.00, 'Molto richiesto recentemente', 'hybrid', '2026-01-16 10:55:43'),
(81, 4, 27, 10.00, 'Molto richiesto recentemente', 'hybrid', '2026-01-16 10:55:43'),
(83, 4, 30, 10.00, 'Molto richiesto recentemente', 'hybrid', '2026-01-16 10:55:43'),
(84, 4, 6, 7.14, 'Ti piace il genere Juvenile Fiction; Hai mostrato interesse per Juvenile Fiction', 'hybrid', '2026-01-16 10:55:43'),
(85, 7, 17, 40.00, 'Apprezzato da utenti con gusti simili ai tuoi', 'hybrid', '2026-01-16 17:37:21'),
(86, 7, 19, 10.00, 'Molto richiesto recentemente', 'hybrid', '2026-01-16 17:37:21'),
(89, 7, 30, 10.00, 'Molto richiesto recentemente', 'hybrid', '2026-01-16 17:37:21');

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

--
-- Dump dei dati per la tabella `copia`
--

INSERT INTO `copia` (`id_copia`, `id_libro`, `codice_barcode`, `stato_fisico`, `disponibile`, `data_acquisizione`) VALUES
(1, 5, 'LIB0000051765702031001', 'ottimo', 0, '2025-12-14 08:47:11'),
(4, 8, 'LIB0000081765702662001', 'ottimo', 0, '2025-12-14 08:57:42'),
(87, 10, 'LIB0000101765900051001', 'ottimo', 0, '2025-12-16 15:47:31'),
(88, 10, 'LIB0000101765900051002', 'ottimo', 0, '2025-12-16 15:47:31'),
(91, 10, 'LIB0000101765900051005', 'ottimo', 1, '2025-12-16 15:47:31'),
(92, 10, 'LIB0000101765900051006', 'ottimo', 1, '2025-12-16 15:47:31'),
(93, 5, 'LIB0000051765900123001', 'ottimo', 0, '2025-12-16 15:48:43'),
(95, 5, 'LIB0000051765900123003', 'ottimo', 1, '2025-12-16 15:48:43'),
(96, 11, 'LIB0000111765963109001', 'ottimo', 0, '2025-12-17 09:18:29'),
(98, 11, 'LIB0000111765963109003', 'ottimo', 0, '2025-12-17 09:18:29'),
(99, 12, 'LIB0000121765963182001', 'ottimo', 0, '2025-12-17 09:19:42'),
(100, 13, 'LIB0000131765963392001', 'buono', 0, '2025-12-17 09:23:12'),
(101, 14, 'LIB0000141765963474001', 'ottimo', 0, '2025-12-17 09:24:34'),
(103, 16, 'LIB0000161767088034001', 'buono', 1, '2025-12-30 09:47:14'),
(104, 17, 'LIB0000171767088208001', 'ottimo', 0, '2025-12-30 09:50:08'),
(105, 17, 'LIB0000171767096704001', 'ottimo', 1, '2025-12-30 12:11:44'),
(106, 17, 'LIB0000171767096704002', 'ottimo', 1, '2025-12-30 12:11:44'),
(107, 14, 'LIB0000141767106553001', 'ottimo', 1, '2025-12-30 14:55:53'),
(108, 14, 'LIB0000141767106553002', 'ottimo', 1, '2025-12-30 14:55:53'),
(109, 14, 'LIB0000141767106553003', 'ottimo', 1, '2025-12-30 14:55:53'),
(110, 14, 'LIB0000141767106553004', 'buono', 0, '2025-12-30 14:55:53'),
(112, 19, 'LIB0000191768033689001', 'buono', 1, '2026-01-10 08:28:09'),
(113, 19, 'LIB0000191768033689002', 'ottimo', 1, '2026-01-10 08:28:09'),
(114, 19, 'LIB0000191768033689003', 'ottimo', 1, '2026-01-10 08:28:09'),
(115, 20, 'LIB0000201768033758001', 'ottimo', 1, '2026-01-10 08:29:18'),
(116, 20, 'LIB0000201768033758002', 'ottimo', 1, '2026-01-10 08:29:18'),
(117, 20, 'LIB0000201768033758003', 'ottimo', 1, '2026-01-10 08:29:18'),
(118, 20, 'LIB0000201768033758004', 'ottimo', 1, '2026-01-10 08:29:18'),
(119, 21, 'LIB0000211768033835001', 'ottimo', 1, '2026-01-10 08:30:35'),
(120, 21, 'LIB0000211768033835002', 'ottimo', 1, '2026-01-10 08:30:35'),
(121, 22, 'LIB0000221768033895001', 'ottimo', 1, '2026-01-10 08:31:35'),
(122, 23, 'LIB0000231768034031001', 'buono', 1, '2026-01-10 08:33:51'),
(123, 23, 'LIB0000231768034031002', 'ottimo', 1, '2026-01-10 08:33:51'),
(124, 23, 'LIB0000231768034031003', 'ottimo', 1, '2026-01-10 08:33:51'),
(125, 23, 'LIB0000231768034031004', 'ottimo', 1, '2026-01-10 08:33:51'),
(126, 24, 'LIB0000241768034113001', 'buono', 1, '2026-01-10 08:35:13'),
(127, 25, 'LIB0000251768034245001', 'ottimo', 1, '2026-01-10 08:37:25'),
(128, 25, 'LIB0000251768034245002', 'ottimo', 1, '2026-01-10 08:37:25'),
(129, 25, 'LIB0000251768034245003', 'ottimo', 1, '2026-01-10 08:37:25'),
(130, 25, 'LIB0000251768034245004', 'ottimo', 1, '2026-01-10 08:37:25'),
(131, 25, 'LIB0000251768034245005', 'ottimo', 1, '2026-01-10 08:37:25'),
(132, 26, 'LIB0000261768034298001', 'ottimo', 0, '2026-01-10 08:38:18'),
(133, 26, 'LIB0000261768034298002', 'ottimo', 1, '2026-01-10 08:38:18'),
(134, 26, 'LIB0000261768034298003', 'ottimo', 1, '2026-01-10 08:38:18'),
(135, 27, 'LIB0000271768034354001', 'ottimo', 1, '2026-01-10 08:39:14'),
(136, 27, 'LIB0000271768034354002', 'ottimo', 1, '2026-01-10 08:39:14'),
(137, 27, 'LIB0000271768034354003', 'ottimo', 1, '2026-01-10 08:39:14'),
(138, 27, 'LIB0000271768034354004', 'ottimo', 1, '2026-01-10 08:39:14'),
(139, 27, 'LIB0000271768034354005', 'ottimo', 1, '2026-01-10 08:39:14'),
(140, 27, 'LIB0000271768034354006', 'ottimo', 1, '2026-01-10 08:39:14'),
(141, 27, 'LIB0000271768034354007', 'ottimo', 1, '2026-01-10 08:39:14'),
(142, 27, 'LIB0000271768034354008', 'ottimo', 1, '2026-01-10 08:39:14'),
(143, 27, 'LIB0000271768034354009', 'ottimo', 1, '2026-01-10 08:39:14'),
(144, 27, 'LIB0000271768034354010', 'ottimo', 1, '2026-01-10 08:39:14'),
(145, 28, 'LIB0000281768034432001', 'buono', 1, '2026-01-10 08:40:32'),
(146, 29, 'LIB0000291768034608001', 'ottimo', 1, '2026-01-10 08:43:28'),
(147, 30, 'LIB0000301768034653001', 'ottimo', 1, '2026-01-10 08:44:13'),
(148, 30, 'LIB0000301768034653002', 'ottimo', 1, '2026-01-10 08:44:13'),
(149, 30, 'LIB0000301768034653003', 'ottimo', 1, '2026-01-10 08:44:13'),
(150, 30, 'LIB0000301768034653004', 'ottimo', 1, '2026-01-10 08:44:13');

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

--
-- Dump dei dati per la tabella `feedback_raccomandazione`
--

INSERT INTO `feedback_raccomandazione` (`id_feedback`, `id_utente`, `id_libro`, `feedback`, `motivo`, `data_feedback`) VALUES
(1, 4, 10, 'thumbs_down', NULL, '2025-12-16 15:18:31'),
(2, 4, 11, 'thumbs_down', NULL, '2025-12-19 08:35:40'),
(3, 4, 12, 'thumbs_down', NULL, '2025-12-19 08:35:41'),
(5, 7, 28, 'thumbs_down', NULL, '2026-01-16 20:00:35'),
(6, 7, 29, 'thumbs_down', NULL, '2026-01-16 20:00:36'),
(7, 4, 28, 'thumbs_up', NULL, '2026-01-16 20:35:51'),
(8, 4, 29, 'thumbs_down', NULL, '2026-01-16 20:35:52');

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

--
-- Dump dei dati per la tabella `interazione_utente`
--

INSERT INTO `interazione_utente` (`id_interazione`, `id_utente`, `id_libro`, `tipo_interazione`, `data_interazione`, `durata_visualizzazione`, `fonte`) VALUES
(1, 4, 5, 'view_dettaglio', '2025-12-16 15:14:40', 3, 'homepage'),
(2, 4, 8, 'click', '2025-12-16 15:17:43', NULL, 'catalogo'),
(3, 4, 8, 'click', '2025-12-16 15:18:04', NULL, 'trending'),
(4, 4, 8, 'view_dettaglio', '2025-12-16 15:18:08', 3, 'trending'),
(10, 4, 10, 'click', '2025-12-18 08:27:19', NULL, 'unknown'),
(11, 4, 5, 'click', '2025-12-18 08:28:11', NULL, 'trending'),
(12, 4, 5, 'click', '2025-12-18 08:28:14', NULL, 'trending'),
(13, 4, 5, 'click', '2025-12-18 08:28:16', NULL, 'trending'),
(14, 4, 5, 'click', '2025-12-18 08:28:18', NULL, 'trending'),
(15, 4, 5, 'click', '2025-12-18 08:28:19', NULL, 'trending'),
(16, 4, 5, 'click', '2025-12-18 08:28:21', NULL, 'trending'),
(17, 7, 10, 'view_dettaglio', '2025-12-18 09:15:57', 3, 'homepage_trending'),
(18, 7, 5, 'view_dettaglio', '2025-12-18 09:19:24', 12, 'direct'),
(20, 7, 5, 'view_dettaglio', '2025-12-18 09:21:50', 6, 'homepage'),
(21, 7, 8, 'click', '2025-12-18 09:21:52', NULL, 'libri_correlati'),
(22, 7, 12, 'click', '2025-12-18 09:21:53', NULL, 'libri_correlati'),
(23, 7, 8, 'click', '2025-12-18 09:21:55', NULL, 'libri_correlati'),
(24, 7, 5, 'click', '2025-12-18 09:21:56', NULL, 'libri_correlati'),
(25, 7, 10, 'click', '2025-12-18 09:22:10', NULL, 'libri_correlati'),
(27, 7, 12, 'click', '2025-12-18 09:22:14', NULL, 'libri_correlati'),
(28, 7, 11, 'click', '2025-12-18 09:22:15', NULL, 'libri_correlati'),
(29, 7, 12, 'click', '2025-12-18 09:22:18', NULL, 'libri_correlati'),
(30, 7, 8, 'click', '2025-12-18 09:22:19', NULL, 'libri_correlati'),
(31, 7, 5, 'click', '2025-12-18 09:22:20', NULL, 'libri_correlati'),
(32, 7, 11, 'click', '2025-12-18 09:22:22', NULL, 'libri_correlati'),
(33, 7, 8, 'click', '2025-12-19 08:08:11', NULL, 'catalogo'),
(34, 7, 8, 'view_dettaglio', '2025-12-19 08:08:22', 10, 'homepage'),
(35, 7, 8, 'click', '2025-12-19 08:11:46', NULL, 'trending'),
(36, 7, 12, 'click', '2025-12-19 08:13:55', NULL, 'trending'),
(37, 7, 12, 'view_dettaglio', '2025-12-19 08:14:09', 14, 'trending'),
(39, 4, 12, 'view_dettaglio', '2025-12-19 08:32:27', 8, 'homepage_trending'),
(40, 4, 12, 'click', '2025-12-19 08:32:33', NULL, 'libri_correlati'),
(42, 4, 12, 'view_dettaglio', '2025-12-19 08:33:22', 49, 'libro_correlato'),
(44, 4, 12, 'view_dettaglio', '2025-12-19 08:33:27', 4, 'libro_correlato'),
(45, 4, 8, 'view_dettaglio', '2025-12-19 08:33:49', 3, 'direct'),
(46, 4, 11, 'view_dettaglio', '2025-12-19 08:36:05', 7, 'homepage'),
(47, 4, 11, 'view_dettaglio', '2025-12-19 08:39:06', 4, 'homepage_trending'),
(50, 4, 6, 'view_dettaglio', '2025-12-19 11:33:42', 3, 'homepage'),
(51, 4, 5, 'click', '2025-12-19 11:34:58', NULL, 'trending'),
(52, 4, 5, 'click', '2025-12-19 11:35:01', NULL, 'trending'),
(54, 4, 6, 'view_dettaglio', '2025-12-19 11:46:53', 5, 'homepage'),
(55, 4, 12, 'view_dettaglio', '2025-12-19 11:47:42', 4, 'homepage_trending'),
(56, 4, 8, 'click', '2025-12-19 11:57:02', NULL, 'trending'),
(57, 4, 8, 'view_dettaglio', '2025-12-19 11:57:06', 3, 'trending'),
(59, 4, 11, 'view_dettaglio', '2025-12-19 12:10:18', 17, 'homepage_trending'),
(60, 4, 6, 'view_dettaglio', '2025-12-19 12:19:15', 6, 'homepage'),
(61, 4, 12, 'click', '2025-12-29 10:39:34', NULL, 'trending'),
(62, 4, 12, 'click', '2025-12-29 10:39:36', NULL, 'trending'),
(63, 4, 14, 'click', '2025-12-29 13:19:53', NULL, 'trending'),
(64, 4, 8, 'click', '2025-12-29 13:20:51', NULL, 'trending'),
(65, 4, 8, 'view_dettaglio', '2025-12-29 13:24:48', 236, 'trending'),
(67, 4, 16, 'view_dettaglio', '2025-12-30 09:47:21', 5, 'direct'),
(68, 4, 17, 'view_dettaglio', '2025-12-30 09:50:34', 18, 'direct'),
(69, 4, 17, 'view_dettaglio', '2025-12-30 09:50:45', 4, 'direct');

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

--
-- Dump dei dati per la tabella `libro`
--

INSERT INTO `libro` (`id_libro`, `titolo`, `editore`, `anno_pubblicazione`, `isbn`, `ean`, `categoria`, `descrizione`, `immagine_copertina_url`, `collocazione`, `data_inserimento`) VALUES
(5, 'I pilastri della terra', NULL, 2016, '9788804666929', '9788804666929', 'Fiction', 'Un mystery, una storia d\'amore, una grande rievocazione storica: nella sua opera più ambiziosa e acclamata, Ken Follett tocca una dimensione epica, trasportandoci nell\'Inghilterra medievale al tempo della costruzione di una cattedrale gotica. Intreccio, azione e passioni si sviluppano così sullo sfondo di un\'era ricca di intrighi e cospirazioni, pericoli e minacce, guerre civili, carestie, conflitti religiosi e lotte per la successione al trono. Con la stessa suspense che caratterizza tutti i suoi thriller, Follett ricrea un\'epoca scomparsa e affascinante. Foreste, castelli e monasteri sono l\'avvolgente paesaggio, mosso dai ritmi della vita quotidiana e dalla pressione di eventi storici e naturali entro il quale per circa quarant\'anni si confrontano e si scontrano le segrete aspirazioni e i sentimenti dei protagonisti - monaci, mercanti, artigiani, nobili, fanciulle misteriose -, vittime o pedine di avvenimenti che ne segnano i destini e rimettono continuamente in discussione la costruzione della cattedrale.\n', 'https://books.google.com/books/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', NULL, '2025-12-14 08:47:11'),
(6, 'L\'albero delle bugie', 'Mondadori', 2016, '9788804664925', '9788804664925', 'Juvenile Fiction', 'Fin da quando era piccola Faith ha imparato a nascondere dietro le buone maniere la sua intelligenza acuta e ardente: nell\'Inghilterra vittoriana questo è ciò che devono fare le brave signorine. Figlia del reverendo Sunderly, esperto studioso di fossili, Faith deve fingere di non essere attratta dai misteri della scienza, di non avere fame di conoscenza, di non sognare la libertà. Tutto cambia dopo la morte del padre: frugando tra oggetti e documenti misteriosi, Faith scopre l\'esistenza di un albero incredibile, che si nutre di bugie per dar vita a frutti magici capaci di rivelare segreti. È proprio grazie al potere oscuro di questo albero che Faith fa esplodere il coraggio e la rabbia covati per anni, alla ricerca della verità e del suo posto nel mondo. Magia, scienza e desiderio di libertà si incontrano in un questo romanzo, con una coraggiosa eroina che rompe gli schemi, nel solco di Jane Eyre. Età di lettura: da 13 anni.\n', 'https://books.google.com/books/content?id=6Zg2vgAACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', NULL, '2025-12-14 08:54:16'),
(7, 'Una ragazza senza ricordi', 'Oscar bestsellers', 2019, '9788804707783', '9788804707783', 'Juvenile Fiction', 'Triss ha un\'unica certezza: da quando è caduta nel fiume Ma-caber, nella sua vita tutto è cambiato. Era una notte buia, di cui non riesce a ricordare nulla. I minuti passati sott\'acqua sembrano averla trasformata: Pen, la sorellina di nove anni, ha paura di lei, e continua a dire che in realtà Triss non è più Triss. Sembrano pensarla così anche i suoi genitori, che bisbigliano sottovoce dietro porte chiuse celando segreti e misteri, come le lettere che continuano a ricevere da Sebastian, il figlio morto in battaglia durante la Prima guerra mondiale. E intanto Triss ha continuamente fame, una fame insaziabile e brutale, piange lacrime di ragnatela e si ritrova in un corpo sempre più fragile, che sembra fatto di foglie e fango. Ben presto, Triss scopre l\'esistenza di un perfido architetto che vive tra il mondo reale e l\'Altronde, una dimensione popolata di malevole creature senza volto, ed è lì che Triss e Pen devono avventurarsi, prima che sia troppo tardi. Età di lettura. da 12 anni.\n', 'https://books.google.com/books/content?id=FSa_wQEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', NULL, '2025-12-14 08:55:51'),
(8, 'L\'Ultimo giorno di un condannato', 'Universale Economica', 2016, '9788807902499', '9788807902499', 'Fiction', 'È anonimo l\'autore che, nel 1829, dà alle stampe questo piccolo, gigantesco libro. Ma è inconfondibilmente Victor Hugo. Sono anni in cui il progresso sembra trasportare l\'umanità intera, sul suo dorso poderoso, verso un futuro di pace, prosperità, ricchezza e fratellanza. Ma negli stessi anni si tagliano ancora teste davanti a un pubblico pagante, si marcisce in carcere, ci si lascia morire per una colpa non sempre dimostrata oltre ogni ragionevole dubbio. Hugo parla a nome dell\'umanità, come sempre, e lo fa attraverso la voce di un uomo qualunque, di un condannato qualunque, di un miserabile che rappresenta tutti i miserabili di tutte le nazioni e tutte le epoche. Un crimine di cui non conosciamo i dettagli lo ha fatto gettare in una cella. Persone di cui non conosciamo il nome dispongono della sua vita, come divinità autoproclamate. Un\'angoscia di cui conosciamo fin troppo bene la lama lo tortura, giorno dopo giorno, e gli fa desiderare che il tempo corra sempre più veloce. Verso la fine dell\'attesa, venga essa con la liberazione o con l\'oblio.\n', 'https://books.google.com/books/content?id=U3--DAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', NULL, '2025-12-14 08:57:42'),
(10, 'La biblioteca perduta', 'Nord', 2025, '9788842937456', '9788842937456', 'Fiction', 'Arcipelago delle Svalbard, 1764. Nove cadaveri congelati in unâ€™angusta caverna. Ãˆ questo il macabro spettacolo che si presenta davanti al comandante Vasilij ÄŒiÄagov. Tuttavia, la sua attenzione non Ã¨ concentrata sui corpi, bensÃ¬ su ciÃ² che li circonda: la zanna di un mammut su cui sono scolpite misteriose piramidi; e un monito inciso su una roccia: Non varcate quella soglia, non destate ciÃ² che vi riposaâ€¦\r\nMosca, oggi. Monsignor Alessio Borrelli, membro della Pontificia commissione di archeologia sacra del Vaticano, non crede ai suoi occhi: in quella cripta appena riaperta sono state rinvenute alcune casse che contengono libri rarissimi ed esemplari unici. In lui sorge il sospetto che siano addirittura parte della Biblioteca dâ€™Oro, lâ€™immensa collezione di volumi andata perduta dopo la morte di Ivan il Terribile, che si dice contenesse testi introvabiliâ€¦ e pericolosi. Ma non câ€™Ã¨ tempo per accertarsene, perchÃ© scatta una trappola e Borrelli cade vittima di unâ€™imboscata. Prima di morire, perÃ², riesce a mandare una richiesta di aiuto alla Sigma Force. ToccherÃ  quindi a Gray Pierce e ai suoi compagni scoprire chi si cela dietro quellâ€™attentato e districare la matassa di indizi che legano il mito della Biblioteca dâ€™Oro a quello di una misteriosa spedizione artica partita nel 1764 per ordine di Caterina la Grande. Un mito che adesso rischia di distruggere il mondo.\r\n\r\nUN CONTINENTE SCOMPARSO.\r\n\r\nUNA MISSIONE SEGRETA.\r\n\r\nUN MITO CHE RISCHIA DI DISTRUGGERE IL MONDO.', 'https://books.google.com/books/content?id=B_en0QEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', NULL, '2025-12-14 09:14:24'),
(11, 'L\'uomo che scambiÃ² sua moglie per un cappello', 'Adelphi', 2001, '9788845916250', '9788845916250', 'Fiction', 'Il saggio Ã¨ articolato in quattro sezioni, ognuna delle quali raggruppa una serie di casi clinici anche molto diversi tra loro, ma accomunati dalla natura della disfunzione primaria che li ha generati. Le quattro sezioni sono rispettivamente: \"Perdite\", \"Eccessi\", \"Trasporti\", \"Il mondo dei semplici\"', 'https://books.google.com/books/content?id=U2DMAAAACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', NULL, '2025-12-17 09:18:29'),
(12, 'Una vita come tante', 'Sellerio Editore Palermo', 2016, '9788838935688', '9788838935688', 'Fiction', 'In una New York fervida e sontuosa vivono quattro ragazzi, ex compagni di college, che da sempre sono stati vicini lâ€™uno allâ€™altro. Si sono trasferiti nella metropoli da una cittadina del New England, e all\'inizio sono sostenuti solo dalla loro amicizia e dall\'ambizione. Willem, dall\'animo gentile, vuole fare lâ€™attore. JB, scaltro e a volte crudele, insegue un accesso al mondo dell\'arte. Malcolm Ã¨ un architetto frustrato in uno studio prestigioso. Jude, avvocato brillante e di enigmatica riservatezza, Ã¨ il loro centro di gravitÃ . Nei suoi riguardi lâ€™affetto e la solidarietÃ  prendono una piega differente, per lui i ragazzi hanno una cura particolare, una sensibilitÃ  speciale e tormentata, perchÃ© la sua vita sempre oscilla tra la luce del riscatto e il baratro dellâ€™autodistruzione. Intorno a Jude, al suo passato, alla sua lotta per conquistarsi un futuro, si plasmano campi di forze e tensioni, lealtÃ  e tradimenti, sogni e disperazione. E la sua storia diventa una disamina, magnifica e perturbante, della crudeltÃ  umana e del potere taumaturgico dellâ€™amicizia', 'https://books.google.com/books/content?id=Y8OpDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', NULL, '2025-12-17 09:19:42'),
(13, 'Leggere pericolosamente. Il potere sovversivo della letteratura in tempi difficili', 'Adelphi', 2024, '9788845938603', '9788845938603', 'Biography & Autobiography', 'Â«FinchÃ© possiamo immaginare, siamo liberiÂ» ha detto David Grossman. Ma â€“ si potrebbe obiettare â€“ non sarÃ  un lusso riservato agli scrittori? In altre parole: la letteratura esercita un effettivo potere sulla nostra vita quotidiana?\r\n\r\nÂ«Rushdie, Platone, Bradbury, Hurston, Morrison, Grossman, Ackerman, Khoury, Atwood, Baldwin e Coates: Azar Nafisi si serve di questi autori per dar voce ai suoi pensieri e alle sue preoccupazioni. Come sempre, lâ€™autrice ci risveglia dal nostro torpore mettendoci davanti agli occhi una cruda realtÃ , senza mai lasciarci soli e accompagnandoci mano nella mano in un viaggio letterario dalle note agrodolci. Â» - Gaia Ferrari, Maremosso', 'https://books.google.com/books/content?id=7Uqo0AEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', NULL, '2025-12-17 09:23:12'),
(14, 'La guerra del Peloponneso. Testo greco a fronte', 'Feltrinelli', 2024, '9788807904622', '9788807904622', 'Literary Collections', 'Nel 431 a.C. scoppiò tra Atene e Sparta la guerra del Peloponneso: una guerra che insanguinò la Grecia per quasi trent’anni e segnò la cupa fine del periodo d’oro della civiltà ellenica. Tucidide, consapevole di vivere un evento di portata eccezionale, lo assunse come momento esemplare di un’analisi che mirava a cogliere, al di là dei nudi fatti, le forze profonde sottese ai processi della Storia. Un’analisi lucida e disillusa che spinge il lettore a interrogarsi sui problemi che si ripresentano sempre uguali alla coscienza storica: i meccanismi e la moralità del potere, gli arbitrii e i diritti dei vincitori e dei vinti, la giustizia dei potenti e la giustizia dei deboli.', 'https://books.google.com/books/content?id=bBme0AEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', NULL, '2025-12-17 09:24:34'),
(16, 'Diversamente sani. Manuale per meglio sopravvivere ai medici e alle malattie', NULL, 2024, '9791280657596', '9791280657596', 'Health & Fitness', NULL, 'https://books.google.com/books/content?id=TTKt0AEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', NULL, '2025-12-30 09:47:14'),
(17, 'La prima Bibbia. Per la famiglia, la catechesi e la scuola', NULL, 1998, '9788821535949', '9788821535949', 'Juvenile Nonfiction', NULL, 'https://books.google.com/books/content?id=CVtcAAAACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', NULL, '2025-12-30 09:50:08'),
(19, 'L\'ultimo segreto', 'Rizzoli', 2025, '9788817174459', '9788817174459', 'Narrativa', 'A quasi dieci anni dal suo ultimo successo, Dan Brown torna con il suo romanzo piÃ¹ ambizioso ed emozionante: una nuova caccia di Robert Langdon dove, come sempre nei suoi libri, nulla Ã¨ piÃ¹ pericoloso della conoscenza, e nulla Ã¨ piÃ¹ efficace di una mente affilata.', 'https://books.google.com/books/content?id=Zn-X0QEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', 'A7', '2026-01-10 08:28:09'),
(20, 'Sotto mentite spoglie', 'Sellerio Editore Palermo', 2025, '9788838948978', '9788838948978', 'Narrativa', 'Passo dopo passo, perÃ², anche se stanco, amareggiato, arrabbiato, Rocco Schiavone continua a guardare il mondo con gli occhi socchiusi, a indignarsi, a tenere insieme il cuore e il cervello, la memoria e il futuro.', 'https://books.google.com/books/content?id=HQ2r0QEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', 'A24', '2026-01-10 08:29:18'),
(21, 'Normal People', 'Faber & Faber Limited', 2019, '9780571334650', '9780571334650', 'Studenti universitari', 'Connell e Marianne crescono nella stessa piccola cittÃ  nell\'ovest dell\'Irlanda, ma le somiglianze finiscono lÃ¬. A scuola, Connell Ã¨ popolare e benvoluto, mentre Marianne Ã¨ una solitaria. Ma quando i due iniziano una conversazione - imbarazzante ma elettrizzante - inizia qualcosa che cambia la vita. Normal People Ã¨ una storia di reciproco fascino, amicizia e amore. Ci porta da quella prima conversazione agli anni successivi, in compagnia di due persone che cercano di stare lontane ma scoprono di non poterlo fare.', 'https://books.google.com/books/content?id=bhSougEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', 'B23', '2026-01-10 08:30:35'),
(22, 'The Let Them Theory', 'Hay House UK Ltd', 2024, '9781788176187', '9781788176187', 'Psicologia', '#1 New York Times Bestseller #1 Sunday Times Bestseller #1 Amazon Bestseller #1 Audible Bestseller A Life-Changing Tool Millions of People Canâ€™t Stop Talking About What if the key to happiness, success, and love was as simple as two words? If you\'ve ever felt stuck, overwhelmed, or frustrated with where you are, the problem isn\'t you. The problem is the power you give to other people. Two simple wordsâ€”Let Themâ€”will set you free. Free from the opinions, drama, and judgments of others. Free from the exhausting cycle of trying to manage everything and everyone around you. The Let Them Theory puts the power to create a life you love back in your handsâ€”and this book will show you exactly how to do it. In her latest groundbreaking book, The Let Them Theory, Mel Robbinsâ€”New York Times bestselling author and one of the world\'s most respected experts on motivation, confidence, and mindsetâ€”teaches you how to stop wasting energy on what you can\'t control and start focusing on what truly matters: YOU. Your happiness. Your goals. Your life. Using the same no-nonsense, science-backed approach that\'s made The Mel Robbins Podcast a global sensation, Robbins explains why The Let Them Theory is already loved by millions and how you can apply it in eight key areas of your life to make the biggest impact. Within a few pages, you\'ll realize how much energy and time you\'ve been wasting trying to control the wrong thingsâ€”at work, in relationships, and in pursuing your goalsâ€”and how this is keeping you from the happiness and success you deserve. Written as an easy-to-understand guide, Robbins shares relatable stories from her own life, highlights key takeaways, relevant research and introduces you to world-renowned experts in psychology, neuroscience, relationships, happiness, and ancient wisdom who champion The Let Them Theory every step of the way. Learn how to: Â· Stop wasting energy on things you can\'t control Â· Stop comparing yourself to other people Â· Break free from fear and self-doubt Â· Release the grip of people\'s expectations Â· Build the best friendships of your life Â· Create the love you deserve Â· Pursue what truly matters to you with confidence Â· Build resilience against everyday stressors and distractions Â· Define your own path to success, joy, and fulfillment . . . and so much more.', 'https://books.google.com/books/content?id=ZEbT0AEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', 'B24', '2026-01-10 08:31:35'),
(23, 'Humankind', 'Bloomsbury Publishing', 2021, '9781408898956', '9781408898956', 'Economia aziendale', 'THE INTERNATIONAL BESTSELLER A Guardian, Daily Telegraph, New Statesman and Daily Express Book of the Year \'Hugely, highly and happily recommended\' Stephen Fry \'You should read Humankind. You\'ll learn a lot (I did) and you\'ll have good reason to feel better about the human race\' Tim Harford \'Made me see humanity from a fresh perspective\' Yuval Noah Harari It\'s a belief that unites the left and right, psychologists and philosophers, writers and historians. It drives the headlines that surround us and the laws that touch our lives. From Machiavelli to Hobbes, Freud to Dawkins, the roots of this belief have sunk deep into Western thought. Human beings, we\'re taught, are by nature selfish and governed by self-interest. Humankind makes a new argument: that it is realistic, as well as revolutionary, to assume that people are good. By thinking the worst of others, we bring out the worst in our politics and economics too. In this major book, internationally bestselling author Rutger Bregman takes some of the world\'s most famous studies and events and reframes them, providing a new perspective on the last 200,000 years of human history. From the real-life Lord of the Flies to the Blitz, a Siberian fox farm to an infamous New York murder, Stanley Milgram\'s Yale shock machine to the Stanford prison experiment, Bregman shows how believing in human kindness and altruism can be a new way to think - and act as the foundation for achieving true change in our society. It is time for a new view of human nature.', 'https://books.google.com/books/content?id=U4S9zQEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', 'B45', '2026-01-10 08:33:51'),
(24, 'Careless People', 'Macmillan', 2025, '9781035065936', '9781035065936', 'Biografia e autobiografia', 'The #1 global bestsellerA Book of the Year for Audible, The Times, Financial Times, The New York Times, Time, Cosmopolitan, The Economist, Spectator and more Winner of the Blueprint Asia-Pacific Whistleblowing Prize 2025 Shortlisted for the Westminster Book Awards 2025 Shortlisted for the Hatchards First Biography Prize 2025 â€˜How else to put this? Bloody hellâ€™ â€“ The Guardian â€˜Devastating . . . Funny . . . Highly enjoyableâ€™ â€“ The Times â€˜Jaw-dropping . . . A tell-all tomeâ€™ â€“ Financial Times Sarah Wynn-Williams joined Facebook believing the company could change things for the better. Instead, what she encountered over seven years was so shocking that Meta obtained a legal order to silence her. Now you can read her story. Candid and entertaining, Wynn-Williamsâ€™ account pulls back the curtain on Mark Zuckerberg, Sheryl Sandberg and the global elite. She exposes the true cost of Silicon Valleyâ€™s ambition, from outrageous schemes cooked up on private jets to the alarming consequences of Facebookâ€™s aggressive pursuit of global dominance. Careless People is a gripping and utterly explosive read that will forever change how you view the technology that runs our lives â€“ and the unchecked power of those who control it. â€˜Amazing: of all the books in all the world Mr Free Speech Zuckerberg wants to ban, itâ€™s the one about himâ€™ â€“ Marina Hyde â€˜A Bridget Jonesâ€™s Diary-style tale of a young woman thrown into a series of improbable situationsâ€™ â€“ The Times â€˜Darkly funny and genuinely shocking: an ugly, detailed portrait of one of the most powerful companies in the worldâ€™ â€“ The New York Times', 'https://books.google.com/books/content?id=f5ka0QEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', 'C3', '2026-01-10 08:35:13'),
(25, 'Mandorla amara', 'Einaudi', 2025, '9788806260309', '9788806260309', 'Narrativa', 'Sette cadaveri su uno yacht alla deriva. Causa della morte, avvelenamento. Un delitto quanto mai insolito che spalanca un abisso di ipotesi, sospetti e stranezze in cui Vanina Guarrasi, nonostante il difficile momento personale, Ã¨ pronta a calarsi.', 'https://books.google.com/books/content?id=ifGy0QEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', 'E4', '2026-01-10 08:37:25'),
(26, 'La levatrice', 'Nord', 2025, '9788842936428', '9788842936428', 'Narrativa', 'Una storia di coraggio, riscatto e libertÃ .', 'https://books.google.com/books/content?id=FpFg0QEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', 'C4', '2026-01-10 08:38:18'),
(27, 'I burger di Ciccio', 'Salani', 2023, '9791259573162', '9791259573162', 'Cucina', 'Allacciate i grembiuli. Accendete i fornelli. Câ€™Ã¨ Ciccio in cucina. A pranzo, a cena, per un sostanzioso spuntino, ogni momento Ã¨ quello buono per uno dei miei favolosi burger. Partendo da ingredienti semplici, combinati con una buona dose di fantasia, daremo vita insieme a creazioni squisite, perfette per stupire il vostro palato e quello dei vostri fortunati ospiti. In questo libro troverete classici intramontabili come il Cacio e Pepe e il BBQ, ma anche panini originali e ricette mai viste prima, dal mio burger a base di sushi a quello farcito di gelato e marshmallow. E se non ne avete mai abbastanza di hamburger, ho messo in caldo tanti spunti e idee per appagare la vostra curiositÃ . Per diventare veri chef si incomincia dalle basi: imparerete come scegliere gli ingredienti migliori â€“ dal pesce alla carne, passando per verdure, formaggi e salse â€“, e quali sono gli strumenti imprescindibili in una cucina che si rispetti. E ancora, scoprirete quali sono i migliori panini del mondo e dove gustarli, e quali sono le ricette piÃ¹ strane e creative mai realizzate (hamburger di ramen vi dice qualcosa?!). Siete pronti a sfidarmi e a preparare il vostro burger da leccarsi i baffi?', 'https://books.google.com/books/content?id=RH62EAAAQBAJ&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api', 'A1', '2026-01-10 08:39:14'),
(28, 'Bianca come il latte, rossa come il sangue', 'Edizioni Mondadori', 2010, '9788852012495', '9788852012495', 'Narrativa', 'Leo Ã¨ un sedicenne come tanti: ama le chiacchiere con gli amici, il calcetto, le scorribande in motorino e vive in perfetta simbiosi con il suo iPod. Le ore passate a scuola sono uno strazio, i professori \"una specie protetta che speri si estingua definitivamente\".', 'https://books.google.com/books/content?id=PBOTc8CEpaMC&printsec=frontcover&img=1&zoom=1&edge=curl&source=gbs_api', 'A2', '2026-01-10 08:40:32'),
(29, 'Un giorno questo dolore ti sarÃ  utile', 'Adelphi', 2010, '9788845925023', '9788845925023', 'Narrativa', 'Â«Avrei passato il resto della mia vita in transito, protetto dal treno, mentre questo mondo impossibile e disgraziato sfrecciava fuori dal finestrino.Â»', 'https://books.google.com/books/content?id=bFCFRQAACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', 'C5', '2026-01-10 08:43:28'),
(30, 'Se i gatti scomparissero dal mondo', 'Einaudi', 2020, '9788806245702', '9788806245702', 'Narrativa', 'Cosa sei disposto a dare al Diavolo per poter vivere un giorno in piÃº? Attento: ciÃ² che il Diavolo sceglierÃ  di prendersi sparirÃ  dal mondo, per tutti. I telefonini? Va bene. E i film, gli orologi... d\'accordo, ma i gatti? Sei pronto a rinunciare ai gatti?', 'https://books.google.com/books/content?id=q6ORzQEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api', 'E4', '2026-01-10 08:44:13');

-- --------------------------------------------------------

--
-- Struttura della tabella `libro_autore`
--

CREATE TABLE `libro_autore` (
  `id_libro` int(11) NOT NULL,
  `id_autore` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `libro_autore`
--

INSERT INTO `libro_autore` (`id_libro`, `id_autore`) VALUES
(5, 5),
(6, 6),
(7, 6),
(8, 7),
(10, 9),
(11, 10),
(12, 11),
(13, 12),
(16, 14),
(19, 15),
(20, 16),
(21, 17),
(22, 18),
(23, 19),
(24, 20),
(25, 21),
(26, 22),
(28, 23),
(29, 24),
(30, 25);

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

--
-- Dump dei dati per la tabella `livello_utente`
--

INSERT INTO `livello_utente` (`id_utente`, `livello`, `esperienza_totale`, `esperienza_livello_corrente`, `esperienza_prossimo_livello`, `titolo`, `ultimo_aggiornamento`) VALUES
(4, 1, 15, 15, 100, 'Lettore Novizio', '2026-01-16 20:36:32'),
(7, 1, 10, 10, 100, 'Lettore Novizio', '2026-01-16 17:37:50'),
(14, 1, 0, 0, 100, 'Lettore Novizio', '2026-01-12 18:48:59'),
(17, 1, 0, 0, 100, 'Lettore Novizio', '2026-01-12 18:48:59'),
(18, 2, 105, 5, 200, 'Lettore Novizio', '2026-01-13 17:20:08');

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
-- Dump dei dati per la tabella `multa`
--

INSERT INTO `multa` (`id_multa`, `id_utente`, `id_prestito`, `importo`, `causale`, `tipo_multa`, `giorni_ritardo`, `data_creazione`, `stato`, `note`, `data_pagamento`) VALUES
(1, 7, 2, 10.00, 'idk', 'ritardo', 10, '2026-01-16 17:57:35', 'pagata', '\n[Pagamento]  - Bibliotecario: ', '2026-01-16 21:17:56');

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

--
-- Dump dei dati per la tabella `notifica`
--

INSERT INTO `notifica` (`id_notifica`, `id_utente`, `tipo`, `titolo`, `messaggio`, `priorita`, `dati_extra`, `data_creazione`, `letta`, `data_invio_email`, `email_inviata`) VALUES
(1, 4, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'L\'Ultimo giorno di un condannato\'. Data di restituzione: 17/01/2026', 'media', NULL, '2025-12-17 07:02:27', 0, NULL, 0),
(2, 4, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'I pilastri della terra\'. Data di restituzione: 17/01/2026', 'media', NULL, '2025-12-17 09:47:13', 0, NULL, 0),
(3, 7, 'prenotazione', 'Prenotazione confermata', 'Hai prenotato \'L\'Ultimo giorno di un condannato\'. Posizione in coda: 1. Tempo stimato: circa 14 giorni.', 'media', NULL, '2025-12-17 11:07:55', 0, NULL, 0),
(4, 7, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'I pilastri della terra\'. Data di restituzione: 17/01/2026', 'media', NULL, '2025-12-17 11:08:13', 0, NULL, 0),
(5, 7, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'La biblioteca perduta\'. Data di restituzione: 17/01/2026', 'media', NULL, '2025-12-17 12:14:03', 0, NULL, 0),
(6, 7, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'L\'uomo che scambiÃ² sua moglie per un cappello\'. Data di restituzione: 17/01/2026', 'media', NULL, '2025-12-17 12:16:44', 0, NULL, 0),
(7, 7, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'La guerra del Peloponneso. Testo greco a fronte\'. Data di restituzione: 17/01/2026', 'media', NULL, '2025-12-17 12:16:52', 0, NULL, 0),
(8, 7, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'Leggere pericolosamente. Il potere sovversivo della letteratura in tempi difficili\'. Data di restituzione: 17/01/2026', 'media', NULL, '2025-12-17 12:16:58', 0, NULL, 0),
(9, 7, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'Madame Bovary. Ediz. integrale\'. Data di restituzione: 17/01/2026', 'media', NULL, '2025-12-17 12:17:02', 0, NULL, 0),
(10, 7, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'Una vita come tante\'. Data di restituzione: 17/01/2026', 'media', NULL, '2025-12-17 12:17:09', 0, NULL, 0),
(11, 4, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'La prima Bibbia. Per la famiglia, la catechesi e la scuola\'. Data di restituzione: 30/01/2026', 'media', NULL, '2025-12-30 12:11:50', 0, NULL, 0),
(12, 4, 'prenotazione', 'Prenotazione confermata', 'Hai prenotato \'La guerra del Peloponneso. Testo greco a fronte\'. Posizione in coda: 1. Tempo stimato: circa 14 giorni.', 'media', NULL, '2025-12-30 12:12:26', 0, NULL, 0),
(13, 4, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'La biblioteca perduta\'. Data di restituzione: 30/01/2026', 'media', NULL, '2025-12-30 12:19:51', 0, NULL, 0),
(14, 4, 'prenotazione', 'Prenotazione confermata', 'Hai prenotato \'Leggere pericolosamente. Il potere sovversivo della letteratura in tempi difficili\'. Posizione in coda: 1. Tempo stimato: circa 14 giorni.', 'media', NULL, '2025-12-30 12:20:32', 0, NULL, 0),
(15, 4, 'prenotazione', 'Prenotazione confermata', 'Hai prenotato \'Una vita come tante\'. Posizione in coda: 1. Tempo stimato: circa 14 giorni.', 'media', NULL, '2025-12-30 12:24:29', 0, NULL, 0),
(16, 4, 'prenotazione', 'Prenotazione confermata', 'Hai prenotato \'La guerra del Peloponneso. Testo greco a fronte\'. Posizione in coda: 1. Tempo stimato: circa 14 giorni.', 'media', NULL, '2025-12-30 12:27:38', 0, NULL, 0),
(17, 4, 'prenotazione', 'Prenotazione confermata', 'Hai prenotato \'La guerra del Peloponneso. Testo greco a fronte\'. Posizione in coda: 1. Tempo stimato: circa 14 giorni.', 'media', NULL, '2025-12-30 12:30:02', 0, NULL, 0),
(18, 4, 'prenotazione', 'Prenotazione confermata', 'Hai prenotato \'Leggere pericolosamente. Il potere sovversivo della letteratura in tempi difficili\'. Posizione in coda: 1. Tempo stimato: circa 14 giorni.', 'media', NULL, '2025-12-30 12:35:10', 0, NULL, 0),
(25, 14, 'prenotazione', 'Prenotazione confermata', 'Hai prenotato \'L\'Ultimo giorno di un condannato\'. Posizione in coda: 2. Tempo stimato: circa 28 giorni.', 'media', NULL, '2025-12-30 15:07:17', 0, NULL, 0),
(26, 14, 'prenotazione', 'Prenotazione confermata', 'Hai prenotato \'Una vita come tante\'. Posizione in coda: 1. Tempo stimato: circa 14 giorni.', 'media', NULL, '2025-12-30 15:07:22', 0, NULL, 0),
(28, 4, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'La guerra del Peloponneso. Testo greco a fronte\'. Restituzione entro: 08/02/2026', 'media', NULL, '2026-01-09 08:30:31', 0, NULL, 0),
(29, 4, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'La guerra del Peloponneso. Testo greco a fronte\'. Restituzione entro: 08/02/2026', 'media', NULL, '2026-01-09 08:34:44', 0, NULL, 0),
(30, 4, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'La guerra del Peloponneso. Testo greco a fronte\'. Restituzione entro: 08/02/2026', 'media', NULL, '2026-01-09 08:38:50', 0, NULL, 0),
(31, 4, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'La guerra del Peloponneso. Testo greco a fronte\'. Restituzione entro: 08/02/2026', 'media', NULL, '2026-01-09 09:15:09', 0, NULL, 0),
(32, 18, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'L\'ultimo segreto\'. Data di restituzione: 13/02/2026', 'media', NULL, '2026-01-13 16:51:22', 0, NULL, 0),
(33, 18, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'Careless People\'. Data di restituzione: 13/02/2026', 'media', NULL, '2026-01-13 17:11:33', 0, NULL, 0),
(34, 18, 'badge', 'Nuovo Badge Sbloccato! 🏆', 'Hai ottenuto il badge \'Pagina Uno\': Primo libro letto', 'media', NULL, '2026-01-13 17:11:33', 0, NULL, 0),
(35, 18, 'obiettivo', 'Obiettivo Completato! 🎯', 'Hai completato l\'obiettivo \'Lettore Mensile\'!', 'media', NULL, '2026-01-13 17:12:46', 0, NULL, 0),
(36, 18, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'Careless People\'. Data di restituzione: 13/02/2026', 'media', NULL, '2026-01-13 17:13:16', 0, NULL, 0),
(37, 18, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'Bianca come il latte, rossa come il sangue\'. Data di restituzione: 13/02/2026', 'media', NULL, '2026-01-13 17:13:23', 0, NULL, 0),
(38, 18, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'Diversamente sani. Manuale per meglio sopravvivere ai medici e alle malattie\'. Data di restituzione: 13/02/2026', 'media', NULL, '2026-01-13 17:13:29', 0, NULL, 0),
(39, 18, 'badge', 'Nuovo Badge Sbloccato! 🏆', 'Hai ottenuto il badge \'Riconsegna Lampo\': Restituzione veloce', 'media', NULL, '2026-01-13 17:15:08', 0, NULL, 0),
(40, 18, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'Humankind\'. Data di restituzione: 13/02/2026', 'media', NULL, '2026-01-13 17:16:12', 0, NULL, 0),
(41, 18, 'badge', 'Nuovo Badge Sbloccato! 🏆', 'Hai ottenuto il badge \'Lettore Curioso\': 5 libri letti', 'media', NULL, '2026-01-13 17:16:24', 0, NULL, 0),
(42, 18, 'badge', 'Nuovo Badge Sbloccato! 🏆', 'Hai ottenuto il badge \'Critico Novizio\': Prima recensione', 'media', NULL, '2026-01-13 17:20:08', 0, NULL, 0),
(43, 18, 'sistema', 'Level Up! 🎉', 'Sei salito al livello 2: Lettore Novizio!', 'media', NULL, '2026-01-13 17:20:08', 0, NULL, 0),
(44, 18, 'prenotazione', 'Prenotazione confermata', 'Hai prenotato \'L\'Ultimo giorno di un condannato\'. Posizione in coda: 3. Tempo stimato: circa 42 giorni.', 'media', NULL, '2026-01-16 10:05:10', 0, NULL, 0),
(45, 18, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'La levatrice\'. Data di restituzione: 16/02/2026', 'media', NULL, '2026-01-16 10:05:31', 0, NULL, 0),
(46, 18, 'prenotazione', 'Prenotazione confermata', 'Hai prenotato \'Leggere pericolosamente. Il potere sovversivo della letteratura in tempi difficili\'. Posizione in coda: 1. Tempo stimato: circa 14 giorni.', 'media', NULL, '2026-01-16 10:21:32', 0, NULL, 0),
(47, 18, '', 'Libro Disponibile', 'Il libro \'Leggere pericolosamente. Il potere sovversivo della letteratura in tempi difficili\' è ora disponibile per il ritiro. Hai 48 ore per ritirarlo.', 'media', NULL, '2026-01-16 10:21:48', 0, NULL, 0),
(48, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'Ultimo giorno di un condannato\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"1\",\"titolo_libro\":\"L\'Ultimo giorno di un condannato\",\"data_scadenza\":\"2026-01-17 08:02:27\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U3--DAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:33:58', 0, NULL, 0),
(49, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"2\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 10:47:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:34:01', 0, NULL, 0),
(50, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"3\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 12:08:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:34:04', 0, NULL, 0),
(51, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La biblioteca perduta\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"4\",\"titolo_libro\":\"La biblioteca perduta\",\"data_scadenza\":\"2026-01-17 13:14:03\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=B_en0QEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:34:07', 0, NULL, 0),
(52, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'uomo che scambiÃ² sua moglie per un cappello\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"5\",\"titolo_libro\":\"L\'uomo che scambi\\u00c3\\u00b2 sua moglie per un cappello\",\"data_scadenza\":\"2026-01-17 13:16:44\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U2DMAAAACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:34:09', 0, NULL, 0),
(53, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La guerra del Peloponneso. Testo greco a fronte\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"6\",\"titolo_libro\":\"La guerra del Peloponneso. Testo greco a fronte\",\"data_scadenza\":\"2026-01-17 13:16:52\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=bBme0AEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:34:12', 0, NULL, 0),
(54, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'Una vita come tante\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"9\",\"titolo_libro\":\"Una vita come tante\",\"data_scadenza\":\"2026-01-17 13:17:09\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=Y8OpDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:34:15', 0, NULL, 0),
(55, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'Ultimo giorno di un condannato\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"1\",\"titolo_libro\":\"L\'Ultimo giorno di un condannato\",\"data_scadenza\":\"2026-01-17 08:02:27\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U3--DAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:34:31', 0, NULL, 0),
(56, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"2\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 10:47:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:34:34', 0, NULL, 0),
(57, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"3\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 12:08:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:34:37', 0, NULL, 0),
(58, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La biblioteca perduta\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"4\",\"titolo_libro\":\"La biblioteca perduta\",\"data_scadenza\":\"2026-01-17 13:14:03\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=B_en0QEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:34:39', 0, NULL, 0),
(59, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'uomo che scambiÃ² sua moglie per un cappello\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"5\",\"titolo_libro\":\"L\'uomo che scambi\\u00c3\\u00b2 sua moglie per un cappello\",\"data_scadenza\":\"2026-01-17 13:16:44\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U2DMAAAACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:34:42', 0, NULL, 0),
(60, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La guerra del Peloponneso. Testo greco a fronte\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"6\",\"titolo_libro\":\"La guerra del Peloponneso. Testo greco a fronte\",\"data_scadenza\":\"2026-01-17 13:16:52\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=bBme0AEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:34:45', 0, NULL, 0),
(61, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'Una vita come tante\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"9\",\"titolo_libro\":\"Una vita come tante\",\"data_scadenza\":\"2026-01-17 13:17:09\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=Y8OpDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:34:48', 0, NULL, 0),
(62, 7, 'prestito', 'Prestito attivato', 'Hai preso in prestito \'Careless People\'. Data di restituzione: 16/02/2026', 'media', NULL, '2026-01-16 17:37:50', 0, NULL, 0),
(63, 7, 'badge', 'Nuovo Badge Sbloccato!', 'Hai ottenuto il badge \'Pagina Uno\': Primo libro letto', 'media', NULL, '2026-01-16 17:37:50', 0, NULL, 0),
(64, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'Ultimo giorno di un condannato\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"1\",\"titolo_libro\":\"L\'Ultimo giorno di un condannato\",\"data_scadenza\":\"2026-01-17 08:02:27\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U3--DAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:38:00', 0, NULL, 0),
(65, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"2\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 10:47:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:38:03', 0, NULL, 0),
(66, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"3\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 12:08:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:38:06', 0, NULL, 0),
(67, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La biblioteca perduta\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"4\",\"titolo_libro\":\"La biblioteca perduta\",\"data_scadenza\":\"2026-01-17 13:14:03\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=B_en0QEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:38:09', 0, NULL, 0),
(68, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'uomo che scambiÃ² sua moglie per un cappello\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"5\",\"titolo_libro\":\"L\'uomo che scambi\\u00c3\\u00b2 sua moglie per un cappello\",\"data_scadenza\":\"2026-01-17 13:16:44\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U2DMAAAACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:38:11', 0, NULL, 0),
(69, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La guerra del Peloponneso. Testo greco a fronte\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"6\",\"titolo_libro\":\"La guerra del Peloponneso. Testo greco a fronte\",\"data_scadenza\":\"2026-01-17 13:16:52\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=bBme0AEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:38:16', 0, NULL, 0),
(70, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'Una vita come tante\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"9\",\"titolo_libro\":\"Una vita come tante\",\"data_scadenza\":\"2026-01-17 13:17:09\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=Y8OpDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:38:21', 0, NULL, 0),
(71, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'Ultimo giorno di un condannato\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"1\",\"titolo_libro\":\"L\'Ultimo giorno di un condannato\",\"data_scadenza\":\"2026-01-17 08:02:27\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U3--DAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:38:24', 0, NULL, 0),
(72, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"2\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 10:47:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:38:27', 0, NULL, 0),
(73, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"3\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 12:08:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:38:29', 0, NULL, 0),
(74, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La biblioteca perduta\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"4\",\"titolo_libro\":\"La biblioteca perduta\",\"data_scadenza\":\"2026-01-17 13:14:03\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=B_en0QEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:38:32', 0, NULL, 0),
(75, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'uomo che scambiÃ² sua moglie per un cappello\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"5\",\"titolo_libro\":\"L\'uomo che scambi\\u00c3\\u00b2 sua moglie per un cappello\",\"data_scadenza\":\"2026-01-17 13:16:44\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U2DMAAAACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:38:35', 0, NULL, 0),
(76, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La guerra del Peloponneso. Testo greco a fronte\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"6\",\"titolo_libro\":\"La guerra del Peloponneso. Testo greco a fronte\",\"data_scadenza\":\"2026-01-17 13:16:52\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=bBme0AEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:38:37', 0, NULL, 0),
(77, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'Una vita come tante\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"9\",\"titolo_libro\":\"Una vita come tante\",\"data_scadenza\":\"2026-01-17 13:17:09\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=Y8OpDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:38:41', 0, NULL, 0),
(78, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'Ultimo giorno di un condannato\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"1\",\"titolo_libro\":\"L\'Ultimo giorno di un condannato\",\"data_scadenza\":\"2026-01-17 08:02:27\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U3--DAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:38:43', 0, NULL, 0),
(79, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"2\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 10:47:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:38:46', 0, NULL, 0),
(80, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"3\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 12:08:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:38:49', 0, NULL, 0),
(81, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La biblioteca perduta\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"4\",\"titolo_libro\":\"La biblioteca perduta\",\"data_scadenza\":\"2026-01-17 13:14:03\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=B_en0QEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:38:52', 0, NULL, 0),
(82, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'uomo che scambiÃ² sua moglie per un cappello\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"5\",\"titolo_libro\":\"L\'uomo che scambi\\u00c3\\u00b2 sua moglie per un cappello\",\"data_scadenza\":\"2026-01-17 13:16:44\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U2DMAAAACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:38:54', 0, NULL, 0),
(83, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La guerra del Peloponneso. Testo greco a fronte\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"6\",\"titolo_libro\":\"La guerra del Peloponneso. Testo greco a fronte\",\"data_scadenza\":\"2026-01-17 13:16:52\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=bBme0AEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:38:57', 0, NULL, 0),
(84, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'Una vita come tante\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"9\",\"titolo_libro\":\"Una vita come tante\",\"data_scadenza\":\"2026-01-17 13:17:09\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=Y8OpDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:39:00', 0, NULL, 0),
(85, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'Ultimo giorno di un condannato\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"1\",\"titolo_libro\":\"L\'Ultimo giorno di un condannato\",\"data_scadenza\":\"2026-01-17 08:02:27\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U3--DAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:18', 0, NULL, 0),
(86, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'Ultimo giorno di un condannato\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"1\",\"titolo_libro\":\"L\'Ultimo giorno di un condannato\",\"data_scadenza\":\"2026-01-17 08:02:27\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U3--DAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:21', 0, NULL, 0),
(87, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"2\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 10:47:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:21', 0, NULL, 0),
(88, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'Ultimo giorno di un condannato\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"1\",\"titolo_libro\":\"L\'Ultimo giorno di un condannato\",\"data_scadenza\":\"2026-01-17 08:02:27\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U3--DAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:22', 0, NULL, 0),
(89, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"3\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 12:08:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:24', 0, NULL, 0),
(90, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"2\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 10:47:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:25', 0, NULL, 0),
(91, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"2\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 10:47:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:26', 0, NULL, 0),
(92, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La biblioteca perduta\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"4\",\"titolo_libro\":\"La biblioteca perduta\",\"data_scadenza\":\"2026-01-17 13:14:03\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=B_en0QEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:26', 0, NULL, 0),
(93, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"3\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 12:08:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:28', 0, NULL, 0),
(94, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"3\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 12:08:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:29', 0, NULL, 0),
(95, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'uomo che scambiÃ² sua moglie per un cappello\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"5\",\"titolo_libro\":\"L\'uomo che scambi\\u00c3\\u00b2 sua moglie per un cappello\",\"data_scadenza\":\"2026-01-17 13:16:44\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U2DMAAAACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:29', 0, NULL, 0),
(96, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La biblioteca perduta\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"4\",\"titolo_libro\":\"La biblioteca perduta\",\"data_scadenza\":\"2026-01-17 13:14:03\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=B_en0QEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:31', 0, NULL, 0),
(97, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La guerra del Peloponneso. Testo greco a fronte\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"6\",\"titolo_libro\":\"La guerra del Peloponneso. Testo greco a fronte\",\"data_scadenza\":\"2026-01-17 13:16:52\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=bBme0AEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:31', 0, NULL, 0),
(98, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La biblioteca perduta\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"4\",\"titolo_libro\":\"La biblioteca perduta\",\"data_scadenza\":\"2026-01-17 13:14:03\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=B_en0QEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:32', 0, NULL, 0),
(99, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'Una vita come tante\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"9\",\"titolo_libro\":\"Una vita come tante\",\"data_scadenza\":\"2026-01-17 13:17:09\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=Y8OpDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:34', 0, NULL, 0),
(100, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'uomo che scambiÃ² sua moglie per un cappello\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"5\",\"titolo_libro\":\"L\'uomo che scambi\\u00c3\\u00b2 sua moglie per un cappello\",\"data_scadenza\":\"2026-01-17 13:16:44\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U2DMAAAACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:34', 0, NULL, 0),
(101, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'uomo che scambiÃ² sua moglie per un cappello\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"5\",\"titolo_libro\":\"L\'uomo che scambi\\u00c3\\u00b2 sua moglie per un cappello\",\"data_scadenza\":\"2026-01-17 13:16:44\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U2DMAAAACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:35', 0, NULL, 0),
(102, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La guerra del Peloponneso. Testo greco a fronte\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"6\",\"titolo_libro\":\"La guerra del Peloponneso. Testo greco a fronte\",\"data_scadenza\":\"2026-01-17 13:16:52\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=bBme0AEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:37', 0, NULL, 0),
(103, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La guerra del Peloponneso. Testo greco a fronte\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"6\",\"titolo_libro\":\"La guerra del Peloponneso. Testo greco a fronte\",\"data_scadenza\":\"2026-01-17 13:16:52\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=bBme0AEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:38', 0, NULL, 0),
(104, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'Una vita come tante\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"9\",\"titolo_libro\":\"Una vita come tante\",\"data_scadenza\":\"2026-01-17 13:17:09\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=Y8OpDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:40', 0, NULL, 0),
(105, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'Una vita come tante\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"9\",\"titolo_libro\":\"Una vita come tante\",\"data_scadenza\":\"2026-01-17 13:17:09\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=Y8OpDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 17:52:41', 0, NULL, 0),
(106, 18, 'prestito', 'Prestito Attivato', 'Hai ritirato \'Leggere pericolosamente. Il potere sovversivo della letteratura in tempi difficili\'. Data restituzione: 16/02/2026', 'media', NULL, '2026-01-16 20:07:10', 0, NULL, 0),
(107, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'Ultimo giorno di un condannato\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"1\",\"titolo_libro\":\"L\'Ultimo giorno di un condannato\",\"data_scadenza\":\"2026-01-17 08:02:27\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U3--DAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:19:53', 0, NULL, 0),
(108, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"2\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 10:47:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:19:55', 0, NULL, 0),
(109, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"3\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 12:08:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:19:57', 0, NULL, 0),
(110, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La biblioteca perduta\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"4\",\"titolo_libro\":\"La biblioteca perduta\",\"data_scadenza\":\"2026-01-17 13:14:03\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=B_en0QEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:19:59', 0, NULL, 0),
(111, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'Ultimo giorno di un condannato\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"1\",\"titolo_libro\":\"L\'Ultimo giorno di un condannato\",\"data_scadenza\":\"2026-01-17 08:02:27\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U3--DAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:20:00', 0, NULL, 0),
(112, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'uomo che scambiÃ² sua moglie per un cappello\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"5\",\"titolo_libro\":\"L\'uomo che scambi\\u00c3\\u00b2 sua moglie per un cappello\",\"data_scadenza\":\"2026-01-17 13:16:44\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U2DMAAAACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:20:01', 0, NULL, 0),
(113, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"2\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 10:47:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:20:02', 0, NULL, 0),
(114, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La guerra del Peloponneso. Testo greco a fronte\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"6\",\"titolo_libro\":\"La guerra del Peloponneso. Testo greco a fronte\",\"data_scadenza\":\"2026-01-17 13:16:52\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=bBme0AEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:20:02', 0, NULL, 0),
(115, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'Una vita come tante\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"9\",\"titolo_libro\":\"Una vita come tante\",\"data_scadenza\":\"2026-01-17 13:17:09\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=Y8OpDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:20:04', 0, NULL, 0),
(116, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"3\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 12:08:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:20:04', 0, NULL, 0),
(117, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La biblioteca perduta\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"4\",\"titolo_libro\":\"La biblioteca perduta\",\"data_scadenza\":\"2026-01-17 13:14:03\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=B_en0QEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:20:07', 0, NULL, 0),
(118, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'uomo che scambiÃ² sua moglie per un cappello\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"5\",\"titolo_libro\":\"L\'uomo che scambi\\u00c3\\u00b2 sua moglie per un cappello\",\"data_scadenza\":\"2026-01-17 13:16:44\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U2DMAAAACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:20:09', 0, NULL, 0),
(119, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La guerra del Peloponneso. Testo greco a fronte\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"6\",\"titolo_libro\":\"La guerra del Peloponneso. Testo greco a fronte\",\"data_scadenza\":\"2026-01-17 13:16:52\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=bBme0AEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:20:12', 0, NULL, 0),
(120, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'Una vita come tante\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"9\",\"titolo_libro\":\"Una vita come tante\",\"data_scadenza\":\"2026-01-17 13:17:09\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=Y8OpDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:20:15', 0, NULL, 0),
(121, 4, 'badge', 'Nuovo Badge Sbloccato!', 'Hai ottenuto il badge \'Critico Novizio\': Prima recensione', 'media', NULL, '2026-01-16 20:36:32', 0, NULL, 0),
(122, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'Ultimo giorno di un condannato\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"1\",\"titolo_libro\":\"L\'Ultimo giorno di un condannato\",\"data_scadenza\":\"2026-01-17 08:02:27\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U3--DAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:44:18', 0, NULL, 0),
(123, 4, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"2\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 10:47:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:44:21', 0, NULL, 0),
(124, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'I pilastri della terra\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"3\",\"titolo_libro\":\"I pilastri della terra\",\"data_scadenza\":\"2026-01-17 12:08:13\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=FVLBDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:44:23', 0, NULL, 0),
(125, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La biblioteca perduta\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"4\",\"titolo_libro\":\"La biblioteca perduta\",\"data_scadenza\":\"2026-01-17 13:14:03\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=B_en0QEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:44:26', 0, NULL, 0),
(126, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'L\'uomo che scambiÃ² sua moglie per un cappello\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"5\",\"titolo_libro\":\"L\'uomo che scambi\\u00c3\\u00b2 sua moglie per un cappello\",\"data_scadenza\":\"2026-01-17 13:16:44\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=U2DMAAAACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:44:28', 0, NULL, 0),
(127, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'La guerra del Peloponneso. Testo greco a fronte\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"6\",\"titolo_libro\":\"La guerra del Peloponneso. Testo greco a fronte\",\"data_scadenza\":\"2026-01-17 13:16:52\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=bBme0AEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:44:31', 0, NULL, 0),
(128, 7, '', 'Promemoria: Restituzione libro tra 0 giorni', 'Il libro \'Una vita come tante\' deve essere restituito entro il 17/01/2026', 'media', '{\"id_prestito\":\"9\",\"titolo_libro\":\"Una vita come tante\",\"data_scadenza\":\"2026-01-17 13:17:09\",\"immagine_copertina\":\"https:\\/\\/books.google.com\\/books\\/content?id=Y8OpDAEACAAJ&printsec=frontcover&img=1&zoom=1&source=gbs_api\"}', '2026-01-16 20:44:34', 0, NULL, 0);

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

--
-- Dump dei dati per la tabella `obiettivo`
--

INSERT INTO `obiettivo` (`id_obiettivo`, `nome`, `descrizione`, `target`, `tipo`, `anno_riferimento`, `ricorrente`, `punti_esperienza`, `icona`, `attivo`, `ordine_visualizzazione`) VALUES
(1, 'Lettore Annuale', 'Leggi 20 libri quest\'anno', 20, 'libri_letti', 2026, 1, 100, '📚', 1, 1),
(2, 'Esploratore di Generi', 'Leggi libri in 5 generi diversi', 5, 'generi_diversi', 2026, 1, 75, '🎭', 1, 2),
(3, 'Recensore', 'Scrivi 10 recensioni', 10, 'recensioni', 2026, 1, 50, '✍️', 1, 3),
(4, 'Grande Lettore', 'Leggi 50 libri quest\'anno', 50, 'libri_letti', 2026, 1, 250, '📖', 1, 2),
(5, 'Lettore Mensile', 'Leggi almeno 2 libri questo mese', 2, 'libri_letti', NULL, 1, 25, '📅', 1, 5);

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

--
-- Dump dei dati per la tabella `pagamento`
--

INSERT INTO `pagamento` (`id_pagamento`, `id_multa`, `importo`, `data_pagamento`, `metodo_pagamento`, `id_bibliotecario`, `ricevuta_pdf`, `note_pagamento`, `transazione_id`) VALUES
(1, 1, 10.00, '2026-01-16 20:17:56', 'contanti', 4, NULL, '', NULL);

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

--
-- Dump dei dati per la tabella `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`id`, `id_utente`, `token`, `expires_at`, `used`, `created_at`) VALUES
(3, 4, 'e37b6be0b5bc3e66205635094db25016bcd4038b1fafd1fc635a60e2bb72b00f', '2026-01-16 22:05:32', 1, '2026-01-16 20:05:32'),
(4, 4, 'd8691b345ab37d9fb50a84ed8ae53e93584694d2598c1277d59b455b32b915c9', '2026-01-16 22:24:05', 1, '2026-01-16 20:24:05');

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

--
-- Dump dei dati per la tabella `preferenze_notifiche`
--

INSERT INTO `preferenze_notifiche` (`id_preferenza`, `id_utente`, `email_attive`, `promemoria_scadenza`, `notifiche_ritardo`, `notifiche_prenotazioni`, `quiet_hours_attive`, `quiet_hours_inizio`, `quiet_hours_fine`, `data_creazione`, `ultimo_aggiornamento`) VALUES
(1, 4, 1, 1, 1, 1, 0, '22:00:00', '08:00:00', '2026-01-16 17:29:16', '2026-01-16 17:29:16'),
(2, 7, 1, 1, 1, 1, 0, '22:00:00', '08:00:00', '2026-01-16 17:29:16', '2026-01-16 17:29:16'),
(3, 14, 1, 1, 1, 1, 0, '22:00:00', '08:00:00', '2026-01-16 17:29:16', '2026-01-16 17:29:16'),
(4, 17, 1, 1, 1, 1, 0, '22:00:00', '08:00:00', '2026-01-16 17:29:16', '2026-01-16 17:29:16'),
(5, 18, 1, 1, 1, 1, 0, '22:00:00', '08:00:00', '2026-01-16 17:29:16', '2026-01-16 17:29:16');

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

--
-- Dump dei dati per la tabella `prenotazione`
--

INSERT INTO `prenotazione` (`id_prenotazione`, `id_utente`, `id_libro`, `data_prenotazione`, `stato`, `data_disponibilita`, `data_scadenza_ritiro`, `posizione_coda`, `id_copia_assegnata`) VALUES
(1, 7, 8, '2025-12-17 11:07:55', 'attiva', NULL, NULL, 1, NULL),
(2, 4, 14, '2025-12-30 12:12:26', 'annullata', NULL, NULL, 1, NULL),
(3, 4, 13, '2025-12-30 12:20:32', 'annullata', NULL, NULL, 1, NULL),
(4, 4, 12, '2025-12-30 12:24:29', 'annullata', NULL, NULL, 1, NULL),
(5, 4, 14, '2025-12-30 12:27:38', 'annullata', NULL, NULL, 1, NULL),
(6, 4, 14, '2025-12-30 12:30:02', 'annullata', NULL, NULL, 1, NULL),
(7, 4, 13, '2025-12-30 12:35:10', 'annullata', NULL, NULL, 1, NULL),
(9, 14, 8, '2025-12-30 15:07:17', 'attiva', NULL, NULL, 2, NULL),
(10, 14, 12, '2025-12-30 15:07:22', 'attiva', NULL, NULL, 1, NULL),
(11, 18, 8, '2026-01-16 10:05:10', 'attiva', NULL, NULL, 3, NULL),
(12, 18, 13, '2026-01-16 10:21:32', 'ritirata', NULL, '2026-01-18 11:21:48', 1, 100);

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

--
-- Dump dei dati per la tabella `prestito`
--

INSERT INTO `prestito` (`id_prestito`, `id_utente`, `id_copia`, `id_bibliotecario`, `data_prestito`, `data_scadenza`, `data_restituzione_effettiva`, `rinnovato`, `note`) VALUES
(1, 4, 4, NULL, '2025-12-17 07:02:27', '2026-01-17 08:02:27', NULL, 0, 'Prestito diretto dal catalogo'),
(2, 4, 1, NULL, '2025-12-17 09:47:13', '2026-01-17 10:47:13', NULL, 0, 'Prestito diretto dal catalogo'),
(3, 7, 93, NULL, '2025-12-17 11:08:13', '2026-01-17 12:08:13', NULL, 0, 'Prestito diretto dal catalogo'),
(4, 7, 87, NULL, '2025-12-17 12:14:03', '2026-01-17 13:14:03', NULL, 0, 'Prestito diretto dal catalogo'),
(5, 7, 96, NULL, '2025-12-17 12:16:44', '2026-01-17 13:16:44', NULL, 0, 'Prestito diretto dal catalogo'),
(6, 7, 101, NULL, '2025-12-17 12:16:52', '2026-01-17 13:16:52', NULL, 0, 'Prestito diretto dal catalogo'),
(7, 7, 100, NULL, '2025-12-17 12:16:58', '2026-01-17 13:16:58', '2026-01-16 11:21:48', 0, 'Prestito diretto dal catalogo'),
(9, 7, 99, NULL, '2025-12-17 12:17:09', '2026-01-17 13:17:09', NULL, 0, 'Prestito diretto dal catalogo'),
(10, 4, 104, NULL, '2025-12-30 12:11:50', '2026-01-30 13:11:50', NULL, 0, 'Prestito diretto dal catalogo'),
(11, 4, 88, NULL, '2025-12-30 12:19:51', '2026-01-30 13:19:51', NULL, 0, 'Prestito diretto dal catalogo'),
(18, 4, 110, NULL, '2026-01-09 08:30:31', '2026-02-08 09:30:31', '2026-01-09 09:34:13', 0, 'Prestito rapido tramite scanner - Bibliotecario: '),
(19, 4, 110, NULL, '2026-01-09 08:34:44', '2026-02-08 09:34:44', '2026-01-09 09:37:47', 0, 'Prestito rapido tramite scanner - Bibliotecario: '),
(20, 4, 110, NULL, '2026-01-09 08:38:50', '2026-02-08 09:38:50', '2026-01-09 09:39:20', 0, 'Prestito rapido tramite scanner - Bibliotecario: '),
(21, 4, 110, NULL, '2026-01-09 09:15:09', '2026-02-08 10:15:09', NULL, 0, 'Prestito rapido tramite scanner - Bibliotecario: '),
(22, 18, 112, NULL, '2026-01-13 16:51:22', '2026-02-13 17:51:22', '2026-01-13 18:05:18', 0, 'Prestito diretto dal catalogo'),
(23, 18, 126, NULL, '2026-01-13 17:11:33', '2026-02-13 18:11:33', '2026-01-13 18:12:46', 0, 'Prestito diretto dal catalogo'),
(24, 18, 126, NULL, '2026-01-13 17:13:16', '2026-02-13 18:13:16', '2026-01-13 18:13:46', 0, 'Prestito diretto dal catalogo'),
(25, 18, 145, NULL, '2026-01-13 17:13:23', '2026-02-13 18:13:23', '2026-01-13 18:14:31', 0, 'Prestito diretto dal catalogo'),
(26, 18, 103, NULL, '2026-01-13 17:13:29', '2026-02-13 18:13:29', '2026-01-13 18:15:08', 0, 'Prestito diretto dal catalogo'),
(27, 18, 122, NULL, '2026-01-13 17:16:12', '2026-02-13 18:16:12', '2026-01-13 18:16:24', 0, 'Prestito diretto dal catalogo'),
(28, 18, 132, NULL, '2026-01-16 10:05:31', '2026-02-16 11:05:31', NULL, 0, 'Prestito diretto dal catalogo'),
(29, 7, 126, NULL, '2026-01-16 17:37:50', '2026-02-16 18:37:50', '2026-01-16 21:15:36', 0, 'Prestito diretto dal catalogo'),
(30, 18, 100, 4, '2026-01-16 20:07:10', '2026-01-14 21:07:10', NULL, 0, 'Prestito da prenotazione - Bibliotecario: ');

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

--
-- Dump dei dati per la tabella `profilo_preferenze`
--

INSERT INTO `profilo_preferenze` (`id_utente`, `categorie_preferite`, `autori_preferiti`, `pattern_lettura`, `ultimo_aggiornamento`) VALUES
(4, '{\"Fiction\":0.531,\"Health & Fitness\":0.013,\"Juvenile Fiction\":0.038,\"Juvenile Nonfiction\":0.101,\"Literary Collections\":0.317}', '[{\"autore\":\"Ken Follett\",\"id_autore\":5,\"prestiti\":1,\"voto_medio\":\"2.0000\"},{\"autore\":\"Victor Hugo\",\"id_autore\":7,\"prestiti\":1,\"voto_medio\":null},{\"autore\":\"James Rollins\",\"id_autore\":9,\"prestiti\":1,\"voto_medio\":null}]', NULL, '2026-01-16 10:55:43'),
(7, '{\"Biography & Autobiography\":0.09,\"Fiction\":0.819,\"Literary Collections\":0.09}', '[{\"autore\":\"Ken Follett\",\"id_autore\":\"5\",\"prestiti\":\"1\",\"voto_medio\":\"5.0000\"},{\"autore\":\"Oliver Sacks\",\"id_autore\":\"10\",\"prestiti\":\"1\",\"voto_medio\":null},{\"autore\":\"Hanya Yanagihara\",\"id_autore\":\"11\",\"prestiti\":\"1\",\"voto_medio\":null},{\"autore\":\"Azar Nafisi\",\"id_autore\":\"12\",\"prestiti\":\"1\",\"voto_medio\":null},{\"autore\":\"James Rollins\",\"id_autore\":\"9\",\"prestiti\":\"1\",\"voto_medio\":null}]', NULL, '2026-01-16 17:37:21'),
(14, '[]', '[]', NULL, '2025-12-30 14:13:49'),
(17, '[]', '[]', NULL, '2026-01-16 10:37:06'),
(18, '{\"Biografia e autobiografia\":0.311,\"Economia aziendale\":0.156,\"Health & Fitness\":0.156,\"Narrativa\":0.378}', '[{\"autore\":\"Sarah Wynn-Williams\",\"id_autore\":20,\"prestiti\":2,\"voto_medio\":null},{\"autore\":\"Dan Brown\",\"id_autore\":15,\"prestiti\":1,\"voto_medio\":\"5.0000\"},{\"autore\":\"Alessandro D\'Avenia\",\"id_autore\":23,\"prestiti\":1,\"voto_medio\":\"5.0000\"},{\"autore\":\"Rutger Bregman\",\"id_autore\":19,\"prestiti\":1,\"voto_medio\":null},{\"autore\":\"Massimo Citro Della Riva\",\"id_autore\":14,\"prestiti\":1,\"voto_medio\":null}]', NULL, '2026-01-16 09:29:54');

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

--
-- Dump dei dati per la tabella `progresso_obiettivo`
--

INSERT INTO `progresso_obiettivo` (`id_progresso`, `id_utente`, `id_obiettivo`, `anno_riferimento`, `progresso_attuale`, `completato`, `notificato`, `data_completamento`) VALUES
(1, 18, 1, 2026, 5, 0, 0, NULL),
(2, 18, 2, 2026, 4, 0, 0, NULL),
(3, 18, 3, 2026, 5, 0, 0, NULL),
(4, 18, 4, 2026, 5, 0, 0, NULL),
(5, 18, 5, 2026, 5, 1, 0, NULL),
(6, 7, 1, 2026, 1, 0, 0, NULL),
(7, 7, 2, 2026, 1, 0, 0, NULL),
(8, 7, 3, 2026, 0, 0, 0, NULL),
(9, 7, 4, 2026, 1, 0, 0, NULL),
(10, 7, 5, 2026, 1, 0, 0, NULL),
(11, 4, 1, 2026, 1, 0, 0, NULL),
(12, 4, 2, 2026, 1, 0, 0, NULL),
(13, 4, 3, 2026, 1, 0, 0, NULL),
(14, 4, 4, 2026, 1, 0, 0, NULL),
(15, 4, 5, 2026, 1, 0, 0, NULL);

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

--
-- Dump dei dati per la tabella `recensione`
--

INSERT INTO `recensione` (`id_recensione`, `id_libro`, `id_utente`, `voto`, `testo`, `data_recensione`) VALUES
(5, 5, 4, 2, 'sbocco', '2025-12-16 15:27:44'),
(8, 5, 7, 5, 'sburr', '2025-12-17 11:09:16'),
(9, 8, 7, 3, 'Interessante ma in po noioso', '2025-12-17 12:11:44'),
(10, 11, 4, 5, 'belloo', '2025-12-19 08:36:05'),
(11, 14, 4, 3, 'molto bell', '2025-12-30 12:39:21'),
(16, 5, 14, 3, 'uoooohoooo', '2025-12-30 14:14:46'),
(17, 14, 14, 5, 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', '2025-12-30 15:25:44'),
(19, 19, 18, 5, 'a', '2026-01-13 17:20:10'),
(21, 28, 18, 5, 'a', '2026-01-13 17:20:17'),
(22, 20, 18, 2, 'a', '2026-01-13 17:20:29'),
(23, 30, 18, 5, 'a', '2026-01-13 17:20:32'),
(24, 29, 18, 5, 'a', '2026-01-13 17:20:39'),
(25, 6, 4, 5, 'dsadasd', '2026-01-16 20:37:03');

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

--
-- Dump dei dati per la tabella `ruolo`
--

INSERT INTO `ruolo` (`id_ruolo`, `nome_ruolo`, `livello_permesso`, `descrizione`) VALUES
(1, 'utente', 1, 'Utente base della biblioteca'),
(2, 'bibliotecario', 2, 'Gestore dei prestiti e catalogazione'),
(3, 'amministratore', 3, 'Amministratore del sistema');

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

--
-- Dump dei dati per la tabella `storico_xp`
--

INSERT INTO `storico_xp` (`id_storico`, `id_utente`, `punti_guadagnati`, `motivo`, `riferimento_tipo`, `riferimento_id`, `data_guadagno`) VALUES
(1, 18, 10, 'badge', 'badge', 1, '2026-01-13 17:11:33'),
(2, 18, 25, 'obiettivo', 'badge', 5, '2026-01-13 17:12:46'),
(3, 18, 30, 'badge', 'badge', 6, '2026-01-13 17:15:08'),
(4, 18, 25, 'badge', 'badge', 7, '2026-01-13 17:16:24'),
(5, 18, 15, 'badge', 'badge', 16, '2026-01-13 17:20:08'),
(6, 7, 10, 'badge', 'badge', 1, '2026-01-16 17:37:50'),
(7, 4, 15, 'badge', 'badge', 16, '2026-01-16 20:36:32');

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

--
-- Dump dei dati per la tabella `template_email`
--

INSERT INTO `template_email` (`id_template`, `nome_template`, `tipo_notifica`, `oggetto`, `corpo_html`, `corpo_testo`, `variabili`, `attivo`, `data_creazione`, `ultimo_aggiornamento`) VALUES
(1, 'prestito_conferma', 'prestito', '📚 Prestito confermato - {{titolo_libro}}', '<h1>Ciao {{nome}}!</h1><p>Hai preso in prestito <strong>{{titolo_libro}}</strong>.</p><p>Data scadenza: {{data_scadenza}}</p>', NULL, '[\"nome\", \"titolo_libro\", \"data_scadenza\", \"giorni_rimasti\"]', 1, '2026-01-16 10:03:00', '2026-01-16 10:03:00'),
(2, 'scadenza_promemoria', 'scadenza', '⏰ Promemoria: Restituzione libro tra {{giorni_rimasti}} giorni', '<h1>Ciao {{nome}}!</h1><p>Ricordati di restituire <strong>{{titolo_libro}}</strong> entro il {{data_scadenza}}.</p>', NULL, '[\"nome\", \"titolo_libro\", \"data_scadenza\", \"giorni_rimasti\"]', 1, '2026-01-16 10:03:00', '2026-01-16 10:03:00'),
(3, 'ritardo_lieve', 'ritardo', '⚠️ Libro in ritardo - {{titolo_libro}}', '<h1>Ciao {{nome}}!</h1><p>Il libro <strong>{{titolo_libro}}</strong> è in ritardo di {{giorni_ritardo}} giorni.</p><p>I nuovi prestiti sono bloccati fino alla restituzione.</p>', NULL, '[\"nome\", \"titolo_libro\", \"giorni_ritardo\", \"multa_attuale\"]', 1, '2026-01-16 10:03:00', '2026-01-16 10:03:00'),
(4, 'ritardo_medio', 'ritardo', '🚨 Ritardo significativo - Multa in accumulo', '<h1>Ciao {{nome}}!</h1><p>Il libro <strong>{{titolo_libro}}</strong> è in ritardo di {{giorni_ritardo}} giorni.</p><p>Multa accumulata: €{{multa_attuale}} (€0,50/giorno)</p>', NULL, '[\"nome\", \"titolo_libro\", \"giorni_ritardo\", \"multa_attuale\"]', 1, '2026-01-16 10:03:00', '2026-01-16 10:03:00'),
(5, 'prenotazione_disponibile', 'prenotazione', '🎉 Il tuo libro è disponibile! - {{titolo_libro}}', '<h1>Ciao {{nome}}!</h1><p>Il libro <strong>{{titolo_libro}}</strong> è disponibile!</p><p>Ritiralo entro {{ore_rimaste}} ore o la prenotazione scadrà.</p>', NULL, '[\"nome\", \"titolo_libro\", \"ore_rimaste\", \"data_scadenza\"]', 1, '2026-01-16 10:03:00', '2026-01-16 10:03:00');

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

--
-- Dump dei dati per la tabella `trend_libri`
--

INSERT INTO `trend_libri` (`id_libro`, `trend_score`, `prestiti_ultimi_7_giorni`, `prestiti_ultimi_30_giorni`, `click_ultimi_7_giorni`, `prenotazioni_attive`, `velocita_trend`, `ultimo_aggiornamento`) VALUES
(5, 16.67, 0, 0, 0, 0, 0.00, '2026-01-16 20:35:37'),
(6, 15.00, 0, 0, 0, 0, 0.00, '2026-01-16 20:35:37'),
(7, 15.00, 0, 0, 0, 0, 0.00, '2026-01-16 20:35:37'),
(8, 60.00, 0, 0, 0, 3, 0.00, '2026-01-16 20:35:37'),
(10, 18.00, 0, 1, 0, 0, -100.00, '2026-01-16 20:35:37'),
(11, 25.00, 0, 0, 0, 0, 0.00, '2026-01-16 20:35:38'),
(12, 30.00, 0, 0, 0, 1, 0.00, '2026-01-16 20:35:38'),
(13, 25.00, 1, 1, 0, 0, 328.00, '2026-01-16 20:35:38'),
(14, 32.00, 0, 4, 0, 0, -100.00, '2026-01-16 20:35:38'),
(16, 25.00, 1, 1, 0, 0, 328.00, '2026-01-16 20:35:38'),
(17, 18.00, 0, 1, 0, 0, -100.00, '2026-01-16 20:35:38'),
(19, 35.00, 1, 1, 0, 0, 328.00, '2026-01-16 20:35:38'),
(20, 10.00, 0, 0, 0, 0, 0.00, '2026-01-16 20:35:38'),
(21, 15.00, 0, 0, 0, 0, 0.00, '2026-01-16 20:35:38'),
(22, 15.00, 0, 0, 0, 0, 0.00, '2026-01-16 20:35:38'),
(23, 25.00, 1, 1, 0, 0, 328.00, '2026-01-16 20:35:38'),
(24, 45.00, 3, 3, 0, 0, 328.00, '2026-01-16 20:35:38'),
(25, 15.00, 0, 0, 0, 0, 0.00, '2026-01-16 20:35:38'),
(26, 25.00, 1, 1, 0, 0, 328.00, '2026-01-16 20:35:38'),
(27, 15.00, 0, 0, 0, 0, 0.00, '2026-01-16 20:35:38'),
(28, 35.00, 1, 1, 0, 0, 328.00, '2026-01-16 20:35:38'),
(29, 25.00, 0, 0, 0, 0, 0.00, '2026-01-16 20:35:38'),
(30, 25.00, 0, 0, 0, 0, 0.00, '2026-01-16 20:35:38');

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
-- Dump dei dati per la tabella `utente`
--

INSERT INTO `utente` (`id_utente`, `nome`, `cognome`, `data_nascita`, `sesso`, `comune_nascita`, `codice_catastale`, `codice_fiscale`, `codice_tessera`, `email`, `password_hash`, `username`, `foto`, `email_verificata`, `verification_token`, `verification_expires`, `stato_account`, `prestiti_bloccati`, `motivo_blocco`, `tentativi_falliti`, `data_sospensione`, `data_registrazione`, `data_ultimo_accesso`) VALUES
(4, 'Matteo', 'Castelli', '2007-02-18', 'M', 'Thiene', 'L157', 'CSTMTT07B18L157N', 'USER00000004', 'matteocastelli1802@gmail.com', '$2y$10$RwbN.4Njb3JBzXAj6Fab6.RtO6L/SrN6r2Q.QOTPnTBk/3bmgAshG', 'matteoâ˜º', '../../public/uploads/avatars/avatar_6953e2d4b9206.jpg', 1, NULL, NULL, 'attivo', 0, NULL, 0, '2026-01-16 21:26:25', '2025-12-14 08:40:41', '2026-01-16 21:35:37'),
(7, 'Brajan', 'Mako', '1967-01-01', 'F', 'Camisano', 'B484', 'MKABJN67A41B484O', 'USER00000007', 'brajan@gmail.com', '$2y$10$7MtiZQ4RkcOO8AWscVRImOwFqynM1NelpS1EVZd7zNIS38PyNC13.', 'brajan67', '../../public/uploads/avatars/avatar_6953e307d6ede.jpg', 1, NULL, NULL, 'attivo', 0, NULL, 0, NULL, '2025-12-17 11:05:51', '2026-01-16 20:59:19'),
(14, 'Mattia', 'Guadagna', '1954-02-11', 'F', 'Creazzo', 'D136', 'GDGMTT54B51D136R', 'USER00000014', 'mattia@gmail.com', '$2y$10$l8hu5GnkJsdegVs1gxUlBeD4IWOi0JCRt.gpajLb8zH1M/LLQ/llq', 'mattiaGuadagna54', '../../public/uploads/avatars/avatar_6953e3390d7b9.JPG', 1, NULL, NULL, 'attivo', 0, NULL, 0, NULL, '2025-12-30 14:13:36', '2025-12-30 16:07:10'),
(17, 'Christian', 'Tibaldo', '2007-12-12', 'M', 'Gavardo', 'D940', 'TBLCRS07T12D940A', 'USER000017', 'coso@gmail.com', '$2y$10$WDRJZtCYCairorQ/VjeMRerk57/WMZCQYNRe0bZH66Sv0G6DEbu/.', 'Tiba', '../../public/uploads/avatars/avatar_696a16be80ffb.jpg', 1, NULL, NULL, 'attivo', 0, NULL, 0, NULL, '2026-01-01 19:40:58', '2026-01-16 11:37:06'),
(18, 'Giovanni', 'Montagna', '2007-11-26', 'M', 'Vicenza', 'L840', 'MNTGNN07S26L840A', 'USER00000018', '10934075@itisrossi.vi.it', '$2y$10$ZIBiOpmSUpbwjigm4K20Y.XCdUMvdOUoelILSq7.omR0Y2RHctoHu', 'Gio', '../../public/assets/img/default_avatar.png', 1, 'd3741a5499909307d67ec166eb578665ae9a391f55ba74b9478558522c72bd28', '2026-01-14 17:45:53', 'attivo', 1, NULL, 0, NULL, '2026-01-13 16:45:53', '2026-01-16 11:05:04');

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

--
-- Dump dei dati per la tabella `utente_badge`
--

INSERT INTO `utente_badge` (`id_utente_badge`, `id_utente`, `id_badge`, `data_ottenimento`, `progressione_attuale`, `notificato`) VALUES
(1, 18, 1, '2026-01-13 17:11:33', 0, 0),
(2, 18, 6, '2026-01-13 17:15:08', 0, 0),
(3, 18, 7, '2026-01-13 17:16:24', 0, 0),
(4, 18, 16, '2026-01-13 17:20:08', 0, 0),
(5, 7, 1, '2026-01-16 17:37:50', 0, 0),
(6, 4, 16, '2026-01-16 20:36:32', 0, 0);

-- --------------------------------------------------------

--
-- Struttura della tabella `utente_ruolo`
--

CREATE TABLE `utente_ruolo` (
  `id_utente` int(11) NOT NULL,
  `id_ruolo` int(11) NOT NULL,
  `data_assegnazione` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `utente_ruolo`
--

INSERT INTO `utente_ruolo` (`id_utente`, `id_ruolo`, `data_assegnazione`) VALUES
(4, 2, '2025-12-14 08:41:19'),
(7, 1, '2025-12-17 11:06:38'),
(14, 1, '2025-12-30 14:13:49'),
(17, 2, '2026-01-01 19:41:36'),
(18, 2, '2026-01-13 16:48:38');

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
  ADD KEY `idx_multa_tipo` (`tipo_multa`,`stato`),
  ADD KEY `idx_multa_stato_data` (`stato`,`data_creazione`);

--
-- Indici per le tabelle `notifica`
--
ALTER TABLE `notifica`
  ADD PRIMARY KEY (`id_notifica`),
  ADD KEY `idx_utente_letta` (`id_utente`,`letta`),
  ADD KEY `idx_notifica_priorita` (`priorita`,`data_creazione`),
  ADD KEY `idx_notifica_email` (`email_inviata`,`data_creazione`),
  ADD KEY `idx_notifica_tipo_data` (`tipo`,`data_creazione`);

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
  ADD KEY `idx_utente_bloccato` (`prestiti_bloccati`),
  ADD KEY `idx_prestiti_bloccati` (`prestiti_bloccati`);

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
  MODIFY `id_autore` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT per la tabella `badge`
--
ALTER TABLE `badge`
  MODIFY `id_badge` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT per la tabella `cache_raccomandazioni`
--
ALTER TABLE `cache_raccomandazioni`
  MODIFY `id_cache` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

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
  MODIFY `id_copia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=151;

--
-- AUTO_INCREMENT per la tabella `feedback_raccomandazione`
--
ALTER TABLE `feedback_raccomandazione`
  MODIFY `id_feedback` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT per la tabella `interazione_utente`
--
ALTER TABLE `interazione_utente`
  MODIFY `id_interazione` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT per la tabella `libro`
--
ALTER TABLE `libro`
  MODIFY `id_libro` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

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
  MODIFY `id_multa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT per la tabella `notifica`
--
ALTER TABLE `notifica`
  MODIFY `id_notifica` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT per la tabella `obiettivo`
--
ALTER TABLE `obiettivo`
  MODIFY `id_obiettivo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT per la tabella `pagamento`
--
ALTER TABLE `pagamento`
  MODIFY `id_pagamento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT per la tabella `partecipazione_sfida`
--
ALTER TABLE `partecipazione_sfida`
  MODIFY `id_partecipazione` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT per la tabella `preferenze_notifiche`
--
ALTER TABLE `preferenze_notifiche`
  MODIFY `id_preferenza` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT per la tabella `prenotazione`
--
ALTER TABLE `prenotazione`
  MODIFY `id_prenotazione` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT per la tabella `prestito`
--
ALTER TABLE `prestito`
  MODIFY `id_prestito` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT per la tabella `progresso_obiettivo`
--
ALTER TABLE `progresso_obiettivo`
  MODIFY `id_progresso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT per la tabella `recensione`
--
ALTER TABLE `recensione`
  MODIFY `id_recensione` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT per la tabella `ruolo`
--
ALTER TABLE `ruolo`
  MODIFY `id_ruolo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT per la tabella `sfide_evento`
--
ALTER TABLE `sfide_evento`
  MODIFY `id_sfida` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `storico_xp`
--
ALTER TABLE `storico_xp`
  MODIFY `id_storico` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT per la tabella `template_email`
--
ALTER TABLE `template_email`
  MODIFY `id_template` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT per la tabella `utente`
--
ALTER TABLE `utente`
  MODIFY `id_utente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT per la tabella `utente_badge`
--
ALTER TABLE `utente_badge`
  MODIFY `id_utente_badge` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
