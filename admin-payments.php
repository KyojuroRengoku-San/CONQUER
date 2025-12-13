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

// Initialize variables
$payments = [];
$totalRevenue = 0;
$monthlyRevenue = 0;
$pendingPayments = 0;

try {
    // Get payments with filters
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $month = isset($_GET['month']) ? $_GET['month'] : '';

    $whereClauses = [];
    $params = [];

    if($status) {
        $whereClauses[] = "p.status = ?";
        $params[] = $status;
    }

    if($month) {
        $whereClauses[] = "DATE_FORMAT(p.payment_date, '%Y-%m') = ?";
        $params[] = $month;
    }

    $whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    $sql = "
        SELECT p.*, u.full_name, u.email, 
        gm.MembershipPlan
        FROM payments p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN gym_members gm ON u.email = gm.Email
        $whereSQL
        ORDER BY p.payment_date DESC
        LIMIT 50
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Stats with fallbacks
    $totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'")->fetchColumn();
    $monthlyRevenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND DATE_FORMAT(payment_date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')")->fetchColumn();
    $pendingPayments = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Payments error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management | Admin Dashboard</title>
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
                <input type="text" placeholder="Search payments...">
            </div>
            <div class="top-bar-actions">
                <button class="btn-primary" onclick="window.location.href='admin-add-payment.php'">
                    <i class="fas fa-plus"></i> Record Payment
                </button>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>Payment Management</h1>
                    <p>Monitor and manage all payments</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3>$<?php echo number_format($totalRevenue, 2); ?></h3>
                        <p>Total Revenue</p>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="stats-grid-small">
                <div class="stat-card-small">
                    <h3>$<?php echo number_format($monthlyRevenue, 2); ?></h3>
                    <p>This Month</p>
                </div>
                <div class="stat-card-small">
                    <h3><?php echo $pendingPayments; ?></h3>
                    <p>Pending</p>
                </div>
                <div class="stat-card-small">
                    <h3><?php echo count($payments); ?></h3>
                    <p>Recent Payments</p>
                </div>
                <div class="stat-card-small">
                    <h3>97%</h3>
                    <p>Success Rate</p>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" class="payment-filters">
                <div>
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>
                <div>
                    <label>Month</label>
                    <input type="month" name="month" value="<?php echo $month; ?>">
                </div>
                <button type="submit" class="btn-primary">Filter</button>
                <button type="button" class="btn-secondary" onclick="window.location.href='admin-payments.php'">Clear</button>
            </form>

            <!-- Payments Table -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Payment History</h3>
                    <a href="admin-export-payments.php" class="btn-secondary">
                        <i class="fas fa-download"></i> Export
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="payments-table">
                            <thead>
                                <tr>
                                    <th>Payment ID</th>
                                    <th>Member</th>
                                    <th>Plan</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($payments as $payment): ?>
                                    <tr>
                                        <td>#PAY-<?php echo str_pad($payment['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($payment['full_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($payment['email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($payment['MembershipPlan'] ?? 'N/A'); ?></td>
                                        <td class="payment-amount">$<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?><br>
                                            <small><?php echo date('g:i A', strtotime($payment['payment_date'])); ?></small>
                                        </td>
                                        <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                        <td>
                                            <span class="payment-status status-<?php echo $payment['status']; ?>">
                                                <?php echo ucfirst($payment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <button class="btn-sm" onclick="window.location.href='admin-payment-view.php?id=<?php echo $payment['id']; ?>'">
                                                    <i class="fas fa-receipt"></i>
                                                </button>
                                                <?php if($payment['status'] === 'pending'): ?>
                                                    <button class="btn-sm btn-success" onclick="markAsPaid(<?php echo $payment['id']; ?>)">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function markAsPaid(paymentId) {
            if(confirm('Mark this payment as completed?')) {
                window.location.href = 'admin-mark-paid.php?id=' + paymentId;
            }
        }
    </script>
</body>
</html>