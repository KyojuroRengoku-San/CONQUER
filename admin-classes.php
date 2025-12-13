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

// If no classes in database, create sample data
try {
    $classCount = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    
    if($classCount == 0) {
        // Insert sample classes if none exist
        $sampleClasses = [
            [
                'class_name' => 'Morning Yoga Flow',
                'class_type' => 'yoga',
                'trainer_name' => 'Sarah Chen',
                'schedule' => date('Y-m-d H:i:s', strtotime('+1 day 8:00 AM')),
                'duration_minutes' => 60,
                'max_capacity' => 20,
                'enrollment_count' => 15,
                'description' => 'Gentle morning yoga to start your day'
            ],
            [
                'class_name' => 'HIIT Blast',
                'class_type' => 'hiit',
                'trainer_name' => 'Alex Morgan',
                'schedule' => date('Y-m-d H:i:s', strtotime('+2 days 6:00 PM')),
                'duration_minutes' => 45,
                'max_capacity' => 15,
                'enrollment_count' => 12,
                'description' => 'High intensity interval training'
            ],
            [
                'class_name' => 'Strength Training',
                'class_type' => 'strength',
                'trainer_name' => 'Marcus Johnson',
                'schedule' => date('Y-m-d H:i:s', strtotime('+3 days 7:00 AM')),
                'duration_minutes' => 75,
                'max_capacity' => 12,
                'enrollment_count' => 10,
                'description' => 'Build muscle and strength'
            ]
        ];
        
        // Try to insert sample data
        foreach($sampleClasses as $index => $class) {
            $insertStmt = $pdo->prepare("
                INSERT INTO classes (class_name, class_type, schedule, duration_minutes, max_capacity, description, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $insertStmt->execute([
                $class['class_name'],
                $class['class_type'],
                $class['schedule'],
                $class['duration_minutes'],
                $class['max_capacity'],
                $class['description']
            ]);
        }
    }
    
    // Get classes with proper table joins
    $sql = "
        SELECT 
            c.*, 
            COALESCE(u.full_name, t.full_name, 'Unknown Trainer') as trainer_name,
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
    // Use sample data if database fails
    $classes = $sampleClasses ?? [];
    $totalClasses = count($classes);
    $upcomingCount = $totalClasses;
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
    
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        /* Additional styles for classes management */
        .classes-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .classes-table th {
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
        
        .classes-table td {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid #e9ecef;
            vertical-align: top;
            color: #495057;
        }
        
        .classes-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .classes-table small {
            font-size: 0.85rem;
            color: #6c757d;
            line-height: 1.4;
        }
        
        /* Status tabs */
        .status-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .status-tab {
            padding: 0.75rem 1.5rem;
            border: none;
            background: #f8f9fa;
            border-radius: 8px;
            font-family: inherit;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
            color: #495057;
            border: 1px solid transparent;
        }
        
        .status-tab:hover {
            background: #e9ecef;
            transform: translateY(-1px);
        }
        
        .status-tab.active {
            background: #ff4757;
            color: white;
            border-color: #ff4757;
            box-shadow: 0 4px 12px rgba(255, 71, 87, 0.2);
        }
        
        /* Class type badges */
        .class-type {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
            min-width: 80px;
        }
        
        .type-yoga { 
            background: rgba(155, 89, 182, 0.15); 
            color: #9b59b6; 
            border: 1px solid rgba(155, 89, 182, 0.3);
        }
        
        .type-hiit { 
            background: rgba(231, 76, 60, 0.15); 
            color: #e74c3c; 
            border: 1px solid rgba(231, 76, 60, 0.3);
        }
        
        .type-strength { 
            background: rgba(52, 152, 219, 0.15); 
            color: #3498db; 
            border: 1px solid rgba(52, 152, 219, 0.3);
        }
        
        .type-cardio { 
            background: rgba(46, 204, 113, 0.15); 
            color: #2ecc71; 
            border: 1px solid rgba(46, 204, 113, 0.3);
        }
        
        .type-crossfit { 
            background: rgba(81, 236, 236, 0.15); 
            color: #00cec9; 
            border: 1px solid rgba(81, 236, 236, 0.3);
        }
        
        .type-others { 
            background: rgba(162, 155, 254, 0.15); 
            color: #6c5ce7; 
            border: 1px solid rgba(162, 155, 254, 0.3);
        }
        
        /* Enrollment progress */
        .enrollment-progress {
            width: 80px;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .enrollment-fill {
            height: 100%;
            background: linear-gradient(90deg, #2ed573 0%, #1dd1a1 100%);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .enrollment-fill.warning {
            background: linear-gradient(90deg, #ffa502 0%, #e69500 100%);
        }
        
        .enrollment-fill.danger {
            background: linear-gradient(90deg, #ff4757 0%, #ff2e43 100%);
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
        
        /* Filter select */
        .status-tabs select {
            padding: 0.75rem 1rem;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            color: #495057;
            margin-left: auto;
        }
        
        .status-tabs select:focus {
            outline: none;
            border-color: #ff4757;
            box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.1);
        }
        
        /* Schedule cell styling */
        .schedule-cell {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .schedule-date {
            font-weight: 600;
            color: #2f3542;
            font-size: 0.95rem;
        }
        
        .schedule-time {
            color: #6c757d;
            font-size: 0.85rem;
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
            .classes-table {
                min-width: 800px;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            .status-tabs {
                flex-direction: column;
                align-items: stretch;
            }
            
            .status-tabs select {
                margin-left: 0;
                width: 100%;
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
                <input type="text" placeholder="Search classes by name, type..." id="searchInput">
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
                    <i class="fas fa-calendar-alt"></i> Upcoming Classes (<?php echo $upcomingCount; ?>)
                </button>
                <button class="status-tab <?php echo $status === 'past' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?status=past&type=<?php echo $type; ?>'">
                    <i class="fas fa-history"></i> Past Classes (<?php echo $pastCount; ?>)
                </button>
                <select onchange="window.location.href='?status=<?php echo $status; ?>&type='+this.value">
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
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <span style="font-size: 0.9rem; color: #6c757d;">
                            <i class="fas fa-filter"></i> Filtered by: 
                            <strong><?php echo $type ? ucfirst($type) : 'All Types'; ?></strong>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if($totalClasses > 0): ?>
                        <div class="table-container">
                            <table class="classes-table" id="classesTable">
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
                                        $enrollmentCount = $class['enrollment_count'] ?? 0;
                                        $maxCapacity = $class['max_capacity'] ?? 20;
                                        $enrollmentPercent = ($enrollmentCount / $maxCapacity) * 100;
                                        
                                        // Determine progress bar color
                                        $progressClass = '';
                                        if($enrollmentPercent >= 90) {
                                            $progressClass = 'danger';
                                        } elseif($enrollmentPercent >= 75) {
                                            $progressClass = 'warning';
                                        }
                                    ?>
                                        <tr data-class-name="<?php echo strtolower(htmlspecialchars($class['class_name'])); ?>" 
                                            data-class-type="<?php echo htmlspecialchars($class['class_type']); ?>"
                                            data-trainer-name="<?php echo strtolower(htmlspecialchars($class['trainer_name'])); ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($class['class_name']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($class['description'] ?? 'No description'); ?></small>
                                            </td>
                                            <td>
                                                <span class="class-type type-<?php echo $class['class_type']; ?>">
                                                    <?php echo ucfirst($class['class_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($class['trainer_name']); ?></td>
                                            <td>
                                                <div class="schedule-cell">
                                                    <span class="schedule-date"><?php echo date('M j, Y', strtotime($class['schedule'])); ?></span>
                                                    <span class="schedule-time"><?php echo date('g:i A', strtotime($class['schedule'])); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: #2f3542;"><?php echo $class['duration_minutes'] ?? 60; ?> mins</span>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div class="enrollment-progress">
                                                        <div class="enrollment-fill <?php echo $progressClass; ?>" 
                                                             style="width: <?php echo min($enrollmentPercent, 100); ?>%"></div>
                                                    </div>
                                                    <span style="font-weight: 600; font-size: 0.9rem; min-width: 50px;">
                                                        <?php echo $enrollmentCount; ?>/<?php echo $maxCapacity; ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <button class="btn-sm" onclick="window.location.href='admin-class-view.php?id=<?php echo $class['id'] ?? ''; ?>'">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn-sm" onclick="window.location.href='admin-edit-class.php?id=<?php echo $class['id'] ?? ''; ?>'">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if($status === 'upcoming'): ?>
                                                        <button class="btn-sm btn-success" onclick="window.location.href='admin-class-bookings.php?id=<?php echo $class['id'] ?? ''; ?>'">
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
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h3 style="color: #495057; margin: 1rem 0 0.5rem;">No Classes Found</h3>
                            <p style="margin-bottom: 2rem;">No <?php echo $status; ?> classes scheduled.</p>
                            <button class="btn-primary" onclick="window.location.href='admin-add-class.php'">
                                <i class="fas fa-plus"></i> Schedule Your First Class
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#classesTable tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const className = row.getAttribute('data-class-name');
                const classType = row.getAttribute('data-class-type');
                const trainerName = row.getAttribute('data-trainer-name');
                const rowText = row.textContent.toLowerCase();
                
                if (className.includes(searchTerm) || 
                    classType.includes(searchTerm) || 
                    trainerName.includes(searchTerm) ||
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
                        <h3 style="color: #495057; margin: 1rem 0 0.5rem;">No Matching Classes</h3>
                        <p>No classes found matching "${searchTerm}"</p>
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
    </script>
</body>
</html>