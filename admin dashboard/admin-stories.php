<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get stories with filters
$status = isset($_GET['status']) ? $_GET['status'] : 'pending';

$whereClause = $status === 'all' ? '' : "WHERE approved = " . ($status === 'approved' ? '1' : '0');
$stories = $pdo->query("
    SELECT ss.*, u.full_name, u.email 
    FROM success_stories ss
    JOIN users u ON ss.user_id = u.id
    $whereClause
    ORDER BY ss.created_at DESC
")->fetchAll();

$pendingCount = $pdo->query("SELECT COUNT(*) FROM success_stories WHERE approved = 0")->fetchColumn();
$approvedCount = $pdo->query("SELECT COUNT(*) FROM success_stories WHERE approved = 1")->fetchColumn();
$totalStories = $pendingCount + $approvedCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Success Stories | Admin Dashboard</title>
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        .story-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        .story-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .story-header {
            padding: 1.5rem 1.5rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
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
        }
        .story-body {
            padding: 1rem 1.5rem;
        }
        .story-meta {
            display: flex;
            gap: 1rem;
            margin: 0.5rem 0;
            font-size: 0.9rem;
            color: #666;
        }
        .story-content {
            margin: 1rem 0;
            line-height: 1.6;
        }
        .story-images {
            display: flex;
            gap: 0.5rem;
            margin: 1rem 0;
        }
        .story-img {
            width: 100px;
            height: 100px;
            border-radius: 5px;
            object-fit: cover;
        }
        .story-actions {
            display: flex;
            gap: 0.5rem;
            padding: 1rem 1.5rem;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
        .story-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-left: auto;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
    </style>
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
                <span class="notification-badge"><?php echo $pendingCount; ?></span>
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