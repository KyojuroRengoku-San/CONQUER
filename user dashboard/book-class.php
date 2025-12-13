<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'member') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$success = false;

// Get user info
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // Get member info
    $memberStmt = $pdo->prepare("SELECT * FROM gym_members WHERE Email = ?");
    $memberStmt->execute([$user['email']]);
    $member = $memberStmt->fetch();
    
    // Get available classes
    $classesStmt = $pdo->prepare("
        SELECT c.*, t.full_name as trainer_name, t.specialization,
               (c.capacity - (SELECT COUNT(*) FROM bookings WHERE class_id = c.id AND status = 'confirmed')) as available_slots
        FROM classes c 
        JOIN users t ON c.trainer_id = t.id 
        WHERE c.schedule > NOW() 
        AND c.status = 'active'
        AND (c.capacity - (SELECT COUNT(*) FROM bookings WHERE class_id = c.id AND status = 'confirmed')) > 0
        ORDER BY c.schedule ASC
    ");
    $classesStmt->execute();
    $availableClasses = $classesStmt->fetchAll();
    
    // Get class types for filter
    $typesStmt = $pdo->prepare("SELECT DISTINCT class_type FROM classes WHERE status = 'active' ORDER BY class_type");
    $typesStmt->execute();
    $classTypes = $typesStmt->fetchAll();
    
    // Handle booking submission
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_class'])) {
        $class_id = $_POST['class_id'];
        $notes = $_POST['notes'] ?? '';
        
        // Check if user already booked this class
        $checkStmt = $pdo->prepare("SELECT * FROM bookings WHERE user_id = ? AND class_id = ?");
        $checkStmt->execute([$user_id, $class_id]);
        
        if($checkStmt->rowCount() > 0) {
            $message = "You have already booked this class!";
            $success = false;
        } else {
            // Check class availability
            $availabilityStmt = $pdo->prepare("
                SELECT capacity, 
                       (SELECT COUNT(*) FROM bookings WHERE class_id = ? AND status = 'confirmed') as booked_count
                FROM classes WHERE id = ?
            ");
            $availabilityStmt->execute([$class_id, $class_id]);
            $classInfo = $availabilityStmt->fetch();
            
            if($classInfo && $classInfo['booked_count'] < $classInfo['capacity']) {
                // Insert booking
                $insertStmt = $pdo->prepare("
                    INSERT INTO bookings (user_id, class_id, booking_date, status, notes) 
                    VALUES (?, ?, NOW(), 'pending', ?)
                ");
                
                if($insertStmt->execute([$user_id, $class_id, $notes])) {
                    $message = "Class booked successfully! Waiting for confirmation.";
                    $success = true;
                    
                    // Refresh available classes
                    $classesStmt->execute();
                    $availableClasses = $classesStmt->fetchAll();
                } else {
                    $message = "Error booking class. Please try again.";
                    $success = false;
                }
            } else {
                $message = "This class is now full!";
                $success = false;
            }
        }
    }
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Class | CONQUER Gym</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="user-dashboard.css">
    
    <style>
        /* Additional styles for booking page */
        .filters {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }
        
        .filter-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .filter-select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--light-color);
            border-radius: var(--radius-sm);
            background: var(--white);
            color: var(--dark-color);
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            transition: var(--transition);
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .reset-filters {
            align-self: flex-end;
            padding: 0.75rem 1.5rem;
            background: var(--light-color);
            border: none;
            border-radius: var(--radius-sm);
            color: var(--dark-color);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
        }
        
        .reset-filters:hover {
            background: var(--gray);
            color: var(--white);
        }
        
        .class-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .class-card {
            background: var(--white);
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        
        [data-theme="dark"] .class-card {
            background: var(--dark-color);
        }
        
        .class-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }
        
        .class-card-header {
            padding: 1.5rem;
            border-bottom: 2px solid var(--light-color);
        }
        
        .class-card-header h3 {
            margin: 0 0 0.5rem 0;
            color: var(--dark-color);
            font-size: 1.25rem;
        }
        
        .class-meta {
            display: flex;
            gap: 1rem;
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .class-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .class-card-body {
            padding: 1.5rem;
        }
        
        .class-detail {
            margin-bottom: 1rem;
        }
        
        .class-detail strong {
            color: var(--dark-color);
            display: inline-block;
            min-width: 100px;
        }
        
        .class-detail span {
            color: var(--gray);
        }
        
        .slots-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--light-color);
        }
        
        .slots-count {
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .slots-count.low {
            color: var(--danger);
        }
        
        .slots-count.moderate {
            color: var(--warning);
        }
        
        .slots-count.high {
            color: var(--success);
        }
        
        .booking-form {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            margin-top: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--light-color);
            border-radius: var(--radius-sm);
            background: var(--white);
            color: var(--dark-color);
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .textarea-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .message {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            margin-bottom: 2rem;
            font-weight: 600;
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
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
        }
        
        @media (max-width: 768px) {
            .class-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-row {
                flex-direction: column;
            }
            
            .reset-filters {
                align-self: stretch;
                text-align: center;
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
            <a href="my-classes.php">
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
            <a href="book-class.php" class="active">
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
                <input type="text" placeholder="Search classes..." id="searchClasses">
            </div>
            <div class="top-bar-actions">
                <button class="btn-notification">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </button>
                <button class="btn-primary" onclick="window.location.href='my-classes.php'">
                    <i class="fas fa-calendar-check"></i>
                    My Classes
                </button>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>Book a Class 🏋️‍♀️</h1>
                    <p>Browse available classes and book your spot today</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3><?php echo count($availableClasses); ?></h3>
                        <p>Available Classes</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $member ? htmlspecialchars($member['MembershipPlan']) : 'No Plan'; ?></h3>
                        <p>Your Plan</p>
                    </div>
                </div>
            </div>

            <?php if($message): ?>
                <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filters">
                <h3 style="margin: 0 0 1rem 0; color: var(--dark-color);">Filter Classes</h3>
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="filterType"><i class="fas fa-filter"></i> Class Type</label>
                        <select id="filterType" class="filter-select">
                            <option value="all">All Types</option>
                            <?php foreach($classTypes as $type): ?>
                                <option value="<?php echo strtolower($type['class_type']); ?>">
                                    <?php echo htmlspecialchars($type['class_type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="filterDate"><i class="fas fa-calendar"></i> Date Range</label>
                        <select id="filterDate" class="filter-select">
                            <option value="all">All Dates</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="filterTime"><i class="fas fa-clock"></i> Time of Day</label>
                        <select id="filterTime" class="filter-select">
                            <option value="all">Any Time</option>
                            <option value="morning">Morning (5am-12pm)</option>
                            <option value="afternoon">Afternoon (12pm-5pm)</option>
                            <option value="evening">Evening (5pm-9pm)</option>
                        </select>
                    </div>
                    
                    <button class="reset-filters" id="resetFilters">
                        <i class="fas fa-redo"></i> Reset Filters
                    </button>
                </div>
            </div>

            <!-- Available Classes -->
            <?php if(count($availableClasses) > 0): ?>
                <div class="class-grid" id="classesContainer">
                    <?php foreach($availableClasses as $class): 
                        $date = new DateTime($class['schedule']);
                        $slots = $class['available_slots'];
                        $slotClass = 'high';
                        $percentage = ($slots / $class['capacity']) * 100;
                        
                        if($percentage <= 25) {
                            $slotClass = 'low';
                        } elseif($percentage <= 50) {
                            $slotClass = 'moderate';
                        }
                    ?>
                        <div class="class-card" 
                             data-type="<?php echo strtolower($class['class_type']); ?>"
                             data-date="<?php echo $date->format('Y-m-d'); ?>"
                             data-time="<?php echo $date->format('H:i'); ?>">
                            <div class="class-card-header">
                                <h3><?php echo htmlspecialchars($class['class_name']); ?></h3>
                                <div class="class-meta">
                                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($class['trainer_name']); ?></span>
                                    <span><i class="fas fa-clock"></i> <?php echo $date->format('g:i A'); ?></span>
                                </div>
                            </div>
                            <div class="class-card-body">
                                <div class="class-detail">
                                    <strong>Type:</strong>
                                    <span class="class-tag <?php echo strtolower($class['class_type']); ?>">
                                        <?php echo htmlspecialchars($class['class_type']); ?>
                                    </span>
                                </div>
                                
                                <div class="class-detail">
                                    <strong>Date:</strong>
                                    <span><?php echo $date->format('F j, Y (l)'); ?></span>
                                </div>
                                
                                <div class="class-detail">
                                    <strong>Duration:</strong>
                                    <span><?php echo htmlspecialchars($class['duration']); ?> minutes</span>
                                </div>
                                
                                <div class="class-detail">
                                    <strong>Location:</strong>
                                    <span><?php echo htmlspecialchars($class['location']); ?></span>
                                </div>
                                
                                <div class="class-detail">
                                    <strong>Intensity:</strong>
                                    <span><?php echo htmlspecialchars($class['intensity_level']); ?></span>
                                </div>
                                
                                <div class="class-detail">
                                    <strong>Description:</strong>
                                    <span><?php echo htmlspecialchars($class['description']); ?></span>
                                </div>
                                
                                <div class="slots-info">
                                    <div>
                                        <span class="slots-count <?php echo $slotClass; ?>">
                                            <?php echo $slots; ?> slots available
                                        </span>
                                        <small style="display: block; color: var(--gray); font-size: 0.85rem;">
                                            Total capacity: <?php echo $class['capacity']; ?>
                                        </small>
                                    </div>
                                    
                                    <button type="button" class="btn-sm btn-book" 
                                            data-class-id="<?php echo $class['id']; ?>"
                                            data-class-name="<?php echo htmlspecialchars($class['class_name']); ?>"
                                            data-class-time="<?php echo $date->format('F j, Y g:i A'); ?>">
                                        Book Now
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-classes">
                    <i class="fas fa-calendar-times" style="font-size: 3rem; color: var(--gray); margin-bottom: 1rem;"></i>
                    <h3>No classes available at the moment</h3>
                    <p>Check back later or contact support for more information.</p>
                    <button class="btn-primary mt-3" onclick="window.location.href='contact.php'">
                        <i class="fas fa-envelope"></i> Contact Support
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Booking Modal -->
    <div class="modal-overlay" id="bookingModal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Booking</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="bookingForm" method="POST">
                    <input type="hidden" name="class_id" id="modalClassId">
                    
                    <div class="form-group">
                        <label>Class:</label>
                        <p id="modalClassName" style="color: var(--dark-color); font-weight: 600;"></p>
                    </div>
                    
                    <div class="form-group">
                        <label>Time:</label>
                        <p id="modalClassTime" style="color: var(--dark-color);"></p>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Special Notes (Optional):</label>
                        <textarea name="notes" id="notes" class="form-control textarea-control" 
                                  placeholder="Any special requirements or notes for the trainer..."></textarea>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                        <button type="submit" name="book_class" class="btn-primary">
                            <i class="fas fa-check"></i> Confirm Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const filterType = document.getElementById('filterType');
            const filterDate = document.getElementById('filterDate');
            const filterTime = document.getElementById('filterTime');
            const resetFilters = document.getElementById('resetFilters');
            const searchInput = document.getElementById('searchClasses');
            const classCards = document.querySelectorAll('.class-card');
            
            function filterClasses() {
                const typeValue = filterType.value;
                const dateValue = filterDate.value;
                const timeValue = filterTime.value;
                const searchValue = searchInput.value.toLowerCase();
                const today = new Date();
                
                classCards.forEach(card => {
                    const classType = card.getAttribute('data-type');
                    const classDate = new Date(card.getAttribute('data-date'));
                    const classTime = card.getAttribute('data-time');
                    const className = card.querySelector('h3').textContent.toLowerCase();
                    const trainerName = card.querySelector('.class-meta span:first-child').textContent.toLowerCase();
                    
                    let show = true;
                    
                    // Type filter
                    if(typeValue !== 'all' && classType !== typeValue) {
                        show = false;
                    }
                    
                    // Date filter
                    if(dateValue !== 'all') {
                        const diffTime = classDate - today;
                        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                        
                        switch(dateValue) {
                            case 'today':
                                if(diffDays !== 0) show = false;
                                break;
                            case 'week':
                                if(diffDays < 0 || diffDays > 7) show = false;
                                break;
                            case 'month':
                                if(diffDays < 0 || diffDays > 30) show = false;
                                break;
                        }
                    }
                    
                    // Time filter
                    if(timeValue !== 'all') {
                        const hour = parseInt(classTime.split(':')[0]);
                        
                        switch(timeValue) {
                            case 'morning':
                                if(hour < 5 || hour >= 12) show = false;
                                break;
                            case 'afternoon':
                                if(hour < 12 || hour >= 17) show = false;
                                break;
                            case 'evening':
                                if(hour < 17 || hour >= 21) show = false;
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
            
            filterType.addEventListener('change', filterClasses);
            filterDate.addEventListener('change', filterClasses);
            filterTime.addEventListener('change', filterClasses);
            resetFilters.addEventListener('click', function() {
                filterType.value = 'all';
                filterDate.value = 'all';
                filterTime.value = 'all';
                searchInput.value = '';
                filterClasses();
            });
            searchInput.addEventListener('input', filterClasses);
            
            // Booking modal
            const bookButtons = document.querySelectorAll('.btn-book');
            const bookingModal = document.getElementById('bookingModal');
            const modalClassId = document.getElementById('modalClassId');
            const modalClassName = document.getElementById('modalClassName');
            const modalClassTime = document.getElementById('modalClassTime');
            
            bookButtons.forEach(button => {
                button.addEventListener('click', function() {
                    modalClassId.value = this.getAttribute('data-class-id');
                    modalClassName.textContent = this.getAttribute('data-class-name');
                    modalClassTime.textContent = this.getAttribute('data-class-time');
                    bookingModal.style.display = 'flex';
                });
            });
        });
        
        function closeModal() {
            document.getElementById('bookingModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('bookingModal');
            if(event.target === modal) {
                closeModal();
            }
        });
    </script>
    
    <style>
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
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
    </style>
</body>
</html>