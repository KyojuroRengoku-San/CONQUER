<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if(!isset($_GET['id'])) {
    header('Location: admin-stories.php');
    exit();
}

$story_id = $_GET['id'];

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Only feature approved stories
    $stmt = $pdo->prepare("
        UPDATE success_stories 
        SET is_featured = 1, featured_date = NOW() 
        WHERE id = ? AND approved = 1
    ");
    $stmt->execute([$story_id]);
    
    $_SESSION['admin_message'] = 'Story featured successfully';
    
} catch(PDOException $e) {
    $_SESSION['admin_message'] = 'Error featuring story: ' . $e->getMessage();
}

header('Location: admin-stories.php');
exit();
?>