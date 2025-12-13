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

// Get members with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search and filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$membershipPlan = isset($_GET['plan']) ? $_GET['plan'] : '';

$whereClauses = ["u.user_type = 'member'"];
$params = [];

if($search) {
    $whereClauses[] = "(u.full_name LIKE ? OR u.email LIKE ? OR gm.MembershipPlan LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if($membershipPlan) {
    $whereClauses[] = "gm.MembershipPlan = ?";
    $params[] = $membershipPlan;
}

$whereSQL = implode(' AND ', $whereClauses);

try {
    // Count total members
    $countSQL = "SELECT COUNT(*) FROM users u LEFT JOIN gym_members gm ON u.email = gm.Email WHERE $whereSQL";
    $stmt = $pdo->prepare($countSQL);
    $stmt->execute($params);
    $totalMembers = $stmt->fetchColumn();

    // Get members - FIXED: Check if payments table exists
    $membersSql = "
        SELECT 
            u.*, 
            gm.*,
            (SELECT COUNT(*) FROM payments p WHERE p.user_id = u.id AND p.status = 'completed') as total_payments,
            (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.user_id = u.id AND p.status = 'completed') as total_spent
        FROM users u 
        LEFT JOIN gym_members gm ON u.email = gm.Email 
        WHERE $whereSQL 
        ORDER BY u.created_at DESC 
        LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($membersSql);
    $stmt->execute($params);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unique membership plans
    $plans = $pdo->query("SELECT DISTINCT MembershipPlan FROM gym_members WHERE MembershipPlan IS NOT NULL AND MembershipPlan != ''")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $members = [];
    $totalMembers = 0;
    $plans = [];
    error_log("Members query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Members | Admin Dashboard</title>
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
                <input type="text" placeholder="Search members..." value="<?php echo htmlspecialchars($search); ?>" id="searchInput">
            </div>
            <div class="top-bar-actions">
                <button class="btn-primary" onclick="window.location.href='admin-add-member.php'">
                    <i class="fas fa-plus"></i> Add Member
                </button>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>Manage Members</h1>
                    <p>View, edit, and manage all gym members</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3><?php echo $totalMembers; ?></h3>
                        <p>Total Members</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $page; ?></h3>
                        <p>Current Page</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" class="filters">
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Name, email, or plan" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <label>Membership Plan</label>
                    <select name="plan">
                        <option value="">All Plans</option>
                        <?php foreach($plans as $plan): ?>
                            <option value="<?php echo htmlspecialchars($plan['MembershipPlan']); ?>" <?php echo $membershipPlan == $plan['MembershipPlan'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($plan['MembershipPlan']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary">Apply Filters</button>
                <button type="button" class="btn-secondary" onclick="window.location.href='admin-members.php'">Clear</button>
            </form>

            <!-- Members Table -->
            <div class="content-card">
                <div class="card-header">
                    <h3>Members List</h3>
                    <a href="admin-export-members.php" class="btn-secondary">
                        <i class="fas fa-download"></i> Export CSV
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="members-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Membership Plan</th>
                                    <th>Join Date</th>
                                    <th>Total Spent</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($members as $member): ?>
                                    <tr>
                                        <td>#<?php echo $member['id']; ?></td>
                                        <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                                        <td><?php echo htmlspecialchars($member['MembershipPlan'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($member['JoinDate'] ?? $member['created_at'])); ?></td>
                                        <td>$<?php echo number_format($member['total_spent'] ?? 0, 2); ?></td>
                                        <td>
                                            <span class="member-status status-<?php echo ($member['status'] ?? 'active'); ?>">
                                                <?php echo ucfirst($member['status'] ?? 'active'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <button class="btn-sm" onclick="window.location.href='admin-member-view.php?id=<?php echo $member['id']; ?>'">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-sm" onclick="window.location.href='admin-edit-member.php?id=<?php echo $member['id']; ?>'">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn-sm btn-danger" onclick="confirmDelete(<?php echo $member['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if($totalMembers > $limit): ?>
                        <div class="pagination">
                            <?php
                            $totalPages = ceil($totalMembers / $limit);
                            for($i = 1; $i <= $totalPages; $i++):
                            ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&plan=<?php echo urlencode($membershipPlan); ?>" 
                                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(memberId) {
            if(confirm('Are you sure you want to delete this member?')) {
                window.location.href = 'admin-delete-member.php?id=' + memberId;
            }
        }

        // Live search
        document.getElementById('searchInput').addEventListener('keyup', function(e) {
            if(e.key === 'Enter') {
                const search = this.value;
                window.location.href = 'admin-members.php?search=' + encodeURIComponent(search);
            }
        });
    </script>
</body>
</html>