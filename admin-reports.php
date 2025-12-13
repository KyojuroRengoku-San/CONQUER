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

// Get report data
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Initialize all variables
$revenueData = [];
$membershipData = [];
$attendanceData = [];
$topTrainers = [];
$topPlans = [];

try {
    // Revenue report with error handling
    $revenueStmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(payment_date, '%Y-%m-%d') as date,
            COUNT(*) as count,
            COALESCE(SUM(amount), 0) as revenue
        FROM payments 
        WHERE payment_date BETWEEN ? AND ?
        AND status = 'completed'
        GROUP BY DATE(payment_date)
        ORDER BY date
    ");
    $revenueStmt->execute([$startDate, $endDate]);
    $revenueData = $revenueStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Membership growth
    $membershipStmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as new_members
        FROM users 
        WHERE user_type = 'member'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $membershipStmt->execute();
    $membershipData = $membershipStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Class attendance - with safe fallback
    try {
        $attendanceStmt = $pdo->prepare("
            SELECT 
                c.class_type,
                COUNT(b.id) as attendance,
                AVG(c.current_enrollment) as avg_attendance
            FROM classes c
            LEFT JOIN bookings b ON c.id = b.class_id AND b.status = 'confirmed'
            WHERE c.schedule BETWEEN ? AND ?
            GROUP BY c.class_type
        ");
        $attendanceStmt->execute([$startDate, $endDate]);
        $attendanceData = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $attendanceData = [];
    }
    
    // Top trainers - with proper error handling
    try {
        $trainersStmt = $pdo->prepare("
            SELECT 
                COALESCE(u.full_name, 'Unknown') as full_name,
                COUNT(c.id) as classes_taught,
                AVG(c.current_enrollment) as avg_attendance
            FROM classes c
            LEFT JOIN trainers t ON c.trainer_id = t.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE c.schedule BETWEEN ? AND ?
            GROUP BY c.trainer_id
            ORDER BY classes_taught DESC
            LIMIT 5
        ");
        $trainersStmt->execute([$startDate, $endDate]);
        $topTrainers = $trainersStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $topTrainers = [];
    }
    
    // Top memberships
    try {
        $topPlans = $pdo->query("
            SELECT 
                MembershipPlan,
                COUNT(*) as count,
                COUNT(*) * 100.0 / (SELECT COUNT(*) FROM gym_members WHERE MembershipPlan IS NOT NULL) as percentage
            FROM gym_members 
            WHERE MembershipPlan IS NOT NULL AND MembershipPlan != ''
            GROUP BY MembershipPlan
            ORDER BY count DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $topPlans = [];
    }
    
} catch (PDOException $e) {
    error_log("Reports error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics | Admin Dashboard</title>
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
</head>
<body>
    <?php include 'admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search reports...">
            </div>
            <div class="top-bar-actions">
                <button class="btn-primary" onclick="generatePDF()">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
                <button class="btn-primary" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>Reports & Analytics</h1>
                    <p>Comprehensive insights and analytics for your gym</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3>$<?php echo array_sum(array_column($revenueData, 'revenue')); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo array_sum(array_column($revenueData, 'count')); ?></h3>
                        <p>Transactions</p>
                    </div>
                </div>
            </div>

            <!-- Date Filters -->
            <form method="GET" class="report-filters">
                <div>
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?php echo $startDate; ?>">
                </div>
                <div>
                    <label>End Date</label>
                    <input type="date" name="end_date" value="<?php echo $endDate; ?>">
                </div>
                <button type="submit" class="btn-primary">Generate Report</button>
                <button type="button" class="btn-secondary" onclick="window.location.href='admin-reports.php'">
                    Reset to Current Month
                </button>
            </form>

            <!-- Reports Grid -->
            <div class="reports-grid">
                <!-- Revenue Chart -->
                <div class="report-card full-width">
                    <h3>Revenue Trend</h3>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <!-- Membership Growth -->
                <div class="report-card">
                    <h3>Membership Growth</h3>
                    <div class="chart-container">
                        <canvas id="membershipChart"></canvas>
                    </div>
                </div>

                <!-- Class Attendance -->
                <div class="report-card">
                    <h3>Class Attendance</h3>
                    <div class="chart-container">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>

                <!-- Top Trainers -->
                <div class="report-card">
                    <h3>Top Trainers</h3>
                    <div style="margin-top: 1rem;">
                        <?php foreach($topTrainers as $trainer): ?>
                            <div class="stat-row">
                                <div class="stat-label"><?php echo htmlspecialchars($trainer['full_name']); ?></div>
                                <div class="stat-value"><?php echo $trainer['classes_taught']; ?> classes</div>
                                <div class="stat-change change-positive">
                                    <?php echo round($trainer['avg_attendance'], 1); ?> avg
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Membership Plans -->
                <div class="report-card">
                    <h3>Membership Plans Distribution</h3>
                    <div style="margin-top: 1rem;">
                        <?php foreach($topPlans as $plan): ?>
                            <div class="stat-row">
                                <div class="stat-label"><?php echo htmlspecialchars($plan['MembershipPlan']); ?></div>
                                <div class="stat-value"><?php echo $plan['count']; ?> members</div>
                                <div class="stat-change change-positive">
                                    <?php echo round($plan['percentage'], 1); ?>%
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Key Metrics -->
                <div class="report-card">
                    <h3>Key Performance Indicators</h3>
                    <div style="margin-top: 1rem;">
                        <?php
                        $totalRevenue = array_sum(array_column($revenueData, 'revenue'));
                        $avgDailyRevenue = $totalRevenue / max(count($revenueData), 1);
                        $transactionCount = array_sum(array_column($revenueData, 'count'));
                        $avgTransaction = $totalRevenue / max($transactionCount, 1);
                        ?>
                        <div class="stat-row">
                            <div class="stat-label">Total Revenue</div>
                            <div class="stat-value">$<?php echo number_format($totalRevenue, 2); ?></div>
                        </div>
                        <div class="stat-row">
                            <div class="stat-label">Avg Daily Revenue</div>
                            <div class="stat-value">$<?php echo number_format($avgDailyRevenue, 2); ?></div>
                        </div>
                        <div class="stat-row">
                            <div class="stat-label">Total Transactions</div>
                            <div class="stat-value"><?php echo $transactionCount; ?></div>
                        </div>
                        <div class="stat-row">
                            <div class="stat-label">Avg Transaction Value</div>
                            <div class="stat-value">$<?php echo number_format($avgTransaction, 2); ?></div>
                        </div>
                        <div class="stat-row">
                            <div class="stat-label">Class Attendance Rate</div>
                            <div class="stat-value"><?php echo count($attendanceData) > 0 ? round(array_sum(array_column($attendanceData, 'attendance')) / max(array_sum(array_column($attendanceData, 'avg_attendance')) * count($attendanceData), 1) * 100, 1) : 0; ?>%</div>
                            <div class="stat-change change-positive">+5%</div>
                        </div>
                        <div class="stat-row">
                            <div class="stat-label">Member Retention</div>
                            <div class="stat-value">92%</div>
                            <div class="stat-change change-positive">+2%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($revenueData, 'date')); ?>,
                datasets: [{
                    label: 'Revenue ($)',
                    data: <?php echo json_encode(array_column($revenueData, 'revenue')); ?>,
                    borderColor: '#ff4757',
                    backgroundColor: 'rgba(255, 71, 87, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                }
            }
        });

        // Membership Growth Chart
        const membershipCtx = document.getElementById('membershipChart').getContext('2d');
        new Chart(membershipCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($membershipData, 'month')); ?>,
                datasets: [{
                    label: 'New Members',
                    data: <?php echo json_encode(array_column($membershipData, 'new_members')); ?>,
                    backgroundColor: '#667eea'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                }
            }
        });

        // Attendance Chart
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(attendanceCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($attendanceData, 'class_type')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($attendanceData, 'attendance')); ?>,
                    backgroundColor: [
                        '#ff4757', '#2ed573', '#1e90ff', '#ffa502', '#ff6b81', '#a29bfe'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });

        function generatePDF() {
            window.location.href = 'admin-generate-pdf.php?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>';
        }
        
        function exportToExcel() {
            window.location.href = 'admin-export-excel.php?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>';
        }
    </script>
</body>
</html>