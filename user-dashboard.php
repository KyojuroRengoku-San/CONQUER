<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'member') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user info
try {
    // User info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // Member info
    $memberStmt = $pdo->prepare("SELECT * FROM gym_members WHERE Email = ?");
    $memberStmt->execute([$user['email']]);
    $member = $memberStmt->fetch();
    
    // Upcoming classes
    $classesStmt = $pdo->prepare("
        SELECT c.*, t.full_name as trainer_name 
        FROM bookings b 
        JOIN classes c ON b.class_id = c.id 
        JOIN users t ON c.trainer_id = t.id 
        WHERE b.user_id = ? AND b.status = 'confirmed' 
        AND c.schedule > NOW() 
        ORDER BY c.schedule ASC 
        LIMIT 3
    ");
    $classesStmt->execute([$user_id]);
    $upcomingClasses = $classesStmt->fetchAll();
    
    // Recent payments
    $paymentsStmt = $pdo->prepare("
        SELECT * FROM payments 
        WHERE user_id = ? 
        ORDER BY payment_date DESC 
        LIMIT 5
    ");
    $paymentsStmt->execute([$user_id]);
    $recentPayments = $paymentsStmt->fetchAll();
    
    // Success stories count
    $storiesStmt = $pdo->prepare("SELECT COUNT(*) as count FROM success_stories WHERE user_id = ? AND approved = 1");
    $storiesStmt->execute([$user_id]);
    $storiesCount = $storiesStmt->fetch()['count'];
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard | CONQUER Gym</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-dumbbell"></i>
                <span>CONQUER</span>
            </div>
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                    <p>Member</p>
                </div>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="user-dashboard.php" class="active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="profile.php">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>
            <a href="my-classes.php">
                <i class="fas fa-calendar-alt"></i>
                <span>My Classes</span>
            </a>
            <a href="payments.php">
                <i class="fas fa-credit-card"></i>
                <span>Payments</span>
            </a>
            <a href="success-stories.php">
                <i class="fas fa-trophy"></i>
                <span>Success Stories</span>
            </a>
            <a href="book-class.php">
                <i class="fas fa-plus-circle"></i>
                <span>Book Class</span>
            </a>
            <a href="contact.php">
                <i class="fas fa-envelope"></i>
                <span>Support</span>
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search...">
            </div>
            <div class="top-bar-actions">
                <button class="btn-notification">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </button>
                <button class="btn-primary" onclick="window.location.href='book-class.php'">
                    <i class="fas fa-plus"></i>
                    Book Class
                </button>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>! 💪</h1>
                    <p>Track your progress, book classes, and continue your fitness journey</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3><?php echo count($upcomingClasses); ?></h3>
                        <p>Upcoming Classes</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $storiesCount; ?></h3>
                        <p>Success Stories</p>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Membership</h3>
                        <p><?php echo $member ? htmlspecialchars($member['MembershipPlan']) : 'No Plan'; ?></p>
                        <span class="status-badge active"><?php echo $member ? htmlspecialchars($member['MembershipStatus']) : 'Inactive'; ?></span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Classes</h3>
                        <p><?php echo count($upcomingClasses); ?> Booked</p>
                        <a href="my-classes.php">View All →</a>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Achievements</h3>
                        <p><?php echo $storiesCount; ?> Stories</p>
                        <a href="submit-story.php">Share Story →</a>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Health Score</h3>
                        <p>85% Progress</p>
                        <div class="progress-bar">
                            <div class="progress" style="width: 85%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="content-grid">
                <!-- Upcoming Classes -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>Upcoming Classes</h3>
                        <a href="my-classes.php">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if(count($upcomingClasses) > 0): ?>
                            <?php foreach($upcomingClasses as $class): ?>
                                <div class="class-item">
                                    <div class="class-time">
                                        <h4><?php echo date('g:i A', strtotime($class['schedule'])); ?></h4>
                                        <p><?php echo date('M j', strtotime($class['schedule'])); ?></p>
                                    </div>
                                    <div class="class-details">
                                        <h4><?php echo htmlspecialchars($class['class_name']); ?></h4>
                                        <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($class['trainer_name']); ?></p>
                                        <span class="class-tag <?php echo strtolower($class['class_type']); ?>">
                                            <?php echo htmlspecialchars($class['class_type']); ?>
                                        </span>
                                    </div>
                                    <div class="class-actions">
                                        <button class="btn-sm" onclick="window.location.href='class-details.php?id=<?php echo $class['id']; ?>'">
                                            View
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="empty-state">No upcoming classes booked</p>
                            <button class="btn-primary" onclick="window.location.href='book-class.php'">
                                <i class="fas fa-plus"></i>
                                Book Your First Class
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Payments -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>Recent Payments</h3>
                        <a href="payments.php">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if(count($recentPayments) > 0): ?>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recentPayments as $payment): ?>
                                            <tr>
                                                <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                                <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo strtolower($payment['status']); ?>">
                                                        <?php echo htmlspecialchars($payment['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="empty-state">No payment history</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions-grid">
                            <a href="book-class.php" class="action-item">
                                <i class="fas fa-plus-circle"></i>
                                <span>Book Class</span>
                            </a>
                            <a href="profile.php" class="action-item">
                                <i class="fas fa-user-edit"></i>
                                <span>Edit Profile</span>
                            </a>
                            <a href="payments.php" class="action-item">
                                <i class="fas fa-credit-card"></i>
                                <span>Make Payment</span>
                            </a>
                            <a href="submit-story.php" class="action-item">
                                <i class="fas fa-trophy"></i>
                                <span>Share Story</span>
                            </a>
                            <a href="index.html#trainers" class="action-item">
                                <i class="fas fa-users"></i>
                                <span>Find Trainer</span>
                            </a>
                            <a href="contact.php" class="action-item">
                                <i class="fas fa-question-circle"></i>
                                <span>Get Help</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Progress Tracker -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>Fitness Progress</h3>
                    </div>
                    <div class="card-body">
                        <div class="progress-tracker">
                            <div class="progress-item">
                                <div class="progress-label">
                                    <span>Weight Goal</span>
                                    <span>75%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress" style="width: 75%"></div>
                                </div>
                            </div>
                            <div class="progress-item">
                                <div class="progress-label">
                                    <span>Strength</span>
                                    <span>60%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress" style="width: 60%"></div>
                                </div>
                            </div>
                            <div class="progress-item">
                                <div class="progress-label">
                                    <span>Cardio</span>
                                    <span>85%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress" style="width: 85%"></div>
                                </div>
                            </div>
                            <div class="progress-item">
                                <div class="progress-label">
                                    <span>Consistency</span>
                                    <span>90%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress" style="width: 90%"></div>
                                </div>
                            </div>
                        </div>
                        <button class="btn-secondary" onclick="window.location.href='progress.php'">
                            View Detailed Progress
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Panel (hidden by default) -->
    <div class="notification-panel" id="notificationPanel">
        <div class="notification-header">
            <h3>Notifications</h3>
            <button class="close-btn" onclick="closeNotifications()">&times;</button>
        </div>
        <div class="notification-list">
            <div class="notification-item">
                <i class="fas fa-calendar text-primary"></i>
                <div class="notification-content">
                    <p>Class reminder: Yoga with Sarah at 6:00 PM</p>
                    <span>2 hours ago</span>
                </div>
            </div>
            <div class="notification-item">
                <i class="fas fa-credit-card text-success"></i>
                <div class="notification-content">
                    <p>Payment of $49.00 completed successfully</p>
                    <span>1 day ago</span>
                </div>
            </div>
            <div class="notification-item">
                <i class="fas fa-trophy text-warning"></i>
                <div class="notification-content">
                    <p>Congratulations! You've completed 10 classes this month</p>
                    <span>2 days ago</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle notification panel
        document.querySelector('.btn-notification').addEventListener('click', function() {
            const panel = document.getElementById('notificationPanel');
            panel.classList.toggle('active');
        });

        function closeNotifications() {
            document.getElementById('notificationPanel').classList.remove('active');
        }

        // Close notification panel when clicking outside
        document.addEventListener('click', function(event) {
            const panel = document.getElementById('notificationPanel');
            const btn = document.querySelector('.btn-notification');
            
            if (!panel.contains(event.target) && !btn.contains(event.target)) {
                panel.classList.remove('active');
            }
        });
    </script>
</body>
</html>