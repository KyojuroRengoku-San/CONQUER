<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if(!isset($_GET['id']) || !isset($_GET['reason'])) {
    header('Location: admin-stories.php');
    exit();
}

$story_id = $_GET['id'];
$reason = $_GET['reason'];

try {
    $pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("
        UPDATE success_stories 
        SET approved = 0, rejected_reason = ?, rejection_date = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$reason, $story_id]);
    
    $_SESSION['admin_message'] = 'Story rejected successfully';
    
} catch(PDOException $e) {
    $_SESSION['admin_message'] = 'Error rejecting story: ' . $e->getMessage();
}

header('Location: admin-stories.php');
exit();
?>