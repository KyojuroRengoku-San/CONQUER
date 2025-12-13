<?php
// config/database.php
// Database configuration
$host = 'localhost';
$dbname = 'conquer_gym';
$username = 'root';
$password = '';

// Create connection using PDO
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Set charset to UTF-8
    $pdo->exec("SET NAMES utf8mb4");
    
    // Connection successful - you can optionally log this
    // error_log("Database connection established to: $dbname");
    
} catch(PDOException $e) {
    // Log the error (don't display sensitive info in production)
    error_log("Database connection failed: " . $e->getMessage());
    
    // Display user-friendly message
    die("Could not connect to the database. Please try again later.");
}

// Optional: Create a function to get the database connection
function getDatabaseConnection() {
    global $pdo;
    return $pdo;
}

// Optional: Helper function for prepared statements
function query($sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
?>