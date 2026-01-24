USE `biblioteca`;

CREATE TABLE statistiche_gioco (
                                   id INT PRIMARY KEY AUTO_INCREMENT,
                                   id_utente INT NOT NULL,
                                   vittorie INT NOT NULL DEFAULT 0,
                                   sconfitte INT NOT NULL DEFAULT 0,
                                   CONSTRAINT fk_statistiche_utent`e FOREIGN KEY (id_utente) REFERENCES utente(id_utente)
                                       ON DELETE CASCADE
                                       ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



`