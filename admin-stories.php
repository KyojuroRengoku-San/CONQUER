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

// Handle bulk actions
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['story_ids'])) {
    $action = $_POST['action'];
    $storyIds = $_POST['story_ids'];
    $adminId = $_SESSION['user_id'];
    
    try {
        switch($action) {
            case 'approve':
                $stmt = $pdo->prepare("UPDATE success_stories SET approved = 1, approved_by = ?, approved_date = NOW(), rejected_reason = NULL WHERE id IN (" . implode(',', $storyIds) . ")");
                $stmt->execute([$adminId]);
                $_SESSION['admin_message'] = count($storyIds) . ' story(s) approved successfully';
                break;
                
            case 'reject':
                $reason = $_POST['reject_reason'] ?? 'Story does not meet our guidelines.';
                $stmt = $pdo->prepare("UPDATE success_stories SET approved = 0, rejected_reason = ?, rejection_date = NOW() WHERE id IN (" . implode(',', $storyIds) . ")");
                $stmt->execute([$reason]);
                $_SESSION['admin_message'] = count($storyIds) . ' story(s) rejected';
                break;
                
            case 'feature':
                $stmt = $pdo->prepare("UPDATE success_stories SET is_featured = 1, featured_date = NOW() WHERE id IN (" . implode(',', $storyIds) . ") AND approved = 1");
                $stmt->execute();
                $_SESSION['admin_message'] = count($storyIds) . ' story(s) featured';
                break;
                
            case 'unfeature':
                $stmt = $pdo->prepare("UPDATE success_stories SET is_featured = 0 WHERE id IN (" . implode(',', $storyIds) . ")");
                $stmt->execute();
                $_SESSION['admin_message'] = count($storyIds) . ' story(s) unfeatured';
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM success_stories WHERE id IN (" . implode(',', $storyIds) . ")");
                $stmt->execute();
                $_SESSION['admin_message'] = count($storyIds) . ' story(s) deleted';
                break;
        }
        
        header('Location: admin-stories.php');
        exit();
        
    } catch(PDOException $e) {
        error_log("Bulk action error: " . $e->getMessage());
        $_SESSION['admin_message'] = 'Error processing bulk action';
    }
}

// Initialize variables
$stories = [];
$pendingCount = 0;
$approvedCount = 0;
$featuredCount = 0;
$totalStories = 0;
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'pending';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

try {
    // Build query with filters
    $whereClauses = [];
    $params = [];
    
    switch($status) {
        case 'pending':
            $whereClauses[] = "ss.approved = 0 AND ss.rejected_reason IS NULL";
            break;
        case 'approved':
            $whereClauses[] = "ss.approved = 1 AND ss.is_featured = 0";
            break;
        case 'featured':
            $whereClauses[] = "ss.approved = 1 AND ss.is_featured = 1";
            break;
        case 'rejected':
            $whereClauses[] = "ss.rejected_reason IS NOT NULL";
            break;
    }
    
    if(!empty($searchTerm)) {
        $whereClauses[] = "(ss.title LIKE :search OR ss.story_text LIKE :search OR u.full_name LIKE :search OR u.email LIKE :search)";
        $params[':search'] = "%$searchTerm%";
    }
    
    $whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
    
    // Get stories with pagination
    $sql = "
        SELECT 
            ss.id,
            ss.title,
            ss.story_text,
            COALESCE(ss.weight_loss, 0) as weight_loss,
            COALESCE(ss.months_taken, 6) as months_taken,
            ss.approved,
            ss.is_featured,
            ss.before_image,
            ss.after_image,
            ss.created_at,
            ss.rejected_reason,
            ss.admin_notes,
            u.full_name,
            u.email,
            u.profile_image,
            admin.full_name as approved_by_name
        FROM success_stories ss
        JOIN users u ON ss.user_id = u.id
        LEFT JOIN users admin ON ss.approved_by = admin.id
        $whereClause
        ORDER BY 
            CASE 
                WHEN ss.approved = 0 AND ss.rejected_reason IS NULL THEN 1
                WHEN ss.rejected_reason IS NOT NULL THEN 2
                WHEN ss.is_featured = 1 THEN 3
                ELSE 4
            END,
            ss.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($sql);
    
    foreach($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total counts
    $countSql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN ss.approved = 0 AND ss.rejected_reason IS NULL THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN ss.approved = 1 AND ss.is_featured = 0 THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN ss.approved = 1 AND ss.is_featured = 1 THEN 1 ELSE 0 END) as featured,
            SUM(CASE WHEN ss.rejected_reason IS NOT NULL THEN 1 ELSE 0 END) as rejected
        FROM success_stories ss
        JOIN users u ON ss.user_id = u.id
        " . ($whereClause ? $whereClause : '');
    
    $countStmt = $pdo->prepare($countSql);
    foreach($params as $key => $value) {
        if($key !== ':limit' && $key !== ':offset') {
            $countStmt->bindValue($key, $value);
        }
    }
    $countStmt->execute();
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);
    
    $totalStories = $counts['total'] ?? 0;
    $pendingCount = $counts['pending'] ?? 0;
    $approvedCount = $counts['approved'] ?? 0;
    $featuredCount = $counts['featured'] ?? 0;
    $rejectedCount = $counts['rejected'] ?? 0;
    
    $totalPages = ceil($totalStories / $limit);
    
} catch (PDOException $e) {
    error_log("Stories error: " . $e->getMessage());
    $stories = [];
}

