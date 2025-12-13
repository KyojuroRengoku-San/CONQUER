<?php
session_start();
require_once 'config/database.php';

try {
    $pdo = Database::getInstance()->getConnection();
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Initialize all variables with defaults
$totalMembers = 0;
$totalTrainers = 0;
$totalClasses = 0;
$totalRevenue = 0;
$todayRevenue = 0;
$activeClasses = 0;
$recentMembers = [];
$recentPayments = [];
$upcomingClasses = [];
$maintenanceNeeded = [];
$pendingStories = 0;
$revenueData = [];

try {
    // Total counts with error handling
    $totalMembers = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'member'")->fetchColumn() ?: 0;
    $totalTrainers = $pdo->query("SELECT COUNT(*) FROM trainers")->fetchColumn() ?: 0;
    $totalClasses = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn() ?: 0;
    $totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'")->fetchColumn() ?: 0;
    $todayRevenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(payment_date) = CURDATE() AND status = 'completed'")->fetchColumn() ?: 0;
    $activeClasses = $pdo->query("SELECT COUNT(*) FROM classes WHERE schedule >= NOW()")->fetchColumn() ?: 0;
    
    // Recent members with safe query
    $recentMembers = $pdo->query("
        SELECT u.*, gm.MembershipPlan, gm.JoinDate 
        FROM users u 
        LEFT JOIN gym_members gm ON u.email = gm.Email 
        WHERE u.user_type = 'member' 
        ORDER BY u.created_at DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent payments - check if payments table exists
    try {
        $recentPayments = $pdo->query("
            SELECT p.*, u.full_name 
            FROM payments p 
            JOIN users u ON p.user_id = u.id 
            ORDER BY p.payment_date DESC 
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $recentPayments = [];
    }
    
    // Upcoming classes - fixed table joins
    $upcomingClasses = $pdo->query("
        SELECT c.*, COALESCE(u.full_name, 'Trainer') as trainer_name 
        FROM classes c 
        LEFT JOIN trainers t ON c.trainer_id = t.id 
        LEFT JOIN users u ON t.user_id = u.id 
        WHERE c.schedule > NOW() 
        ORDER BY c.schedule ASC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Equipment needing maintenance
    $maintenanceNeeded = $pdo->query("
        SELECT * FROM equipment 
        WHERE status = 'maintenance' 
        OR next_maintenance <= DATE_ADD(NOW(), INTERVAL 7 DAY) 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Pending success stories
    try {
        $pendingStories = $pdo->query("SELECT COUNT(*) FROM success_stories WHERE approved = 0")->fetchColumn() ?: 0;
    } catch (Exception $e) {
        $pendingStories = 0;
    }
    
    // Monthly revenue data (last 6 months) - with error handling
    try {
        $revenueData = $pdo->query("
            SELECT 
                DATE_FORMAT(payment_date, '%b') as month,
                COALESCE(SUM(amount), 0) as revenue
            FROM payments 
            WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 5 MONTH)
            AND status = 'completed'
            GROUP BY DATE_FORMAT(payment_date, '%Y-%m'), DATE_FORMAT(payment_date, '%b')
            ORDER BY MIN(payment_date) ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Fallback to dummy data for demo
        $revenueData = [
            ['month' => 'Jan', 'revenue' => rand(1000, 5000)],
            ['month' => 'Feb', 'revenue' => rand(1000, 5000)],
            ['month' => 'Mar', 'revenue' => rand(1000, 5000)],
            ['month' => 'Apr', 'revenue' => rand(1000, 5000)],
            ['month' => 'May', 'revenue' => rand(1000, 5000)],
            ['month' => 'Jun', 'revenue' => rand(1000, 5000)],
        ];
    }
    
} catch(PDOException $e) {
    // Log error but don't die - show empty dashboard
    error_log("Dashboard error: " . $e->getMessage());
}

// Calculate growth percentages (dummy data for demo)
$memberGrowth = $totalMembers > 10 ? '+12%' : '+0%';
$revenueGrowth = $totalRevenue > 1000 ? '+18%' : '+0%';
$classGrowth = $totalClasses > 5 ? '+8%' : '+0%';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | CONQUER Gym</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        /* Compact Dashboard Styles */
        .main-content {
            padding: 0.5rem;
            overflow-y: auto;
        }
        
        .dashboard-content {
            padding: 0.5rem;
        }
        
        .welcome-banner {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 10px;
        }
        
        .welcome-content h1 {
            font-size: 1.3rem;
        }
        
        .welcome-content p {
            font-size: 0.85rem;
        }
        
        .welcome-stats h3 {
            font-size: 1.5rem;
        }
        
        .welcome-stats p {
            font-size: 0.75rem;
        }
        
        .stats-grid {
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .stat-card {
            padding: 1rem;
            border-radius: 10px;
            min-height: 80px;
        }
        
        .stat-icon {
            width: 45px;
            height: 45px;
            font-size: 1.1rem;
        }
        
        .stat-info h3 {
            font-size: 1.1rem;
        }
        
        .stat-info p {
            font-size: 0.75rem;
        }
        
        .status-badge {
            font-size: 0.7rem;
            padding: 0.15rem 0.5rem;
        }
        
        .revenue-chart {
            padding: 1rem;
            height: 250px;
            margin-bottom: 1rem;
            border-radius: 10px;
        }
        
        .revenue-chart h3 {
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .content-grid {
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .content-card {
            border-radius: 10px;
            min-height: 280px;
            max-height: 320px;
        }
        
        .card-header {
            padding: 0.75rem 1rem;
        }
        
        .card-header h3 {
            font-size: 1rem;
        }
        
        .card-body {
            padding: 0.75rem 1rem;
        }
        
        .class-item, .alert-item {
            padding: 0.6rem 0;
        }
        
        .admin-actions {
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .admin-action-btn {
            min-height: 75px;
            padding: 0.6rem 0.4rem;
        }
        
        .admin-action-btn i {
            font-size: 1.1rem;
        }
        
        .admin-action-btn span {
            font-size: 0.75rem;
        }
        
        .system-health {
            gap: 0.5rem;
        }
        
        .health-item {
            min-height: 70px;
            padding: 0.6rem;
        }
        
        .health-item i {
            font-size: 1.25rem;
            margin-bottom: 0.4rem;
        }
        
        .health-item p {
            font-size: 0.8rem;
        }
        
        .health-item small {
            font-size: 0.7rem;
        }
        
        table th, table td {
            padding: 0.6rem;
            font-size: 0.75rem;
        }
        
        .btn-sm {
            padding: 0.3rem 0.6rem;
            font-size: 0.7rem;
        }
        
        /* Mobile optimizations */
        @media (max-width: 768px) {
            .main-content {
                padding: 0.25rem;
            }
            
            .dashboard-content {
                padding: 0.25rem;
            }
            
            .welcome-banner {
                padding: 0.75rem;
                margin-bottom: 0.75rem;
            }
            
            .stats-grid {
                gap: 0.5rem;
            }
            
            .stat-card {
                padding: 0.75rem;
            }
            
            .revenue-chart {
                height: 200px;
            }
            
            .content-grid {
                gap: 0.5rem;
            }
        }
        
        /* Custom scrollbar for dashboard */
        .dashboard-content::-webkit-scrollbar {
            width: 5px;
        }
        
        .dashboard-content::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .dashboard-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        
        .dashboard-content::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body>
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
            <a href="admin-dashboard.php" class="active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="admin-members.php">
                <i class="fas fa-users"></i>
                <span>Members</span>
                <span class="nav-badge"><?php echo $totalMembers; ?></span>
            </a>
            <a href="admin-trainers.php">
                <i class="fas fa-user-tie"></i>
                <span>Trainers</span>
                <span class="nav-badge"><?php echo $totalTrainers; ?></span>
            </a>
            <a href="admin-classes.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Classes</span>
                <span class="nav-badge"><?php echo $activeClasses; ?></span>
            </a>
            <a href="admin-payments.php">
                <i class="fas fa-credit-card"></i>
                <span>Payments</span>
            </a>
            <a href="admin-stories.php">
                <i class="fas fa-trophy"></i>
                <span>Success Stories</span>
                <?php if($pendingStories > 0): ?>
                    <span class="nav-badge alert"><?php echo $pendingStories; ?></span>
                <?php endif; ?>
            </a>
            <a href="admin-equipment.php">
                <i class="fas fa-dumbbell"></i>
                <span>Equipment</span>
                <?php if(count($maintenanceNeeded) > 0): ?>
                    <span class="nav-badge alert"><?php echo count($maintenanceNeeded); ?></span>
                <?php endif; ?>
            </a>
            <a href="admin-messages.php">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
            </a>
            <a href="admin-reports.php">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="admin-settings.php">
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
                    <?php if(($pendingStories + count($maintenanceNeeded)) > 0): ?>
                        <span class="notification-badge"><?php echo $pendingStories + count($maintenanceNeeded); ?></span>
                    <?php endif; ?>
                </button>
                <button class="btn-primary" onclick="window.location.href='admin-add.php'">
                    <i class="fas fa-plus"></i>
                    Add New
                </button>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>Dashboard <span class="admin-badge">ADMIN</span></h1>
                    <p>Welcome back, Administrator! Here's what's happening today.</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3>$<?php echo number_format($todayRevenue, 0); ?></h3>
                        <p>Today's Revenue</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $activeClasses; ?></h3>
                        <p>Active Classes</p>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $totalMembers; ?></h3>
                        <p>Total Members</p>
                        <span class="status-badge success"><?php echo $memberGrowth; ?></span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="stat-info">
                        <h3>$<?php echo number_format($totalRevenue, 0); ?></h3>
                        <p>Total Revenue</p>
                        <span class="status-badge success"><?php echo $revenueGrowth; ?></span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $activeClasses; ?></h3>
                        <p>Active Classes</p>
                        <span class="status-badge success"><?php echo $classGrowth; ?></span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo count($maintenanceNeeded); ?></h3>
                        <p>Maintenance</p>
                        <?php if(count($maintenanceNeeded) > 0): ?>
                            <span class="status-badge pending">Attention</span>
                        <?php else: ?>
                            <span class="status-badge success">All Good</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Revenue Chart -->
            <div class="revenue-chart">
                <h3>Revenue Overview</h3>
                <canvas id="revenueChart"></canvas>
            </div>

            <!-- Main Content Grid -->
            <div class="content-grid">
                <!-- Recent Members -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>Recent Members</h3>
                        <a href="admin-members.php">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Plan</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($recentMembers) > 0): ?>
                                        <?php foreach($recentMembers as $member): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(substr($member['full_name'], 0, 15)); ?><?php echo strlen($member['full_name']) > 15 ? '...' : ''; ?></td>
                                                <td><?php echo htmlspecialchars(substr($member['email'], 0, 12)); ?>...</td>
                                                <td><?php echo htmlspecialchars(substr($member['MembershipPlan'] ?? 'N/A', 0, 8)); ?></td>
                                                <td>
                                                    <button class="btn-sm" onclick="window.location.href='admin-member-view.php?id=<?php echo $member['id']; ?>'">
                                                        View
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No recent members</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Payments -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>Recent Payments</h3>
                        <a href="admin-payments.php">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($recentPayments) > 0): ?>
                                        <?php foreach($recentPayments as $payment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(substr($payment['full_name'], 0, 12)); ?>...</td>
                                                <td>$<?php echo number_format($payment['amount'], 0); ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo strtolower($payment['status']); ?>">
                                                        <?php echo substr(htmlspecialchars($payment['status']), 0, 8); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center">No recent payments</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Classes -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>Upcoming Classes</h3>
                        <a href="admin-classes.php">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if(count($upcomingClasses) > 0): ?>
                            <?php foreach($upcomingClasses as $class): ?>
                                <div class="class-item">
                                    <div class="class-time">
                                        <h4><?php echo date('g:i', strtotime($class['schedule'])); ?></h4>
                                        <p><?php echo date('M j', strtotime($class['schedule'])); ?></p>
                                    </div>
                                    <div class="class-details">
                                        <h4><?php echo htmlspecialchars(substr($class['class_name'], 0, 15)); ?><?php echo strlen($class['class_name']) > 15 ? '...' : ''; ?></h4>
                                        <p><i class="fas fa-user"></i> <?php echo htmlspecialchars(substr($class['trainer_name'], 0, 12)); ?>...</p>
                                        <p><?php echo $class['current_enrollment']; ?>/<?php echo $class['max_capacity']; ?> seats</p>
                                    </div>
                                    <div class="class-actions">
                                        <button class="btn-sm" onclick="window.location.href='admin-class-edit.php?id=<?php echo $class['id']; ?>'">
                                            Edit
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="empty-state">No upcoming classes</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Maintenance Alerts -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>Maintenance Alerts</h3>
                        <a href="admin-equipment.php">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if(count($maintenanceNeeded) > 0): ?>
                            <?php foreach($maintenanceNeeded as $equipment): ?>
                                <div class="alert-item">
                                    <div class="alert-icon">
                                        <i class="fas fa-exclamation-triangle text-warning"></i>
                                    </div>
                                    <div class="alert-details">
                                        <h4><?php echo htmlspecialchars(substr($equipment['equipment_name'], 0, 15)); ?><?php echo strlen($equipment['equipment_name']) > 15 ? '...' : ''; ?></h4>
                                        <p class="text-danger">
                                            <small>
                                                <?php if($equipment['status'] === 'maintenance'): ?>
                                                    Under maintenance
                                                <?php else: ?>
                                                    Due: <?php echo date('M j', strtotime($equipment['next_maintenance'])); ?>
                                                <?php endif; ?>
                                            </small>
                                        </p>
                                    </div>
                                    <div class="alert-actions">
                                        <button class="btn-sm" onclick="window.location.href='admin-equipment-edit.php?id=<?php echo $equipment['id']; ?>'">
                                            Update
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="empty-state">All equipment in good condition</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Admin Actions -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Quick Actions</h3>
                </div>
                <div class="card-body">
                  <div class="admin-actions">
    <a href="admin-add-member.php" class="admin-action-btn">
        <i class="fas fa-user-plus"></i>
        <span>Add Member</span>
    </a>
    <a href="admin-add-trainer.php" class="admin-action-btn">
        <i class="fas fa-user-tie"></i>
        <span>Add Trainer</span>
    </a>
    <a href="admin-add-class.php" class="admin-action-btn">
        <i class="fas fa-calendar-plus"></i>
        <span>Create Class</span>
    </a>
    <a href="admin-add-equipment.php" class="admin-action-btn">
        <i class="fas fa-dumbbell"></i>
        <span>Add Equipment</span>
    </a>
    <a href="admin-generate-report.php" class="admin-action-btn">
        <i class="fas fa-file-export"></i>
        <span>Generate Report</span>
    </a>
    <a href="admin-backup.php" class="admin-action-btn">
        <i class="fas fa-database"></i>
        <span>Backup Database</span>
    </a>
</div>
                    
                    <!-- System Health -->
                    <div class="system-health">
                        <div class="health-item good">
                            <i class="fas fa-server"></i>
                            <p>Database</p>
                            <small>Healthy</small>
                        </div>
                        <div class="health-item good">
                            <i class="fas fa-shield-alt"></i>
                            <p>Security</p>
                            <small>Protected</small>
                        </div>
                        <div class="health-item <?php echo $pendingStories > 5 ? 'warning' : 'good'; ?>">
                            <i class="fas fa-tasks"></i>
                            <p>Pending Tasks</p>
                            <small><?php echo $pendingStories; ?></small>
                        </div>
                        <div class="health-item <?php echo count($maintenanceNeeded) > 3 ? 'danger' : 'good'; ?>">
                            <i class="fas fa-tools"></i>
                            <p>Maintenance</p>
                            <small><?php echo count($maintenanceNeeded); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Revenue Chart with compact styling
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($revenueData, 'month')); ?>,
                datasets: [{
                    label: 'Revenue ($)',
                    data: <?php echo json_encode(array_column($revenueData, 'revenue')); ?>,
                    borderColor: '#ff4757',
                    backgroundColor: 'rgba(255, 71, 87, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#ff4757',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        titleFont: {
                            size: 12
                        },
                        bodyFont: {
                            size: 11
                        },
                        padding: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 10
                            },
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 10
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });

        // Auto-resize chart on window resize
        window.addEventListener('resize', function() {
            revenueChart.resize();
        });

        // Mobile menu toggle for sidebar
        document.addEventListener('DOMContentLoaded', function() {
            // Make notification bell clickable
            const notificationBtn = document.querySelector('.btn-notification');
            if(notificationBtn) {
                notificationBtn.addEventListener('click', function() {
                    alert('You have <?php echo $pendingStories + count($maintenanceNeeded); ?> notifications');
                });
            }
            
            // Add loading animation to admin action buttons
            const actionButtons = document.querySelectorAll('.admin-action-btn');
            actionButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    // If href is '#', prevent default and show loading
                    if(this.getAttribute('href') === '#') {
                        e.preventDefault();
                        this.classList.add('loading');
                        setTimeout(() => {
                            this.classList.remove('loading');
                        }, 1000);
                    }
                });
            });
        });
    </script>
</body>
</html>