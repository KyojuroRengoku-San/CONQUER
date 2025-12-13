<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'member') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$filter = $_GET['filter'] ?? 'all';
$message = '';
$success = false;

// Handle class cancellation
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_class'])) {
    $booking_id = $_POST['booking_id'];
    
    try {
        // Check if it's not too late to cancel (at least 24 hours before)
        $checkStmt = $pdo->prepare("
            SELECT c.schedule 
            FROM bookings b 
            JOIN classes c ON b.class_id = c.id 
            WHERE b.id = ? AND b.user_id = ?
        ");
        $checkStmt->execute([$booking_id, $user_id]);
        $booking = $checkStmt->fetch();
        
        if($booking) {
            $classTime = new DateTime($booking['schedule']);
            $now = new DateTime();
            $hoursDiff = ($classTime->getTimestamp() - $now->getTimestamp()) / 3600;
            
            if($hoursDiff >= 24) {
                // Cancel booking
                $cancelStmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?");
                if($cancelStmt->execute([$booking_id, $user_id])) {
                    $message = "Class cancelled successfully!";
                    $success = true;
                } else {
                    $message = "Error cancelling class. Please try again.";
                    $success = false;
                }
            } else {
                $message = "Cannot cancel class less than 24 hours before start time.";
                $success = false;
            }
        } else {
            $message = "Booking not found!";
            $success = false;
        }
    } catch(PDOException $e) {
        $message = "Database error: " . $e->getMessage();
        $success = false;
    }
}

