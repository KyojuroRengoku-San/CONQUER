<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'member') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Get user info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // Get member info
    $memberStmt = $pdo->prepare("SELECT * FROM gym_members WHERE Email = ?");
    $memberStmt->execute([$user['email']]);
    $member = $memberStmt->fetch();
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | CONQUER Gym</title>
    <link rel="stylesheet" href="dashboard-style.css">
</head>
<body>
    <!-- Include the same sidebar from user-dashboard.php -->
    <?php include 'sidebar-user.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <!-- Same top bar as dashboard -->
        </div>
        
        <div class="dashboard-content">
            <div class="welcome-banner">
                <h1>My Profile</h1>
            </div>
            
            <div class="content-card">
                <div class="card-header">
                    <h3>Personal Information</h3>
                </div>
                <div class="card-body">
                    <form action="update-profile.php" method="POST">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($member['ContactNumber'] ?? ''); ?>">
                        </div>
                        <button type="submit" class="btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>