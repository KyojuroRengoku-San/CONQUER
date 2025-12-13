<?php
// setup_database.php
// Run this file once to set up your database

echo "<h2>Conquer Gym Database Setup</h2>";
echo "Starting database setup...<br><br>";

$host = 'localhost';
$username = 'root';
$password = '';

try {
    // Connect to MySQL server
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connected to MySQL server successfully.<br><br>";
    
    // Check if SQL file exists
    $sqlFile = 'conquer_gym.sql';
    
    if (!file_exists($sqlFile)) {
        echo "❌ Error: SQL file not found: $sqlFile<br>";
        echo "Please make sure 'conquer_gym.sql' is in the same directory.<br>";
        exit;
    }
    
    echo "📄 Reading SQL file: $sqlFile<br>";
    
    // Read the entire SQL file
    $sql = file_get_contents($sqlFile);
    
    if (empty($sql)) {
        echo "❌ Error: SQL file is empty.<br>";
        exit;
    }
    
    echo "✅ SQL file loaded successfully.<br>";
    echo "Executing SQL commands...<br><hr>";
    
    // Split SQL by semicolon to execute commands one by one
    $queries = explode(';', $sql);
    $queryCount = 0;
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($queries as $query) {
        $query = trim($query);
        
        // Skip empty queries
        if (empty($query)) {
            continue;
        }
        
        $queryCount++;
        
        // Add semicolon back for execution
        $query .= ';';
        
        try {
            $pdo->exec($query);
            echo "✅ Query $queryCount executed successfully<br>";
            $successCount++;
            
            // Add a small pause for readability
            usleep(100000); // 0.1 second
            
        } catch (PDOException $e) {
            echo "❌ Error in query $queryCount: " . $e->getMessage() . "<br>";
            $errorCount++;
        }
    }
    
    echo "<hr><h3>Setup Summary:</h3>";
    echo "Total queries processed: $queryCount<br>";
    echo "Successful queries: $successCount<br>";
    echo "Failed queries: $errorCount<br><br>";
    
    if ($errorCount == 0) {
        echo "🎉 <strong>Database setup completed successfully!</strong><br>";
        echo "You can now use the Conquer Gym system.<br>";
    } else {
        echo "⚠️ <strong>Database setup completed with some errors.</strong><br>";
        echo "Please check the errors above.<br>";
    }
    
    echo "<br><hr>";
    echo "<h4>Next Steps:</h4>";
    echo "1. Delete or rename this setup_database.php file for security<br>";
    echo "2. Use database.php in your other PHP files to connect to the database<br>";
    echo "3. Access your application at: http://localhost/CONQUER/<br>";
    
} catch(PDOException $e) {
    echo "❌ <strong>Fatal Error:</strong> " . $e->getMessage() . "<br>";
    echo "<br>Please check:<br>";
    echo "1. Is XAMPP MySQL running?<br>";
    echo "2. Are the username and password correct?<br>";
    echo "3. Is the host name correct?<br>";
}
?>