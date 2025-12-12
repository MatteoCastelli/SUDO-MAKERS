<?php

namespace Proprietario\SudoMakers;

use PDO;
use PDOException;

class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        try {
            $this->pdo = new PDO(
                "mysql:host=localhost;dbname=biblioteca;charset=utf8mb4",
                "root",
                "",
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,    // gestisce errori come eccezioni
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // default fetch associative array
                    PDO::ATTR_EMULATE_PREPARES => false,            // usa preparazione nativa se possibile
                ]
            );
        } catch (PDOException $e) {
            // Puoi loggare o gestire diversamente l'errore
            die("Errore connessione DB: " . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self(); // chiama il costruttore
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->pdo;
    }

    // Previeni clonazione o unserialize dell'istanza singleton
    private function __clone() {}
    private function __wakeup() {}
}
