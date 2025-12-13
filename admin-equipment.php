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

// Get equipment with filters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$location = isset($_GET['location']) ? $_GET['location'] : '';

$whereClauses = [];
$params = [];

if($status) {
    $whereClauses[] = "status = ?";
    $params[] = $status;
}

if($location) {
    $whereClauses[] = "location = ?";
    $params[] = $location;
}

$whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

try {
    // Get all equipment with fallback for missing columns
    $stmt = $pdo->prepare("
        SELECT * FROM equipment
        $whereSQL
        ORDER BY 
            CASE 
                WHEN status = 'maintenance' THEN 1
                WHEN next_maintenance <= DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 2
                ELSE 3 
            END,
            next_maintenance ASC
    ");
    $stmt->execute($params);
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Stats
    $totalEquipment = $pdo->query("SELECT COUNT(*) FROM equipment")->fetchColumn();
    $needsMaintenance = $pdo->query("SELECT COUNT(*) FROM equipment WHERE status = 'maintenance' OR next_maintenance <= DATE_ADD(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $locations = $pdo->query("SELECT DISTINCT location FROM equipment WHERE location IS NOT NULL AND location != ''")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Debug: Check what columns exist in the equipment table
try {
    $columnsStmt = $pdo->query("SHOW COLUMNS FROM equipment");
    $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
    // Uncomment to debug: print_r($columns);
} catch (PDOException $e) {
    // Continue without column info
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Management | Admin Dashboard</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        /* Additional styles for equipment management */
        .equipment-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .equipment-table th {
            background: #f8f9fa;
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
        }
        
        .equipment-table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            vertical-align: top;
        }
        
        .equipment-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .equipment-table small {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        /* Equipment status badges */
        .status-operational {
            background: rgba(46, 213, 115, 0.2);
            color: #2ed573;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-maintenance {
            background: rgba(255, 165, 2, 0.2);
            color: #ffa502;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-outofservice {
            background: rgba(255, 71, 87, 0.2);
            color: #ff4757;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-active {
            background: rgba(46, 213, 115, 0.2);
            color: #2ed573;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        /* Maintenance indicators */
        .maintenance-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .indicator-good {
            background-color: #2ed573;
        }
        
        .indicator-warning {
            background-color: #ffa502;
        }
        
        .indicator-danger {
            background-color: #ff4757;
        }
        
        /* Button styles */
        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background: #e9ecef;
            color: #495057;
            transition: all 0.3s;
        }
        
        .btn-sm:hover {
            background: #dee2e6;
        }
        
        .btn-sm.btn-warning {
            background: #ffa502;
            color: white;
        }
        
        .btn-sm.btn-warning:hover {
            background: #e69500;
        }
        
        /* Filter form styles */
        .equipment-filters {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        
        .equipment-filters > div {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 200px;
        }
        
        .equipment-filters label {
            font-weight: 600;
            color: #2f3542;
            font-size: 0.9rem;
        }
        
        .equipment-filters select {
            padding: 0.75rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .equipment-filters select:focus {
            outline: none;
            border-color: #ff4757;
            box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.1);
        }
        
        /* Status color adjustments */
        .status-active { 
            background: rgba(46, 213, 115, 0.2); 
            color: #2ed573; 
        }
        .status-inactive { 
            background: rgba(255, 71, 87, 0.2); 
            color: #ff4757; 
        }
    </style>
</head>
<body>
    <?php include 'admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search equipment..." id="searchInput">
            </div>
            <div class="top-bar-actions">
                <button class="btn-primary" onclick="window.location.href='admin-add-equipment.php'">
                    <i class="fas fa-plus"></i> Add Equipment
                </button>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>Equipment Management</h1>
                    <p>Track and maintain gym equipment</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3><?php echo $needsMaintenance; ?></h3>
                        <p>Need Maintenance</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $totalEquipment; ?></h3>
                        <p>Total Items</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" class="equipment-filters">
                <div>
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="operational" <?php echo $status === 'operational' ? 'selected' : ''; ?>>Operational</option>
                        <option value="maintenance" <?php echo $status === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                        <option value="outofservice" <?php echo $status === 'outofservice' ? 'selected' : ''; ?>>Out of Service</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    </select>
                </div>
                <div>
                    <label>Location</label>
                    <select name="location">
                        <option value="">All Locations</option>
                        <?php foreach($locations as $loc): ?>
                            <option value="<?php echo htmlspecialchars($loc['location']); ?>" <?php echo $location === $loc['location'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($loc['location']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary">Filter</button>
                <button type="button" class="btn-secondary" onclick="window.location.href='admin-equipment.php'">Clear</button>
            </form>

            <!-- Equipment Table -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Equipment Inventory</h3>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="equipment-table">
                            <thead>
                                <tr>
                                    <th>Equipment</th>
                                    <th>Brand/Model</th>
                                    <th>Location</th>
                                    <th>Purchase Date</th>
                                    <th>Last Maintenance</th>
                                    <th>Next Maintenance</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($equipment as $item): 
                                    // Calculate days until maintenance with null check
                                    $daysUntilMaintenance = 'N/A';
                                    $indicatorClass = 'indicator-good';
                                    
                                    if(!empty($item['next_maintenance']) && $item['next_maintenance'] != '0000-00-00') {
                                        $daysUntilMaintenance = floor((strtotime($item['next_maintenance']) - time()) / (60 * 60 * 24));
                                        
                                        if($item['status'] === 'maintenance' || $item['status'] === 'outofservice') {
                                            $indicatorClass = 'indicator-danger';
                                        } elseif($daysUntilMaintenance <= 7) {
                                            $indicatorClass = 'indicator-warning';
                                        } else {
                                            $indicatorClass = 'indicator-good';
                                        }
                                    }
                                    
                                    // Safely access array keys with isset checks
                                    $serialNumber = isset($item['serial_number']) && !empty($item['serial_number']) ? htmlspecialchars($item['serial_number']) : 'N/A';
                                    $model = isset($item['model']) && !empty($item['model']) ? htmlspecialchars($item['model']) : '';
                                    $brand = isset($item['brand']) ? htmlspecialchars($item['brand']) : 'Unknown';
                                    $locationName = isset($item['location']) ? htmlspecialchars($item['location']) : 'Unknown';
                                    $purchaseDate = isset($item['purchase_date']) && $item['purchase_date'] != '0000-00-00' ? date('M j, Y', strtotime($item['purchase_date'])) : 'Unknown';
                                    $lastMaintenance = isset($item['last_maintenance']) && $item['last_maintenance'] != '0000-00-00' ? date('M j, Y', strtotime($item['last_maintenance'])) : 'Never';
                                    $nextMaintenance = isset($item['next_maintenance']) && $item['next_maintenance'] != '0000-00-00' ? date('M j, Y', strtotime($item['next_maintenance'])) : 'Not scheduled';
                                    $status = isset($item['status']) ? htmlspecialchars($item['status']) : 'Unknown';
                                    
                                    // Format days text
                                    if($daysUntilMaintenance === 'N/A') {
                                        $daysText = '';
                                    } elseif($daysUntilMaintenance > 0) {
                                        $daysText = "in $daysUntilMaintenance days";
                                    } elseif($daysUntilMaintenance === 0) {
                                        $daysText = "Due today";
                                    } else {
                                        $daysText = "Overdue";
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['equipment_name']); ?></strong><br>
                                            <?php if($serialNumber !== 'N/A'): ?>
                                                <small>Serial: <?php echo $serialNumber; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $brand; ?>
                                            <?php if($model): ?>
                                                <br><small><?php echo $model; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $locationName; ?></td>
                                        <td><?php echo $purchaseDate; ?></td>
                                        <td><?php echo $lastMaintenance; ?></td>
                                        <td>
                                            <?php if($nextMaintenance !== 'Not scheduled'): ?>
                                                <span class="maintenance-indicator <?php echo $indicatorClass; ?>"></span>
                                                <?php echo $nextMaintenance; ?><br>
                                                <?php if($daysText): ?>
                                                    <small><?php echo $daysText; ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php echo $nextMaintenance; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-<?php echo strtolower($status); ?>">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <button class="btn-sm" onclick="window.location.href='admin-equipment-view.php?id=<?php echo $item['id']; ?>'">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-sm" onclick="window.location.href='admin-edit-equipment.php?id=<?php echo $item['id']; ?>'">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if($status !== 'maintenance'): ?>
                                                    <button class="btn-sm btn-warning" onclick="markForMaintenance(<?php echo $item['id']; ?>)">
                                                        <i class="fas fa-tools"></i>
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

    <script>
        function markForMaintenance(equipmentId) {
            if(confirm('Mark this equipment for maintenance?')) {
                window.location.href = 'admin-mark-maintenance.php?id=' + equipmentId;
            }
        }
        
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.equipment-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if(text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>