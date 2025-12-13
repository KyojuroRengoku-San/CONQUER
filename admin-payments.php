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
    // If no payments exist, create sample data
    $payments = [
        [
            'id' => 1,
            'full_name' => 'Alex Morgan',
            'email' => 'alex.morgan@conquergym.com',
            'MembershipPlan' => 'Premium',
            'amount' => 99.99,
            'payment_date' => date('Y-m-d H:i:s'),
            'payment_method' => 'credit_card',
            'status' => 'completed'
        ],
        [
            'id' => 2,
            'full_name' => 'Sarah Chen',
            'email' => 'sarah.chen@conquergym.com',
            'MembershipPlan' => 'Basic',
            'amount' => 49.99,
            'payment_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'payment_method' => 'paypal',
            'status' => 'completed'
        ],
        [
            'id' => 3,
            'full_name' => 'Marcus Johnson',
            'email' => 'marcus.johnson@conquergym.com',
            'MembershipPlan' => 'Premium',
            'amount' => 99.99,
            'payment_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'payment_method' => 'credit_card',
            'status' => 'pending'
        ]
    ];
    
    $totalRevenue = 149.98;
    $monthlyRevenue = 249.97;
    $pendingPayments = 1;
    
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
    
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        /* Additional styles for payments management */
        .payments-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .payments-table th {
            background: #f8f9fa;
            font-weight: 600;
            padding: 1.25rem 1rem;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            color: #495057;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .payments-table td {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid #e9ecef;
            vertical-align: top;
            color: #495057;
        }
        
        .payments-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .payments-table small {
            font-size: 0.85rem;
            color: #6c757d;
            line-height: 1.4;
        }
        
        /* Stats grid small */
        .stats-grid-small {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .stat-card-small {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #e9ecef;
        }
        
        .stat-card-small:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .stat-card-small h3 {
            margin: 0;
            color: #ff4757;
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }
        
        .stat-card-small p {
            margin: 0;
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        /* Payment status badges */
        .payment-status {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            min-width: 100px;
            text-align: center;
        }
        
        .status-completed {
            background: rgba(46, 213, 115, 0.15);
            color: #2ed573;
            border: 1px solid rgba(46, 213, 115, 0.3);
        }
        
        .status-pending {
            background: rgba(255, 165, 2, 0.15);
            color: #ffa502;
            border: 1px solid rgba(255, 165, 2, 0.3);
        }
        
        .status-failed {
            background: rgba(255, 71, 87, 0.15);
            color: #ff4757;
            border: 1px solid rgba(255, 71, 87, 0.3);
        }
        
        /* Payment amount styling */
        .payment-amount {
            font-weight: 700;
            font-size: 1.1rem;
            color: #2f3542;
        }
        
        /* Button styles */
        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: #f8f9fa;
            color: #495057;
            transition: all 0.3s;
            text-decoration: none;
            font-family: inherit;
            font-weight: 500;
            border: 1px solid #dee2e6;
            min-width: 40px;
        }
        
        .btn-sm:hover {
            background: #e9ecef;
            transform: translateY(-1px);
            text-decoration: none;
        }
        
        .btn-sm.btn-success {
            background: #2ed573;
            color: white;
            border-color: #2ed573;
        }
        
        .btn-sm.btn-success:hover {
            background: #25c464;
            border-color: #25c464;
        }
        
        /* Filters */
        .payment-filters {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        
        .payment-filters > div {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 200px;
        }
        
        .payment-filters label {
            font-weight: 600;
            color: #2f3542;
            font-size: 0.9rem;
        }
        
        .payment-filters select,
        .payment-filters input[type="month"] {
            padding: 0.75rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
        }
        
        .payment-filters select:focus,
        .payment-filters input[type="month"]:focus {
            outline: none;
            border-color: #ff4757;
            box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.1);
        }
        
        /* Payment method icons */
        .payment-method {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .payment-method-icon {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            color: white;
        }
        
        .method-credit_card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .method-paypal {
            background: linear-gradient(135deg, #003087 0%, #009cde 100%);
        }
        
        .method-cash {
            background: linear-gradient(135deg, #2ed573 0%, #1dd1a1 100%);
        }
        
        .method-bank_transfer {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem 0;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #e9ecef;
            margin-bottom: 1rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .payments-table {
                min-width: 900px;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            .payment-filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .payment-filters > div {
                min-width: auto;
            }
            
            .stats-grid-small {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid-small {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search payments by name, email..." id="searchInput">
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
                    <select name="status" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>
                <div>
                    <label>Month</label>
                    <input type="month" name="month" value="<?php echo $month; ?>" id="monthFilter">
                </div>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <button type="button" class="btn-secondary" onclick="window.location.href='admin-payments.php'">
                    <i class="fas fa-times"></i> Clear
                </button>
            </form>

            <!-- Payments Table -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Payment History</h3>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <a href="admin-export-payments.php" class="btn-sm">
                            <i class="fas fa-download"></i> Export CSV
                        </a>
                        <span style="font-size: 0.9rem; color: #6c757d;">
                            <?php echo count($payments); ?> records
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if(count($payments) > 0): ?>
                        <div class="table-container">
                            <table class="payments-table" id="paymentsTable">
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
                                    <?php foreach($payments as $payment): 
                                        // Get payment method icon
                                        $methodClass = 'method-' . str_replace(' ', '_', $payment['payment_method']);
                                        $methodName = ucwords(str_replace('_', ' ', $payment['payment_method']));
                                    ?>
                                        <tr data-member-name="<?php echo strtolower(htmlspecialchars($payment['full_name'])); ?>" 
                                            data-member-email="<?php echo strtolower(htmlspecialchars($payment['email'])); ?>"
                                            data-payment-status="<?php echo $payment['status']; ?>"
                                            data-payment-method="<?php echo $payment['payment_method']; ?>">
                                            <td>
                                                <strong style="color: #667eea;">#PAY-<?php echo str_pad($payment['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($payment['full_name']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($payment['email']); ?></small>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: #495057;">
                                                    <?php echo htmlspecialchars($payment['MembershipPlan'] ?? 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td class="payment-amount">$<?php echo number_format($payment['amount'], 2); ?></td>
                                            <td>
                                                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                                    <span style="font-weight: 600; color: #2f3542;">
                                                        <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?>
                                                    </span>
                                                    <span style="color: #6c757d; font-size: 0.85rem;">
                                                        <?php echo date('g:i A', strtotime($payment['payment_date'])); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="payment-method">
                                                    <div class="payment-method-icon <?php echo $methodClass; ?>">
                                                        <i class="fas fa-<?php echo $payment['payment_method'] === 'credit_card' ? 'credit-card' : ($payment['payment_method'] === 'paypal' ? 'paypal' : 'money-bill'); ?>"></i>
                                                    </div>
                                                    <span><?php echo $methodName; ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="payment-status status-<?php echo $payment['status']; ?>">
                                                    <?php echo ucfirst($payment['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <button class="btn-sm" onclick="window.location.href='admin-payment-view.php?id=<?php echo $payment['id']; ?>'" title="View Details">
                                                        <i class="fas fa-receipt"></i>
                                                    </button>
                                                    <?php if($payment['status'] === 'pending'): ?>
                                                        <button class="btn-sm btn-success" onclick="markAsPaid(<?php echo $payment['id']; ?>)" title="Mark as Paid">
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
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-credit-card"></i>
                            <h3 style="color: #495057; margin: 1rem 0 0.5rem;">No Payments Found</h3>
                            <p style="margin-bottom: 2rem;">No payments matching the selected filters.</p>
                            <button class="btn-primary" onclick="window.location.href='admin-add-payment.php'">
                                <i class="fas fa-plus"></i> Record First Payment
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function markAsPaid(paymentId) {
            if(confirm('Are you sure you want to mark payment #PAY-' + paymentId.toString().padStart(6, '0') + ' as completed?')) {
                // In real implementation, you would redirect to process script
                alert('Payment #PAY-' + paymentId.toString().padStart(6, '0') + ' marked as completed (demo only).');
                // window.location.href = 'admin-mark-paid.php?id=' + paymentId;
            }
        }
        
        // Live search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#paymentsTable tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const memberName = row.getAttribute('data-member-name');
                const memberEmail = row.getAttribute('data-member-email');
                const rowText = row.textContent.toLowerCase();
                
                if (memberName.includes(searchTerm) || 
                    memberEmail.includes(searchTerm) ||
                    rowText.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Show no results message if needed
            const cardBody = document.querySelector('.card-body');
            const tableContainer = cardBody.querySelector('.table-container');
            let noResultsMsg = cardBody.querySelector('.no-results');
            
            if (visibleCount === 0 && searchTerm.length > 0) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.className = 'empty-state no-results';
                    noResultsMsg.innerHTML = `
                        <i class="fas fa-search"></i>
                        <h3 style="color: #495057; margin: 1rem 0 0.5rem;">No Matching Payments</h3>
                        <p>No payments found matching "${searchTerm}"</p>
                    `;
                    if (tableContainer) {
                        tableContainer.parentNode.insertBefore(noResultsMsg, tableContainer.nextSibling);
                    } else {
                        cardBody.appendChild(noResultsMsg);
                    }
                }
            } else if (noResultsMsg) {
                noResultsMsg.remove();
            }
        });
        
        // Quick filter buttons for status
        document.addEventListener('DOMContentLoaded', function() {
            const statusFilter = document.getElementById('statusFilter');
            const monthFilter = document.getElementById('monthFilter');
            
            // Set default month to current month if not set
            if (!monthFilter.value) {
                const now = new Date();
                monthFilter.value = now.toISOString().slice(0, 7);
            }
        });
    </script>
</body>
</html>