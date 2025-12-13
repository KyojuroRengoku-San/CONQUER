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

// Use hardcoded sample trainers data
$trainers = [
    [
        'id' => 1,
        'full_name' => 'Alex Morgan',
        'email' => 'alex.morgan@conquergym.com',
        'specialization' => 'Strength & Conditioning',
        'certifications' => 'NASM Certified',
        'experience_years' => 10,
        'rating' => 4.8,
        'bio' => 'Expert in strength training and conditioning with 10+ years of experience. Specializes in functional training and injury prevention.',
        'total_classes' => 150,
        'phone' => '(555) 123-4567'
    ],
    [
        'id' => 2,
        'full_name' => 'Sarah Chen',
        'email' => 'sarah.chen@conquergym.com',
        'specialization' => 'Yoga & Mobility',
        'certifications' => 'RYT 500 Certified',
        'experience_years' => 8,
        'rating' => 5.0,
        'bio' => 'RYT 500 certified yoga instructor with 8 years of teaching experience. Specializes in Vinyasa, Hatha, and restorative yoga.',
        'total_classes' => 200,
        'phone' => '(555) 234-5678'
    ],
    [
        'id' => 3,
        'full_name' => 'Marcus Johnson',
        'email' => 'marcus.johnson@conquergym.com',
        'specialization' => 'Bodybuilding & Nutrition',
        'certifications' => 'IFBB Pro',
        'experience_years' => 15,
        'rating' => 5.0,
        'bio' => 'IFBB Pro bodybuilder with 15 years of experience in bodybuilding and nutrition coaching. Multiple competition wins.',
        'total_classes' => 180,
        'phone' => '(555) 345-6789'
    ]
];

