<?php
class Database
{
    private static $instance = null;
    private $pdo;

    public function __construct()
    {
        $this->pdo = new PDO("mysql:host=localhost;dbname=biblioteca", "root", "");
    }

    public static function getInstance()
    {
        if(self::$instance === null )
            self::$instance = new self();//chiamata al costruttore
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->pdo;
    }
}