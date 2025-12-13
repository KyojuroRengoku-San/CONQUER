<?php
session_start();
require_once 'config/database.php';

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

// Count total members
$countSQL = "SELECT COUNT(*) FROM users u LEFT JOIN gym_members gm ON u.email = gm.Email WHERE $whereSQL";
$stmt = $pdo->prepare($countSQL);
$stmt->execute($params);
$totalMembers = $stmt->fetchColumn();

// Get members - REMOVED phone column if it doesn't exist
$sql = "SELECT u.*, gm.*, 
        (SELECT COUNT(*) FROM payments p WHERE p.user_id = u.id AND p.status = 'completed') as total_payments,
        (SELECT SUM(p.amount) FROM payments p WHERE p.user_id = u.id AND p.status = 'completed') as total_spent
        FROM users u 
        LEFT JOIN gym_members gm ON u.email = gm.Email 
        WHERE $whereSQL 
        ORDER BY u.created_at DESC 
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

// Get unique membership plans
$plans = $pdo->query("SELECT DISTINCT MembershipPlan FROM gym_members WHERE MembershipPlan IS NOT NULL")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Members | Admin Dashboard</title>
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        .members-table {
            margin-top: 1rem;
        }
        .member-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .member-actions {
            display: flex;
            gap: 0.5rem;
        }
        .filters {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            display: flex;
            gap: 1rem;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        .page-link {
            padding: 0.5rem 1rem;
            background: var(--light-color);
            border-radius: 5px;
            text-decoration: none;
            color: var(--dark-color);
        }
        .page-link.active {
            background: var(--primary-color);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Sidebar (same as dashboard) -->
    <?php include 'admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search members..." value="<?php echo htmlspecialchars($search); ?>">
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
                            <option value="<?php echo $plan['MembershipPlan']; ?>" <?php echo $membershipPlan == $plan['MembershipPlan'] ? 'selected' : ''; ?>>
                                <?php echo $plan['MembershipPlan']; ?>
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
                    <div class="table-container members-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <!-- Phone column removed -->
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
                                        <!-- Phone cell removed -->
                                        <td><?php echo htmlspecialchars($member['MembershipPlan'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($member['JoinDate'] ?? $member['created_at'])); ?></td>
                                        <td>$<?php echo number_format($member['total_spent'] ?? 0, 2); ?></td>
                                        <td>
                                            <span class="member-status <?php echo ($member['status'] ?? 'active') == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo ucfirst($member['status'] ?? 'active'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="member-actions">
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
        document.querySelector('.search-bar input').addEventListener('keyup', function(e) {
            if(e.key === 'Enter') {
                const search = this.value;
                window.location.href = 'admin-members.php?search=' + encodeURIComponent(search);
            }
        });
    </script>
</body>
</html>