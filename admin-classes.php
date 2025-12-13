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

// Get classes with filters
$type = isset($_GET['type']) ? $_GET['type'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'upcoming';

$whereClauses = [];
$params = [];

if($type) {
    $whereClauses[] = "c.class_type = ?";
    $params[] = $type;
}

if($status === 'upcoming') {
    $whereClauses[] = "c.schedule > NOW()";
} elseif($status === 'past') {
    $whereClauses[] = "c.schedule < NOW()";
}

$whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

try {
    // Get classes with proper table joins
    $sql = "
        SELECT 
            c.*, 
            COALESCE(u.full_name, 'Unknown Trainer') as trainer_name,
            (SELECT COUNT(*) FROM bookings b WHERE b.class_id = c.id AND b.status = 'confirmed') as enrollment_count
        FROM classes c
        LEFT JOIN trainers t ON c.trainer_id = t.id
        LEFT JOIN users u ON t.user_id = u.id
        $whereSQL
        ORDER BY c.schedule " . ($status === 'past' ? 'DESC' : 'ASC');
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalClasses = count($classes);
    $upcomingCount = $pdo->query("SELECT COUNT(*) FROM classes WHERE schedule > NOW()")->fetchColumn();
    $pastCount = $pdo->query("SELECT COUNT(*) FROM classes WHERE schedule < NOW()")->fetchColumn();
    
} catch (PDOException $e) {
    $classes = [];
    $totalClasses = 0;
    $upcomingCount = 0;
    $pastCount = 0;
    error_log("Classes query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes | Admin Dashboard</title>
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
                <input type="text" placeholder="Search classes...">
            </div>
            <div class="top-bar-actions">
                <button class="btn-primary" onclick="window.location.href='admin-add-class.php'">
                    <i class="fas fa-plus"></i> Schedule Class
                </button>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>Manage Classes</h1>
                    <p>Schedule and manage fitness classes</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3><?php echo $upcomingCount; ?></h3>
                        <p>Upcoming</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $pastCount; ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
            </div>

            <!-- Status Tabs -->
            <div class="status-tabs">
                <button class="status-tab <?php echo $status === 'upcoming' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?status=upcoming&type=<?php echo $type; ?>'">
                    Upcoming Classes (<?php echo $upcomingCount; ?>)
                </button>
                <button class="status-tab <?php echo $status === 'past' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?status=past&type=<?php echo $type; ?>'">
                    Past Classes (<?php echo $pastCount; ?>)
                </button>
                <select onchange="window.location.href='?status=<?php echo $status; ?>&type='+this.value" style="margin-left: auto; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-family: inherit;">
                    <option value="">All Types</option>
                    <option value="yoga" <?php echo $type === 'yoga' ? 'selected' : ''; ?>>Yoga</option>
                    <option value="hiit" <?php echo $type === 'hiit' ? 'selected' : ''; ?>>HIIT</option>
                    <option value="strength" <?php echo $type === 'strength' ? 'selected' : ''; ?>>Strength</option>
                    <option value="cardio" <?php echo $type === 'cardio' ? 'selected' : ''; ?>>Cardio</option>
                    <option value="crossfit" <?php echo $type === 'crossfit' ? 'selected' : ''; ?>>CrossFit</option>
                    <option value="others" <?php echo $type === 'others' ? 'selected' : ''; ?>>Others</option>
                </select>
            </div>

            <!-- Classes Table -->
            <div class="content-card">
                <div class="card-header">
                    <h3><?php echo $status === 'upcoming' ? 'Upcoming' : 'Past'; ?> Classes</h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="classes-table">
                            <thead>
                                <tr>
                                    <th>Class Name</th>
                                    <th>Type</th>
                                    <th>Trainer</th>
                                    <th>Schedule</th>
                                    <th>Duration</th>
                                    <th>Enrollment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($classes as $class): 
                                    $enrollmentPercent = ($class['enrollment_count'] / $class['max_capacity']) * 100;
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($class['class_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($class['description'] ?? ''); ?></small>
                                        </td>
                                        <td>
                                            <span class="class-type type-<?php echo $class['class_type']; ?>">
                                                <?php echo ucfirst($class['class_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($class['trainer_name']); ?></td>
                                        <td>
                                            <strong><?php echo date('M j, Y', strtotime($class['schedule'])); ?></strong><br>
                                            <small><?php echo date('g:i A', strtotime($class['schedule'])); ?></small>
                                        </td>
                                        <td><?php echo $class['duration_minutes']; ?> mins</td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <div class="enrollment-progress">
                                                    <div class="enrollment-fill" style="width: <?php echo min($enrollmentPercent, 100); ?>%"></div>
                                                </div>
                                                <span><?php echo $class['enrollment_count']; ?>/<?php echo $class['max_capacity']; ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <button class="btn-sm" onclick="window.location.href='admin-class-view.php?id=<?php echo $class['id']; ?>'">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-sm" onclick="window.location.href='admin-edit-class.php?id=<?php echo $class['id']; ?>'">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if($status === 'upcoming'): ?>
                                                    <button class="btn-sm btn-success" onclick="window.location.href='admin-class-bookings.php?id=<?php echo $class['id']; ?>'">
                                                        <i class="fas fa-users"></i>
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
</body>
</html>