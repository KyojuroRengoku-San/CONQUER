<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'member') {
    header('Location: login.php');
    exit();
}

// Initialize database connection
$pdo = null;
$message = '';
$success = false;
$user_id = $_SESSION['user_id'];

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
    
    // Get user's success stories
    $userStories = [];
    try {
        $storiesStmt = $pdo->prepare("
            SELECT ss.*, u.full_name as user_name, t.full_name as trainer_name 
            FROM success_stories ss 
            LEFT JOIN users u ON ss.user_id = u.id 
            LEFT JOIN users t ON ss.trainer_id = t.id 
            WHERE ss.user_id = ? 
            ORDER BY ss.created_at DESC
        ");
        $storiesStmt->execute([$user_id]);
        $userStories = $storiesStmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Stories query error: " . $e->getMessage());
    }
    
    // Get featured success stories (all users)
    $featuredStories = [];
    try {
        $featuredStmt = $pdo->prepare("
            SELECT ss.*, u.full_name as user_name, t.full_name as trainer_name 
            FROM success_stories ss 
            LEFT JOIN users u ON ss.user_id = u.id 
            LEFT JOIN users t ON ss.trainer_id = t.id 
            WHERE ss.approved = 1 
            AND (ss.is_featured = 1 OR ss.user_id = ?)
            ORDER BY ss.is_featured DESC, ss.created_at DESC 
            LIMIT 6
        ");
        $featuredStmt->execute([$user_id]);
        $featuredStories = $featuredStmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Featured stories query error: " . $e->getMessage());
    }
    
    // Count stats
    $approvedCount = 0;
    $pendingCount = 0;
    $totalWeightLoss = 0;
    
    foreach($userStories as $story) {
        if($story['approved']) {
            $approvedCount++;
            $totalWeightLoss += $story['weight_loss'];
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
            margin-bottom: 1.5rem;
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
            margin-top: 0.5rem;
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
        
        @media (max-width: 768px) {
            .stories-grid {
                grid-template-columns: 1fr;
            }
            
            .story-stats {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-dumbbell"></i>
                <span>CONQUER</span>
            </div>
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo isset($user['full_name']) ? strtoupper(substr($user['full_name'], 0, 1)) : 'U'; ?>
                </div>
                <div class="user-details">
                    <h4><?php echo isset($user['full_name']) ? htmlspecialchars($user['full_name']) : 'User'; ?></h4>
                    <p>Member</p>
                </div>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="user-dashboard.php">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="user-profile.php">
                <i class="fas fa-user"></i>
                <span>My Profile</span>
            </a>
            <a href="user-classes.php">
                <i class="fas fa-calendar-alt"></i>
                <span>My Classes</span>
            </a>
            <a href="user-payments.php">
                <i class="fas fa-credit-card"></i>
                <span>Payments</span>
            </a>
            <a href="user-stories.php">
                <i class="fas fa-trophy"></i>
                <span>Success Stories</span>
            </a>
            <a href="user-bookclass.php">
                <i class="fas fa-plus-circle"></i>
                <span>Book Class</span>
            </a>
            <a href="user-contact.php">
                <i class="fas fa-envelope"></i>
                <span>Support</span>
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search stories..." id="searchStories">
            </div>
            <div class="top-bar-actions">
                <button class="btn-notification">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </button>
                <button class="btn-primary" onclick="window.location.href='submit-story.php'">
                    <i class="fas fa-plus"></i>
                    Share Your Story
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
                        <p>Your Stories</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo number_format($totalWeightLoss, 0); ?>lbs</h3>
                        <p>Total Lost</p>
                    </div>
                </div>
            </div>

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
                            <h3><?php echo array_reduce($userStories, function($carry, $story) { return $carry + ($story['is_featured'] ? 1 : 0); }, 0); ?></h3>
                            <p>Featured Stories</p>
                        </div>
                        <div class="stat-card pending">
                            <h3><?php echo $pendingCount; ?></h3>
                            <p>Pending Approval</p>
                        </div>
                    </div>
                </div>

                <!-- Featured Stories -->
                <div class="stories-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 class="section-title" style="margin: 0;">Featured Success Stories</h3>
                        <button class="btn-secondary" onclick="window.location.href='browse-stories.php'">
                            <i class="fas fa-th-list"></i> Browse All
                        </button>
                    </div>
                    
                    <?php if(count($featuredStories) > 0): ?>
                        <div class="stories-grid" id="featuredStories">
                            <?php foreach($featuredStories as $story): 
                                $isFeatured = $story['is_featured'] == 1;
                                $isPending = $story['approved'] == 0;
                                $userName = isset($story['user_name']) ? $story['user_name'] : 'Anonymous';
                                $trainerName = isset($story['trainer_name']) ? $story['trainer_name'] : 'Our Trainer';
                                $firstLetter = strtoupper(substr($userName, 0, 1));
                            ?>
                                <div class="story-card <?php echo $isFeatured ? 'featured' : ''; ?> <?php echo $isPending ? 'pending' : ''; ?>">
                                    <?php if($isFeatured): ?>
                                        <div class="featured-badge">
                                            <i class="fas fa-star"></i> Featured
                                        </div>
                                    <?php endif; ?>
                                    
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
                                        
                                        <?php if($story['user_id'] == $user_id): ?>
                                        <div class="story-actions">
                                            <button class="btn-sm" onclick="window.location.href='edit-story.php?id=<?php echo $story['id']; ?>'">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if($isPending): ?>
                                                <span class="pending-badge">
                                                    <i class="fas fa-clock"></i> Pending Approval
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-stories">
                            <i class="fas fa-trophy" style="font-size: 3rem; color: var(--gray); margin-bottom: 1rem;"></i>
                            <h3>No success stories yet</h3>
                            <p>Be the first to share your fitness journey!</p>
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
                        <div class="table-container" style="margin: 2rem 0;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Date</th>
                                        <th>Weight Loss</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($userStories as $story): 
                                        $statusClass = $story['approved'] ? 'status-completed' : 'status-pending';
                                        $statusText = $story['approved'] ? 'Approved' : ($story['is_featured'] ? 'Featured' : 'Pending');
                                    ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($story['title']); ?></strong></td>
                                            <td><?php echo isset($story['created_at']) ? date('M j, Y', strtotime($story['created_at'])) : 'N/A'; ?></td>
                                            <td><?php echo number_format($story['weight_loss'], 1); ?> lbs</td>
                                            <td><?php echo $story['months_taken']; ?> months</td>
                                            <td>
                                                <span class="status-badge <?php echo $statusClass; ?>">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <button class="action-btn view" onclick="viewStory(<?php echo $story['id']; ?>)">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <button class="action-btn edit" onclick="window.location.href='edit-story.php?id=<?php echo $story['id']; ?>'">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
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

                <!-- Motivation Section -->
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

    <script>
        // Toggle read more/less
        function toggleReadMore(storyId) {
            const textElement = document.getElementById(`storyText-${storyId}`);
            const button = textElement.nextElementSibling;
            
            if(textElement.classList.contains('expanded')) {
                textElement.classList.remove('expanded');
                const fullText = textElement.getAttribute('data-full');
                textElement.innerHTML = fullText.substring(0, 200) + '...';
                button.textContent = 'Read More';
            } else {
                const fullText = textElement.textContent;
                textElement.setAttribute('data-full', fullText);
                textElement.classList.add('expanded');
                // Get full text from server in real application
                textElement.innerHTML = fullText.replace('...', '') + ' (This is the full story in a real application)';
                button.textContent = 'Read Less';
            }
        }
        
        // Search functionality
        document.getElementById('searchStories').addEventListener('input', function() {
            const searchValue = this.value.toLowerCase();
            const cards = document.querySelectorAll('.story-card');
            
            cards.forEach(card => {
                const title = card.querySelector('h4').textContent.toLowerCase();
                const text = card.querySelector('.story-text').textContent.toLowerCase();
                const author = card.querySelector('.story-meta span:first-child').textContent.toLowerCase();
                
                if(title.includes(searchValue) || text.includes(searchValue) || author.includes(searchValue)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        
        // View story details
        function viewStory(storyId) {
            // In a real application, this would open a modal with full story details
            window.location.href = `story-details.php?id=${storyId}`;
        }
        
        // Delete story confirmation
        function deleteStory(storyId) {
            if(confirm('Are you sure you want to delete this story? This action cannot be undone.')) {
                // In a real application, this would submit a delete request
                alert(`Story ${storyId} would be deleted in a real application.`);
            }
        }
        
        // Filter by status
        document.addEventListener('DOMContentLoaded', function() {
            // Add filter buttons for user stories table
            const filterRow = document.createElement('div');
            filterRow.innerHTML = `
                <div style="margin: 1rem 0; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <button class="filter-btn active" data-filter="all">All Stories</button>
                    <button class="filter-btn" data-filter="approved">Approved</button>
                    <button class="filter-btn" data-filter="pending">Pending</button>
                    <button class="filter-btn" data-filter="featured">Featured</button>
                </div>
            `;
            
            document.querySelector('.section-title:last-of-type').after(filterRow);
            
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        });
        
        // Style for action buttons
        const style = document.createElement('style');
        style.textContent = `
            .action-btn {
                padding: 0.25rem 0.5rem;
                border-radius: var(--radius-sm);
                border: none;
                cursor: pointer;
                font-size: 0.85rem;
                transition: var(--transition);
            }
            
            .action-btn.view {
                background: var(--light-color);
                color: var(--dark-color);
            }
            
            .action-btn.edit {
                background: var(--warning-light);
                color: var(--warning);
            }
            
            .action-btn.delete {
                background: var(--danger-light);
                color: var(--danger);
            }
            
            .action-btn:hover {
                opacity: 0.8;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>