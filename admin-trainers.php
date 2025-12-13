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

try {
    // Get trainers with proper error handling
    $stmt = $pdo->query("
        SELECT 
            t.*, 
            u.full_name, 
            u.email,
            COALESCE(t.specialty, t.specialization, 'General') as specialization,
            COALESCE(t.certification, t.certifications, 'Not specified') as certifications,
            COALESCE(t.years_experience, t.experience_years, 0) as experience_years,
            (SELECT COUNT(*) FROM classes c WHERE c.trainer_id = t.id) as total_classes
        FROM trainers t
        LEFT JOIN users u ON t.user_id = u.id
        ORDER BY COALESCE(u.full_name, 'ZZZ') ASC
    ");
    
    if($stmt) {
        $trainers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $trainers = [];
    }
    
    $totalTrainers = count($trainers);
    
} catch (PDOException $e) {
    // If there's an error, show empty state
    $trainers = [];
    $totalTrainers = 0;
    error_log("Trainer query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Trainers | Admin Dashboard</title>
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
                <input type="text" placeholder="Search trainers...">
            </div>
            <div class="top-bar-actions">
                <button class="btn-primary" onclick="window.location.href='admin-add-trainer.php'">
                    <i class="fas fa-plus"></i> Add Trainer
                </button>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>Manage Trainers</h1>
                    <p>Professional fitness trainers at your gym</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3><?php echo $totalTrainers; ?></h3>
                        <p>Total Trainers</p>
                    </div>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h3>Trainers Directory</h3>
                </div>
                <div class="card-body">
                    <?php if($totalTrainers > 0): ?>
                        <div class="trainer-grid">
                            <?php foreach($trainers as $trainer): ?>
                                <div class="trainer-card">
                                    <div class="trainer-header">
                                        <div class="trainer-avatar">
                                            <i class="fas fa-user-tie"></i>
                                        </div>
                                        <h3><?php echo htmlspecialchars($trainer['full_name']); ?></h3>
                                        <p><?php echo htmlspecialchars($trainer['specialization']); ?></p>
                                    </div>
                                    <div class="trainer-body">
                                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($trainer['email']); ?></p>
                                        <p><i class="fas fa-certificate"></i> <?php echo htmlspecialchars($trainer['certifications']); ?></p>
                                        <?php if(!empty($trainer['bio'])): ?>
                                            <p><i class="fas fa-info-circle"></i> <?php echo substr(htmlspecialchars($trainer['bio']), 0, 100); ?>...</p>
                                        <?php endif; ?>
                                        
                                        <div class="trainer-stats">
                                            <div class="stat-item">
                                                <div class="stat-value"><?php echo $trainer['experience_years']; ?>+</div>
                                                <small>Years</small>
                                            </div>
                                            <div class="stat-item">
                                                <div class="stat-value"><?php echo $trainer['total_classes']; ?></div>
                                                <small>Classes</small>
                                            </div>
                                            <div class="stat-item">
                                                <div class="stat-value"><?php echo $trainer['rating']; ?></div>
                                                <small>Rating</small>
                                            </div>
                                        </div>
                                        
                                        <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                                            <button class="btn-sm" onclick="window.location.href='admin-trainer-view.php?id=<?php echo $trainer['id']; ?>'">
                                                View Profile
                                            </button>
                                            <button class="btn-sm" onclick="window.location.href='admin-edit-trainer.php?id=<?php echo $trainer['id']; ?>'">
                                                Edit
                                            </button>
                                            <button class="btn-sm btn-danger" onclick="confirmDelete(<?php echo $trainer['id']; ?>)">
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="empty-state">No trainers found</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(trainerId) {
            if(confirm('Are you sure you want to remove this trainer?')) {
                window.location.href = 'admin-delete-trainer.php?id=' + trainerId;
            }
        }
    </script>
</body>
</html>