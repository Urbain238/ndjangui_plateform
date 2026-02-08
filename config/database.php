<?php
class Database {
    private static $instance = null;
    private $pdo;

    // Mise à jour avec tes infos InfinityFree
    private $host = 'sql302.infinityfree.com';
    private $db   = 'if0_41023667_applicatindb';
    private $user = 'if0_41023667';
    private $pass = 'QeTw0A7riTMUvWY';
    private $charset = 'utf8mb4';

    private function __construct() {
        $dsn = "mysql:host=$this->host;dbname=$this->db;charset=$this->charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
        } catch (\PDOException $e) {
            // En production, on ne donne pas les détails de l'erreur, mais on peut les loguer
            die("Erreur critique de base de données : " . $e->getMessage());
        }
    }

    public static function getConnection() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->pdo;
    }
}
?>