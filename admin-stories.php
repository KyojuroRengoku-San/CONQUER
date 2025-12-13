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

// Initialize variables
$stories = [];
$pendingCount = 0;
$approvedCount = 0;
$totalStories = 0;

try {
    // Get stories with filters
    $status = isset($_GET['status']) ? $_GET['status'] : 'all';

    $whereClause = '';
    $params = [];
    
    if($status === 'pending') {
        $whereClause = "WHERE COALESCE(ss.approved, 0) = 0";
    } elseif($status === 'approved') {
        $whereClause = "WHERE COALESCE(ss.approved, 0) = 1";
    }
    
    $sql = "
        SELECT 
            ss.id,
            ss.title,
            ss.story_text,
            COALESCE(ss.weight_loss, 0) as weight_loss,
            COALESCE(ss.duration_months, 6) as duration_months,
            COALESCE(ss.approved, 1) as approved,
            ss.before_image,
            ss.after_image,
            ss.created_at,
            u.full_name,
            u.email 
        FROM success_stories ss
        JOIN users u ON ss.user_id = u.id
        $whereClause
        ORDER BY ss.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get counts
    $pendingCountStmt = $pdo->query("SELECT COUNT(*) FROM success_stories WHERE COALESCE(approved, 0) = 0");
    $pendingCount = $pendingCountStmt->fetchColumn() ?: 0;
    
    $approvedCountStmt = $pdo->query("SELECT COUNT(*) FROM success_stories WHERE COALESCE(approved, 0) = 1");
    $approvedCount = $approvedCountStmt->fetchColumn() ?: 0;
    
    $totalStories = $pendingCount + $approvedCount;
    
} catch (PDOException $e) {
    error_log("Stories error: " . $e->getMessage());
    
    // Try a simpler query if the first one fails
    try {
        $sql = "SELECT * FROM success_stories LIMIT 10";
        $stmt = $pdo->query($sql);
        $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add user information if missing
        foreach($stories as &$story) {
            if(!isset($story['full_name'])) {
                $story['full_name'] = 'Unknown Member';
                $story['email'] = 'unknown@email.com';
            }
            
            // Set default values for missing fields
            $story['weight_loss'] = $story['weight_loss'] ?? 0;
            $story['duration_months'] = $story['duration_months'] ?? 6;
            $story['approved'] = $story['approved'] ?? 1;
        }
        
        $totalStories = count($stories);
        $approvedCount = $totalStories;
        $pendingCount = 0;
        
    } catch (PDOException $e2) {
        error_log("Fallback stories query also failed: " . $e2->getMessage());
        $stories = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Success Stories | Admin Dashboard</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        /* Additional styles for stories management */
        .story-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .story-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #e8e8e8;
            display: flex;
            flex-direction: column;
        }
        
        .story-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .story-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 1rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .story-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .story-header > div {
            flex: 1;
        }
        
        .story-header h4 {
            font-size: 1.2rem;
            margin-bottom: 0.25rem;
            color: #2f3542;
            font-weight: 600;
        }
        
        .story-header small {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .story-body {
            padding: 1.5rem;
            flex-grow: 1;
        }
        
        .story-meta {
            display: flex;
            gap: 1.5rem;
            margin: 0.75rem 0 1rem;
            padding: 0.75rem 0;
            border-top: 1px solid #e9ecef;
            border-bottom: 1px solid #e9ecef;
        }
        
        .story-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: #495057;
            font-weight: 500;
        }
        
        .story-meta i {
            color: #ff4757;
            width: 16px;
        }
        
        .story-body h4 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: #2f3542;
            font-weight: 700;
            line-height: 1.3;
        }
        
        .story-content {
            margin: 1rem 0;
            line-height: 1.6;
            color: #495057;
            font-size: 0.95rem;
        }
        
        .story-images {
            display: flex;
            gap: 1rem;
            margin: 1.5rem 0;
            flex-wrap: wrap;
        }
        
        .story-img {
            width: 120px;
            height: 120px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #dee2e6;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .story-img:hover {
            transform: scale(1.05);
            border-color: #667eea;
        }
        
        .story-actions {
            display: flex;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }
        
        /* Story status badges */
        .story-status {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
        }
        
        .status-pending { 
            background: rgba(255, 165, 2, 0.15);
            color: #ffa502;
            border: 1px solid rgba(255, 165, 2, 0.3);
        }
        
        .status-approved { 
            background: rgba(46, 213, 115, 0.15);
            color: #2ed573;
            border: 1px solid rgba(46, 213, 115, 0.3);
        }
        
        /* Button styles */
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
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
            flex: 1;
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
        
        .btn-sm.btn-danger {
            background: #ff4757;
            color: white;
            border-color: #ff4757;
        }
        
        .btn-sm.btn-danger:hover {
            background: #ff2e43;
            border-color: #ff2e43;
        }
        
        /* Status tabs */
        .status-tabs {
            display: flex;
            gap: 0.75rem;
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
        
        .status-tab.active i {
            color: white;
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
        @media (max-width: 992px) {
            .story-grid {
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .story-grid {
                grid-template-columns: 1fr;
            }
            
            .story-card {
                max-width: 500px;
                margin: 0 auto;
            }
            
            .status-tabs {
                flex-direction: column;
                align-items: stretch;
            }
            
            .status-tab {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .story-actions {
                flex-direction: column;
            }
            
            .story-meta {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .story-images {
                flex-direction: column;
            }
            
            .story-img {
                width: 100%;
                max-width: 250px;
                height: 200px;
                margin: 0 auto;
            }
        }
        
        /* Search bar */
        #searchInput {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        #searchInput:focus {
            outline: none;
            border-color: #ff4757;
            box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.1);
        }
        
        /* Weight loss formatting */
        .weight-loss {
            font-weight: 700;
            color: #ff4757;
        }
        
        .duration {
            font-weight: 700;
            color: #667eea;
        }
    </style>
</head>
<body>
    <?php include 'admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search stories by name, title..." id="searchInput">
            </div>
            <div class="top-bar-actions">
                <?php if($pendingCount > 0): ?>
                    <div style="position: relative;">
                        <button class="btn-notification">
                            <i class="fas fa-bell"></i>
                        </button>
                        <span class="notification-badge"><?php echo $pendingCount; ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>Success Stories</h1>
                    <p>Review and approve member success stories</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3><?php echo $pendingCount; ?></h3>
                        <p>Pending Review</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $totalStories; ?></h3>
                        <p>Total Stories</p>
                    </div>
                </div>
            </div>

            <!-- Status Tabs -->
            <div class="status-tabs">
                <button class="status-tab <?php echo $status === 'pending' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?status=pending'">
                    <i class="fas fa-clock"></i> Pending Review (<?php echo $pendingCount; ?>)
                </button>
                <button class="status-tab <?php echo $status === 'approved' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?status=approved'">
                    <i class="fas fa-check-circle"></i> Approved (<?php echo $approvedCount; ?>)
                </button>
                <button class="status-tab <?php echo $status === 'all' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?status=all'">
                    <i class="fas fa-list"></i> All Stories (<?php echo $totalStories; ?>)
                </button>
            </div>

            <!-- Stories Grid -->
            <div class="content-card">
                <div class="card-header">
                    <h3><?php echo ucfirst($status); ?> Stories</h3>
                    <span style="font-size: 0.9rem; color: #6c757d; font-weight: 500;">
                        Showing <?php echo count($stories); ?> story<?php echo count($stories) !== 1 ? 's' : ''; ?>
                    </span>
                </div>
                <div class="card-body">
                    <?php if(count($stories) > 0): ?>
                        <div class="story-grid" id="storyGrid">
                            <?php foreach($stories as $story): 
                                // Safely get all values with defaults
                                $weightLoss = isset($story['weight_loss']) ? floatval($story['weight_loss']) : 0;
                                $durationMonths = isset($story['duration_months']) ? intval($story['duration_months']) : 6;
                                $isApproved = isset($story['approved']) ? boolval($story['approved']) : true;
                                $fullName = isset($story['full_name']) ? htmlspecialchars($story['full_name']) : 'Unknown Member';
                                $email = isset($story['email']) ? htmlspecialchars($story['email']) : 'unknown@email.com';
                                $title = isset($story['title']) ? htmlspecialchars($story['title']) : 'Success Story';
                                $storyText = isset($story['story_text']) ? htmlspecialchars($story['story_text']) : '';
                            ?>
                                <div class="story-card" 
                                     data-story-id="<?php echo $story['id'] ?? ''; ?>"
                                     data-story-title="<?php echo strtolower($title); ?>"
                                     data-member-name="<?php echo strtolower($fullName); ?>"
                                     data-story-status="<?php echo $isApproved ? 'approved' : 'pending'; ?>">
                                    <div class="story-header">
                                        <div class="story-avatar">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div>
                                            <h4><?php echo $fullName; ?></h4>
                                            <small><?php echo $email; ?></small>
                                        </div>
                                        <span class="story-status status-<?php echo $isApproved ? 'approved' : 'pending'; ?>">
                                            <?php echo $isApproved ? 'Approved' : 'Pending Review'; ?>
                                        </span>
                                    </div>
                                    <div class="story-body">
                                        <div class="story-meta">
                                            <?php if($weightLoss > 0): ?>
                                                <span><i class="fas fa-weight"></i> <span class="weight-loss"><?php echo number_format($weightLoss, 1); ?> lbs</span> lost</span>
                                            <?php endif; ?>
                                            <?php if($durationMonths > 0): ?>
                                                <span><i class="fas fa-clock"></i> <span class="duration"><?php echo $durationMonths; ?> month<?php echo $durationMonths !== 1 ? 's' : ''; ?></span></span>
                                            <?php endif; ?>
                                        </div>
                                        <h4><?php echo $title; ?></h4>
                                        <div class="story-content">
                                            <?php 
                                                if(!empty($storyText)) {
                                                    echo nl2br(substr($storyText, 0, 250));
                                                    if(strlen($storyText) > 250) {
                                                        echo '...';
                                                    }
                                                } else {
                                                    echo 'No story text provided.';
                                                }
                                            ?>
                                        </div>
                                        
                                        <?php if(isset($story['before_image']) || isset($story['after_image'])): ?>
                                            <div class="story-images">
                                                <?php if(!empty($story['before_image'])): ?>
                                                    <div class="image-container" style="text-align: center;">
                                                        <div style="font-size: 0.8rem; color: #6c757d; margin-bottom: 0.5rem; font-weight: 500;">Before</div>
                                                        <img src="uploads/<?php echo htmlspecialchars($story['before_image']); ?>" 
                                                             alt="Before" 
                                                             class="story-img"
                                                             onerror="this.src='https://via.placeholder.com/120x120/667eea/ffffff?text=Before'">
                                                    </div>
                                                <?php endif; ?>
                                                <?php if(!empty($story['after_image'])): ?>
                                                    <div class="image-container" style="text-align: center;">
                                                        <div style="font-size: 0.8rem; color: #6c757d; margin-bottom: 0.5rem; font-weight: 500;">After</div>
                                                        <img src="uploads/<?php echo htmlspecialchars($story['after_image']); ?>" 
                                                             alt="After" 
                                                             class="story-img"
                                                             onerror="this.src='https://via.placeholder.com/120x120/2ed573/ffffff?text=After'">
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="story-actions">
                                        <button class="btn-sm" onclick="window.location.href='admin-story-view.php?id=<?php echo $story['id'] ?? ''; ?>'">
                                            <i class="fas fa-eye"></i> View Full Story
                                        </button>
                                        <?php if(!$isApproved): ?>
                                            <button class="btn-sm btn-success" onclick="approveStory(<?php echo $story['id'] ?? ''; ?>)">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn-sm btn-danger" onclick="deleteStory(<?php echo $story['id'] ?? ''; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-newspaper fa-3x"></i>
                            <h3 style="color: #495057; margin: 1rem 0 0.5rem;">No Stories Found</h3>
                            <p style="margin-bottom: 2rem;">No <?php echo $status; ?> stories to display.</p>
                            <p style="font-size: 0.9rem; color: #6c757d;">Encourage members to share their success stories!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function approveStory(storyId) {
            if(confirm('Are you sure you want to approve this success story? It will be visible to all members.')) {
                window.location.href = 'admin-approve-story.php?id=' + storyId;
            }
        }
        
        function deleteStory(storyId) {
            if(confirm('Are you sure you want to delete this success story? This action cannot be undone.')) {
                window.location.href = 'admin-delete-story.php?id=' + storyId;
            }
        }
        
        // Live search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            const storyCards = document.querySelectorAll('.story-card');
            const storyGrid = document.getElementById('storyGrid');
            let visibleCount = 0;
            
            storyCards.forEach(card => {
                const storyTitle = card.getAttribute('data-story-title');
                const memberName = card.getAttribute('data-member-name');
                const rowText = card.textContent.toLowerCase();
                
                if (storyTitle.includes(searchTerm) || 
                    memberName.includes(searchTerm) ||
                    rowText.includes(searchTerm)) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Show no results message if needed
            const cardBody = document.querySelector('.card-body');
            let noResultsMsg = cardBody.querySelector('.no-results');
            
            if (visibleCount === 0 && searchTerm.length > 0) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.className = 'empty-state no-results';
                    noResultsMsg.innerHTML = `
                        <i class="fas fa-search"></i>
                        <h3 style="color: #495057; margin: 1rem 0 0.5rem;">No Matching Stories</h3>
                        <p>No stories found matching "${searchTerm}"</p>
                    `;
                    if (storyGrid) {
                        storyGrid.parentNode.insertBefore(noResultsMsg, storyGrid.nextSibling);
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