<?php
session_start();
require_once 'config/database.php';

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

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$equipment = $pdo->prepare("
    SELECT * FROM equipment
    $whereSQL
    ORDER BY 
        CASE WHEN status = 'maintenance' THEN 1
             WHEN next_maintenance <= DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 2
             ELSE 3 END,
        next_maintenance ASC
");
$equipment->execute($params);
$equipment = $equipment->fetchAll();

// Stats
$totalEquipment = $pdo->query("SELECT COUNT(*) FROM equipment")->fetchColumn();
$needsMaintenance = $pdo->query("SELECT COUNT(*) FROM equipment WHERE status = 'maintenance' OR next_maintenance <= DATE_ADD(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$locations = $pdo->query("SELECT DISTINCT location FROM equipment WHERE location IS NOT NULL")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Management | Admin Dashboard</title>
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        .equipment-table .equipment-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status-operational { background: #d4edda; color: #155724; }
        .status-maintenance { background: #fff3cd; color: #856404; }
        .status-outofservice { background: #f8d7da; color: #721c24; }
        
        .maintenance-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }
        .indicator-good { background: #2ed573; }
        .indicator-warning { background: #ffa502; }
        .indicator-danger { background: #ff4757; }
        
        .equipment-filters {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            margin-bottom: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <?php include 'admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search equipment...">
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
                    </select>
                </div>
                <div>
                    <label>Location</label>
                    <select name="location">
                        <option value="">All Locations</option>
                        <?php foreach($locations as $loc): ?>
                            <option value="<?php echo $loc['location']; ?>" <?php echo $location === $loc['location'] ? 'selected' : ''; ?>>
                                <?php echo $loc['location']; ?>
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
                    <div class="table-container equipment-table">
                        <table>
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
                                    $daysUntilMaintenance = floor((strtotime($item['next_maintenance']) - time()) / (60 * 60 * 24));
                                    $indicatorClass = '';
                                    if($item['status'] === 'maintenance' || $item['status'] === 'outofservice') {
                                        $indicatorClass = 'indicator-danger';
                                    } elseif($daysUntilMaintenance <= 7) {
                                        $indicatorClass = 'indicator-warning';
                                    } else {
                                        $indicatorClass = 'indicator-good';
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['equipment_name']); ?></strong><br>
                                            <small>Serial: <?php echo htmlspecialchars($item['serial_number']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['brand']); ?> <?php echo htmlspecialchars($item['model']); ?></td>
                                        <td><?php echo htmlspecialchars($item['location']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($item['purchase_date'])); ?></td>
                                        <td><?php echo $item['last_maintenance'] ? date('M j, Y', strtotime($item['last_maintenance'])) : 'Never'; ?></td>
                                        <td>
                                            <span class="maintenance-indicator <?php echo $indicatorClass; ?>"></span>
                                            <?php echo date('M j, Y', strtotime($item['next_maintenance'])); ?><br>
                                            <small><?php echo $daysUntilMaintenance > 0 ? "in $daysUntilMaintenance days" : "Overdue"; ?></small>
                                        </td>
                                        <td>
                                            <span class="equipment-status status-<?php echo $item['status']; ?>">
                                                <?php echo ucfirst($item['status']); ?>
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
                                                <?php if($item['status'] !== 'maintenance'): ?>
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
    </script>
</body>
</html>