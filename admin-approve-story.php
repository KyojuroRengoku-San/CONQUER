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
$admin_id = $_SESSION['user_id'];

try {
    $pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("
        UPDATE success_stories 
        SET approved = 1, approved_by = ?, approved_date = NOW(), rejected_reason = NULL 
        WHERE id = ?
    ");
    $stmt->execute([$admin_id, $story_id]);
    
    $_SESSION['admin_message'] = 'Story approved successfully';
    
} catch(PDOException $e) {
    $_SESSION['admin_message'] = 'Error approving story: ' . $e->getMessage();
}

header('Location: admin-stories.php');
exit();
?>