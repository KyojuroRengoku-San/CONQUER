<?php
// config/database.php
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        // Use these exact values based on your conquer_gym.sql
        $host = 'localhost';
        $dbname = 'conquer_gym'; // Changed from 'your_database_name'
        $username = 'root'; // Default for XAMPP/WAMP
        $password = ''; // Default for XAMPP/WAMP (empty)
        
        try {
            $this->connection = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage() . 
                "<br>Please check: 
                <br>1. Database name: $dbname
                <br>2. Username: $username
                <br>3. Make sure MySQL is running in XAMPP/WAMP");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
}
?>