// Handle status messages
if(isset($_SESSION['admin_message'])) {
    $adminMessage = $_SESSION['admin_message'];
    unset($_SESSION['admin_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Success Stories | Admin Dashboard</title>
    
    <!-- Fonts & Icons (same as before) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        /* ... keep existing styles, add these ... */
        
        /* Bulk actions */
        .bulk-actions {
            display: flex;
            gap: 0.5rem;
            margin: 1rem 0;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .bulk-select {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-right: 1rem;
        }
        
        .bulk-select input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .bulk-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .bulk-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }
        
        .bulk-btn.approve {
            background: rgba(46, 213, 115, 0.1);
            color: #2ED573;
            border: 1px solid rgba(46, 213, 115, 0.3);
        }
        
        .bulk-btn.reject {
            background: rgba(255, 71, 87, 0.1);
            color: #FF4757;
            border: 1px solid rgba(255, 71, 87, 0.3);
        }
        
        .bulk-btn.feature {
            background: rgba(255, 215, 0, 0.1);
            color: #FFD700;
            border: 1px solid rgba(255, 215, 0, 0.3);
        }
        
        .bulk-btn.delete {
            background: rgba(255, 71, 87, 0.1);
            color: #FF4757;
            border: 1px solid rgba(255, 71, 87, 0.3);
        }
        
        .bulk-btn:hover {
            opacity: 0.8;
            transform: translateY(-2px);
        }
        
        /* Story checkbox */
        .story-checkbox {
            position: absolute;
            top: 1rem;
            left: 1rem;
            z-index: 10;
            width: 20px;
            height: 20px;
            cursor: pointer;
            border: 2px solid #dee2e6;
            border-radius: 4px;
            background: white;
            appearance: none;
            -webkit-appearance: none;
        }
        
        .story-checkbox:checked {
            background: #FF4757;
            border-color: #FF4757;
            position: relative;
        }
        
        .story-checkbox:checked::after {
            content: '✓';
            color: white;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 12px;
            font-weight: bold;
        }
        
        /* Admin notes */
        .admin-notes {
            background: rgba(102, 126, 234, 0.05);
            border-left: 3px solid #667eea;
            padding: 0.75rem;
            margin: 0.75rem 0;
            border-radius: 0 5px 5px 0;
        }
        
        .admin-notes h5 {
            color: #667eea;
            margin: 0 0 0.25rem 0;
            font-size: 0.85rem;
        }
        
        .admin-notes p {
            margin: 0;
            font-size: 0.8rem;
            color: #666;
        }
        
        /* Rejection info */
        .rejection-info {
            background: rgba(255, 71, 87, 0.05);
            border-left: 3px solid #FF4757;
            padding: 0.75rem;
            margin: 0.75rem 0;
            border-radius: 0 5px 5px 0;
        }
        
        .rejection-info h5 {
            color: #FF4757;
            margin: 0 0 0.25rem 0;
            font-size: 0.85rem;
        }
        
        .rejection-info p {
            margin: 0;
            font-size: 0.8rem;
            color: #666;
        }
        
        /* Action buttons with icons */
        .btn-action {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s;
            text-decoration: none;
            font-family: inherit;
            font-weight: 500;
            border: 1px solid transparent;
            flex: 1;
            background: white;
            color: #495057;
        }
        
        /* Quick actions in story card */
        .quick-actions {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: flex;
            gap: 0.25rem;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .story-card:hover .quick-actions {
            opacity: 1;
        }
        
        .quick-action-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: none;
            background: white;
            color: #495057;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .quick-action-btn:hover {
            transform: scale(1.1);
        }
        
        .quick-action-btn.approve:hover {
            background: #2ED573;
            color: white;
        }
        
        .quick-action-btn.reject:hover {
            background: #FF4757;
            color: white;
        }
        
        .quick-action-btn.feature:hover {
            background: #FFD700;
            color: white;
        }
        
        /* Stats cards */
        .stat-card.pending { border-left: 4px solid #FFA502; }
        .stat-card.approved { border-left: 4px solid #2ED573; }
        .stat-card.featured { border-left: 4px solid #FFD700; }
        .stat-card.rejected { border-left: 4px solid #FF4757; }
        
        /* Modal for reject reason */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #2f3542;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
        }
        
        .modal textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.9rem;
            resize: vertical;
            min-height: 100px;
            margin-bottom: 1rem;
        }
        
        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
    </style>
</head>
<body>
    <?php include 'admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <form method="GET" action="" style="display: inline;">
                    <input type="text" placeholder="Search stories..." name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                </form>
            </div>
            <div class="top-bar-actions">
                <?php if($pendingCount > 0): ?>
                    <div style="position: relative;">
                        <button class="btn-notification" onclick="window.location.href='?status=pending'">
                            <i class="fas fa-bell"></i>
                        </button>
                        <span class="notification-badge"><?php echo $pendingCount; ?></span>
                    </div>
                <?php endif; ?>
                <button class="btn-primary" onclick="window.location.href='admin-add-story.php'">
                    <i class="fas fa-plus"></i> Add Story
                </button>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1><i class="fas fa-trophy" style="margin-right: 10px; color: #FFD700;"></i> Success Stories Management</h1>
                    <p>Approve, reject, and feature member success stories</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3><?php echo $totalStories; ?></h3>
                        <p>Total Stories</p>
                    </div>
                </div>
            </div>

            <!-- Status Messages -->
            <?php if(isset($adminMessage)): ?>
                <div class="alert alert-success" style="margin-bottom: 1.5rem;">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $adminMessage; ?>
                </div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="quick-stats">
                <div class="stat-card pending">
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $pendingCount; ?></h3>
                        <p>Pending Review</p>
                    </div>
                </div>
                <div class="stat-card approved">
                    <div class="stat-icon approved">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $approvedCount; ?></h3>
                        <p>Approved Stories</p>
                    </div>
                </div>
                <div class="stat-card featured">
                    <div class="stat-icon featured">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $featuredCount; ?></h3>
                        <p>Featured Stories</p>
                    </div>
                </div>
                <div class="stat-card rejected">
                    <div class="stat-icon rejected">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $rejectedCount; ?></h3>
                        <p>Rejected Stories</p>
                    </div>
                </div>
            </div>

            <!-- Status Tabs -->
            <div class="status-tabs">
                <button class="status-tab <?php echo $status === 'pending' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?status=pending<?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>'">
                    <i class="fas fa-clock"></i> Pending
                    <span class="count"><?php echo $pendingCount; ?></span>
                </button>
                <button class="status-tab <?php echo $status === 'approved' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?status=approved<?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>'">
                    <i class="fas fa-check-circle"></i> Approved
                    <span class="count"><?php echo $approvedCount; ?></span>
                </button>
                <button class="status-tab <?php echo $status === 'featured' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?status=featured<?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>'">
                    <i class="fas fa-star"></i> Featured
                    <span class="count"><?php echo $featuredCount; ?></span>
                </button>
                <button class="status-tab <?php echo $status === 'rejected' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?status=rejected<?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>'">
                    <i class="fas fa-times-circle"></i> Rejected
                    <span class="count"><?php echo $rejectedCount; ?></span>
                </button>
                <button class="status-tab <?php echo $status === 'all' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?status=all<?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>'">
                    <i class="fas fa-list"></i> All
                    <span class="count"><?php echo $totalStories; ?></span>
                </button>
            </div>

            <!-- Bulk Actions Form -->
            <form id="bulkActionForm" method="POST" action="">
                <div class="bulk-actions">
                    <div class="bulk-select">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                        <label for="selectAll" style="font-weight: 500;">Select All</label>
                    </div>
                    <div class="bulk-buttons">
                        <button type="button" class="bulk-btn approve" onclick="showBulkAction('approve')">
                            <i class="fas fa-check"></i> Approve Selected
                        </button>
                        <button type="button" class="bulk-btn reject" onclick="showRejectModal('bulk')">
                            <i class="fas fa-times"></i> Reject Selected
                        </button>
                        <button type="button" class="bulk-btn feature" onclick="showBulkAction('feature')">
                            <i class="fas fa-star"></i> Feature Selected
                        </button>
                        <button type="button" class="bulk-btn delete" onclick="showBulkAction('delete')">
                            <i class="fas fa-trash"></i> Delete Selected
                        </button>
                    </div>
                </div>

                <!-- Hidden inputs for bulk actions -->
                <input type="hidden" name="action" id="bulkAction">
                <input type="hidden" name="story_ids" id="selectedStories">
                <input type="hidden" name="reject_reason" id="rejectReason">
                
                <!-- Stories Grid -->
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-filter"></i> <?php echo ucfirst($status); ?> Stories</h3>
                        <div>
                            <span style="font-size: 0.9rem; color: #6c757d; font-weight: 500;">
                                Showing <?php echo count($stories); ?> of <?php echo $totalStories; ?> story<?php echo $totalStories !== 1 ? 's' : ''; ?>
                            </span>
                            <?php if(!empty($searchTerm)): ?>
                                <span style="font-size: 0.9rem; color: #FF4757; margin-left: 1rem;">
                                    <i class="fas fa-search"></i> Searching: "<?php echo htmlspecialchars($searchTerm); ?>"
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if(count($stories) > 0): ?>
                            <div class="story-grid" id="storyGrid">
                                <?php foreach($stories as $story): 
                                    $isApproved = $story['approved'] == 1;
                                    $isFeatured = $story['is_featured'] == 1;
                                    $isRejected = !empty($story['rejected_reason']);
                                    $fullName = htmlspecialchars($story['full_name']);
                                    $email = htmlspecialchars($story['email']);
                                    $title = htmlspecialchars($story['title']);
                                    $storyText = htmlspecialchars($story['story_text']);
                                    $createdAt = date('M d, Y', strtotime($story['created_at']));
                                    $profileImage = $story['profile_image'];
                                    $initials = strtoupper(substr($fullName, 0, 2));
                                    $statusClass = $isRejected ? 'rejected' : ($isFeatured ? 'featured' : ($isApproved ? 'approved' : 'pending'));
                                ?>
                                    <div class="story-card <?php echo $statusClass; ?>" 
                                         data-story-id="<?php echo $story['id']; ?>">
                                        
                                        <input type="checkbox" 
                                               class="story-checkbox" 
                                               name="story_ids[]" 
                                               value="<?php echo $story['id']; ?>"
                                               onchange="updateSelectedCount()">
                                        
                                        <div class="quick-actions">
                                            <?php if(!$isApproved && !$isRejected): ?>
                                                <button type="button" class="quick-action-btn approve" 
                                                        onclick="approveStory(<?php echo $story['id']; ?>)"
                                                        title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="quick-action-btn reject" 
                                                        onclick="showRejectModal(<?php echo $story['id']; ?>)"
                                                        title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if($isApproved && !$isFeatured): ?>
                                                <button type="button" class="quick-action-btn feature" 
                                                        onclick="featureStory(<?php echo $story['id']; ?>)"
                                                        title="Feature">
                                                    <i class="fas fa-star"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="story-header">
                                            <div class="story-avatar">
                                                <?php if($profileImage): ?>
                                                    <img src="uploads/<?php echo htmlspecialchars($profileImage); ?>" 
                                                         alt="<?php echo $fullName; ?>"
                                                         onerror="this.parentElement.innerHTML='<div class=\"avatar-placeholder\"><?php echo $initials; ?></div>'">
                                                <?php else: ?>
                                                    <div class="avatar-placeholder"><?php echo $initials; ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="story-header-content">
                                                <h4><?php echo $fullName; ?></h4>
                                                <small><?php echo $email; ?></small>
                                                <span class="story-status status-<?php echo $statusClass; ?>">
                                                    <i class="fas fa-<?php echo $isFeatured ? 'star' : ($isApproved ? 'check-circle' : ($isRejected ? 'times-circle' : 'clock')); ?>"></i>
                                                    <?php echo $isFeatured ? 'Featured' : ($isApproved ? 'Approved' : ($isRejected ? 'Rejected' : 'Pending')); ?>
                                                </span>
                                            </div>
                                            <div class="story-date">
                                                <i class="far fa-calendar"></i> <?php echo $createdAt; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="story-body">
                                            <div class="story-meta">
                                                <?php if($story['weight_loss'] > 0): ?>
                                                    <div class="meta-item">
                                                        <i class="fas fa-weight scale weight-loss-icon"></i>
                                                        <span class="weight-loss"><?php echo number_format($story['weight_loss'], 1); ?> lbs</span>
                                                        <span style="color: #8a94a6;">lost</span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if($story['months_taken'] > 0): ?>
                                                    <div class="meta-item">
                                                        <i class="fas fa-clock duration-icon"></i>
                                                        <span class="duration"><?php echo $story['months_taken']; ?> month<?php echo $story['months_taken'] !== 1 ? 's' : ''; ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <h4><?php echo $title; ?></h4>
                                            
                                            <div class="story-content">
                                                <?php 
                                                    echo nl2br(substr($storyText, 0, 250));
                                                    if(strlen($storyText) > 250) {
                                                        echo '...';
                                                    }
                                                ?>
                                            </div>
                                            
                                            <!-- Admin Notes -->
                                            <?php if(!empty($story['admin_notes'])): ?>
                                                <div class="admin-notes">
                                                    <h5><i class="fas fa-sticky-note"></i> Admin Notes:</h5>
                                                    <p><?php echo htmlspecialchars($story['admin_notes']); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Rejection Reason -->
                                            <?php if(!empty($story['rejected_reason'])): ?>
                                                <div class="rejection-info">
                                                    <h5><i class="fas fa-exclamation-triangle"></i> Rejection Reason:</h5>
                                                    <p><?php echo htmlspecialchars($story['rejected_reason']); ?></p>
                                                    <?php if(!empty($story['rejection_date'])): ?>
                                                        <small style="color: #999;">Rejected on <?php echo date('M d, Y', strtotime($story['rejection_date'])); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Approval Info -->
                                            <?php if($isApproved && !empty($story['approved_by_name'])): ?>
                                                <div style="font-size: 0.8rem; color: #6c757d; margin-top: 1rem;">
                                                    <i class="fas fa-user-check"></i> Approved by <?php echo htmlspecialchars($story['approved_by_name']); ?>
                                                    <?php if(!empty($story['approved_date'])): ?>
                                                        on <?php echo date('M d, Y', strtotime($story['approved_date'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Featured Info -->
                                            <?php if($isFeatured): ?>
                                                <div style="font-size: 0.8rem; color: #FFD700; margin-top: 0.5rem;">
                                                    <i class="fas fa-star"></i> Featured on <?php echo isset($story['featured_date']) ? date('M d, Y', strtotime($story['featured_date'])) : 'N/A'; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if($story['before_image'] || $story['after_image']): ?>
                                                <div class="story-images">
                                                    <?php if(!empty($story['before_image'])): ?>
                                                        <div class="image-container" style="flex: 1; text-align: center;">
                                                            <div style="font-size: 0.8rem; color: #FF4757; margin-bottom: 0.5rem; font-weight: 600;">Before</div>
                                                            <img src="uploads/<?php echo htmlspecialchars($story['before_image']); ?>" 
                                                                 alt="Before" 
                                                                 class="story-img"
                                                                 onerror="this.src='https://via.placeholder.com/300x200/667eea/ffffff?text=Before'">
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if(!empty($story['after_image'])): ?>
                                                        <div class="image-container" style="flex: 1; text-align: center;">
                                                            <div style="font-size: 0.8rem; color: #2ED573; margin-bottom: 0.5rem; font-weight: 600;">After</div>
                                                            <img src="uploads/<?php echo htmlspecialchars($story['after_image']); ?>" 
                                                                 alt="After" 
                                                                 class="story-img"
                                                                 onerror="this.src='https://via.placeholder.com/300x200/2ed573/ffffff?text=After'">
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="story-actions">
                                            <button type="button" class="btn-action btn-view" onclick="window.location.href='admin-story-view.php?id=<?php echo $story['id']; ?>'">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                            <button type="button" class="btn-action btn-edit" onclick="window.location.href='admin-edit-story.php?id=<?php echo $story['id']; ?>'">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if($isFeatured): ?>
                                                <button type="button" class="btn-action btn-reject" onclick="unfeatureStory(<?php echo $story['id']; ?>)">
                                                    <i class="fas fa-star"></i> Unfeature
                                                </button>
                                            <?php elseif($isApproved): ?>
                                                <button type="button" class="btn-action btn-reject" onclick="unapproveStory(<?php echo $story['id']; ?>)">
                                                    <i class="fas fa-ban"></i> Unapprove
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Pagination (same as before) -->
                            <?php if($totalPages > 1): ?>
                                <div class="pagination">
                                    <!-- ... pagination code ... -->
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-newspaper fa-4x"></i>
                                <h3>No Stories Found</h3>
                                <p style="margin-bottom: 1.5rem;">No <?php echo $status; ?> stories to display.</p>
                                <?php if(!empty($searchTerm)): ?>
                                    <button class="btn-action" onclick="window.location.href='?status=<?php echo $status; ?>'">
                                        <i class="fas fa-times"></i> Clear Search
                                    </button>
                                <?php else: ?>
                                    <button class="btn-action" onclick="window.location.href='admin-add-story.php'">
                                        <i class="fas fa-plus"></i> Add First Story
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-times-circle" style="color: #FF4757; margin-right: 10px;"></i> Reject Story</h3>
                <button class="close-modal" onclick="closeRejectModal()">&times;</button>
            </div>
            <div id="modalContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Bulk actions
        function toggleSelectAll(checkbox) {
            const storyCheckboxes = document.querySelectorAll('.story-checkbox');
            storyCheckboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateSelectedCount();
        }
        
        function updateSelectedCount() {
            const selected = document.querySelectorAll('.story-checkbox:checked');
            const selectAll = document.getElementById('selectAll');
            const storyCheckboxes = document.querySelectorAll('.story-checkbox');
            
            // Update select all checkbox
            selectAll.checked = selected.length === storyCheckboxes.length;
            selectAll.indeterminate = selected.length > 0 && selected.length < storyCheckboxes.length;
            
            // Update bulk buttons text
            const bulkButtons = document.querySelectorAll('.bulk-btn');
            bulkButtons.forEach(btn => {
                const originalText = btn.innerHTML;
                const icon = btn.querySelector('i').outerHTML;
                const text = originalText.replace(icon, '').trim();
                btn.innerHTML = icon + ' ' + text + (selected.length > 0 ? ' (' + selected.length + ')' : '');
            });
        }
        
        function showBulkAction(action) {
            const selected = Array.from(document.querySelectorAll('.story-checkbox:checked'))
                .map(cb => cb.value);
            
            if(selected.length === 0) {
                alert('Please select at least one story.');
                return;
            }
            
            if(action === 'delete') {
                if(!confirm(`Are you sure you want to delete ${selected.length} story(s)? This action cannot be undone.`)) {
                    return;
                }
            } else if(action === 'feature') {
                if(!confirm(`Feature ${selected.length} story(s)? They will be prominently displayed on the success stories page.`)) {
                    return;
                }
            } else if(action === 'approve') {
                if(!confirm(`Approve ${selected.length} story(s)? They will be visible to all members.`)) {
                    return;
                }
            }
            
            document.getElementById('bulkAction').value = action;
            document.getElementById('selectedStories').value = selected.join(',');
            document.getElementById('bulkActionForm').submit();
        }
        
        // Individual actions
        function approveStory(storyId) {
            if(confirm('Approve this success story? It will be visible to all members.')) {
                window.location.href = 'admin-approve-story.php?id=' + storyId;
            }
        }
        
        function featureStory(storyId) {
            if(confirm('Feature this story? It will be prominently displayed on the success stories page.')) {
                window.location.href = 'admin-feature-story.php?id=' + storyId;
            }
        }
        
        function unfeatureStory(storyId) {
            if(confirm('Remove this story from featured section?')) {
                window.location.href = 'admin-unfeature-story.php?id=' + storyId;
            }
        }
        
        function unapproveStory(storyId) {
            if(confirm('Unapprove this story? It will no longer be visible to members.')) {
                window.location.href = 'admin-unapprove-story.php?id=' + storyId;
            }
        }
        
        function deleteStory(storyId) {
            if(confirm('Delete this success story? This action cannot be undone.')) {
                window.location.href = 'admin-delete-story.php?id=' + storyId;
            }
        }
        
        // Reject modal
        let currentStoryId = null;
        
        function showRejectModal(storyId) {
            currentStoryId = storyId;
            const modal = document.getElementById('rejectModal');
            const content = document.getElementById('modalContent');
            
            if(storyId === 'bulk') {
                const selected = Array.from(document.querySelectorAll('.story-checkbox:checked'))
                    .map(cb => cb.value);
                
                if(selected.length === 0) {
                    alert('Please select at least one story to reject.');
                    return;
                }
                
                content.innerHTML = `
                    <p>Rejecting ${selected.length} selected story(s). Please provide a reason:</p>
                    <textarea id="rejectReasonText" placeholder="Why are you rejecting these stories? Provide constructive feedback that can help the member improve their submission."></textarea>
                    <div class="modal-actions">
                        <button class="bulk-btn reject" onclick="submitBulkReject()">
                            <i class="fas fa-times"></i> Reject Selected
                        </button>
                        <button class="btn-action" onclick="closeRejectModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                `;
            } else {
                content.innerHTML = `
                    <p>Rejecting story by ${document.querySelector(`.story-card[data-story-id="${storyId}"] .story-header h4`).textContent}. Please provide a reason:</p>
                    <textarea id="rejectReasonText" placeholder="Why are you rejecting this story? Provide constructive feedback that can help the member improve their submission."></textarea>
                    <div class="modal-actions">
                        <button class="bulk-btn reject" onclick="submitReject()">
                            <i class="fas fa-times"></i> Reject Story
                        </button>
                        <button class="btn-action" onclick="closeRejectModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                `;
            }
            
            modal.style.display = 'flex';
        }
        
        function submitReject() {
            const reason = document.getElementById('rejectReasonText').value.trim();
            if(!reason) {
                alert('Please provide a rejection reason.');
                return;
            }
            
            window.location.href = 'admin-reject-story.php?id=' + currentStoryId + '&reason=' + encodeURIComponent(reason);
        }
        
        function submitBulkReject() {
            const reason = document.getElementById('rejectReasonText').value.trim();
            if(!reason) {
                alert('Please provide a rejection reason.');
                return;
            }
            
            const selected = Array.from(document.querySelectorAll('.story-checkbox:checked'))
                .map(cb => cb.value);
            
            document.getElementById('bulkAction').value = 'reject';
            document.getElementById('selectedStories').value = selected.join(',');
            document.getElementById('rejectReason').value = reason;
            document.getElementById('bulkActionForm').submit();
        }
        
        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
            currentStoryId = null;
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('rejectModal');
            if(event.target === modal) {
                closeRejectModal();
            }
        }
        
        // Search debounce
        let searchTimeout;
        document.querySelector('input[name="search"]')?.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedCount();
        });
    </script>
</body>
</html>