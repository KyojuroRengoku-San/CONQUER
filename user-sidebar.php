<?php
// user-sidebar.php
require_once 'config/database.php';

// Get counts for sidebar badges
$totalMembers = ($pdo !== null) ? $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'member'")->fetchColumn() : 0;
$totalTrainers = ($pdo !== null) ? $pdo->query("SELECT COUNT(*) FROM trainers")->fetchColumn() : 0;
$totalClasses = ($pdo !== null) ? $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn() : 0;
$pendingStories = ($pdo !== null) ? $pdo->query("SELECT COUNT(*) FROM success_stories WHERE approved = 0")->fetchColumn() : 0;
$maintenanceNeeded = ($pdo !== null) ? $pdo->query("SELECT COUNT(*) FROM equipment WHERE status = 'maintenance' OR next_maintenance <= DATE_ADD(NOW(), INTERVAL 7 DAY)")->fetchColumn() : 0;
?>
<!-- Sidebar -->
<div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-dumbbell"></i>
                <span>CONQUER</span>
            </div>
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo isset($user['full_name']) ? strtoupper(substr($user['full_name'], 0, 1)) : 'U'; ?>
                </div>
                <div class="user-details">
                    <h4><?php echo isset($user['full_name']) ? htmlspecialchars($user['full_name']) : 'User'; ?></h4>
                    <p>Member</p>
                </div>
            </div>
        </div>
    
    <nav class="sidebar-nav">
        <a href="user-dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'user-dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="user-profile.php">
            <i class="fas fa-user"></i>
            <span>My Profile</span>
        </a>
        <a href="user-classes.php">
            <i class="fas fa-calendar-alt"></i>
            <span>My Classes</span>
        </a>
        <a href="user-payments.php">
            <i class="fas fa-credit-card"></i>
            <span>Payments</span>
        </a>
        <a href="user-stories.php">
            <i class="fas fa-trophy"></i>
            <span>Success Stories</span>
        </a>
        <a href="user-classes.php">
            <i class="fas fa-plus-circle"></i>
            <span>Book Class</span>
        </a>
        <a href="user-contact.php">
            <i class="fas fa-envelope"></i>
                <span>Support</span>
    </nav>

    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>