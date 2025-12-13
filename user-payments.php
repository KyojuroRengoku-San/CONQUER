<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'member') {
    header('Location: login.php');
    exit();
}

// Initialize database connection
$pdo = null;
$message = '';
$success = false;
$user_id = $_SESSION['user_id'];

try {
    require_once 'config/database.php';
    $database = Database::getInstance();
    $pdo = $database->getConnection();

    // Get user info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if(!$user) {
        session_destroy();
        header('Location: login.php');
        exit();
    }
    
    // Get member info
    $member = null;
    try {
        $memberStmt = $pdo->prepare("SELECT * FROM gym_members WHERE Email = ?");
        $memberStmt->execute([$user['email']]);
        $member = $memberStmt->fetch();
    } catch(PDOException $e) {
        error_log("Member info error: " . $e->getMessage());
    }
    
    // Get all payments
    $payments = [];
    try {
        $paymentsStmt = $pdo->prepare("
            SELECT * FROM payments 
            WHERE user_id = ? 
            ORDER BY payment_date DESC
        ");
        $paymentsStmt->execute([$user_id]);
        $payments = $paymentsStmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Payments query error: " . $e->getMessage());
    }
    
    // Calculate totals
    $totalPaid = 0;
    $pendingAmount = 0;
    $completedCount = 0;
    $pendingCount = 0;
    
    foreach($payments as $payment) {
        if($payment['status'] == 'completed') {
            $totalPaid += $payment['amount'];
            $completedCount++;
        } elseif($payment['status'] == 'pending') {
            $pendingAmount += $payment['amount'];
            $pendingCount++;
        }
    }
    
    // Get upcoming payment if any
    $nextPayment = null;
    $nextPaymentDate = date('Y-m-d', strtotime('+1 month'));
    
} catch(PDOException $e) {
    $message = "Database error. Please try again later.";
    error_log("Payments page error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Payments | CONQUER Gym</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    
    <style>
        .payments-content {
            padding: 2rem;
        }
        
        .payments-section {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }
        
        .section-title {
            margin: 0 0 1.5rem 0;
            color: var(--dark-color);
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--light-color);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            text-align: center;
            box-shadow: var(--shadow-sm);
            border-top: 4px solid var(--primary-color);
        }
        
        .stat-card.total {
            border-color: var(--success);
        }
        
        .stat-card.pending {
            border-color: var(--warning);
        }
        
        .stat-card.count {
            border-color: var(--info);
        }
        
        .stat-card.next {
            border-color: var(--primary-color);
        }
        
        .stat-card h3 {
            margin: 0 0 0.5rem 0;
            color: var(--dark-color);
            font-size: 2rem;
        }
        
        .stat-card p {
            margin: 0;
            color: var(--gray);
        }
        
        .payment-methods {
            display: flex;
            gap: 1rem;
            margin: 2rem 0;
            flex-wrap: wrap;
        }
        
        .method-card {
            flex: 1;
            min-width: 200px;
            background: var(--white);
            border: 2px solid var(--light-color);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .method-card:hover, .method-card.active {
            border-color: var(--primary-color);
            background: rgba(52, 152, 219, 0.05);
        }
        
        .method-card i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        
        .method-card h4 {
            margin: 0 0 0.5rem 0;
            color: var(--dark-color);
        }
        
        .method-card p {
            margin: 0;
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .table-container {
            overflow-x: auto;
            margin: 2rem 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
        }
        
        thead {
            background: var(--light-bg);
        }
        
        th {
            padding: 1rem;
            text-align: left;
            color: var(--dark-color);
            font-weight: 600;
            border-bottom: 2px solid var(--light-color);
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid var(--light-color);
            color: var(--gray);
        }
        
        tr:hover {
            background: var(--light-bg);
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-completed {
            background: rgba(46, 213, 115, 0.1);
            color: var(--success);
        }
        
        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }
        
        .status-failed {
            background: rgba(255, 56, 56, 0.1);
            color: var(--danger);
        }
        
        .status-refunded {
            background: rgba(108, 92, 231, 0.1);
            color: var(--secondary-color);
        }
        
        .action-btn {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-sm);
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .action-btn.view {
            background: var(--light-color);
            color: var(--dark-color);
        }
        
        .action-btn.view:hover {
            background: var(--gray);
            color: var(--white);
        }
        
        .action-btn.pay {
            background: var(--success);
            color: white;
        }
        
        .action-btn.pay:hover {
            background: #27ae60;
        }
        
        .no-payments {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray);
        }
        
        .invoice-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .invoice-content {
            background: var(--white);
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
        }
        
        .invoice-header {
            padding: 1.5rem;
            border-bottom: 2px solid var(--light-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .invoice-body {
            padding: 2rem;
        }
        
        .invoice-details {
            margin: 2rem 0;
        }
        
        .invoice-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px dashed var(--light-color);
        }
        
        .invoice-total {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 2px solid var(--light-color);
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        @media (max-width: 768px) {
            .payment-methods {
                flex-direction: column;
            }
            
            .method-card {
                min-width: 100%;
            }
            
            th, td {
                padding: 0.75rem 0.5rem;
                font-size: 0.9rem;
            }
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
            <a href="user-dashboard.php">
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
            <a href="user-bookclass.php">
                <i class="fas fa-plus-circle"></i>
                <span>Book Class</span>
            </a>
            <a href="user-contact.php">
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
                <input type="text" placeholder="Search payments..." id="searchPayments">
            </div>
            <div class="top-bar-actions">
                <button class="btn-notification">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </button>
                <button class="btn-primary" id="makePaymentBtn">
                    <i class="fas fa-credit-card"></i>
                    Make Payment
                </button>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>My Payments 💳</h1>
                    <p>View your payment history and manage billing</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3>$<?php echo number_format($totalPaid, 2); ?></h3>
                        <p>Total Paid</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $completedCount; ?></h3>
                        <p>Payments</p>
                    </div>
                </div>
            </div>

            <?php if($message): ?>
                <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                    <i class="fas <?php echo $success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="payments-content">
                <!-- Payment Overview -->
                <div class="payments-section">
                    <h3 class="section-title">Payment Overview</h3>
                    <div class="stats-grid">
                        <div class="stat-card total">
                            <h3>$<?php echo number_format($totalPaid, 2); ?></h3>
                            <p>Total Paid</p>
                        </div>
                        <div class="stat-card pending">
                            <h3>$<?php echo number_format($pendingAmount, 2); ?></h3>
                            <p>Pending Balance</p>
                        </div>
                        <div class="stat-card count">
                            <h3><?php echo count($payments); ?></h3>
                            <p>Total Transactions</p>
                        </div>
                        <div class="stat-card next">
                            <h3><?php echo isset($member['MembershipPlan']) ? '$' . (strpos($member['MembershipPlan'], 'Legend') !== false ? '49.99' : (strpos($member['MembershipPlan'], 'Champion') !== false ? '79.99' : '29.99')) : '$0.00'; ?></h3>
                            <p>Next Payment</p>
                        </div>
                    </div>
                </div>

                <!-- Payment Methods -->
                <div class="payments-section">
                    <h3 class="section-title">Payment Methods</h3>
                    <div class="payment-methods">
                        <div class="method-card active" data-method="credit_card">
                            <i class="fas fa-credit-card"></i>
                            <h4>Credit Card</h4>
                            <p>Visa, MasterCard, Amex</p>
                        </div>
                        <div class="method-card" data-method="paypal">
                            <i class="fab fa-paypal"></i>
                            <h4>PayPal</h4>
                            <p>Secure online payments</p>
                        </div>
                        <div class="method-card" data-method="bank">
                            <i class="fas fa-university"></i>
                            <h4>Bank Transfer</h4>
                            <p>Direct bank deposit</p>
                        </div>
                        <div class="method-card" data-method="cash">
                            <i class="fas fa-money-bill-wave"></i>
                            <h4>Cash</h4>
                            <p>Pay at reception</p>
                        </div>
                    </div>
                    
                    <div style="margin-top: 2rem;">
                        <button class="btn-primary" id="addPaymentMethod">
                            <i class="fas fa-plus"></i> Add Payment Method
                        </button>
                    </div>
                </div>

                <!-- Payment History -->
                <div class="payments-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 class="section-title" style="margin: 0;">Payment History</h3>
                        <button class="btn-secondary" onclick="window.print()">
                            <i class="fas fa-print"></i> Print Statement
                        </button>
                    </div>
                    
                    <?php if(count($payments) > 0): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="paymentsTable">
                                    <?php foreach($payments as $payment): 
                                        $statusClass = '';
                                        switch($payment['status']) {
                                            case 'completed': $statusClass = 'status-completed'; break;
                                            case 'pending': $statusClass = 'status-pending'; break;
                                            case 'failed': $statusClass = 'status-failed'; break;
                                            case 'refunded': $statusClass = 'status-refunded'; break;
                                        }
                                    ?>
                                        <tr data-status="<?php echo $payment['status']; ?>" 
                                            data-amount="<?php echo $payment['amount']; ?>">
                                            <td><?php echo isset($payment['payment_date']) ? date('M j, Y', strtotime($payment['payment_date'])) : 'N/A'; ?></td>
                                            <td><?php echo isset($payment['subscription_period']) ? $payment['subscription_period'] . ' Membership' : 'Membership Fee'; ?></td>
                                            <td><strong>$<?php echo isset($payment['amount']) ? number_format($payment['amount'], 2) : '0.00'; ?></strong></td>
                                            <td><?php echo isset($payment['payment_method']) ? ucwords(str_replace('_', ' ', $payment['payment_method'])) : 'N/A'; ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $statusClass; ?>">
                                                    <?php echo isset($payment['status']) ? ucfirst($payment['status']) : 'Pending'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="action-btn view" onclick="viewInvoice(<?php echo $payment['id']; ?>)">
                                                    <i class="fas fa-receipt"></i> Invoice
                                                </button>
                                                <?php if($payment['status'] == 'pending'): ?>
                                                    <button class="action-btn pay" onclick="payNow(<?php echo $payment['id']; ?>)">
                                                        <i class="fas fa-dollar-sign"></i> Pay Now
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div style="display: flex; justify-content: center; margin-top: 2rem;">
                            <div style="display: flex; gap: 0.5rem;">
                                <button class="btn-secondary" disabled>Previous</button>
                                <button class="btn-primary">1</button>
                                <button class="btn-secondary">2</button>
                                <button class="btn-secondary">3</button>
                                <button class="btn-secondary">Next</button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="no-payments">
                            <i class="fas fa-credit-card" style="font-size: 3rem; color: var(--gray); margin-bottom: 1rem;"></i>
                            <h3>No payment history</h3>
                            <p>Your payment history will appear here once you make your first payment.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Upcoming Payment -->
                <?php if($pendingAmount > 0): ?>
                <div class="payments-section">
                    <h3 class="section-title">Upcoming Payment</h3>
                    <div style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; padding: 2rem; border-radius: var(--radius-md);">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h4 style="margin: 0 0 0.5rem 0; color: white;">Monthly Membership Fee</h4>
                                <p style="margin: 0; opacity: 0.9;">Due by <?php echo date('F j, Y', strtotime($nextPaymentDate)); ?></p>
                            </div>
                            <div style="text-align: right;">
                                <h2 style="margin: 0; color: white;">$<?php echo number_format($pendingAmount, 2); ?></h2>
                                <button class="btn-primary" style="margin-top: 1rem; background: white; color: var(--primary-color);">
                                    <i class="fas fa-dollar-sign"></i> Pay Now
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Invoice Modal -->
    <div class="invoice-modal" id="invoiceModal">
        <div class="invoice-content">
            <div class="invoice-header">
                <h3 style="margin: 0; color: var(--dark-color);">Payment Invoice</h3>
                <button class="close-btn" onclick="closeInvoice()">&times;</button>
            </div>
            <div class="invoice-body" id="invoiceBody">
                <!-- Invoice content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Payment method selection
        document.querySelectorAll('.method-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.method-card').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
            });
        });
        
        // Search functionality
        document.getElementById('searchPayments').addEventListener('input', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#paymentsTable tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });
        
        // Filter by status
        document.addEventListener('DOMContentLoaded', function() {
            const filterButtons = document.createElement('div');
            filterButtons.innerHTML = `
                <div style="margin: 1rem 0; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <button class="filter-btn active" data-filter="all">All</button>
                    <button class="filter-btn" data-filter="completed">Completed</button>
                    <button class="filter-btn" data-filter="pending">Pending</button>
                    <button class="filter-btn" data-filter="failed">Failed</button>
                </div>
            `;
            
            document.querySelector('.section-title').after(filterButtons);
            
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    const filter = this.getAttribute('data-filter');
                    const rows = document.querySelectorAll('#paymentsTable tr');
                    
                    rows.forEach(row => {
                        if(filter === 'all') {
                            row.style.display = '';
                        } else {
                            row.style.display = row.getAttribute('data-status') === filter ? '' : 'none';
                        }
                    });
                });
            });
        });
        
        // View invoice
        function viewInvoice(paymentId) {
            // In a real application, this would fetch invoice data from the server
            const invoiceData = {
                id: 'INV-' + paymentId.toString().padStart(6, '0'),
                date: '<?php echo date("F j, Y"); ?>',
                amount: '$49.99',
                method: 'Credit Card',
                status: 'Paid',
                description: 'Monthly Membership Fee'
            };
            
            const invoiceHTML = `
                <div class="invoice-details">
                    <h4 style="margin: 0 0 1rem 0; color: var(--dark-color);">CONQUER Gym Invoice</h4>
                    <div class="invoice-row">
                        <span>Invoice Number:</span>
                        <span><strong>${invoiceData.id}</strong></span>
                    </div>
                    <div class="invoice-row">
                        <span>Date:</span>
                        <span>${invoiceData.date}</span>
                    </div>
                    <div class="invoice-row">
                        <span>Description:</span>
                        <span>${invoiceData.description}</span>
                    </div>
                    <div class="invoice-row">
                        <span>Payment Method:</span>
                        <span>${invoiceData.method}</span>
                    </div>
                    <div class="invoice-row">
                        <span>Status:</span>
                        <span class="status-badge status-completed">${invoiceData.status}</span>
                    </div>
                    <div class="invoice-total">
                        <span>Total Amount:</span>
                        <span>${invoiceData.amount}</span>
                    </div>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 2rem;">
                    <button class="btn-primary" onclick="printInvoice()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button class="btn-secondary" onclick="downloadInvoice()">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
            `;
            
            document.getElementById('invoiceBody').innerHTML = invoiceHTML;
            document.getElementById('invoiceModal').style.display = 'flex';
        }
        
        function closeInvoice() {
            document.getElementById('invoiceModal').style.display = 'none';
        }
        
        function printInvoice() {
            window.print();
        }
        
        function downloadInvoice() {
            alert('Invoice download would start here in a real application.');
        }
        
        function payNow(paymentId) {
            if(confirm('Proceed to payment?')) {
                // In a real application, this would redirect to payment gateway
                alert('Redirecting to payment gateway...');
            }
        }
        
        // Make payment button
        document.getElementById('makePaymentBtn').addEventListener('click', function() {
            alert('Payment form would open here in a real application.');
        });
        
        // Add payment method
        document.getElementById('addPaymentMethod').addEventListener('click', function() {
            alert('Add payment method form would open here.');
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('invoiceModal');
            if(event.target === modal) {
                closeInvoice();
            }
        });
    </script>
</body>
</html>