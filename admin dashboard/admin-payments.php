<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

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

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// FIXED: Removed SubscriptionEndDate since it doesn't exist in gym_members table
$payments = $pdo->prepare("
    SELECT p.*, u.full_name, u.email, 
    m.MembershipPlan
    FROM payments p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN gym_members m ON u.email = m.Email
    $whereSQL
    ORDER BY p.payment_date DESC
    LIMIT 50
");
$payments->execute($params);
$payments = $payments->fetchAll();

// Stats
$totalRevenue = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'completed'")->fetchColumn();
$monthlyRevenue = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'completed' AND DATE_FORMAT(payment_date, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')")->fetchColumn();
$pendingPayments = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management | Admin Dashboard</title>
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        .payment-amount {
            font-weight: 700;
            font-size: 1.1rem;
        }
        .payment-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status-completed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-failed { background: #f8d7da; color: #721c24; }
        
        .payment-filters {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            margin-bottom: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card-small {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stat-card-small h3 {
            margin: 0;
            color: var(--primary-color);
        }
    </style>
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
            <div class="stats-grid">
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
                        <table>
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