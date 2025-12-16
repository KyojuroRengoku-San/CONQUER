<?php
session_start();

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'], $_SESSION['user_type'])) {
    header('Location: login.php');
    exit();
}

// Check user type
if ($_SESSION['user_type'] !== 'member') {
    header('Location: unauthorized.php');
    exit();
}

// Initialize variables with defaults
$error = '';
$success = '';
$user_id = (int)$_SESSION['user_id'];
$user = null;
$member = null;
$stats = [
    'upcoming_classes' => 0,
    'completed_classes' => 0,
    'success_stories' => 0,
    'total_paid' => 0
];
$upcomingClasses = [];
$recentPayments = [];
$notifications = [];
$notificationCount = 0;
$user_full_name = 'Member';
$user_email = '';

// Try to connect to database
try {
    require_once 'config/database.php';
    
    $database = Database::getInstance();
    $pdo = $database->getConnection();
    
    // Get user info - check what columns exist first
    try {
        // Try the full query first
        $stmt = $pdo->prepare("SELECT id, email, full_name, phone, created_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // If phone column doesn't exist, try without it
        error_log("First user query failed: " . $e->getMessage());
        $stmt = $pdo->prepare("SELECT id, email, full_name, created_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$user) {
        // User not found, but continue with session
        $user = ['full_name' => 'Member', 'email' => '', 'created_at' => date('Y-m-d H:i:s')];
    }
    
    $user_full_name = $user['full_name'] ?? 'Member';
    $user_email = $user['email'] ?? '';
    
    // Get member info (skip if table doesn't exist)
    try {
        $memberStmt = $pdo->prepare("SELECT * FROM gym_members WHERE Email = ? LIMIT 1");
        $memberStmt->execute([$user_email]);
        $member = $memberStmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table might not exist, that's okay
        $member = null;
    }
    
    // Check for bookings table
    try {
        $bookingsQuery = $pdo->prepare("
            SELECT c.id, c.class_name, c.class_type, c.schedule, b.status as booking_status
            FROM bookings b
            JOIN classes c ON b.class_id = c.id
            WHERE b.user_id = ? 
            AND b.status IN ('confirmed')
            AND c.schedule > NOW()
            ORDER BY c.schedule ASC
            LIMIT 3
        ");
        $bookingsQuery->execute([$user_id]);
        $upcomingClasses = $bookingsQuery->fetchAll(PDO::FETCH_ASSOC);
        $stats['upcoming_classes'] = count($upcomingClasses);
    } catch (PDOException $e) {
        // Tables might not exist, continue without bookings
        $upcomingClasses = [];
    }
    
    // Check for success stories
    try {
        $storiesStmt = $pdo->prepare("SELECT COUNT(*) as count FROM success_stories WHERE user_id = ?");
        $storiesStmt->execute([$user_id]);
        $result = $storiesStmt->fetch(PDO::FETCH_ASSOC);
        $stats['success_stories'] = $result ? (int)$result['count'] : 0;
    } catch (PDOException $e) {
        // Table might not exist
        $stats['success_stories'] = 0;
    }
    
    // Check for payments
    try {
        $paymentsQuery = $pdo->prepare("
            SELECT payment_date, amount, payment_method, status
            FROM payments 
            WHERE user_id = ? 
            ORDER BY payment_date DESC 
            LIMIT 5
        ");
        $paymentsQuery->execute([$user_id]);
        $recentPayments = $paymentsQuery->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate total paid
        $totalStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE user_id = ?");
        $totalStmt->execute([$user_id]);
        $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_paid'] = $totalResult ? (float)$totalResult['total'] : 0;
    } catch (PDOException $e) {
        $recentPayments = [];
    }
    
    // Generate notifications
    $notifications = [];
    
    // Welcome notification for new users (within 7 days)
    if (isset($user['created_at'])) {
        try {
            $joinDate = strtotime($user['created_at']);
            $daysSinceJoin = (time() - $joinDate) / (60 * 60 * 24);
            if ($daysSinceJoin <= 7) {
                $notifications[] = [
                    'type' => 'welcome',
                    'icon' => 'fas fa-heart',
                    'color' => 'success',
                    'title' => 'Welcome to CONQUER Gym!',
                    'message' => 'Start your fitness journey. Book your first class!',
                    'time' => $user['created_at']
                ];
            }
        } catch (Exception $e) {
            // Date parsing failed, skip this notification
        }
    }
    
    // Class reminders
    foreach ($upcomingClasses as $class) {
        if (isset($class['schedule'])) {
            try {
                $classTime = strtotime($class['schedule']);
                $hoursLeft = ($classTime - time()) / 3600;
                
                if ($hoursLeft > 0 && $hoursLeft <= 24) {
                    $hoursLeftRounded = ceil($hoursLeft);
                    $notifications[] = [
                        'type' => 'reminder',
                        'icon' => 'fas fa-calendar-alt',
                        'color' => 'primary',
                        'title' => 'Class Reminder',
                        'message' => ($class['class_name'] ?? 'Class') . " in " . $hoursLeftRounded . " hour" . ($hoursLeftRounded > 1 ? 's' : ''),
                        'time' => $class['schedule']
                    ];
                }
            } catch (Exception $e) {
                // Skip this notification
            }
        }
    }
    
    // Payment reminder
    if ($member && isset($member['MembershipStatus']) && strtolower($member['MembershipStatus']) === 'expiring') {
        $notifications[] = [
            'type' => 'payment',
            'icon' => 'fas fa-credit-card',
            'color' => 'warning',
            'title' => 'Membership Expiring',
            'message' => 'Your membership is expiring soon. Renew now!',
            'time' => date('Y-m-d H:i:s')
        ];
    }
    
    $notificationCount = count($notifications);
    $_SESSION['notification_count'] = $notificationCount;
    
} catch (Exception $e) {
    $error = 'Database connection failed. Please try again later.';
    // Don't show detailed error in production
    if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
        $error .= ' Debug: ' . $e->getMessage();
    }
}

// Function to format time ago
function timeAgo($timestamp) {
    if (!$timestamp) return "recently";
    
    try {
        $currentTime = time();
        $timeDifference = $currentTime - $timestamp;
        
        if ($timeDifference < 60) {
            return "just now";
        } elseif ($timeDifference < 3600) {
            $minutes = floor($timeDifference / 60);
            return $minutes . " minute" . ($minutes > 1 ? "s" : "") . " ago";
        } elseif ($timeDifference < 86400) {
            $hours = floor($timeDifference / 3600);
            return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
        } elseif ($timeDifference < 2592000) {
            $days = floor($timeDifference / 86400);
            return $days . " day" . ($days > 1 ? "s" : "") . " ago";
        } elseif ($timeDifference < 31536000) {
            $months = floor($timeDifference / 2592000);
            return $months . " month" . ($months > 1 ? "s" : "") . " ago";
        } else {
            $years = floor($timeDifference / 31536000);
            return $years . " year" . ($years > 1 ? "s" : "") . " ago";
        }
    } catch (Exception $e) {
        return "recently";
    }
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    
    <style>
        /* Quick Fixes */
        .user-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #ff4757, #ff6b81);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.5rem;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, #ff4757 0%, #ff6b81 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .welcome-content h1 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        
        .welcome-content p {
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .membership-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .status-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-badge.active {
            background: rgba(46, 213, 115, 0.2);
            color: #2ed573;
        }
        
        .welcome-stats {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }
        
        .welcome-stats .stat {
            text-align: center;
        }
        
        .welcome-stats h3 {
            font-size: 2rem;
            margin-bottom: 5px;
        }
        
        .welcome-stats p {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #ff4757;
        }
        
        .stat-info h3 {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .stat-info p {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .stat-info a {
            color: #ff4757;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .content-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h3 {
            font-size: 1rem;
            font-weight: 600;
        }
        
        .card-body {
            padding: 15px;
        }
        
        .class-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .class-item:last-child {
            border-bottom: none;
        }
        
        .class-time {
            min-width: 60px;
            text-align: center;
            margin-right: 15px;
        }
        
        .class-time h4 {
            font-size: 0.9rem;
            color: #ff4757;
            margin-bottom: 2px;
        }
        
        .class-time p {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .class-details {
            flex: 1;
        }
        
        .class-details h4 {
            font-size: 0.9rem;
            margin-bottom: 3px;
        }
        
        .class-tag {
            display: inline-block;
            padding: 2px 8px;
            background: #f8f9fa;
            border-radius: 10px;
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .class-status {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-confirmed {
            background: rgba(46, 213, 115, 0.1);
            color: #2ed573;
        }
        
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        
        .action-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 15px 5px;
            background: #f8f9fa;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
        }
        
        .action-item:hover {
            background: #ff4757;
            color: white;
            transform: translateY(-2px);
        }
        
        .action-item i {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }
        
        .action-item span {
            font-size: 0.8rem;
            text-align: center;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 0.85rem;
        }
        
        th {
            font-weight: 600;
            color: #6c757d;
        }
        
        .progress-tracker {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .progress-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
        }
        
        .progress-bar {
            height: 6px;
            background: #f8f9fa;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progress {
            height: 100%;
            background: linear-gradient(90deg, #ff4757, #ff6b81);
            border-radius: 3px;
        }
        
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 2rem;
            margin-bottom: 10px;
            opacity: 0.3;
            display: block;
        }
        
        .empty-state p {
            margin-bottom: 15px;
        }
        
        .btn-primary {
            background: #ff4757;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            margin-top: 15px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        
        /* Error message */
        .error-message {
            background: #fee;
            border: 1px solid #fcc;
            color: #c00;
            padding: 15px;
            margin: 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .welcome-stats {
                gap: 15px;
            }
            
            .welcome-stats h3 {
                font-size: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .welcome-stats {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Error Banner -->
    <?php if($error): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <h3>Notice</h3>
                <p><?php echo htmlspecialchars($error); ?></p>
                <small>Some features may be limited. The system is still functional.</small>
            </div>
        </div>
    <?php endif; ?>
    
    <?php include 'user-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="search" placeholder="Search dashboard..." aria-label="Search">
            </div>
            <div class="top-bar-actions">
                <button class="btn-notification" id="notificationBtn" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if($notificationCount > 0): ?>
                        <span class="notification-badge"><?php echo $notificationCount; ?></span>
                    <?php endif; ?>
                </button>
                <button class="btn-primary" onclick="window.location.href='user-bookclass.php'">
                    <i class="fas fa-plus"></i> Book Class
                </button>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="welcome-content">
                    <div style="display: flex; align-items: center; margin-bottom: 15px;">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user_full_name, 0, 1)); ?>
                        </div>
                        <div>
                            <h1>Welcome back, <?php echo htmlspecialchars($user_full_name); ?>! 💪</h1>
                            <p>Track your fitness journey and stay motivated</p>
                        </div>
                    </div>
                    <?php if($member && isset($member['MembershipPlan'])): ?>
                        <div class="membership-badge">
                            <i class="fas fa-crown"></i>
                            <span><?php echo htmlspecialchars($member['MembershipPlan']); ?></span>
                            <?php if(isset($member['MembershipStatus'])): ?>
                                <span class="status-badge active">
                                    <?php echo htmlspecialchars($member['MembershipStatus']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3><?php echo $stats['upcoming_classes']; ?></h3>
                        <p>Upcoming Classes</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $stats['success_stories']; ?></h3>
                        <p>Success Stories</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo is_array($recentPayments) ? count($recentPayments) : 0; ?></h3>
                        <p>Recent Payments</p>
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
                        <p><?php echo $member['MembershipPlan'] ?? 'Basic Plan'; ?></p>
                        <span class="status-badge active">Active</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="color: #2ed573;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Classes</h3>
                        <p><?php echo $stats['upcoming_classes']; ?> Booked</p>
                        <a href="user-classes.php">View All →</a>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="color: #ffa502;">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Achievements</h3>
                        <p><?php echo $stats['success_stories']; ?> Stories</p>
                        <a href="user-stories.php">Share Story →</a>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="color: #1e90ff;">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Total Spent</h3>
                        <p>$<?php echo number_format($stats['total_paid'], 2); ?></p>
                        <a href="user-payments.php">View History →</a>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="content-grid">
                <!-- Upcoming Classes -->
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-calendar-alt" style="color: #ff4757;"></i> Upcoming Classes</h3>
                        <a href="user-bookclass.php" style="color: #ff4757; text-decoration: none; font-size: 0.85rem;">Book More</a>
                    </div>
                    <div class="card-body">
                        <?php if(!empty($upcomingClasses)): ?>
                            <?php foreach($upcomingClasses as $class): 
                                $classTime = isset($class['schedule']) ? strtotime($class['schedule']) : time();
                            ?>
                                <div class="class-item">
                                    <div class="class-time">
                                        <h4><?php echo date('g:i A', $classTime); ?></h4>
                                        <p><?php echo date('M j', $classTime); ?></p>
                                    </div>
                                    <div class="class-details">
                                        <h4><?php echo htmlspecialchars($class['class_name'] ?? 'Class'); ?></h4>
                                        <span class="class-tag">
                                            <?php echo htmlspecialchars($class['class_type'] ?? 'General'); ?>
                                        </span>
                                    </div>
                                    <div class="class-status status-confirmed">
                                        Confirmed
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <p>No upcoming classes</p>
                                <button class="btn-primary btn-sm" onclick="window.location.href='user-bookclass.php'">
                                    Book Your First Class
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-bolt" style="color: #ffa502;"></i> Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions-grid">
                            <a href="user-bookclass.php" class="action-item">
                                <i class="fas fa-plus-circle"></i>
                                <span>Book Class</span>
                            </a>
                            <a href="user-profile.php" class="action-item">
                                <i class="fas fa-user-edit"></i>
                                <span>Edit Profile</span>
                            </a>
                            <a href="user-payments.php" class="action-item">
                                <i class="fas fa-credit-card"></i>
                                <span>Make Payment</span>
                            </a>
                            <a href="user-stories.php" class="action-item">
                                <i class="fas fa-trophy"></i>
                                <span>Share Story</span>
                            </a>
                            <a href="user-schedule.php" class="action-item">
                                <i class="fas fa-calendar"></i>
                                <span>View Schedule</span>
                            </a>
                            <a href="user-contact.php" class="action-item">
                                <i class="fas fa-question-circle"></i>
                                <span>Get Help</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recent Payments -->
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-receipt" style="color: #2ed573;"></i> Recent Payments</h3>
                        <a href="user-payments.php" style="color: #ff4757; text-decoration: none; font-size: 0.85rem;">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if(!empty($recentPayments)): ?>
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
                                                <td><?php echo isset($payment['payment_date']) ? date('M j', strtotime($payment['payment_date'])) : 'N/A'; ?></td>
                                                <td><strong>$<?php echo isset($payment['amount']) ? number_format($payment['amount'], 2) : '0.00'; ?></strong></td>
                                                <td><?php echo htmlspecialchars($payment['payment_method'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="status-badge active">
                                                        <?php echo htmlspecialchars($payment['status'] ?? 'Completed'); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-credit-card"></i>
                                <p>No payment history</p>
                                <a href="user-payments.php" class="btn-primary btn-sm">Make Payment</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Progress Tracker -->
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-line" style="color: #1e90ff;"></i> Fitness Progress</h3>
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

    <!-- Notification Panel -->
    <div class="notification-panel" id="notificationPanel">
        <div class="notification-header">
            <h3><i class="fas fa-bell"></i> Notifications</h3>
            <button class="close-btn" id="closeNotifications">&times;</button>
        </div>
        <div class="notification-list">
            <?php if(!empty($notifications)): ?>
                <?php foreach($notifications as $notification): ?>
                    <div class="notification-item" onclick="handleNotificationClick('<?php echo $notification['type']; ?>')">
                        <i class="<?php echo $notification['icon']; ?>" style="color: <?php echo $notification['color'] === 'primary' ? '#ff4757' : ($notification['color'] === 'success' ? '#2ed573' : ($notification['color'] === 'warning' ? '#ffa502' : '#1e90ff')); ?>"></i>
                        <div class="notification-content">
                            <p><strong><?php echo htmlspecialchars($notification['title']); ?></strong></p>
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <small><?php echo timeAgo(strtotime($notification['time'])); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="notification-empty">
                    <i class="far fa-bell-slash"></i>
                    <p>No notifications yet</p>
                    <small>We'll notify you when there's something new</small>
                </div>
            <?php endif; ?>
        </div>
        <?php if(!empty($notifications)): ?>
            <div class="notification-footer">
                <button class="btn-sm btn-secondary w-100" onclick="markAllAsRead()">
                    <i class="fas fa-check"></i> Mark All as Read
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Notification Overlay -->
    <div class="notification-overlay" id="notificationOverlay"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize notifications
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationPanel = document.getElementById('notificationPanel');
            const closeBtn = document.getElementById('closeNotifications');
            const overlay = document.getElementById('notificationOverlay');
            
            if (notificationBtn && notificationPanel) {
                notificationBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationPanel.classList.toggle('active');
                    if (overlay) overlay.classList.toggle('active');
                    
                    // Clear notification badge
                    const badge = this.querySelector('.notification-badge');
                    if (badge) badge.style.display = 'none';
                });
            }
            
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    if (notificationPanel) notificationPanel.classList.remove('active');
                    if (overlay) overlay.classList.remove('active');
                });
            }
            
            if (overlay) {
                overlay.addEventListener('click', function() {
                    if (notificationPanel) notificationPanel.classList.remove('active');
                    this.classList.remove('active');
                });
            }
            
            // Close panel when clicking outside
            document.addEventListener('click', function(e) {
                if (notificationPanel && notificationPanel.classList.contains('active') &&
                    !notificationPanel.contains(e.target) && 
                    notificationBtn && !notificationBtn.contains(e.target)) {
                    notificationPanel.classList.remove('active');
                    if (overlay) overlay.classList.remove('active');
                }
            });
            
            // Close with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && notificationPanel && notificationPanel.classList.contains('active')) {
                    notificationPanel.classList.remove('active');
                    if (overlay) overlay.classList.remove('active');
                }
            });
        });
        
        function handleNotificationClick(type) {
            switch(type) {
                case 'welcome':
                case 'reminder':
                    window.location.href = 'user-bookclass.php';
                    break;
                case 'payment':
                    window.location.href = 'user-payments.php';
                    break;
            }
            closeNotifications();
        }
        
        function markAllAsRead() {
            const notifications = document.querySelectorAll('.notification-item');
            notifications.forEach(n => n.style.opacity = '0.6');
            
            const badge = document.querySelector('.notification-badge');
            if (badge) badge.style.display = 'none';
            
            // Show message
            alert('All notifications marked as read');
            
            setTimeout(() => {
                closeNotifications();
            }, 1000);
        }
        
        function closeNotifications() {
            const panel = document.getElementById('notificationPanel');
            const overlay = document.getElementById('notificationOverlay');
            if (panel) panel.classList.remove('active');
            if (overlay) overlay.classList.remove('active');
        }
    </script>
</body>
</html>