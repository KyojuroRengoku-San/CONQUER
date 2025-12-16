<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'member') {
    header('Location: login.php');
    exit();
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config/database.php';
    
    try {
        $pdo = Database::getInstance()->getConnection();
        
        $title = $_POST['title'];
        $story_text = $_POST['story_text'];
        $weight_loss = floatval($_POST['weight_loss']);
        $months_taken = intval($_POST['months_taken']);
        $trainer_id = $_POST['trainer_id'] ?: null;
        $user_id = $_SESSION['user_id'];
        
        // Handle file uploads
        $before_image = null;
        $after_image = null;
        
        if(isset($_FILES['before_image']) && $_FILES['before_image']['error'] === UPLOAD_ERR_OK) {
            $before_image = uploadImage($_FILES['before_image']);
        }
        
        if(isset($_FILES['after_image']) && $_FILES['after_image']['error'] === UPLOAD_ERR_OK) {
            $after_image = uploadImage($_FILES['after_image']);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO success_stories 
            (user_id, title, story_text, weight_loss, months_taken, trainer_id, before_image, after_image, approved, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        
        $stmt->execute([
            $user_id, $title, $story_text, $weight_loss, $months_taken, 
            $trainer_id, $before_image, $after_image
        ]);
        
        $_SESSION['story_message'] = 'Your success story has been submitted for review!';
        $_SESSION['story_success'] = true;
        header('Location: user-stories.php');
        exit();
        
    } catch(PDOException $e) {
        $error = "Error submitting story: " . $e->getMessage();
    }
}

function uploadImage($file) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $file['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if(!in_array($ext, $allowed)) {
        return null;
    }
    
    $new_filename = uniqid() . '.' . $ext;
    $destination = 'uploads/' . $new_filename;
    
    if(move_uploaded_file($file['tmp_name'], $destination)) {
        return $new_filename;
    }
    
    return null;
}
?>

<!-- HTML form for story submission -->