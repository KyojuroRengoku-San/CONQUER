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
    
    // Handle class cancellation
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_class'])) {
        $booking_id = $_POST['booking_id'];
        
        $checkStmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ?");
        $checkStmt->execute([$booking_id, $user_id]);
        $booking = $checkStmt->fetch();
        
        if($booking) {
            $cancelStmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
            if($cancelStmt->execute([$booking_id])) {
                $message = "Class booking cancelled successfully!";
                $success = true;
            } else {
                $message = "Error cancelling class. Please try again.";
                $success = false;
            }
        } else {
            $message = "Booking not found or you don't have permission to cancel it!";
            $success = false;
        }
    }
    
    // Get all user bookings
    $bookings = [];
    try {
        $bookingsStmt = $pdo->prepare("
            SELECT b.*, c.*, u.full_name as trainer_name 
            FROM bookings b 
            JOIN classes c ON b.class_id = c.id 
            JOIN users u ON c.trainer_id = u.id 
            WHERE b.user_id = ? 
            ORDER BY c.schedule DESC
        ");
        $bookingsStmt->execute([$user_id]);
        $bookings = $bookingsStmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Bookings query error: " . $e->getMessage());
        // Alternative query
        $bookingsStmt = $pdo->prepare("
            SELECT b.*, c.*, 'Trainer' as trainer_name 
            FROM bookings b 
            JOIN classes c ON b.class_id = c.id 
            WHERE b.user_id = ? 
            ORDER BY c.schedule DESC
        ");
        $bookingsStmt->execute([$user_id]);
        $bookings = $bookingsStmt->fetchAll();
    }
    
    // Count by status
    $upcomingCount = 0;
    $completedCount = 0;
    $cancelledCount = 0;
    
    foreach($bookings as $booking) {
        if($booking['status'] == 'confirmed' && strtotime($booking['schedule']) > time()) {
            $upcomingCount++;
        } elseif($booking['status'] == 'attended') {
            $completedCount++;
        } elseif($booking['status'] == 'cancelled') {
            $cancelledCount++;
        }
    }
    
} catch(PDOException $e) {
    $message = "Database error. Please try again later.";
    error_log("My Classes page error: " . $e->getMessage());
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
    <link rel="stylesheet" href="dashboard-style.css">
    
    <style>
        .classes-content {
            padding: 2rem;
        }
        
        .classes-section {
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
        
        .stat-card.upcoming {
            border-color: var(--primary-color);
        }
        
        .stat-card.completed {
            border-color: var(--success);
        }
        
        .stat-card.cancelled {
            border-color: var(--danger);
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
        
        .filters {
            background: var(--light-bg);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            margin-bottom: 2rem;
        }
        
        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 0.75rem 1.5rem;
            background: var(--white);
            border: 2px solid var(--light-color);
            border-radius: var(--radius-sm);
            color: var(--dark-color);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
        }
        
        .filter-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .filter-btn:hover {
            background: var(--light-color);
        }
        
        .classes-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .class-card {
            background: var(--white);
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--primary-color);
        }
        
        .class-card.completed {
            border-color: var(--success);
        }
        
        .class-card.cancelled {
            border-color: var(--danger);
        }
        
        .class-card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--light-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .class-info {
            flex: 1;
        }
        
        .class-info h4 {
            margin: 0 0 0.5rem 0;
            color: var(--dark-color);
            font-size: 1.25rem;
        }
        
        .class-meta {
            display: flex;
            gap: 1rem;
            color: var(--gray);
            font-size: 0.9rem;
            flex-wrap: wrap;
        }
        
        .class-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .class-status {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .status-upcoming {
            background: rgba(52, 152, 219, 0.1);
            color: var(--primary-color);
        }
        
        .status-completed {
            background: rgba(46, 213, 115, 0.1);
            color: var(--success);
        }
        
        .status-cancelled {
            background: rgba(255, 56, 56, 0.1);
            color: var(--danger);
        }
        
        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }
        
        .class-card-body {
            padding: 1.5rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .class-detail {
            margin-bottom: 0.5rem;
        }
        
        .class-detail strong {
            color: var(--dark-color);
            display: inline-block;
            min-width: 100px;
        }
        
        .class-detail span {
            color: var(--gray);
        }
        
        .class-actions {
            padding: 1.5rem;
            border-top: 1px solid var(--light-color);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .message {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            margin-bottom: 2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message.success {
            background: rgba(46, 213, 115, 0.1);
            color: var(--success);
            border: 2px solid var(--success);
        }
        
        .message.error {
            background: rgba(255, 56, 56, 0.1);
            color: var(--danger);
            border: 2px solid var(--danger);
        }
        
        .no-classes {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray);
        }
        
        .class-tag {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 5px;
        }
        
        .class-tag.yoga { background: #e6f7ff; color: #1890ff; }
        .class-tag.hiit { background: #fff7e6; color: #fa8c16; }
        .class-tag.strength { background: #f6ffed; color: #52c41a; }
        .class-tag.cardio { background: #fff0f6; color: #eb2f96; }
        .class-tag.crossfit { background: #f9f0ff; color: #722ed1; }
        .class-tag.pilates { background: #f0f0f0; color: #595959; }
        
        @media (max-width: 768px) {
            .class-card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .class-actions {
                flex-direction: column;
            }
            
            .filter-buttons {
                flex-direction: column;
            }
            
            .filter-btn {
                width: 100%;
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
                <input type="text" placeholder="Search classes..." id="searchClasses">
            </div>
            <div class="top-bar-actions">
                <button class="btn-notification">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </button>
                <button class="btn-primary" onclick="window.location.href='user-bookclass.php'">
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
                    <p>View and manage all your class bookings</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3><?php echo $upcomingCount; ?></h3>
                        <p>Upcoming</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $completedCount; ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
            </div>

            <?php if($message): ?>
                <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                    <i class="fas <?php echo $success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="classes-content">
                <!-- Stats Overview -->
                <div class="classes-section">
                    <h3 class="section-title">Class Overview</h3>
                    <div class="stats-grid">
                        <div class="stat-card upcoming">
                            <h3><?php echo $upcomingCount; ?></h3>
                            <p>Upcoming Classes</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo count($bookings); ?></h3>
                            <p>Total Bookings</p>
                        </div>
                        <div class="stat-card completed">
                            <h3><?php echo $completedCount; ?></h3>
                            <p>Completed</p>
                        </div>
                        <div class="stat-card cancelled">
                            <h3><?php echo $cancelledCount; ?></h3>
                            <p>Cancelled</p>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters">
                    <h4 style="margin: 0 0 1rem 0; color: var(--dark-color);">Filter Classes</h4>
                    <div class="filter-buttons">
                        <button class="filter-btn active" data-filter="all">All Classes</button>
                        <button class="filter-btn" data-filter="upcoming">Upcoming</button>
                        <button class="filter-btn" data-filter="completed">Completed</button>
                        <button class="filter-btn" data-filter="cancelled">Cancelled</button>
                        <button class="filter-btn" data-filter="pending">Pending</button>
                    </div>
                </div>

                <!-- Classes List -->
                <div class="classes-section">
                    <h3 class="section-title">Your Bookings</h3>
                    
                    <?php if(count($bookings) > 0): ?>
                        <div class="classes-list" id="classesList">
                            <?php foreach($bookings as $booking): 
                                $date = new DateTime($booking['schedule']);
                                $now = new DateTime();
                                $isUpcoming = $date > $now && $booking['status'] == 'confirmed';
                                $isCompleted = $booking['status'] == 'attended';
                                $isCancelled = $booking['status'] == 'cancelled';
                                $isPending = $booking['status'] == 'pending';
                                
                                $statusClass = '';
                                $statusText = ucfirst($booking['status']);
                                
                                if($isUpcoming) {
                                    $statusClass = 'status-upcoming';
                                    $statusText = 'Upcoming';
                                } elseif($isCompleted) {
                                    $statusClass = 'status-completed';
                                } elseif($isCancelled) {
                                    $statusClass = 'status-cancelled';
                                } elseif($isPending) {
                                    $statusClass = 'status-pending';
                                }
                                
                                $cardClass = '';
                                if($isCompleted) $cardClass = 'completed';
                                if($isCancelled) $cardClass = 'cancelled';
                                
                                $classType = isset($booking['class_type']) ? strtolower(preg_replace('/[^a-zA-Z]/', '', $booking['class_type'])) : 'others';
                            ?>
                                <div class="class-card <?php echo $cardClass; ?>" 
                                     data-status="<?php echo $booking['status']; ?>"
                                     data-date="<?php echo $date->format('Y-m-d'); ?>">
                                    <div class="class-card-header">
                                        <div class="class-info">
                                            <h4><?php echo htmlspecialchars($booking['class_name']); ?></h4>
                                            <div class="class-meta">
                                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($booking['trainer_name']); ?></span>
                                                <span><i class="fas fa-calendar"></i> <?php echo $date->format('M j, Y'); ?></span>
                                                <span><i class="fas fa-clock"></i> <?php echo $date->format('g:i A'); ?></span>
                                                <span class="class-tag <?php echo $classType; ?>">
                                                    <?php echo htmlspecialchars($booking['class_type']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="class-status <?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="class-card-body">
                                        <div>
                                            <div class="class-detail">
                                                <strong>Duration:</strong>
                                                <span><?php echo isset($booking['duration']) ? htmlspecialchars($booking['duration']) : '60 min'; ?></span>
                                            </div>
                                            <div class="class-detail">
                                                <strong>Location:</strong>
                                                <span><?php echo isset($booking['location']) ? htmlspecialchars($booking['location']) : 'Main Studio'; ?></span>
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <div class="class-detail">
                                                <strong>Intensity:</strong>
                                                <span><?php echo isset($booking['intensity_level']) ? htmlspecialchars($booking['intensity_level']) : 'Medium'; ?></span>
                                            </div>
                                            <div class="class-detail">
                                                <strong>Booked On:</strong>
                                                <span><?php echo isset($booking['booking_date']) ? date('M j, Y', strtotime($booking['booking_date'])) : 'N/A'; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if(isset($booking['notes']) && !empty($booking['notes'])): ?>
                                    <div style="padding: 0 1.5rem;">
                                        <div class="class-detail">
                                            <strong>Notes:</strong>
                                            <span><?php echo htmlspecialchars($booking['notes']); ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="class-actions">
                                        <?php if($isUpcoming): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <button type="submit" name="cancel_class" class="btn-danger" 
                                                        onclick="return confirm('Are you sure you want to cancel this class?')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            </form>
                                            <button class="btn-secondary" onclick="window.location.href='class-details.php?id=<?php echo $booking['class_id']; ?>'">
                                                <i class="fas fa-info-circle"></i> Details
                                            </button>
                                        <?php elseif($isPending): ?>
                                            <span style="color: var(--gray); font-style: italic;">Waiting for confirmation</span>
                                        <?php endif; ?>
                                        
                                        <?php if($isCompleted): ?>
                                            <button class="btn-primary" onclick="window.location.href='submit-review.php?class_id=<?php echo $booking['class_id']; ?>'">
                                                <i class="fas fa-star"></i> Rate Class
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-classes">
                            <i class="fas fa-calendar-times" style="font-size: 3rem; color: var(--gray); margin-bottom: 1rem;"></i>
                            <h3>No classes booked yet</h3>
                            <p>Start your fitness journey by booking your first class!</p>
                            <button class="btn-primary mt-3" onclick="window.location.href='user-bookclass.php'">
                                <i class="fas fa-plus"></i> Book Your First Class
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const filterButtons = document.querySelectorAll('.filter-btn');
            const classCards = document.querySelectorAll('.class-card');
            const searchInput = document.getElementById('searchClasses');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    const filter = this.getAttribute('data-filter');
                    filterClasses(filter);
                });
            });
            
            function filterClasses(filter) {
                const searchValue = searchInput.value.toLowerCase();
                
                classCards.forEach(card => {
                    const status = card.getAttribute('data-status');
                    const date = card.getAttribute('data-date');
                    const className = card.querySelector('h4').textContent.toLowerCase();
                    const trainerName = card.querySelector('.class-meta span:first-child').textContent.toLowerCase();
                    
                    let show = true;
                    
                    // Status filter
                    if(filter !== 'all') {
                        switch(filter) {
                            case 'upcoming':
                                if(status !== 'confirmed' || new Date(date) <= new Date()) show = false;
                                break;
                            case 'completed':
                                if(status !== 'attended') show = false;
                                break;
                            case 'cancelled':
                                if(status !== 'cancelled') show = false;
                                break;
                            case 'pending':
                                if(status !== 'pending') show = false;
                                break;
                        }
                    }
                    
                    // Search filter
                    if(searchValue && !className.includes(searchValue) && !trainerName.includes(searchValue)) {
                        show = false;
                    }
                    
                    card.style.display = show ? 'block' : 'none';
                });
            }
            
            // Search functionality
            searchInput.addEventListener('input', function() {
                const activeFilter = document.querySelector('.filter-btn.active').getAttribute('data-filter');
                filterClasses(activeFilter);
            });
            
            // Initial filter
            filterClasses('all');
        });
        
        // Confirm cancellation
        document.addEventListener('submit', function(e) {
            if(e.target.querySelector('[name="cancel_class"]')) {
                if(!confirm('Are you sure you want to cancel this class?')) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>