$totalTrainers = count($trainers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Trainers | Admin Dashboard</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        /* Additional styles for trainers management */
        .trainer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            margin-top: 1rem;
        }
        
        .trainer-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #e8e8e8;
            display: flex;
            flex-direction: column;
        }
        
        .trainer-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }
        
        .trainer-header {
            padding: 2rem 1.5rem 1.5rem;
            text-align: center;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }
        
        .trainer-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.05) 100%);
            z-index: 1;
        }
        
        .trainer-header > * {
            position: relative;
            z-index: 2;
        }
        
        .trainer-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            border: 4px solid white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .trainer-header h3 {
            font-size: 1.6rem;
            margin-bottom: 0.75rem;
            font-weight: 700;
            color: #2f3542;
        }
        
        .trainer-header p {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }
        
        .trainer-body {
            padding: 2rem 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .trainer-info {
            margin-bottom: 1.5rem;
        }
        
        .trainer-info p {
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            color: #495057;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        
        .trainer-info i {
            width: 20px;
            margin-top: 3px;
            flex-shrink: 0;
            text-align: center;
        }
        
        .trainer-stats {
            display: flex;
            justify-content: space-between;
            margin: 1.5rem 0;
            padding: 1.5rem 0;
            border-top: 1px solid #e9ecef;
            border-bottom: 1px solid #e9ecef;
            flex-shrink: 0;
        }
        
        .stat-item {
            text-align: center;
            flex: 1;
        }
        
        .stat-value {
            font-weight: 800;
            font-size: 1.8rem;
            color: #2f3542;
            margin-bottom: 0.25rem;
        }
        
        .stat-item small {
            color: #6c757d;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Button styles */
        .btn-sm {
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: #495057;
            transition: all 0.3s;
            text-decoration: none;
            font-family: inherit;
            font-weight: 600;
            border: 1px solid #dee2e6;
            flex: 1;
        }
        
        .btn-sm:hover {
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
            transform: translateY(-2px);
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .btn-sm.btn-danger {
            background: linear-gradient(135deg, #ff4757 0%, #ff2e43 100%);
            color: white;
            border-color: #ff4757;
        }
        
        .btn-sm.btn-danger:hover {
            background: linear-gradient(135deg, #ff2e43 0%, #ff1e2e 100%);
            border-color: #ff2e43;
        }
        
        .trainer-actions {
            display: flex;
            gap: 1rem;
            margin-top: auto;
            flex-shrink: 0;
        }
        
        /* Specialization tags */
        .specialization-tag {
            display: inline-block;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            background: rgba(255, 255, 255, 0.95);
            color: inherit;
            border: 2px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        /* Rating stars */
        .rating-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            margin: 1rem 0 1.5rem;
        }
        
        .rating-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: white;
            background: rgba(0,0,0,0.2);
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255,255,255,0.2);
        }
        
        .rating-stars {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .rating-stars i {
            font-size: 1.4rem;
        }
        
        /* Individual trainer colors */
        .trainer-1 .trainer-header {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        }
        
        .trainer-1 .trainer-avatar {
            background: white;
            color: #ff6b6b;
        }
        
        .trainer-1 .rating-stars i,
        .trainer-1 .trainer-info i {
            color: #ff6b6b;
        }
        
        .trainer-2 .trainer-header {
            background: linear-gradient(135deg, #2ed573 0%, #1dd1a1 100%);
        }
        
        .trainer-2 .trainer-avatar {
            background: white;
            color: #2ed573;
        }
        
        .trainer-2 .rating-stars i,
        .trainer-2 .trainer-info i {
            color: #2ed573;
        }
        
        .trainer-3 .trainer-header {
            background: linear-gradient(135deg, #3742fa 0%, #5352ed 100%);
        }
        
        .trainer-3 .trainer-avatar {
            background: white;
            color: #3742fa;
        }
        
        .trainer-3 .rating-stars i,
        .trainer-3 .trainer-info i {
            color: #3742fa;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .trainer-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .trainer-grid {
                grid-template-columns: 1fr;
            }
            
            .trainer-card {
                max-width: 450px;
                margin: 0 auto;
            }
        }
        
        @media (max-width: 480px) {
            .trainer-actions {
                flex-direction: column;
            }
            
            .trainer-stats {
                flex-direction: column;
                gap: 1rem;
            }
            
            .stat-item {
                flex: none;
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
    </style>
</head>
<body>
    <?php include 'admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search trainers by name, specialization..." id="searchInput">
            </div>
            <div class="top-bar-actions">
                <button class="btn-primary" onclick="window.location.href='admin-add-trainer.php'">
                    <i class="fas fa-plus"></i> Add New Trainer
                </button>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>Manage Trainers</h1>
                    <p>Professional fitness trainers at CONQUER Gym</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3><?php echo $totalTrainers; ?></h3>
                        <p>Total Trainers</p>
                    </div>
                </div>
            </div>

            <div class="content-card">
                <div class="card-header">
                    <h3>Trainers Directory</h3>
                    <span class="btn-secondary" style="padding: 0.75rem 1.25rem; font-size: 0.95rem;">
                        <i class="fas fa-sort-alpha-down"></i> Sorted by Name
                    </span>
                </div>
                <div class="card-body">
                    <?php if($totalTrainers > 0): ?>
                        <div class="trainer-grid" id="trainerGrid">
                            <?php foreach($trainers as $index => $trainer): 
                                $rating = floatval($trainer['rating']);
                                $fullStars = floor($rating);
                                $hasHalfStar = ($rating - $fullStars) >= 0.3;
                                $trainerClass = 'trainer-' . ($index + 1);
                            ?>
                                <div class="trainer-card <?php echo $trainerClass; ?>" data-trainer-id="<?php echo $trainer['id']; ?>">
                                    <div class="trainer-header">
                                        <div class="trainer-avatar">
                                            <i class="fas fa-user-tie"></i>
                                        </div>
                                        <h3><?php echo htmlspecialchars($trainer['full_name']); ?></h3>
                                        <span class="specialization-tag"><?php echo htmlspecialchars($trainer['specialization']); ?></span>
                                        
                                        <!-- Rating display -->
                                        <div class="rating-container">
                                            <div class="rating-value"><?php echo number_format($rating, 1); ?>/5.0</div>
                                            <div class="rating-stars">
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <?php if($i <= $fullStars): ?>
                                                        <i class="fas fa-star"></i>
                                                    <?php elseif($i == $fullStars + 1 && $hasHalfStar): ?>
                                                        <i class="fas fa-star-half-alt"></i>
                                                    <?php else: ?>
                                                        <i class="far fa-star"></i>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="trainer-body">
                                        <div class="trainer-info">
                                            <p><i class="fas fa-envelope"></i> <span><?php echo htmlspecialchars($trainer['email']); ?></span></p>
                                            <p><i class="fas fa-certificate"></i> <span><strong>Certifications:</strong> <?php echo htmlspecialchars($trainer['certifications']); ?></span></p>
                                            <p><i class="fas fa-history"></i> <span><strong>Experience:</strong> <?php echo $trainer['experience_years']; ?>+ years</span></p>
                                            <p><i class="fas fa-info-circle"></i> <span><?php echo htmlspecialchars($trainer['bio']); ?></span></p>
                                        </div>
                                        
                                        <div class="trainer-stats">
                                            <div class="stat-item">
                                                <div class="stat-value"><?php echo $trainer['experience_years']; ?>+</div>
                                                <small>Years</small>
                                            </div>
                                            <div class="stat-item">
                                                <div class="stat-value"><?php echo $trainer['total_classes']; ?></div>
                                                <small>Classes</small>
                                            </div>
                                            <div class="stat-item">
                                                <div class="stat-value"><?php echo number_format($rating, 1); ?></div>
                                                <small>Rating</small>
                                            </div>
                                        </div>
                                        
                                        <div class="trainer-actions">
                                            <a href="admin-trainer-view.php?id=<?php echo $trainer['id']; ?>" class="btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="admin-edit-trainer.php?id=<?php echo $trainer['id']; ?>" class="btn-sm">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <button class="btn-sm btn-danger" onclick="confirmDelete(<?php echo $trainer['id']; ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users fa-4x" style="color: #e9ecef; margin-bottom: 1.5rem;"></i>
                            <h3 style="color: #495057; margin-bottom: 1rem;">No Trainers Found</h3>
                            <p style="color: #6c757d; margin-bottom: 2rem;">Add your first trainer to get started</p>
                            <button class="btn-primary" onclick="window.location.href='admin-add-trainer.php'">
                                <i class="fas fa-plus"></i> Add Your First Trainer
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(trainerId) {
            if(confirm('Are you sure you want to delete trainer #' + trainerId + '? This action cannot be undone.')) {
                // In a real app, you would make an AJAX call or redirect to delete script
                alert('Trainer #' + trainerId + ' would be deleted (demo only).');
                // window.location.href = 'admin-delete-trainer.php?id=' + trainerId;
            }
        }
        
        // Live search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            const trainerCards = document.querySelectorAll('.trainer-card');
            const trainerGrid = document.getElementById('trainerGrid');
            let visibleCount = 0;
            
            trainerCards.forEach(card => {
                const trainerName = card.querySelector('h3').textContent.toLowerCase();
                const specialization = card.querySelector('.specialization-tag').textContent.toLowerCase();
                const email = card.querySelector('.trainer-info p:nth-child(1) span').textContent.toLowerCase();
                const certifications = card.querySelector('.trainer-info p:nth-child(2) span').textContent.toLowerCase();
                const bio = card.querySelector('.trainer-info p:nth-child(4) span').textContent.toLowerCase();
                
                if (trainerName.includes(searchTerm) || 
                    specialization.includes(searchTerm) || 
                    email.includes(searchTerm) ||
                    certifications.includes(searchTerm) ||
                    bio.includes(searchTerm)) {
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
                        <i class="fas fa-search fa-3x" style="color: #e9ecef; margin-bottom: 1rem;"></i>
                        <h3 style="color: #495057; margin-bottom: 1rem;">No Matching Trainers</h3>
                        <p style="color: #6c757d;">No trainers found matching "<strong>${searchTerm}</strong>"</p>
                        <p style="color: #6c757d; margin-top: 1rem; font-size: 0.9rem;">Try searching by name, specialization, or certification</p>
                    `;
                    trainerGrid.parentNode.insertBefore(noResultsMsg, trainerGrid.nextSibling);
                }
            } else if (noResultsMsg) {
                noResultsMsg.remove();
            }
        });
    </script>
</body>
</html>