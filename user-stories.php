<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'member') {
    header('Location: login.php');
    exit();
}

$pdo = null;
$message = '';
$success = false;
$user_id = $_SESSION['user_id'];

// Handle status messages
if(isset($_SESSION['story_message'])) {
    $message = $_SESSION['story_message'];
    unset($_SESSION['story_message']);
}
if(isset($_SESSION['story_success'])) {
    $success = $_SESSION['story_success'];
    unset($_SESSION['story_success']);
}

try {
    require_once 'config/database.php';
    $database = Database::getInstance();
    $pdo = $database->getConnection();

    // Get user info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if(!$user) {
        session_destroy();
        header('Location: login.php');
        exit();
    }
    
    // Get user's success stories with status
    $userStories = [];
    try {
        $storiesStmt = $pdo->prepare("
            SELECT 
                ss.*, 
                u.full_name as user_name, 
                t.full_name as trainer_name,
                CASE 
                    WHEN ss.approved = 1 AND ss.is_featured = 1 THEN 'featured'
                    WHEN ss.approved = 1 THEN 'approved'
                    WHEN ss.approved = 0 AND ss.rejected_reason IS NOT NULL THEN 'rejected'
                    ELSE 'pending'
                END as status
            FROM success_stories ss 
            LEFT JOIN users u ON ss.user_id = u.id 
            LEFT JOIN users t ON ss.trainer_id = t.id 
            WHERE ss.user_id = ? 
            ORDER BY 
                CASE 
                    WHEN ss.is_featured = 1 THEN 1
                    WHEN ss.approved = 1 THEN 2
                    WHEN ss.approved = 0 THEN 3
                    WHEN ss.rejected_reason IS NOT NULL THEN 4
                END,
                ss.created_at DESC
        ");
        $storiesStmt->execute([$user_id]);
        $userStories = $storiesStmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Stories query error: " . $e->getMessage());
    }
    
    // Get featured success stories (approved and featured)
    $featuredStories = [];
    try {
        $featuredStmt = $pdo->prepare("
            SELECT ss.*, u.full_name as user_name, t.full_name as trainer_name 
            FROM success_stories ss 
            LEFT JOIN users u ON ss.user_id = u.id 
            LEFT JOIN users t ON ss.trainer_id = t.id 
            WHERE ss.approved = 1 
            AND ss.is_featured = 1
            ORDER BY ss.featured_date DESC, ss.created_at DESC 
            LIMIT 6
        ");
        $featuredStmt->execute();
        $featuredStories = $featuredStmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Featured stories query error: " . $e->getMessage());
    }
    
    // Get approved success stories for inspiration
    $approvedStories = [];
    try {
        $approvedStmt = $pdo->prepare("
            SELECT ss.*, u.full_name as user_name, t.full_name as trainer_name 
            FROM success_stories ss 
            LEFT JOIN users u ON ss.user_id = u.id 
            LEFT JOIN users t ON ss.trainer_id = t.id 
            WHERE ss.approved = 1 
            AND (ss.user_id != ? OR ss.user_id IS NULL)
            ORDER BY ss.created_at DESC 
            LIMIT 4
        ");
        $approvedStmt->execute([$user_id]);
        $approvedStories = $approvedStmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Approved stories query error: " . $e->getMessage());
    }
    
    // Count stats
    $approvedCount = 0;
    $pendingCount = 0;
    $rejectedCount = 0;
    $featuredCount = 0;
    $totalWeightLoss = 0;
    
    foreach($userStories as $story) {
        if($story['approved'] == 1) {
            $approvedCount++;
            if($story['is_featured'] == 1) {
                $featuredCount++;
            }
            $totalWeightLoss += $story['weight_loss'];
        } elseif($story['rejected_reason']) {
            $rejectedCount++;
        } else {
            $pendingCount++;
        }
    }
    
} catch(PDOException $e) {
    $message = "Database error. Please try again later.";
    error_log("Success stories page error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Success Stories | CONQUER Gym</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    
    <style>
        /* ... keep existing styles, add these ... */
        
        /* Status badges */
    .stories-content {
        padding: 2rem;
    }
    
    .stories-section {
        background: var(--white);
        border-radius: var(--radius-md);
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-sm);
    }
    
    .section-title {
        margin: 0 0 1.5rem 0;
        color: var(--dark-color);
        padding-bottom: 0.75rem;
        border-bottom: 2px solid var(--light-color);
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin: 2rem 0;
    }
    
    .stat-card {
        background: var(--white);
        padding: 1.5rem;
        border-radius: var(--radius-md);
        text-align: center;
        box-shadow: var(--shadow-sm);
        border-top: 4px solid var(--primary-color);
    }
    
    .stat-card.stories {
        border-color: var(--primary-color);
    }
    
    .stat-card.weight {
        border-color: var(--success);
    }
    
    .stat-card.featured {
        border-color: var(--warning);
    }
    
    .stat-card.pending {
        border-color: var(--info);
    }
    
    .stat-card.rejected {
        border-color: var(--danger);
    }
    
    .stat-card h3 {
        margin: 0 0 0.5rem 0;
        color: var(--dark-color);
        font-size: 2rem;
    }
    
    .stat-card p {
        margin: 0;
        color: var(--gray);
    }
    
    .stories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        margin: 2rem 0;
    }
    
    .story-card {
        background: var(--white);
        border-radius: var(--radius-md);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        border: 1px solid var(--light-color);
        position: relative;
    }
    
    .story-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
    }
    
    .story-card.featured {
        border: 2px solid var(--warning);
    }
    
    .story-card.pending {
        opacity: 0.8;
    }
    
    .story-header {
        padding: 1.5rem;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
    }
    
    .story-header h4 {
        margin: 0 0 0.5rem 0;
        font-size: 1.25rem;
    }
    
    .story-meta {
        display: flex;
        gap: 1rem;
        font-size: 0.9rem;
        opacity: 0.9;
    }
    
    .story-body {
        padding: 1.5rem;
    }
    
    .story-stats {
        display: flex;
        gap: 1rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }
    
    .stat-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: var(--light-bg);
        border-radius: var(--radius-sm);
        color: var(--dark-color);
    }
    
    .story-text {
        color: var(--gray);
        line-height: 1.6;
        margin-bottom: 1rem;
        max-height: 120px;
        overflow: hidden;
        position: relative;
    }
    
    .story-text.expanded {
        max-height: none;
    }
    
    .read-more {
        background: none;
        border: none;
        color: var(--primary-color);
        cursor: pointer;
        font-weight: 600;
        padding: 0;
        margin: 0.5rem 0;
    }
    
    .story-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1.5rem;
    }
    
    .featured-badge {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: var(--warning);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .pending-badge {
        background: var(--info);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-block;
    }
    
    .no-stories {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--gray);
    }
    
    .quote {
        font-style: italic;
        color: var(--gray);
        border-left: 4px solid var(--primary-color);
        padding-left: 1rem;
        margin: 1.5rem 0;
    }
    
    .testimonial-author {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .author-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: var(--primary-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }
    
    /* Status badges */
    .status-badge {
        padding: 0.35rem 0.85rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-block;
    }
    
    .status-approved { 
        background: rgba(46, 213, 115, 0.15); 
        color: #2ED573;
        border: 1px solid rgba(46, 213, 115, 0.3);
    }
    
    .status-pending { 
        background: rgba(255, 165, 2, 0.15); 
        color: #FFA502;
        border: 1px solid rgba(255, 165, 2, 0.3);
    }
    
    .status-rejected { 
        background: rgba(255, 71, 87, 0.15); 
        color: #FF4757;
        border: 1px solid rgba(255, 71, 87, 0.3);
    }
    
    .status-featured { 
        background: rgba(255, 215, 0, 0.15); 
        color: #FFD700;
        border: 1px solid rgba(255, 215, 0, 0.3);
    }
    
    /* Alert messages */
    .alert {
        padding: 1rem 1.5rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .alert-success {
        background: rgba(46, 213, 115, 0.1);
        color: #2ED573;
        border: 1px solid rgba(46, 213, 115, 0.2);
    }
    
    .alert-error {
        background: rgba(255, 71, 87, 0.1);
        color: #FF4757;
        border: 1px solid rgba(255, 71, 87, 0.2);
    }
    
    .alert-info {
        background: rgba(71, 130, 255, 0.1);
        color: #4782FF;
        border: 1px solid rgba(71, 130, 255, 0.2);
    }
    
    /* Rejection box */
    .rejection-box {
        background: rgba(255, 71, 87, 0.05);
        border: 1px solid rgba(255, 71, 87, 0.2);
        border-radius: 10px;
        padding: 1rem;
        margin: 1rem 0;
    }
    
    .rejection-box h5 {
        color: #FF4757;
        margin: 0 0 0.5rem 0;
        font-size: 0.9rem;
    }
    
    .rejection-box p {
        margin: 0;
        font-size: 0.85rem;
        color: #666;
    }
    
    /* Image preview in table */
    .story-images-preview {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }
    
    .story-img-small {
        width: 60px;
        height: 60px;
        border-radius: 5px;
        object-fit: cover;
        border: 2px solid var(--light-color);
    }
    
    /* Modal for rejection details */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }
    
    .modal-content {
        background: white;
        border-radius: 15px;
        padding: 2rem;
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: var(--shadow-lg);
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }
    
    .modal-header h3 {
        margin: 0;
        color: var(--dark-color);
        font-size: 1.25rem;
    }
    
    .close-modal {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--gray);
        line-height: 1;
    }
    
    /* Action buttons */
    .action-btn {
        padding: 0.4rem 0.8rem;
        border-radius: var(--radius-sm);
        border: none;
        cursor: pointer;
        font-size: 0.85rem;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }
    
    .action-btn.view {
        background: var(--light-color);
        color: var(--dark-color);
    }
    
    .action-btn.edit {
        background: rgba(255, 193, 7, 0.1);
        color: #ffc107;
        border: 1px solid rgba(255, 193, 7, 0.2);
    }
    
    .action-btn.delete {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
        border: 1px solid rgba(220, 53, 69, 0.2);
    }
    
    .action-btn:hover {
        opacity: 0.8;
        transform: translateY(-1px);
    }
    
    /* Filter buttons */
    .filter-btn {
        padding: 0.5rem 1rem;
        background: var(--light-color);
        border: 1px solid var(--light-color);
        border-radius: 20px;
        cursor: pointer;
        font-size: 0.9rem;
        transition: var(--transition);
        color: var(--gray);
    }
    
    .filter-btn:hover {
        background: var(--gray-light);
        border-color: var(--gray-light);
    }
    
    .filter-btn.active {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    .btn-text {
        background: none;
        border: none;
        color: var(--primary-color);
        cursor: pointer;
        padding: 0.25rem;
        margin-left: 0.5rem;
        font-size: 0.9rem;
    }
    
    .btn-text:hover {
        color: var(--secondary-color);
    }
    
    .btn-action {
        padding: 0.5rem 1rem;
        border-radius: var(--radius-sm);
        border: 1px solid var(--primary-color);
        background: white;
        color: var(--primary-color);
        cursor: pointer;
        font-weight: 600;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .btn-action:hover {
        background: var(--primary-color);
        color: white;
    }
    
    .btn-action.btn-reject {
        border-color: var(--danger);
        color: var(--danger);
    }
    
    .btn-action.btn-reject:hover {
        background: var(--danger);
        color: white;
    }
    
    /* Table styles */
    .table-container {
        overflow-x: auto;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
    }
    
    th {
        background: var(--light-bg);
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: var(--dark-color);
        border-bottom: 2px solid var(--light-color);
    }
    
    td {
        padding: 1rem;
        border-bottom: 1px solid var(--light-color);
        vertical-align: top;
    }
    
    tr:hover {
        background: var(--light-bg);
    }
    
    /* Utility classes */
    .mt-3 {
        margin-top: 1rem;
    }
    
    @media (max-width: 768px) {
        .stories-grid {
            grid-template-columns: 1fr;
        }
        
        .story-stats {
            flex-direction: column;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .stories-content {
            padding: 1rem;
        }
        
        .stories-section {
            padding: 1.5rem;
        }
        
        .modal-content {
            padding: 1.5rem;
        }
        
        table {
            font-size: 0.9rem;
        }
        
        th, td {
            padding: 0.75rem 0.5rem;
        }
        
        .action-btn {
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem;
        }
        
        .filter-btn {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }
    }
    
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .story-meta {
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .modal-content {
            padding: 1rem;
            width: 95%;
        }
        
        .story-actions {
            flex-wrap: wrap;
        }
        
        .action-btn {
            flex: 1;
            justify-content: center;
        }
        
        .story-body {
            padding: 1rem;
        }
        
        .story-header {
            padding: 1rem;
        }
    }
</style>
    </style>
</head>
<body>
   <?php include 'user-sidebar.php'; ?>
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search stories..." id="searchStories">
            </div>
            <div class="top-bar-actions">
                <?php if($pendingCount > 0): ?>
                    <div style="position: relative;">
                        <button class="btn-notification" onclick="filterStories('pending')">
                            <i class="fas fa-bell"></i>
                        </button>
                        <span class="notification-badge"><?php echo $pendingCount; ?></span>
                    </div>
                <?php endif; ?>
                <button class="btn-primary" onclick="window.location.href='submit-story.php'">
                    <i class="fas fa-plus"></i> Share Your Story
                </button>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>Success Stories 🏆</h1>
                    <p>Be inspired by fitness transformations and share your own journey</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3><?php echo $approvedCount; ?></h3>
                        <p>Approved Stories</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo number_format($totalWeightLoss, 0); ?>lbs</h3>
                        <p>Total Lost</p>
                    </div>
                </div>
            </div>

            <!-- Status Messages -->
            <?php if($message): ?>
                <div class="alert alert-<?php echo $success ? 'success' : 'error'; ?>">
                    <i class="fas fa-<?php echo $success ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="stories-content">
                <!-- User Stats -->
                <div class="stories-section">
                    <h3 class="section-title">Your Progress</h3>
                    <div class="stats-grid">
                        <div class="stat-card stories">
                            <h3><?php echo count($userStories); ?></h3>
                            <p>Total Stories</p>
                        </div>
                        <div class="stat-card weight">
                            <h3><?php echo number_format($totalWeightLoss, 0); ?>lbs</h3>
                            <p>Weight Lost</p>
                        </div>
                        <div class="stat-card featured">
                            <h3><?php echo $featuredCount; ?></h3>
                            <p>Featured Stories</p>
                        </div>
                        <div class="stat-card pending">
                            <h3><?php echo $pendingCount; ?></h3>
                            <p>Pending Review</p>
                        </div>
                        <?php if($rejectedCount > 0): ?>
                        <div class="stat-card rejected">
                            <h3><?php echo $rejectedCount; ?></h3>
                            <p>Rejected Stories</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Featured Stories -->
                <div class="stories-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 class="section-title" style="margin: 0;">🌟 Featured Success Stories</h3>
                        <button class="btn-secondary" onclick="window.location.href='browse-stories.php'">
                            <i class="fas fa-th-list"></i> Browse All
                        </button>
                    </div>
                    
                    <?php if(count($featuredStories) > 0): ?>
                        <div class="stories-grid" id="featuredStories">
                            <?php foreach($featuredStories as $story): 
                                $userName = isset($story['user_name']) ? $story['user_name'] : 'Anonymous';
                                $trainerName = isset($story['trainer_name']) ? $story['trainer_name'] : 'Our Trainer';
                                $firstLetter = strtoupper(substr($userName, 0, 1));
                            ?>
                                <div class="story-card featured">
                                    <div class="featured-badge">
                                        <i class="fas fa-star"></i> Featured
                                    </div>
                                    
                                    <div class="story-header">
                                        <h4><?php echo htmlspecialchars($story['title']); ?></h4>
                                        <div class="story-meta">
                                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($userName); ?></span>
                                            <span><i class="fas fa-calendar"></i> <?php echo isset($story['created_at']) ? date('M Y', strtotime($story['created_at'])) : 'Recently'; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="story-body">
                                        <div class="story-stats">
                                            <?php if($story['weight_loss'] > 0): ?>
                                            <div class="stat-badge">
                                                <i class="fas fa-weight"></i>
                                                <span><?php echo number_format($story['weight_loss'], 1); ?> lbs lost</span>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if($story['months_taken'] > 0): ?>
                                            <div class="stat-badge">
                                                <i class="fas fa-clock"></i>
                                                <span><?php echo $story['months_taken']; ?> months</span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="story-text" id="storyText-<?php echo $story['id']; ?>">
                                            <?php 
                                            $text = htmlspecialchars($story['story_text']);
                                            if(strlen($text) > 200) {
                                                echo substr($text, 0, 200) . '...';
                                            } else {
                                                echo $text;
                                            }
                                            ?>
                                        </div>
                                        
                                        <?php if(strlen($story['story_text']) > 200): ?>
                                        <button class="read-more" onclick="toggleReadMore(<?php echo $story['id']; ?>)">
                                            Read More
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if($story['before_image'] || $story['after_image']): ?>
                                        <div class="story-images-preview" style="margin: 1rem 0;">
                                            <?php if($story['before_image']): ?>
                                                <img src="uploads/<?php echo htmlspecialchars($story['before_image']); ?>" 
                                                     alt="Before" 
                                                     class="story-img-small"
                                                     onerror="this.style.display='none'">
                                            <?php endif; ?>
                                            <?php if($story['after_image']): ?>
                                                <img src="uploads/<?php echo htmlspecialchars($story['after_image']); ?>" 
                                                     alt="After" 
                                                     class="story-img-small"
                                                     onerror="this.style.display='none'">
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="quote">
                                            "I couldn't have done it without <?php echo htmlspecialchars($trainerName); ?>!"
                                        </div>
                                        
                                        <div class="testimonial-author">
                                            <div class="author-avatar">
                                                <?php echo $firstLetter; ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($userName); ?></strong>
                                                <p style="margin: 0; font-size: 0.9rem; color: var(--gray);">
                                                    CONQUER Gym Member
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-stories">
                            <i class="fas fa-trophy" style="font-size: 3rem; color: var(--gray); margin-bottom: 1rem;"></i>
                            <h3>No featured stories yet</h3>
                            <p>Submit your story and it might get featured!</p>
                            <button class="btn-primary mt-3" onclick="window.location.href='submit-story.php'">
                                <i class="fas fa-plus"></i> Share Your Story
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Your Stories -->
                <div class="stories-section">
                    <h3 class="section-title">Your Stories</h3>
                    
                    <?php if(count($userStories) > 0): ?>
                        <div style="margin: 1rem 0; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <button class="filter-btn active" onclick="filterTable('all')">All Stories</button>
                            <button class="filter-btn" onclick="filterTable('approved')">Approved</button>
                            <button class="filter-btn" onclick="filterTable('pending')">Pending</button>
                            <button class="filter-btn" onclick="filterTable('featured')">Featured</button>
                            <button class="filter-btn" onclick="filterTable('rejected')">Rejected</button>
                        </div>
                        
                        <div class="table-container" style="margin: 2rem 0;">
                            <table id="storiesTable">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Date</th>
                                        <th>Images</th>
                                        <th>Weight Loss</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($userStories as $story): 
                                        $status = $story['status'];
                                        $statusClass = "status-{$status}";
                                        $statusText = ucfirst($status);
                                        
                                        if($story['is_featured'] == 1 && $story['approved'] == 1) {
                                            $statusText = 'Featured';
                                            $statusClass = 'status-featured';
                                        }
                                    ?>
                                        <tr data-status="<?php echo $story['status']; ?>">
                                            <td><strong><?php echo htmlspecialchars($story['title']); ?></strong></td>
                                            <td><?php echo isset($story['created_at']) ? date('M j, Y', strtotime($story['created_at'])) : 'N/A'; ?></td>
                                            <td>
                                                <?php if($story['before_image'] || $story['after_image']): ?>
                                                    <div class="story-images-preview">
                                                        <?php if($story['before_image']): ?>
                                                            <img src="uploads/<?php echo htmlspecialchars($story['before_image']); ?>" 
                                                                 alt="Before" 
                                                                 class="story-img-small"
                                                                 onerror="this.style.display='none'">
                                                        <?php endif; ?>
                                                        <?php if($story['after_image']): ?>
                                                            <img src="uploads/<?php echo htmlspecialchars($story['after_image']); ?>" 
                                                                 alt="After" 
                                                                 class="story-img-small"
                                                                 onerror="this.style.display='none'">
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: var(--gray); font-size: 0.9rem;">No images</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo number_format($story['weight_loss'], 1); ?> lbs</td>
                                            <td><?php echo $story['months_taken']; ?> months</td>
                                            <td>
                                                <span class="status-badge <?php echo $statusClass; ?>">
                                                    <?php echo $statusText; ?>
                                                </span>
                                                <?php if($status == 'rejected'): ?>
                                                    <button class="btn-text" onclick="showRejectionReason(<?php echo $story['id']; ?>, '<?php echo htmlspecialchars(addslashes($story['rejected_reason'])); ?>')">
                                                        <i class="fas fa-info-circle" style="color: #FF4757;"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <?php if($status == 'approved'): ?>
                                                        <button class="action-btn view" onclick="viewStory(<?php echo $story['id']; ?>)">
                                                            <i class="fas fa-eye"></i> View
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if(in_array($status, ['pending', 'rejected'])): ?>
                                                        <button class="action-btn edit" onclick="window.location.href='edit-story.php?id=<?php echo $story['id']; ?>'">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <button class="action-btn delete" onclick="deleteStory(<?php echo $story['id']; ?>)">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div style="text-align: center; margin-top: 2rem;">
                            <button class="btn-primary" onclick="window.location.href='submit-story.php'">
                                <i class="fas fa-plus"></i> Add New Story
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="no-stories">
                            <i class="fas fa-book" style="font-size: 3rem; color: var(--gray); margin-bottom: 1rem;"></i>
                            <h3>You haven't shared any stories yet</h3>
                            <p>Your fitness journey can inspire others. Share your story today!</p>
                            <button class="btn-primary mt-3" onclick="window.location.href='submit-story.php'">
                                <i class="fas fa-plus"></i> Share Your First Story
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Inspiration from Others -->
                <?php if(count($approvedStories) > 0): ?>
                <div class="stories-section">
                    <h3 class="section-title">Inspiration from Others</h3>
                    <p style="color: var(--gray); margin-bottom: 1.5rem;">See what other members have achieved</p>
                    
                    <div class="stories-grid">
                        <?php foreach($approvedStories as $story): 
                            $userName = isset($story['user_name']) ? $story['user_name'] : 'Anonymous';
                            $firstLetter = strtoupper(substr($userName, 0, 1));
                        ?>
                            <div class="story-card">
                                <div class="story-header">
                                    <h4><?php echo htmlspecialchars($story['title']); ?></h4>
                                    <div class="story-meta">
                                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($userName); ?></span>
                                    </div>
                                </div>
                                
                                <div class="story-body">
                                    <div class="story-stats">
                                        <?php if($story['weight_loss'] > 0): ?>
                                        <div class="stat-badge">
                                            <i class="fas fa-weight"></i>
                                            <span><?php echo number_format($story['weight_loss'], 1); ?> lbs lost</span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="story-text">
                                        <?php 
                                        $text = htmlspecialchars($story['story_text']);
                                        echo substr($text, 0, 150) . (strlen($text) > 150 ? '...' : '');
                                        ?>
                                    </div>
                                    
                                    <div class="testimonial-author">
                                        <div class="author-avatar">
                                            <?php echo $firstLetter; ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($userName); ?></strong>
                                            <p style="margin: 0; font-size: 0.9rem; color: var(--gray);">
                                                CONQUER Gym Member
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Submission CTA -->
                <div class="stories-section" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white;">
                    <div style="text-align: center; padding: 2rem;">
                        <h2 style="margin: 0 0 1rem 0; color: white;">Your Journey Matters</h2>
                        <p style="margin: 0 0 2rem 0; opacity: 0.9; max-width: 600px; margin: 0 auto 2rem;">
                            Every fitness journey starts with a single step. Whether you've lost 5 pounds or 50, 
                            your story can motivate someone else to begin their own transformation.
                        </p>
                        <button class="btn-primary" style="background: white; color: var(--primary-color);" onclick="window.location.href='submit-story.php'">
                            <i class="fas fa-trophy"></i> Share Your Success
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Reason Modal -->
    <div id="rejectionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-times-circle" style="color: #FF4757; margin-right: 10px;"></i> Story Rejection Reason</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div id="rejectionContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // ... existing JavaScript functions ...
        
        // Filter table by status
        function filterTable(status) {
            const rows = document.querySelectorAll('#storiesTable tbody tr');
            const buttons = document.querySelectorAll('.filter-btn');
            
            // Update active button
            buttons.forEach(btn => {
                if(btn.textContent.toLowerCase().includes(status)) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            
            // Filter rows
            rows.forEach(row => {
                if(status === 'all' || row.getAttribute('data-status') === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Show rejection reason modal
        function showRejectionReason(storyId, reason) {
            const modal = document.getElementById('rejectionModal');
            const content = document.getElementById('rejectionContent');
            
            content.innerHTML = `
                <div class="rejection-box">
                    <h5><i class="fas fa-exclamation-triangle"></i> Why this story was rejected:</h5>
                    <p>${reason || 'No specific reason provided.'}</p>
                </div>
                <div style="margin-top: 1.5rem;">
                    <p style="color: var(--gray); font-size: 0.9rem;">
                        <i class="fas fa-lightbulb"></i> Tip: You can edit your story to address these concerns and resubmit it for review.
                    </p>
                </div>
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button class="btn-action edit" onclick="window.location.href='edit-story.php?id=${storyId}'">
                        <i class="fas fa-edit"></i> Edit Story
                    </button>
                    <button class="btn-action btn-reject" onclick="closeModal()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            `;
            
            modal.style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('rejectionModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('rejectionModal');
            if(event.target === modal) {
                closeModal();
            }
        }
        
        // Filter stories by status in search
        function filterStories(status) {
            const rows = document.querySelectorAll('.story-card');
            rows.forEach(row => {
                if(status === 'all') {
                    row.style.display = 'block';
                } else if(row.classList.contains(status)) {
                    row.style.display = 'block';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Style for filter buttons
        const style = document.createElement('style');
        style.textContent += `
            .filter-btn {
                padding: 0.5rem 1rem;
                background: var(--light-color);
                border: none;
                border-radius: 20px;
                cursor: pointer;
                font-size: 0.9rem;
                transition: var(--transition);
            }
            
            .filter-btn:hover {
                background: var(--gray-light);
            }
            
            .filter-btn.active {
                background: var(--primary-color);
                color: white;
            }
            
            .btn-text {
                background: none;
                border: none;
                color: var(--primary-color);
                cursor: pointer;
                padding: 0.25rem;
                margin-left: 0.5rem;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>