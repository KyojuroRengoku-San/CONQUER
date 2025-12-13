<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get dashboard statistics
try {
    // Total counts
    $totalMembers = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'member'")->fetchColumn();
    $totalTrainers = $pdo->query("SELECT COUNT(*) FROM trainers")->fetchColumn();
    $totalClasses = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    $totalRevenue = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'completed'")->fetchColumn();
    
    // Recent members
    $recentMembers = $pdo->query("
        SELECT u.*, gm.MembershipPlan, gm.JoinDate 
        FROM users u 
        LEFT JOIN gym_members gm ON u.email = gm.Email 
        WHERE u.user_type = 'member' 
        ORDER BY u.created_at DESC 
        LIMIT 5
    ")->fetchAll();
    
    // Recent payments
    $recentPayments = $pdo->query("
        SELECT p.*, u.full_name 
        FROM payments p 
        JOIN users u ON p.user_id = u.id 
        ORDER BY p.payment_date DESC 
        LIMIT 5
    ")->fetchAll();
    
    // Upcoming classes
    $upcomingClasses = $pdo->query("
        SELECT c.*, u.full_name as trainer_name 
        FROM classes c 
        JOIN users u ON c.trainer_id = u.id 
        WHERE c.schedule > NOW() 
        ORDER BY c.schedule ASC 
        LIMIT 5
    ")->fetchAll();
    
    // Equipment needing maintenance
    $maintenanceNeeded = $pdo->query("
        SELECT * FROM equipment 
        WHERE status = 'maintenance' 
        OR next_maintenance <= DATE_ADD(NOW(), INTERVAL 7 DAY) 
        LIMIT 5
    ")->fetchAll();
    
    // Pending success stories
    $pendingStories = $pdo->query("
        SELECT COUNT(*) FROM success_stories WHERE approved = 0
    ")->fetchColumn();
    
    // Monthly revenue data (last 6 months)
    $revenueData = $pdo->query("
        SELECT 
            DATE_FORMAT(payment_date, '%Y-%m') as month,
            SUM(amount) as revenue
        FROM payments 
        WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        AND status = 'completed'
        GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
        ORDER BY month ASC
    ")->fetchAll();
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
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
        .revenue-chart {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .alert-badge {
            background: #ff4757;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .admin-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .admin-action-btn {
            background: var(--light-color);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            color: var(--dark-color);
            text-decoration: none;
        }
        
        .admin-action-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-3px);
            border-color: var(--primary-color);
        }
        
        .admin-action-btn i {
            font-size: 1.5rem;
        }
        
        .admin-action-btn span {
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .system-health {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding: 1rem;
            background: var(--light-color);
            border-radius: 10px;
        }
        
        .health-item {
            text-align: center;
        }
        
        .health-item i {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .health-item.good i { color: #2ed573; }
        .health-item.warning i { color: #ffa502; }
        .health-item.danger i { color: #ff4757; }
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
                <span class="nav-badge"><?php echo $totalClasses; ?></span>
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
                <input type="text" placeholder="Search members, classes, payments...">
            </div>
            <div class="top-bar-actions">
                <button class="btn-notification">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge"><?php echo $pendingStories + count($maintenanceNeeded); ?></span>
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
                    <h1>Admin Dashboard <span class="admin-badge">SYSTEM</span></h1>
                    <p>Manage your gym operations, members, and revenue</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3><?php echo $totalMembers; ?></h3>
                        <p>Total Members</p>
                    </div>
                    <div class="stat">
                        <h3>$<?php echo number_format($totalRevenue, 2); ?></h3>
                        <p>Total Revenue</p>
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
                        <h3>Total Members</h3>
                        <p><?php echo $totalMembers; ?> Active</p>
                        <span class="status-badge success">+12% this month</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Revenue</h3>
                        <p>$<?php echo number_format($totalRevenue, 2); ?></p>
                        <span class="status-badge success">+18% growth</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Classes</h3>
                        <p><?php echo $totalClasses; ?> Scheduled</p>
                        <a href="admin-classes.php">Manage →</a>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Equipment</h3>
                        <p><?php echo count($maintenanceNeeded); ?> Need Maintenance</p>
                        <?php if(count($maintenanceNeeded) > 0): ?>
                            <span class="alert-badge">Attention Required</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Revenue Chart -->
            <div class="revenue-chart">
                <h3>Monthly Revenue</h3>
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
                                        <th>Joined</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recentMembers as $member): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                                            <td><?php echo htmlspecialchars($member['MembershipPlan'] ?? 'N/A'); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($member['created_at'])); ?></td>
                                            <td>
                                                <button class="btn-sm" onclick="window.location.href='admin-member-view.php?id=<?php echo $member['id']; ?>'">
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
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
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($recentPayments as $payment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payment['full_name']); ?></td>
                                            <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                            <td><?php echo date('M j', strtotime($payment['payment_date'])); ?></td>
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
                                        <h4><?php echo date('g:i A', strtotime($class['schedule'])); ?></h4>
                                        <p><?php echo date('M j', strtotime($class['schedule'])); ?></p>
                                    </div>
                                    <div class="class-details">
                                        <h4><?php echo htmlspecialchars($class['class_name']); ?></h4>
                                        <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($class['trainer_name']); ?></p>
                                        <p><?php echo $class['current_enrollment']; ?>/<?php echo $class['max_capacity']; ?> enrolled</p>
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
                                        <h4><?php echo htmlspecialchars($equipment['equipment_name']); ?></h4>
                                        <p><?php echo htmlspecialchars($equipment['brand']); ?> • <?php echo htmlspecialchars($equipment['location']); ?></p>
                                        <p class="text-danger">
                                            <small>
                                                <?php if($equipment['status'] === 'maintenance'): ?>
                                                    Under maintenance since <?php echo date('M j', strtotime($equipment['last_maintenance'])); ?>
                                                <?php else: ?>
                                                    Maintenance due on <?php echo date('M j', strtotime($equipment['next_maintenance'])); ?>
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
                    <h3>Quick Admin Actions</h3>
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
        // Revenue Chart
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
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>