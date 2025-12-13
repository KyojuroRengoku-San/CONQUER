<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get trainers - CORRECTED column names to match your database
$trainers = $pdo->query("
    SELECT t.*, u.full_name, u.email,
    t.specialty as specialization, 
    t.certification as certifications,
    t.years_experience as experience_years,
    (SELECT COUNT(*) FROM classes c WHERE c.trainer_id = t.id) as total_classes
    FROM trainers t
    JOIN users u ON t.user_id = u.id
    ORDER BY u.full_name
")->fetchAll();

$totalTrainers = count($trainers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Trainers | Admin Dashboard</title>
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        .trainer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        .trainer-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .trainer-card:hover {
            transform: translateY(-5px);
        }
        .trainer-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        .trainer-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-size: 2rem;
        }
        .trainer-body {
            padding: 1.5rem;
        }
        .trainer-specialty {
            display: inline-block;
            background: var(--light-color);
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
            margin: 0.5rem 0;
        }
        .trainer-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-weight: 700;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <?php include 'admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search trainers...">
            </div>
            <div class="top-bar-actions">
                <button class="btn-primary" onclick="window.location.href='admin-add-trainer.php'">
                    <i class="fas fa-plus"></i> Add Trainer
                </button>
            </div>
</div>

        <div class="dashboard-content">
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>Manage Trainers</h1>
                    <p>Professional fitness trainers at your gym</p>
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
                </div>
                <div class="card-body">
                    <?php if($totalTrainers > 0): ?>
                        <div class="trainer-grid">
                            <?php foreach($trainers as $trainer): ?>
                                <div class="trainer-card">
                                    <div class="trainer-header">
                                        <div class="trainer-avatar">
                                            <i class="fas fa-user-tie"></i>
                                        </div>
                                        <h3><?php echo htmlspecialchars($trainer['full_name']); ?></h3>
                                        <p><?php echo htmlspecialchars($trainer['specialization']); ?></p>
                                    </div>
                                    <div class="trainer-body">
                                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($trainer['email']); ?></p>
                                        <p><i class="fas fa-certificate"></i> <?php echo htmlspecialchars($trainer['certifications']); ?></p>
                                        <?php if(!empty($trainer['bio'])): ?>
                                            <p><i class="fas fa-info-circle"></i> <?php echo substr(htmlspecialchars($trainer['bio']), 0, 100); ?>...</p>
                                        <?php endif; ?>
                                        
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
                                                <div class="stat-value"><?php echo $trainer['rating']; ?></div>
                                                <small>Rating</small>
                                            </div>
                                        </div>
                                        
                                        <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                                            <button class="btn-sm" onclick="window.location.href='admin-trainer-view.php?id=<?php echo $trainer['id']; ?>'">
                                                View Profile
                                            </button>
                                            <button class="btn-sm" onclick="window.location.href='admin-edit-trainer.php?id=<?php echo $trainer['id']; ?>'">
                                                Edit
                                            </button>
                                            <button class="btn-sm btn-danger" onclick="confirmDelete(<?php echo $trainer['id']; ?>)">
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="empty-state">No trainers found</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(trainerId) {
            if(confirm('Are you sure you want to remove this trainer?')) {
                window.location.href = 'admin-delete-trainer.php?id=' + trainerId;
            }
        }
    </script>
</body>
</html>