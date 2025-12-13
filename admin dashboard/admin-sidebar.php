<?php
// admin-sidebar.php
require_once 'config/database.php';

// Get counts for sidebar badges
$totalMembers = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'member'")->fetchColumn();
$totalTrainers = $pdo->query("SELECT COUNT(*) FROM trainers")->fetchColumn();
$totalClasses = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$pendingStories = $pdo->query("SELECT COUNT(*) FROM success_stories WHERE approved = 0")->fetchColumn();
$maintenanceNeeded = $pdo->query("SELECT COUNT(*) FROM equipment WHERE status = 'maintenance' OR next_maintenance <= DATE_ADD(NOW(), INTERVAL 7 DAY)")->fetchColumn();
?>
<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-dumbbell"></i>
            <span>CONQUER</span>
            <span class="admin-badge">ADMIN</span>
        </div>
        <div class="user-profile">
            <div class="user-avatar admin">
                <i class="fas fa-crown"></i>
            </div>
            <div class="user-details">
                <h4>Administrator</h4>
                <p>System Admin</p>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <a href="admin-dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'admin-dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="admin-members.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'admin-members.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Members</span>
            <span class="nav-badge"><?php echo $totalMembers; ?></span>
        </a>
        <a href="admin-trainers.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'admin-trainers.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-tie"></i>
            <span>Trainers</span>
            <span class="nav-badge"><?php echo $totalTrainers; ?></span>
        </a>
        <a href="admin-classes.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'admin-classes.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Classes</span>
            <span class="nav-badge"><?php echo $totalClasses; ?></span>
        </a>
        <a href="admin-payments.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'admin-payments.php' ? 'active' : ''; ?>">
            <i class="fas fa-credit-card"></i>
            <span>Payments</span>
        </a>
        <a href="admin-stories.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'admin-stories.php' ? 'active' : ''; ?>">
            <i class="fas fa-trophy"></i>
            <span>Success Stories</span>
            <?php if($pendingStories > 0): ?>
                <span class="nav-badge alert"><?php echo $pendingStories; ?></span>
            <?php endif; ?>
        </a>
        <a href="admin-equipment.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'admin-equipment.php' ? 'active' : ''; ?>">
            <i class="fas fa-dumbbell"></i>
                <span>Equipment</span>
            <?php if($maintenanceNeeded > 0): ?>
                <span class="nav-badge alert"><?php echo $maintenanceNeeded; ?></span>
            <?php endif; ?>
        </a>
        <a href="admin-messages.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'admin-messages.php' ? 'active' : ''; ?>">
            <i class="fas fa-envelope"></i>
            <span>Messages</span>
        </a>
        <a href="admin-reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'admin-reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Reports</span>
        </a>
        <a href="admin-settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'admin-settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>