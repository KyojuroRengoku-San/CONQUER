<?php
session_start();
require_once 'config/database.php';

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

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// FIXED: Using bookings table instead of class_enrollments
$classes = $pdo->prepare("
    SELECT c.*, t.specialty as trainer_name, 
    (SELECT COUNT(*) FROM bookings b WHERE b.class_id = c.id AND b.status = 'confirmed') as enrollment_count
    FROM classes c
    JOIN trainers t ON c.trainer_id = t.id
    $whereSQL
    ORDER BY c.schedule " . ($status === 'past' ? 'DESC' : 'ASC')
);
$classes->execute($params);
$classes = $classes->fetchAll();

$totalClasses = count($classes);
$upcomingCount = $pdo->query("SELECT COUNT(*) FROM classes WHERE schedule > NOW()")->fetchColumn();
$pastCount = $pdo->query("SELECT COUNT(*) FROM classes WHERE schedule < NOW()")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes | Admin Dashboard</title>
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        .classes-table .class-type {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .type-yoga { background: #ffeaa7; color: #e17055; }
        .type-hiit { background: #fab1a0; color: #d63031; }
        .type-strength { background: #74b9ff; color: #0984e3; }
        .type-cardio { background: #fd79a8; color: #c44569; }
        .type-crossfit { background: #81ecec; color: #00cec9; }
        .type-others { background: #a29bfe; color: #6c5ce7; }
        
        .enrollment-progress {
            width: 100px;
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
        }
        .enrollment-fill {
            height: 100%;
            background: var(--primary-color);
            border-radius: 4px;
        }
        
        .status-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 10px;
        }
        .status-tab {
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            cursor: pointer;
            border: 2px solid var(--border-color);
            background: transparent;
            font-weight: 500;
        }
        .status-tab.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
    </style>
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
                <select onchange="window.location.href='?status=<?php echo $status; ?>&type='+this.value" style="margin-left: auto;">
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
                    <div class="table-container classes-table">
                        <table>
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