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
    $status = isset($_GET['status']) ? $_GET['status'] : 'pending';

    if($status === 'all') {
        $whereClause = '';
        $params = [];
    } else {
        $whereClause = "WHERE approved = " . ($status === 'approved' ? '1' : '0');
        $params = [];
    }
    
    $sql = "
        SELECT ss.*, u.full_name, u.email 
        FROM success_stories ss
        JOIN users u ON ss.user_id = u.id
        $whereClause
        ORDER BY ss.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pendingCount = $pdo->query("SELECT COUNT(*) FROM success_stories WHERE approved = 0")->fetchColumn() ?: 0;
    $approvedCount = $pdo->query("SELECT COUNT(*) FROM success_stories WHERE approved = 1")->fetchColumn() ?: 0;
    $totalStories = $pendingCount + $approvedCount;
    
} catch (PDOException $e) {
    error_log("Stories error: " . $e->getMessage());
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
                <input type="text" placeholder="Search stories...">
            </div>
            <div class="top-bar-actions">
                <?php if($pendingCount > 0): ?>
                    <span class="notification-badge"><?php echo $pendingCount; ?></span>
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
                    Pending Review (<?php echo $pendingCount; ?>)
                </button>
                <button class="status-tab <?php echo $status === 'approved' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?status=approved'">
                    Approved (<?php echo $approvedCount; ?>)
                </button>
                <button class="status-tab <?php echo $status === 'all' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?status=all'">
                    All Stories
                </button>
            </div>

            <!-- Stories Grid -->
            <div class="content-card">
                <div class="card-header">
                    <h3><?php echo ucfirst($status); ?> Stories</h3>
                </div>
                <div class="card-body">
                    <?php if(count($stories) > 0): ?>
                        <div class="story-grid">
                            <?php foreach($stories as $story): ?>
                                <div class="story-card">
                                    <div class="story-header">
                                        <div class="story-avatar">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div>
                                            <h4><?php echo htmlspecialchars($story['full_name']); ?></h4>
                                            <small><?php echo htmlspecialchars($story['email']); ?></small>
                                        </div>
                                        <span class="story-status status-<?php echo $story['approved'] ? 'approved' : 'pending'; ?>">
                                            <?php echo $story['approved'] ? 'Approved' : 'Pending'; ?>
                                        </span>
                                    </div>
                                    <div class="story-body">
                                        <div class="story-meta">
                                            <span><i class="fas fa-weight"></i> <?php echo $story['weight_loss']; ?> lbs lost</span>
                                            <span><i class="fas fa-clock"></i> <?php echo $story['duration_months']; ?> months</span>
                                        </div>
                                        <h4><?php echo htmlspecialchars($story['title']); ?></h4>
                                        <div class="story-content">
                                            <?php echo nl2br(htmlspecialchars(substr($story['story_text'], 0, 200))); ?>...
                                        </div>
                                        
                                        <?php if($story['before_image'] || $story['after_image']): ?>
                                            <div class="story-images">
                                                <?php if($story['before_image']): ?>
                                                    <img src="uploads/<?php echo htmlspecialchars($story['before_image']); ?>" alt="Before" class="story-img">
                                                <?php endif; ?>
                                                <?php if($story['after_image']): ?>
                                                    <img src="uploads/<?php echo htmlspecialchars($story['after_image']); ?>" alt="After" class="story-img">
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="story-actions">
                                        <button class="btn-sm" onclick="window.location.href='admin-story-view.php?id=<?php echo $story['id']; ?>'">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <?php if(!$story['approved']): ?>
                                            <button class="btn-sm btn-success" onclick="approveStory(<?php echo $story['id']; ?>)">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn-sm btn-danger" onclick="deleteStory(<?php echo $story['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="empty-state">No stories found</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function approveStory(storyId) {
            if(confirm('Approve this success story?')) {
                window.location.href = 'admin-approve-story.php?id=' + storyId;
            }
        }
        
        function deleteStory(storyId) {
            if(confirm('Delete this success story?')) {
                window.location.href = 'admin-delete-story.php?id=' + storyId;
            }
        }
    </script>
</body>
</html>