try {
    // Get user info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // Build query based on filter
    $query = "
        SELECT b.*, c.*, t.full_name as trainer_name, t.specialization,
               b.status as booking_status
        FROM bookings b 
        JOIN classes c ON b.class_id = c.id 
        JOIN users t ON c.trainer_id = t.id 
        WHERE b.user_id = ?
    ";
    
    $params = [$user_id];
    
    switch($filter) {
        case 'upcoming':
            $query .= " AND c.schedule > NOW() AND b.status = 'confirmed'";
            break;
        case 'past':
            $query .= " AND c.schedule <= NOW()";
            break;
        case 'pending':
            $query .= " AND b.status = 'pending'";
            break;
        case 'cancelled':
            $query .= " AND b.status = 'cancelled'";
            break;
        case 'confirmed':
            $query .= " AND b.status = 'confirmed'";
            break;
        // 'all' shows everything
    }
    
    $query .= " ORDER BY c.schedule " . ($filter === 'past' ? 'DESC' : 'ASC');
    
    $bookingsStmt = $pdo->prepare($query);
    $bookingsStmt->execute($params);
    $bookings = $bookingsStmt->fetchAll();
    
    // Get statistics
    $statsStmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN c.schedule > NOW() AND b.status = 'confirmed' THEN 1 ELSE 0 END) as upcoming,
            SUM(CASE WHEN c.schedule <= NOW() THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            COUNT(*) as total
        FROM bookings b 
        JOIN classes c ON b.class_id = c.id 
        WHERE b.user_id = ?
    ");
    $statsStmt->execute([$user_id]);
    $stats = $statsStmt->fetch();
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Classes | CONQUER Gym</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="user-dashboard.css">
    
    <style>
        /* Additional styles for my classes page */
        .stats-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .stat-tab {
            flex: 1;
            min-width: 150px;
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            text-align: center;
            text-decoration: none;
            color: var(--dark-color);
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }
        
        [data-theme="dark"] .stat-tab {
            background: var(--dark-color);
        }
        
        .stat-tab:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-tab.active {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow-primary);
        }
        
        .stat-tab h3 {
            margin: 0 0 0.5rem 0;
            font-size: 2rem;
            font-weight: 800;
        }
        
        .stat-tab p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .class-filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 0.75rem 1.5rem;
            background: var(--light-color);
            border: none;
            border-radius: var(--radius-md);
            color: var(--dark-color);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-btn:hover {
            background: var(--gray);
            color: var(--white);
        }
        
        .filter-btn.active {
            background: var(--gradient-primary);
            color: white;
            box-shadow: var(--shadow-primary);
        }
        
        .classes-container {
            display: grid;
            gap: 1.5rem;
        }
        
        .booking-card {
            background: var(--white);
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        
        [data-theme="dark"] .booking-card {
            background: var(--dark-color);
        }
        
        .booking-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .booking-card-header {
            padding: 1.5rem;
            border-bottom: 2px solid var(--light-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .booking-date {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .booking-card-body {
            padding: 1.5rem;
        }
        
        .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .booking-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            border-top: 1px solid var(--light-color);
            padding-top: 1.5rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .modal-overlay {
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
        
        .modal-content {
            background: var(--white);
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow-lg);
        }
        
        [data-theme="dark"] .modal-content {
            background: var(--dark-color);
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 2px solid var(--light-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            color: var(--dark-color);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        @media (max-width: 768px) {
            .stats-tabs {
                flex-direction: column;
            }
            
            .stat-tab {
                min-width: 100%;
            }
            
            .booking-card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .booking-actions {
                flex-direction: column;
            }
            
            .booking-actions button {
                width: 100%;
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
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                    <p>Member</p>
                </div>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="user-dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="profile.php">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>
            <a href="my-classes.php" class="active">
                <i class="fas fa-calendar-alt"></i>
                <span>My Classes</span>
            </a>
            <a href="payments.php">
                <i class="fas fa-credit-card"></i>
                <span>Payments</span>
            </a>
            <a href="success-stories.php">
                <i class="fas fa-trophy"></i>
                <span>Success Stories</span>
            </a>
            <a href="book-class.php">
                <i class="fas fa-plus-circle"></i>
                <span>Book Class</span>
            </a>
            <a href="contact.php">
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
                <input type="text" placeholder="Search my classes..." id="searchClasses">
            </div>
            <div class="top-bar-actions">
                <button class="btn-notification">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </button>
                <button class="btn-primary" onclick="window.location.href='book-class.php'">
                    <i class="fas fa-plus"></i>
                    Book New Class
                </button>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>My Classes 📅</h1>
                    <p>Manage your bookings and track your fitness journey</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Bookings</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $stats['completed']; ?></h3>
                        <p>Classes Completed</p>
                    </div>
                </div>
            </div>

            <?php if($message): ?>
                <div class="message <?php echo $success ? 'success' : 'error'; ?>" style="margin-bottom: 2rem;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Stats Tabs -->
            <div class="stats-tabs">
                <a href="?filter=upcoming" class="stat-tab <?php echo $filter === 'upcoming' ? 'active' : ''; ?>">
                    <h3><?php echo $stats['upcoming']; ?></h3>
                    <p>Upcoming</p>
                </a>
                <a href="?filter=past" class="stat-tab <?php echo $filter === 'past' ? 'active' : ''; ?>">
                    <h3><?php echo $stats['completed']; ?></h3>
                    <p>Completed</p>
                </a>
                <a href="?filter=pending" class="stat-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>Pending</p>
                </a>
                <a href="?filter=cancelled" class="stat-tab <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">
                    <h3><?php echo $stats['cancelled']; ?></h3>
                    <p>Cancelled</p>
                </a>
                <a href="?filter=all" class="stat-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    <h3><?php echo $stats['total']; ?></h3>
                    <p>All Classes</p>
                </a>
            </div>

            <!-- Filter Buttons -->
            <div class="class-filters">
                <button class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>" onclick="window.location.href='?filter=all'">
                    <i class="fas fa-list"></i> All
                </button>
                <button class="filter-btn <?php echo $filter === 'upcoming' ? 'active' : ''; ?>" onclick="window.location.href='?filter=upcoming'">
                    <i class="fas fa-clock"></i> Upcoming
                </button>
                <button class="filter-btn <?php echo $filter === 'past' ? 'active' : ''; ?>" onclick="window.location.href='?filter=past'">
                    <i class="fas fa-check-circle"></i> Past
                </button>
                <button class="filter-btn <?php echo $filter === 'confirmed' ? 'active' : ''; ?>" onclick="window.location.href='?filter=confirmed'">
                    <i class="fas fa-calendar-check"></i> Confirmed
                </button>
                <button class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>" onclick="window.location.href='?filter=pending'">
                    <i class="fas fa-hourglass-half"></i> Pending
                </button>
                <button class="filter-btn <?php echo $filter === 'cancelled' ? 'active' : ''; ?>" onclick="window.location.href='?filter=cancelled'">
                    <i class="fas fa-times-circle"></i> Cancelled
                </button>
            </div>

            <!-- Classes List -->
            <div class="classes-container">
                <?php if(count($bookings) > 0): ?>
                    <?php foreach($bookings as $booking): 
                        $date = new DateTime($booking['schedule']);
                        $now = new DateTime();
                        $isPast = $date <= $now;
                        $canCancel = !$isPast && $booking['booking_status'] === 'confirmed' && 
                                     (($date->getTimestamp() - $now->getTimestamp()) / 3600) >= 24;
                    ?>
                        <div class="booking-card">
                            <div class="booking-card-header">
                                <div>
                                    <div class="booking-date">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo $date->format('F j, Y'); ?>
                                        <span style="color: var(--gray); margin: 0 0.5rem;">•</span>
                                        <i class="fas fa-clock"></i>
                                        <?php echo $date->format('g:i A'); ?>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                                        <span class="status-badge <?php echo strtolower($booking['booking_status']); ?>">
                                            <?php echo htmlspecialchars($booking['booking_status']); ?>
                                        </span>
                                        <span class="class-tag <?php echo strtolower($booking['class_type']); ?>">
                                            <?php echo htmlspecialchars($booking['class_type']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <span class="status-badge <?php echo $isPast ? 'completed' : 'pending'; ?>">
                                        <?php echo $isPast ? 'Completed' : 'Upcoming'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="booking-card-body">
                                <h3 style="margin: 0 0 1rem 0; color: var(--dark-color);">
                                    <?php echo htmlspecialchars($booking['class_name']); ?>
                                </h3>
                                
                                <div class="booking-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Trainer</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($booking['trainer_name']); ?></span>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <span class="detail-label">Duration</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($booking['duration']); ?> minutes</span>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <span class="detail-label">Location</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($booking['location']); ?></span>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <span class="detail-label">Intensity</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($booking['intensity_level']); ?></span>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <span class="detail-label">Booking Date</span>
                                        <span class="detail-value"><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></span>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <span class="detail-label">Booking ID</span>
                                        <span class="detail-value">#<?php echo str_pad($booking['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                    </div>
                                </div>
                                
                                <?php if($booking['notes']): ?>
                                    <div style="margin-top: 1rem; padding: 1rem; background: var(--light-color); border-radius: var(--radius-sm);">
                                        <strong>Your Notes:</strong>
                                        <p style="margin: 0.5rem 0 0 0; color: var(--dark-color);"><?php echo htmlspecialchars($booking['notes']); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="booking-actions">
                                    <?php if(!$isPast && $booking['booking_status'] === 'confirmed'): ?>
                                        <?php if($canCancel): ?>
                                            <button class="btn-secondary" 
                                                    onclick="openCancelModal(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['class_name']); ?>')">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-secondary" disabled title="Cannot cancel within 24 hours of class">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <button class="btn-primary" onclick="window.location.href='class-details.php?id=<?php echo $booking['class_id']; ?>'">
                                        <i class="fas fa-info-circle"></i> View Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No classes found</h3>
                        <p>You don't have any <?php echo $filter; ?> classes yet.</p>
                        <?php if($filter !== 'all'): ?>
                            <button class="btn-primary mt-2" onclick="window.location.href='?filter=all'">
                                View All Classes
                            </button>
                        <?php endif; ?>
                        <button class="btn-primary mt-2" onclick="window.location.href='book-class.php'">
                            <i class="fas fa-plus"></i> Book a Class
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div class="modal-overlay" id="cancelModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Cancel Class Booking</h3>
                <button class="close-btn" onclick="closeCancelModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this class booking?</p>
                <p id="cancelClassName" style="font-weight: 600; color: var(--dark-color);"></p>
                <p style="color: var(--warning); font-size: 0.9rem;">
                    <i class="fas fa-exclamation-triangle"></i> 
                    You can only cancel classes at least 24 hours before the start time.
                </p>
                
                <form id="cancelForm" method="POST">
                    <input type="hidden" name="booking_id" id="cancelBookingId">
                    
                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" onclick="closeCancelModal()">
                            <i class="fas fa-arrow-left"></i> Go Back
                        </button>
                        <button type="submit" name="cancel_class" class="btn-primary" style="background: var(--danger);">
                            <i class="fas fa-times"></i> Yes, Cancel Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchClasses').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const classCards = document.querySelectorAll('.booking-card');
            
            classCards.forEach(card => {
                const className = card.querySelector('h3').textContent.toLowerCase();
                const trainerName = card.querySelector('.detail-value:first-child').textContent.toLowerCase();
                const classType = card.querySelector('.class-tag').textContent.toLowerCase();
                
                if(className.includes(searchTerm) || trainerName.includes(searchTerm) || classType.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        
        // Cancel modal functions
        function openCancelModal(bookingId, className) {
            document.getElementById('cancelBookingId').value = bookingId;
            document.getElementById('cancelClassName').textContent = className;
            document.getElementById('cancelModal').style.display = 'flex';
        }
        
        function closeCancelModal() {
            document.getElementById('cancelModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('cancelModal');
            if(event.target === modal) {
                closeCancelModal();
            }
        });
        
        // Auto-hide message after 5 seconds
        <?php if($message): ?>
            setTimeout(() => {
                const messageDiv = document.querySelector('.message');
                if(messageDiv) {
                    messageDiv.style.opacity = '0';
                    messageDiv.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        messageDiv.style.display = 'none';
                    }, 500);
                }
            }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>