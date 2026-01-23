# Sito web biblioteca scolastica
AUTORI:
- Christian Tibaldo (scrum master)
- Matteo Castelli (vice scrum master)
- Giovanni Montagna
- Alex Gogonea
- Adam Moustakim

REQUISITI:
- PHP
- XAMPP
- Composer
- Account mailtrap.io

INSTALLAZIONE:
- Clonare il repository nella cartella htdocs di XAMPP
- Avviare Composer con il comando 'composer init' nella cartella del progetto
- Installare le librerie con i comandi 'composer require phpmailer/phpmailer', 'composer require vlucas/phpdotenv' e 'composer require tecnickcom/tcpdf'
- Inserire le credenziali del proprio sandbox di mailtrap.io nel file .env nei rispettivi campi
- Avviare MySQL e Apache su XAMPP
- Importare il file biblioteca.sql su phpmyadmin
- Aprire il sito sulla pagina web localhost/SudoMakers/src/user/homepage.php
