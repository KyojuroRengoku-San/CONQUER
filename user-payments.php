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

// Check for success/error messages
if(isset($_SESSION['payment_success'])) {
    $message = $_SESSION['payment_success'];
    $success = true;
    unset($_SESSION['payment_success']);
}

if(isset($_SESSION['payment_error'])) {
    $message = $_SESSION['payment_error'];
    $success = false;
    unset($_SESSION['payment_error']);
}

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
            SELECT p.*, u.full_name, u.email 
            FROM payments p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.user_id = ? 
            ORDER BY p.payment_date DESC
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
    $dueAmount = 0;
    
    foreach($payments as $payment) {
        if($payment['status'] == 'completed') {
            $totalPaid += $payment['amount'];
            $completedCount++;
        } elseif($payment['status'] == 'pending') {
            $pendingAmount += $payment['amount'];
            $pendingCount++;
        }
    }
    
    // Calculate due amount based on membership plan
    if($member) {
        $planPrice = 0;
        switch($member['MembershipPlan']) {
            case 'Legend Membership':
                $planPrice = 49.99;
                break;
            case 'Champion Membership':
                $planPrice = 79.99;
                break;
            default:
                $planPrice = 29.99;
        }
        
        // Check if user has paid for current month
        $currentMonth = date('Y-m');
        $paidThisMonth = false;
        foreach($payments as $payment) {
            if($payment['status'] == 'completed' && 
               date('Y-m', strtotime($payment['payment_date'])) == $currentMonth) {
                $paidThisMonth = true;
                break;
            }
        }
        
        if(!$paidThisMonth) {
            $dueAmount = $planPrice;
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
        
        .stat-card.due {
            border-color: var(--danger);
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
            min-width: 180px;
            background: var(--white);
            border: 2px solid var(--light-color);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }
        
        .method-card:hover, .method-card.active {
            border-color: var(--primary-color);
            background: rgba(52, 152, 219, 0.05);
        }
        
        .method-card input[type="radio"] {
            position: absolute;
            opacity: 0;
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
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--light-color);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .form-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        .payment-fields {
            display: none;
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--light-color);
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
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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
        
        .action-btn.download {
            background: var(--info);
            color: white;
        }
        
        .action-btn.download:hover {
            background: #2980b9;
        }
        
        .no-payments {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray);
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .alert-success {
            background: rgba(46, 213, 115, 0.1);
            border: 1px solid rgba(46, 213, 115, 0.2);
            color: var(--success);
        }
        
        .alert-warning {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.2);
            color: var(--warning);
        }
        
        .alert-danger {
            background: rgba(255, 56, 56, 0.1);
            border: 1px solid rgba(255, 56, 56, 0.2);
            color: var(--danger);
        }
        
        .receipt-preview {
            border: 2px dashed var(--light-color);
            border-radius: var(--radius-md);
            padding: 1rem;
            text-align: center;
            background: var(--light-bg);
            margin: 1rem 0;
        }
        
        .receipt-preview img {
            max-width: 200px;
            height: auto;
            border-radius: var(--radius-sm);
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
            animation: fadeIn 0.3s ease-in;
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
        
        .close-btn {
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: var(--gray);
            padding: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: var(--transition);
        }
        
        .close-btn:hover {
            background: var(--light-bg);
            color: var(--danger);
        }
        
        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            margin: 1rem 0;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 0.5rem 1rem;
            border: 1px solid var(--light-color);
            background: var(--white);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .filter-btn.active {
            background: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
        }
        
        .filter-btn:hover:not(.active) {
            background: var(--light-bg);
        }
        
        @media (max-width: 768px) {
            .payment-methods {
                flex-direction: column;
            }
            
            .method-card {
                min-width: 100%;
            }
            
            .form-row {
                flex-direction: column;
                gap: 1rem;
            }
            
            th, td {
                padding: 0.75rem 0.5rem;
                font-size: 0.9rem;
            }
            
            .action-btn {
                padding: 0.5rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'user-sidebar.php'; ?>
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
                    <span class="notification-badge"><?php echo $pendingCount > 0 ? $pendingCount : ''; ?></span>
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

            <!-- Notifications -->
            <?php if($pendingCount > 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                You have <?php echo $pendingCount; ?> pending payment(s) awaiting confirmation.
            </div>
            <?php endif; ?>
            
            <?php if($dueAmount > 0): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                You have a payment due of $<?php echo number_format($dueAmount, 2); ?> for this month.
            </div>
            <?php endif; ?>
            
            <?php if($message): ?>
            <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>">
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
                        <div class="stat-card due">
                            <h3>$<?php echo number_format($dueAmount, 2); ?></h3>
                            <p>Amount Due</p>
                        </div>
                        <div class="stat-card count">
                            <h3><?php echo count($payments); ?></h3>
                            <p>Total Transactions</p>
                        </div>
                        <div class="stat-card next">
                            <h3><?php echo isset($member['MembershipPlan']) ? '$' . (strpos($member['MembershipPlan'], 'Legend') !== false ? '49.99' : (strpos($member['MembershipPlan'], 'Champion') !== false ? '79.99' : '29.99')) : '$0.00'; ?></h3>
                            <p>Monthly Fee</p>
                        </div>
                    </div>
                </div>

                <!-- Make Payment Form -->
                <div class="payments-section" id="paymentFormSection" style="display: none;">
                    <h3 class="section-title">Make a Payment</h3>
                    <form id="paymentForm" action="process-payment.php" method="POST" enctype="multipart/form-data">
                        <div class="payment-methods">
                            <label class="method-card" data-method="credit_card">
                                <input type="radio" name="payment_method" value="credit_card" required>
                                <i class="fas fa-credit-card"></i>
                                <h4>Credit Card</h4>
                                <p>Visa, MasterCard, Amex</p>
                            </label>
                            <label class="method-card" data-method="gcash">
                                <input type="radio" name="payment_method" value="gcash" required>
                                <i class="fas fa-mobile-alt"></i>
                                <h4>GCash</h4>
                                <p>Mobile Payment</p>
                            </label>
                            <label class="method-card" data-method="paymaya">
                                <input type="radio" name="payment_method" value="paymaya" required>
                                <i class="fas fa-wallet"></i>
                                <h4>PayMaya</h4>
                                <p>Mobile Wallet</p>
                            </label>
                            <label class="method-card" data-method="bank_transfer">
                                <input type="radio" name="payment_method" value="bank_transfer" required>
                                <i class="fas fa-university"></i>
                                <h4>Bank Transfer</h4>
                                <p>Direct bank deposit</p>
                            </label>
                            <label class="method-card" data-method="cash">
                                <input type="radio" name="payment_method" value="cash" required>
                                <i class="fas fa-money-bill-wave"></i>
                                <h4>Cash at Reception</h4>
                                <p>Pay with printed receipt</p>
                            </label>
                        </div>
                        
                        <div id="paymentDetails" style="margin-top: 2rem;">
                            <div class="form-group">
                                <label for="amount">Amount ($)</label>
                                <input type="number" id="amount" name="amount" step="0.01" min="1" required 
                                       value="<?php echo $dueAmount > 0 ? $dueAmount : (isset($member['MembershipPlan']) ? (strpos($member['MembershipPlan'], 'Legend') !== false ? '49.99' : (strpos($member['MembershipPlan'], 'Champion') !== false ? '79.99' : '29.99')) : '29.99'); ?>">
                            </div>
                            
                            <div id="creditCardFields" class="payment-fields">
                                <div class="form-group">
                                    <label for="card_number">Card Number</label>
                                    <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19">
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="expiry_date">Expiry Date</label>
                                        <input type="text" id="expiry_date" name="expiry_date" placeholder="MM/YY" maxlength="5">
                                    </div>
                                    <div class="form-group">
                                        <label for="cvv">CVV</label>
                                        <input type="text" id="cvv" name="cvv" placeholder="123" maxlength="4">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="cardholder_name">Cardholder Name</label>
                                    <input type="text" id="cardholder_name" name="cardholder_name" placeholder="As shown on card">
                                </div>
                            </div>
                            
                            <div id="mobilePaymentFields" class="payment-fields">
                                <div class="form-group">
                                    <label for="mobile_number">Mobile Number</label>
                                    <input type="text" id="mobile_number" name="mobile_number" placeholder="0917XXXXXXX">
                                </div>
                                <div class="form-group">
                                    <label for="transaction_ref">Transaction Reference Number</label>
                                    <input type="text" id="transaction_ref" name="transaction_ref" placeholder="GCash/PayMaya Reference">
                                </div>
                            </div>
                            
                            <div id="bankTransferFields" class="payment-fields">
                                <div class="form-group">
                                    <label for="bank_name">Bank Name</label>
                                    <select id="bank_name" name="bank_name">
                                        <option value="">Select Bank</option>
                                        <option value="BDO">BDO</option>
                                        <option value="BPI">BPI</option>
                                        <option value="Metrobank">Metrobank</option>
                                        <option value="UnionBank">UnionBank</option>
                                        <option value="Landbank">Landbank</option>
                                        <option value="Other">Other Bank</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="account_number">Account Number / Reference</label>
                                    <input type="text" id="account_number" name="account_number" placeholder="Account or Reference Number">
                                </div>
                            </div>
                            
                            <div id="cashFields" class="payment-fields">
                                <div class="form-group">
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i> 
                                        <strong>Instructions for Cash Payment:</strong><br>
                                        1. Print this receipt after submitting<br>
                                        2. Bring the printed receipt to reception<br>
                                        3. Pay the exact amount in cash<br>
                                        4. Get your payment confirmed at reception
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="subscription_period">Payment For</label>
                                <select id="subscription_period" name="subscription_period" required>
                                    <option value="">Select Period</option>
                                    <option value="Monthly Membership">Monthly Membership</option>
                                    <option value="Annual Membership">Annual Membership</option>
                                    <option value="Personal Training">Personal Training</option>
                                    <option value="Class Package">Class Package</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="notes">Additional Notes (Optional)</label>
                                <textarea id="notes" name="notes" rows="3" placeholder="Any additional information..."></textarea>
                            </div>
                            
                            <div id="receiptUpload" class="payment-fields" style="display: none;">
                                <div class="form-group">
                                    <label for="receipt_image">Upload Payment Proof</label>
                                    <input type="file" id="receipt_image" name="receipt_image" accept="image/*,.pdf">
                                    <small>Required for GCash, PayMaya, and Bank Transfer payments</small>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-paper-plane"></i> Submit Payment
                                </button>
                                <button type="button" class="btn-secondary" onclick="cancelPayment()">Cancel</button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Payment History -->
                <div class="payments-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 class="section-title" style="margin: 0;">Payment History</h3>
                        <button class="btn-secondary" onclick="window.print()">
                            <i class="fas fa-print"></i> Print Statement
                        </button>
                    </div>
                    
                    <!-- Filter Buttons -->
                    <div class="filter-buttons">
                        <button class="filter-btn active" data-filter="all">All Payments</button>
                        <button class="filter-btn" data-filter="completed">Completed</button>
                        <button class="filter-btn" data-filter="pending">Pending</button>
                        <button class="filter-btn" data-filter="failed">Failed</button>
                    </div>
                    
                    <?php if(count($payments) > 0): ?>
                        <div class="table-container">
                            <table id="paymentsTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Transaction ID</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($payments as $payment): 
                                        $statusClass = '';
                                        switch($payment['status']) {
                                            case 'completed': $statusClass = 'status-completed'; break;
                                            case 'pending': $statusClass = 'status-pending'; break;
                                            case 'failed': $statusClass = 'status-failed'; break;
                                            case 'refunded': $statusClass = 'status-refunded'; break;
                                        }
                                        
                                        $methodName = ucwords(str_replace('_', ' ', $payment['payment_method']));
                                        if($payment['payment_method'] == 'gcash') $methodName = 'GCash';
                                        if($payment['payment_method'] == 'paymaya') $methodName = 'PayMaya';
                                    ?>
                                        <tr data-status="<?php echo $payment['status']; ?>" 
                                            data-amount="<?php echo $payment['amount']; ?>">
                                            <td><?php echo isset($payment['payment_date']) ? date('M j, Y', strtotime($payment['payment_date'])) : 'N/A'; ?></td>
                                            <td><code><?php echo isset($payment['transaction_id']) ? $payment['transaction_id'] : 'N/A'; ?></code></td>
                                            <td><?php echo isset($payment['subscription_period']) ? $payment['subscription_period'] : 'Membership Fee'; ?></td>
                                            <td><strong>$<?php echo isset($payment['amount']) ? number_format($payment['amount'], 2) : '0.00'; ?></strong></td>
                                            <td><?php echo $methodName; ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $statusClass; ?>">
                                                    <?php echo isset($payment['status']) ? ucfirst($payment['status']) : 'Pending'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                                    <button class="action-btn view" onclick="viewInvoice(<?php echo $payment['id']; ?>)">
                                                        <i class="fas fa-receipt"></i> Invoice
                                                    </button>
                                                    <?php if($payment['receipt_image']): ?>
                                                        <button class="action-btn download" onclick="downloadReceipt('<?php echo $payment['receipt_image']; ?>')">
                                                            <i class="fas fa-download"></i> Receipt
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if($payment['status'] == 'pending'): ?>
                                                        <button class="action-btn pay" onclick="payNow(<?php echo $payment['id']; ?>)">
                                                            <i class="fas fa-dollar-sign"></i> Pay Now
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
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
                            <button class="btn-primary" style="margin-top: 1rem;" id="makeFirstPayment">
                                <i class="fas fa-plus"></i> Make Your First Payment
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Upcoming Payment -->
                <?php if($dueAmount > 0): ?>
                <div class="payments-section">
                    <h3 class="section-title">Payment Due</h3>
                    <div style="background: linear-gradient(135deg, var(--danger), #ff7675); color: white; padding: 2rem; border-radius: var(--radius-md);">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h4 style="margin: 0 0 0.5rem 0; color: white;">Monthly Membership Fee</h4>
                                <p style="margin: 0; opacity: 0.9;">Due by <?php echo date('F j, Y', strtotime($nextPaymentDate)); ?></p>
                                <p style="margin: 1rem 0 0 0; font-size: 0.9rem; opacity: 0.9;">
                                    <i class="fas fa-info-circle"></i> Late payments may result in membership suspension
                                </p>
                            </div>
                            <div style="text-align: right;">
                                <h2 style="margin: 0; color: white;">$<?php echo number_format($dueAmount, 2); ?></h2>
                                <button class="btn-primary" style="margin-top: 1rem; background: white; color: var(--danger);" onclick="showPaymentForm()">
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
        // Show/hide payment form
        function showPaymentForm() {
            document.getElementById('paymentFormSection').style.display = 'block';
            window.scrollTo({
                top: document.getElementById('paymentFormSection').offsetTop - 20,
                behavior: 'smooth'
            });
        }
        
        function hidePaymentForm() {
            document.getElementById('paymentFormSection').style.display = 'none';
        }
        
        // Make payment button click
        document.getElementById('makePaymentBtn').addEventListener('click', showPaymentForm);
        document.getElementById('makeFirstPayment').addEventListener('click', showPaymentForm);
        
        // Payment method selection
        document.querySelectorAll('.method-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.method-card').forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                // Show relevant fields based on payment method
                const method = this.getAttribute('data-method');
                document.querySelectorAll('.payment-fields').forEach(field => {
                    field.style.display = 'none';
                });
                
                if(method === 'credit_card') {
                    document.getElementById('creditCardFields').style.display = 'block';
                    document.getElementById('receiptUpload').style.display = 'none';
                } else if(method === 'gcash' || method === 'paymaya') {
                    document.getElementById('mobilePaymentFields').style.display = 'block';
                    document.getElementById('receiptUpload').style.display = 'block';
                } else if(method === 'bank_transfer') {
                    document.getElementById('bankTransferFields').style.display = 'block';
                    document.getElementById('receiptUpload').style.display = 'block';
                } else if(method === 'cash') {
                    document.getElementById('cashFields').style.display = 'block';
                    document.getElementById('receiptUpload').style.display = 'none';
                }
                
                // Format card number input
                if(method === 'credit_card') {
                    document.getElementById('card_number').addEventListener('input', function(e) {
                        let value = e.target.value.replace(/\D/g, '');
                        value = value.replace(/(\d{4})/g, '$1 ').trim();
                        e.target.value = value.substring(0, 19);
                    });
                    
                    document.getElementById('expiry_date').addEventListener('input', function(e) {
                        let value = e.target.value.replace(/\D/g, '');
                        if(value.length >= 2) {
                            value = value.substring(0, 2) + '/' + value.substring(2, 4);
                        }
                        e.target.value = value.substring(0, 5);
                    });
                }
            });
        });
        
        function cancelPayment() {
            hidePaymentForm();
            document.querySelectorAll('.method-card').forEach(c => {
                c.classList.remove('active');
                c.querySelector('input[type="radio"]').checked = false;
            });
            document.querySelectorAll('.payment-fields').forEach(field => {
                field.style.display = 'none';
            });
        }
        
        // Search functionality
        document.getElementById('searchPayments').addEventListener('input', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#paymentsTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });
        
        // Filter by status
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.getAttribute('data-filter');
                const rows = document.querySelectorAll('#paymentsTable tbody tr');
                
                rows.forEach(row => {
                    if(filter === 'all') {
                        row.style.display = '';
                    } else {
                        const status = row.getAttribute('data-status');
                        row.style.display = status === filter ? '' : 'none';
                    }
                });
            });
        });
        
        // View invoice
        function viewInvoice(paymentId) {
            // In a real application, this would fetch invoice data from the server
            fetch('get-invoice.php?id=' + paymentId)
                .then(response => response.json())
                .then(data => {
                    const invoiceHTML = `
                        <div class="invoice-details">
                            <h4 style="margin: 0 0 1rem 0; color: var(--dark-color);">CONQUER Gym Invoice</h4>
                            <div class="invoice-row">
                                <span>Invoice Number:</span>
                                <span><strong>${data.invoice_number || 'INV-' + paymentId.toString().padStart(6, '0')}</strong></span>
                            </div>
                            <div class="invoice-row">
                                <span>Date:</span>
                                <span>${data.date || '<?php echo date("F j, Y"); ?>'}</span>
                            </div>
                            <div class="invoice-row">
                                <span>Member:</span>
                                <span>${data.member_name || '<?php echo htmlspecialchars($user['full_name']); ?>'}</span>
                            </div>
                            <div class="invoice-row">
                                <span>Description:</span>
                                <span>${data.description || 'Membership Fee'}</span>
                            </div>
                            <div class="invoice-row">
                                <span>Payment Method:</span>
                                <span>${data.method || 'Credit Card'}</span>
                            </div>
                            <div class="invoice-row">
                                <span>Status:</span>
                                <span class="status-badge ${data.status === 'completed' ? 'status-completed' : 'status-pending'}">
                                    ${data.status || 'Pending'}
                                </span>
                            </div>
                            <div class="invoice-total">
                                <span>Total Amount:</span>
                                <span>$${data.amount || '49.99'}</span>
                            </div>
                        </div>
                        <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 2rem;">
                            <button class="btn-primary" onclick="printInvoice()">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <button class="btn-secondary" onclick="downloadInvoice(${paymentId})">
                                <i class="fas fa-download"></i> Download PDF
                            </button>
                        </div>
                    `;
                    
                    document.getElementById('invoiceBody').innerHTML = invoiceHTML;
                    document.getElementById('invoiceModal').style.display = 'flex';
                })
                .catch(error => {
                    console.error('Error fetching invoice:', error);
                    // Fallback to static content
                    const invoiceHTML = `
                        <div class="invoice-details">
                            <h4 style="margin: 0 0 1rem 0; color: var(--dark-color);">CONQUER Gym Invoice</h4>
                            <div class="invoice-row">
                                <span>Invoice Number:</span>
                                <span><strong>INV-${paymentId.toString().padStart(6, '0')}</strong></span>
                            </div>
                            <div class="invoice-row">
                                <span>Date:</span>
                                <span><?php echo date("F j, Y"); ?></span>
                            </div>
                            <div class="invoice-row">
                                <span>Description:</span>
                                <span>Membership Fee</span>
                            </div>
                            <div class="invoice-row">
                                <span>Payment Method:</span>
                                <span>Credit Card</span>
                            </div>
                            <div class="invoice-row">
                                <span>Status:</span>
                                <span class="status-badge status-completed">Paid</span>
                            </div>
                            <div class="invoice-total">
                                <span>Total Amount:</span>
                                <span>$49.99</span>
                            </div>
                        </div>
                    `;
                    document.getElementById('invoiceBody').innerHTML = invoiceHTML;
                    document.getElementById('invoiceModal').style.display = 'flex';
                });
        }
        
        function closeInvoice() {
            document.getElementById('invoiceModal').style.display = 'none';
        }
        
        function printInvoice() {
            const invoiceContent = document.getElementById('invoiceBody').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Invoice</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        .invoice { max-width: 600px; margin: 0 auto; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .details { margin: 20px 0; }
                        .detail-row { display: flex; justify-content: space-between; margin: 10px 0; }
                        .total { margin-top: 30px; padding-top: 20px; border-top: 2px solid #000; }
                    </style>
                </head>
                <body>
                    <div class="invoice">
                        ${invoiceContent}
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
        
        function downloadInvoice(paymentId) {
            window.location.href = 'generate-invoice-pdf.php?id=' + paymentId;
        }
        
        function downloadReceipt(imagePath) {
            window.open(imagePath, '_blank');
        }
        
        function payNow(paymentId) {
            if(confirm('Proceed to payment for this transaction?')) {
                // Redirect to payment page with the specific payment ID
                window.location.href = 'make-payment.php?id=' + paymentId;
            }
        }
        
        // Form validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const amount = document.getElementById('amount').value;
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            const subscriptionPeriod = document.getElementById('subscription_period').value;
            
            if(!paymentMethod) {
                e.preventDefault();
                alert('Please select a payment method');
                return;
            }
            
            if(!amount || amount <= 0) {
                e.preventDefault();
                alert('Please enter a valid amount');
                return;
            }
            
            if(!subscriptionPeriod) {
                e.preventDefault();
                alert('Please select what this payment is for');
                return;
            }
            
            // Validate credit card if selected
            if(paymentMethod.value === 'credit_card') {
                const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
                const expiryDate = document.getElementById('expiry_date').value;
                const cvv = document.getElementById('cvv').value;
                
                if(cardNumber.length < 16 || !/^\d+$/.test(cardNumber)) {
                    e.preventDefault();
                    alert('Please enter a valid 16-digit card number');
                    return;
                }
                
                if(!expiryDate.match(/^\d{2}\/\d{2}$/)) {
                    e.preventDefault();
                    alert('Please enter a valid expiry date (MM/YY)');
                    return;
                }
                
                if(cvv.length < 3 || !/^\d+$/.test(cvv)) {
                    e.preventDefault();
                    alert('Please enter a valid CVV');
                    return;
                }
            }
            
            // Validate mobile payment fields if selected
            if(paymentMethod.value === 'gcash' || paymentMethod.value === 'paymaya') {
                const mobileNumber = document.getElementById('mobile_number').value;
                const transactionRef = document.getElementById('transaction_ref').value;
                
                if(!mobileNumber || mobileNumber.length < 11) {
                    e.preventDefault();
                    alert('Please enter a valid mobile number');
                    return;
                }
                
                if(!transactionRef) {
                    e.preventDefault();
                    alert('Please enter a transaction reference number');
                    return;
                }
            }
            
            // Validate bank transfer fields if selected
            if(paymentMethod.value === 'bank_transfer') {
                const bankName = document.getElementById('bank_name').value;
                const accountNumber = document.getElementById('account_number').value;
                
                if(!bankName) {
                    e.preventDefault();
                    alert('Please select a bank');
                    return;
                }
                
                if(!accountNumber) {
                    e.preventDefault();
                    alert('Please enter an account or reference number');
                    return;
                }
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('invoiceModal');
            if(event.target === modal) {
                closeInvoice();
            }
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>