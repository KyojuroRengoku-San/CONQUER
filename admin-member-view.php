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

$memberId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch member details
$member = null;
$gymMember = null;
$bookings = [];
$payments = [];

try {
    // Fetch user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND user_type = 'member'");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($member) {
        // Fetch gym_members details
        $stmt = $pdo->prepare("SELECT * FROM gym_members WHERE Email = ?");
        $stmt->execute([$member['email']]);
        $gymMember = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Fetch bookings
        $stmt = $pdo->prepare("
            SELECT b.*, c.class_name, c.schedule 
            FROM bookings b 
            JOIN classes c ON b.class_id = c.id 
            WHERE b.user_id = ? 
            ORDER BY b.booking_date DESC 
            LIMIT 5
        ");
        $stmt->execute([$memberId]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fetch payments
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY payment_date DESC LIMIT 5");
        $stmt->execute([$memberId]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    error_log("Member view error: " . $e->getMessage());
}

if(!$member) {
    header('Location: admin-members.php');
    exit();
}

$adminName = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($member['full_name']); ?> | CONQUER Gym Admin</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8f9fa;
            color: var(--dark-color, #2f3542);
            min-height: 100vh;
            overflow: hidden;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }
        
        /* Main Content - EXACT SAME AS DASHBOARD */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width, 250px);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
        }
        
        /* Top Bar - EXACT SAME AS DASHBOARD */
        .top-bar {
            background: var(--white, #ffffff);
            padding: 0.75rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.1));
            position: sticky;
            top: 0;
            z-index: 99;
            gap: 1rem;
            flex-wrap: wrap;
            flex-shrink: 0;
            min-height: 60px;
        }
        
        .search-bar {
            display: flex;
            align-items: center;
            background: var(--light-color, #f1f2f6);
            padding: 0.5rem 0.75rem;
            border-radius: var(--radius-md, 12px);
            flex: 1;
            max-width: 400px;
            position: relative;
            min-width: 200px;
        }
        
        .search-bar i {
            position: absolute;
            left: 0.75rem;
            color: var(--text-light, #6c757d);
            z-index: 1;
            font-size: 0.9rem;
        }
        
        .search-bar input {
            border: none;
            background: none;
            outline: none;
            font-family: inherit;
            font-size: 0.85rem;
            width: 100%;
            padding-left: 1.75rem;
            color: var(--dark-color, #2f3542);
        }
        
        .search-bar input::placeholder {
            color: var(--text-light, #6c757d);
            font-size: 0.85rem;
        }
        
        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }
        
        .btn-notification {
            background: var(--light-color, #f1f2f6);
            border: none;
            font-size: 1rem;
            color: var(--dark-color, #2f3542);
            cursor: pointer;
            position: relative;
            padding: 0.4rem;
            border-radius: 50%;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .btn-notification:hover {
            background: var(--primary-color, #ff4757);
            color: white;
        }
        
        .notification-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: var(--danger-color, #ff4757);
            color: white;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            font-size: 0.65rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            border: 2px solid var(--white, #ffffff);
        }
        
        .btn-primary {
            background: var(--primary-color, #ff4757);
            color: white;
            border: none;
            padding: 0.6rem 1.25rem;
            border-radius: var(--radius-md, 12px);
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            text-decoration: none;
            font-size: 0.85rem;
            white-space: nowrap;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark, #ff2e43);
            transform: translateY(-1px);
            box-shadow: 0 3px 15px rgba(255, 71, 87, 0.3);
        }
        
        /* Dashboard Content - EXACT SAME CONTAINER */
        .dashboard-content {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
            overflow-x: hidden;
            width: 100%;
            margin: 0 auto;
        }
        
        /* Page Header - MATCHING DASHBOARD STYLE */
        .page-header {
            background: var(--white, #ffffff);
            border-radius: var(--radius-md, 12px);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.1));
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .page-header h1 {
            font-size: 1.5rem;
            color: var(--dark-color, #2f3542);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
        }
        
        .page-header h1 i {
            color: var(--primary-color, #ff4757);
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.25rem;
            background: var(--light-color, #f1f2f6);
            color: var(--dark-color, #2f3542);
            border-radius: var(--radius-md, 12px);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            font-size: 0.9rem;
        }
        
        .back-btn:hover {
            background: var(--primary-color, #ff4757);
            color: white;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-md, 12px);
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition, all 0.3s cubic-bezier(0.4, 0, 0.2, 1));
            font-family: inherit;
            font-size: 0.9rem;
        }
        
        .btn i {
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: var(--primary-color, #ff4757);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark, #ff2e43);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 71, 87, 0.3);
        }
        
        .btn-warning {
            background: var(--warning-color, #ffa502);
            color: white;
        }
        
        .btn-warning:hover {
            background: #e69500;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 165, 2, 0.3);
        }
        
        .btn-danger {
            background: var(--danger-color, #ff4757);
            color: white;
        }
        
        .btn-danger:hover {
            background: #ff2e43;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 71, 87, 0.3);
        }
        
        .btn-secondary {
            background: var(--light-color, #f1f2f6);
            color: var(--dark-color, #2f3542);
            border: 1px solid var(--border-color, #e0e0e0);
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        /* Member Header - Matching Profile Style */
        .member-header {
            background: var(--white, #ffffff);
            border-radius: var(--radius-md, 12px);
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.1));
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
        }
        
        .member-avatar {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color, #ff4757) 0%, var(--primary-dark, #ff2e43) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3.5rem;
            font-weight: 700;
            flex-shrink: 0;
            box-shadow: 0 8px 25px rgba(255, 71, 87, 0.3);
        }
        
        .member-info {
            flex: 1;
            min-width: 300px;
        }
        
        .member-info h2 {
            font-size: 2rem;
            color: var(--dark-color, #2f3542);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        
        .member-status {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-active {
            background: linear-gradient(to bottom right, rgba(46, 213, 115, 0.1), rgba(46, 213, 115, 0.05));
            color: var(--success-color, #2ed573);
            border: 1px solid rgba(46, 213, 115, 0.3);
        }
        
        .status-inactive {
            background: linear-gradient(to bottom right, rgba(255, 71, 87, 0.1), rgba(255, 71, 87, 0.05));
            color: var(--danger-color, #ff4757);
            border: 1px solid rgba(255, 71, 87, 0.3);
        }
        
        .member-info p {
            color: var(--text-light, #6c757d);
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }
        
        /* Info Grid - Matching Summary Stats */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .info-item {
            background: var(--light-color, #f1f2f6);
            padding: 1rem;
            border-radius: var(--radius-md, 12px);
            border: 1px solid var(--border-color, #e0e0e0);
        }
        
        .info-item h4 {
            color: var(--text-light, #6c757d);
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .info-item p {
            color: var(--dark-color, #2f3542);
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0;
        }
        
        /* Details Grid */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .details-card {
            background: var(--white, #ffffff);
            border-radius: var(--radius-md, 12px);
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.1));
            padding: 1.5rem;
            height: 100%;
        }
        
        .details-card h3 {
            font-size: 1.2rem;
            color: var(--dark-color, #2f3542);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--light-color, #f1f2f6);
        }
        
        .details-card h3 i {
            color: var(--primary-color, #ff4757);
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 400px;
        }
        
        .data-table th {
            text-align: left;
            padding: 1rem;
            background: var(--light-color, #f1f2f6);
            color: var(--dark-color, #2f3542);
            font-weight: 600;
            border-bottom: 2px solid var(--border-color, #e0e0e0);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        
        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color, #e0e0e0);
            color: var(--dark-color, #2f3542);
            font-size: 0.95rem;
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tr:hover {
            background: var(--light-color, #f1f2f6);
        }
        
        /* Badge Styles */
        .badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-success {
            background: linear-gradient(to bottom right, rgba(46, 213, 115, 0.1), rgba(46, 213, 115, 0.05));
            color: var(--success-color, #2ed573);
            border: 1px solid rgba(46, 213, 115, 0.3);
        }
        
        .badge-warning {
            background: linear-gradient(to bottom right, rgba(255, 165, 2, 0.1), rgba(255, 165, 2, 0.05));
            color: var(--warning-color, #ffa502);
            border: 1px solid rgba(255, 165, 2, 0.3);
        }
        
        .badge-danger {
            background: linear-gradient(to bottom right, rgba(255, 71, 87, 0.1), rgba(255, 71, 87, 0.05));
            color: var(--danger-color, #ff4757);
            border: 1px solid rgba(255, 71, 87, 0.3);
        }
        
        /* View All Link */
        .view-all-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            padding: 0.75rem;
            color: var(--primary-color, #ff4757);
            text-decoration: none;
            font-weight: 600;
            border-radius: var(--radius-md, 12px);
            transition: all 0.3s ease;
        }
        
        .view-all-link:hover {
            background: var(--light-color, #f1f2f6);
        }
        
        /* Notes Section */
        .notes-section {
            background: var(--white, #ffffff);
            border-radius: var(--radius-md, 12px);
            box-shadow: var(--shadow-sm, 0 2px 8px rgba(0,0,0,0.1));
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .notes-section h3 {
            font-size: 1.2rem;
            color: var(--dark-color, #2f3542);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--light-color, #f1f2f6);
        }
        
        .notes-section h3 i {
            color: var(--primary-color, #ff4757);
        }
        
        .notes-form {
            margin-bottom: 2rem;
        }
        
        .notes-form textarea {
            width: 100%;
            padding: 1rem;
            border: 1px solid var(--border-color, #e0e0e0);
            border-radius: var(--radius-md, 12px);
            font-family: inherit;
            font-size: 0.95rem;
            color: var(--dark-color, #2f3542);
            resize: vertical;
            min-height: 100px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .notes-form textarea:focus {
            outline: none;
            border-color: var(--primary-color, #ff4757);
            box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.1);
        }
        
        .notes-list {
            margin-top: 1.5rem;
        }
        
        .note-item {
            background: var(--light-color, #f1f2f6);
            padding: 1rem;
            border-radius: var(--radius-md, 12px);
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color, #ff4757);
        }
        
        .note-item:last-child {
            margin-bottom: 0;
        }
        
        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .note-author {
            font-weight: 600;
            color: var(--dark-color, #2f3542);
        }
        
        .note-date {
            font-size: 0.8rem;
            color: var(--text-light, #6c757d);
        }
        
        .note-item p {
            color: var(--dark-color, #2f3542);
            line-height: 1.6;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-light, #6c757d);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--light-color, #f1f2f6);
            margin-bottom: 1rem;
        }
        
        .empty-state h4 {
            font-size: 1.2rem;
            color: var(--dark-color, #2f3542);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                margin-left: 60px;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            
            .top-bar {
                flex-direction: column;
                gap: 0.75rem;
                padding: 0.75rem;
            }
            
            .search-bar {
                max-width: 100%;
                width: 100%;
                min-width: auto;
            }
            
            .top-bar-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .dashboard-content {
                padding: 1rem;
            }
            
            .page-header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
                padding: 1.5rem;
            }
            
            .member-header {
                flex-direction: column;
                text-align: center;
                padding: 1.5rem;
            }
            
            .member-avatar {
                width: 120px;
                height: 120px;
                font-size: 2.5rem;
            }
            
            .member-info h2 {
                font-size: 1.5rem;
            }
            
            .action-buttons {
                justify-content: center;
                width: 100%;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .details-card,
            .notes-section {
                padding: 1.25rem;
            }
            
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .dashboard-content {
                padding: 0.75rem;
            }
            
            .member-info {
                min-width: 100%;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .data-table {
                min-width: 350px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
        
        /* Scrollbar styling - SAME AS DASHBOARD */
        .dashboard-content::-webkit-scrollbar {
            width: 6px;
        }
        
        .dashboard-content::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .dashboard-content::-webkit-scrollbar-thumb {
            background: var(--gray, #a4b0be);
            border-radius: 3px;
        }
        
        .dashboard-content::-webkit-scrollbar-thumb:hover {
            background: var(--text-light, #6c757d);
        }
        
        /* Fix for Firefox */
        @-moz-document url-prefix() {
            .dashboard-content {
                scrollbar-width: thin;
                scrollbar-color: var(--gray, #a4b0be) transparent;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Include Sidebar -->
        <?php include 'admin-sidebar.php'; ?>
        
        <!-- Main Content - EXACT SAME STRUCTURE AS DASHBOARD -->
        <div class="main-content">
            <!-- Top Bar - EXACTLY AS IN DASHBOARD -->
            <div class="top-bar">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search member details...">
                </div>
                <div class="top-bar-actions">
                    <button class="btn-notification">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </button>
                    <a href="admin-add.php" class="btn-primary">
                        <i class="fas fa-plus"></i>
                        Add New
                    </a>
                </div>
            </div>
            
            <!-- Dashboard Content - SAME CONTAINER -->
            <div class="dashboard-content">
                <!-- Page Header -->
                <div class="page-header">
                    <h1>
                        <i class="fas fa-user"></i>
                        Member Details
                    </h1>
                    <a href="admin-members.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i>
                        Back to Members
                    </a>
                </div>
                
                <!-- Member Header -->
                <div class="member-header">
                    <div class="member-avatar">
                        <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
                    </div>
                    <div class="member-info">
                        <h2><?php echo htmlspecialchars($member['full_name']); ?></h2>
                        <span class="member-status <?php echo $member['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $member['is_active'] ? 'Active Member' : 'Inactive'; ?>
                        </span>
                        <p>Member since <?php echo date('F j, Y', strtotime($member['created_at'])); ?></p>
                        
                        <div class="action-buttons">
                            <button class="btn btn-warning" onclick="window.location.href='admin-member-edit.php?id=<?php echo $memberId; ?>'">
                                <i class="fas fa-edit"></i>
                                Edit Member
                            </button>
                            <button class="btn btn-primary" onclick="sendReminder()">
                                <i class="fas fa-envelope"></i>
                                Send Reminder
                            </button>
                            <button class="btn btn-danger" onclick="confirmDeactivation()">
                                <i class="fas fa-user-slash"></i>
                                Deactivate
                            </button>
                        </div>
                        
                        <!-- Basic Info Grid -->
                        <div class="info-grid">
                            <div class="info-item">
                                <h4>Email Address</h4>
                                <p><?php echo htmlspecialchars($member['email']); ?></p>
                            </div>
                            <div class="info-item">
                                <h4>Username</h4>
                                <p><?php echo htmlspecialchars($member['username']); ?></p>
                            </div>
                            <div class="info-item">
                                <h4>Last Login</h4>
                                <p><?php echo $member['last_login'] ? date('M j, Y g:i A', strtotime($member['last_login'])) : 'Never'; ?></p>
                            </div>
                            <div class="info-item">
                                <h4>Account Status</h4>
                                <p><?php echo $member['is_active'] ? 'Active' : 'Suspended'; ?></p>
                            </div>
                        </div>
                        
                        <?php if($gymMember): ?>
                        <!-- Gym Member Info Grid -->
                        <div class="info-grid" style="margin-top: 1rem;">
                            <div class="info-item">
                                <h4>Membership Plan</h4>
                                <p><?php echo htmlspecialchars($gymMember['MembershipPlan']); ?></p>
                            </div>
                            <div class="info-item">
                                <h4>Contact Number</h4>
                                <p><?php echo htmlspecialchars($gymMember['ContactNumber']); ?></p>
                            </div>
                            <div class="info-item">
                                <h4>Age</h4>
                                <p><?php echo htmlspecialchars($gymMember['Age']); ?> years</p>
                            </div>
                            <div class="info-item">
                                <h4>Gym Status</h4>
                                <p><?php echo htmlspecialchars($gymMember['MembershipStatus']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Details Grid -->
                <div class="details-grid">
                    <!-- Recent Bookings -->
                    <div class="details-card">
                        <h3><i class="fas fa-calendar-check"></i> Recent Bookings</h3>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Class</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($bookings) > 0): ?>
                                        <?php foreach($bookings as $booking): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($booking['class_name']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($booking['schedule'])); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $booking['status'] === 'confirmed' ? 'success' : ($booking['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                        <?php echo ucfirst($booking['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3">
                                                <div class="empty-state">
                                                    <i class="fas fa-calendar-times"></i>
                                                    <h4>No Bookings Found</h4>
                                                    <p>This member hasn't booked any classes yet.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if(count($bookings) > 0): ?>
                            <a href="admin-bookings.php?member_id=<?php echo $memberId; ?>" class="view-all-link">
                                <i class="fas fa-list"></i> View All Bookings
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Payment History -->
                    <div class="details-card">
                        <h3><i class="fas fa-credit-card"></i> Payment History</h3>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($payments) > 0): ?>
                                        <?php foreach($payments as $payment): ?>
                                            <tr>
                                                <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                        <?php echo ucfirst($payment['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3">
                                                <div class="empty-state">
                                                    <i class="fas fa-credit-card"></i>
                                                    <h4>No Payments Found</h4>
                                                    <p>No payment history available.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if(count($payments) > 0): ?>
                            <a href="admin-payments.php?member_id=<?php echo $memberId; ?>" class="view-all-link">
                                <i class="fas fa-list"></i> View All Payments
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Notes Section -->
                <div class="notes-section">
                    <h3><i class="fas fa-sticky-note"></i> Admin Notes</h3>
                    <form class="notes-form" onsubmit="addNote(event)">
                        <textarea id="noteText" placeholder="Add a note about this member..."></textarea>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Add Note
                        </button>
                    </form>
                    
                    <div class="notes-list">
                        <!-- Sample notes -->
                        <div class="note-item">
                            <div class="note-header">
                                <span class="note-author">System Admin</span>
                                <span class="note-date">Today, 10:30 AM</span>
                            </div>
                            <p>Member inquired about personal training packages. Forwarded to trainer Mark.</p>
                        </div>
                        <div class="note-item">
                            <div class="note-header">
                                <span class="note-author">System Admin</span>
                                <span class="note-date">Yesterday, 3:45 PM</span>
                            </div>
                            <p>Payment reminder sent for monthly subscription renewal.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function sendReminder() {
            if(confirm('Send payment reminder to <?php echo htmlspecialchars($member['full_name']); ?>?')) {
                alert('Reminder email has been sent!');
                // In real app, you would make an AJAX call here
            }
        }
        
        function confirmDeactivation() {
            if(confirm('Are you sure you want to deactivate <?php echo htmlspecialchars($member['full_name']); ?>?')) {
                if(confirm('This action cannot be undone. The member will lose access to their account.')) {
                    window.location.href = 'admin-member-deactivate.php?id=<?php echo $memberId; ?>';
                }
            }
        }
        
        function addNote(event) {
            event.preventDefault();
            const noteText = document.getElementById('noteText').value.trim();
            
            if(noteText) {
                // In real app, you would send this to server via AJAX
                alert('Note added successfully!');
                document.getElementById('noteText').value = '';
                
                // Simulate adding note to list
                const notesList = document.querySelector('.notes-list');
                const newNote = document.createElement('div');
                newNote.className = 'note-item';
                newNote.innerHTML = `
                    <div class="note-header">
                        <span class="note-author">You</span>
                        <span class="note-date">Just now</span>
                    </div>
                    <p>${noteText}</p>
                `;
                notesList.insertBefore(newNote, notesList.firstChild);
            }
        }
    </script>
</body>
</html>