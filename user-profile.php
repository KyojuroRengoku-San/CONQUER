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
    
    // Get member info
    $member = null;
    try {
        $memberStmt = $pdo->prepare("SELECT * FROM gym_members WHERE Email = ?");
        $memberStmt->execute([$user['email']]);
        $member = $memberStmt->fetch();
    } catch(PDOException $e) {
        error_log("Member info error: " . $e->getMessage());
    }
    
    // Handle profile update
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        if(isset($_POST['update_profile'])) {
            $full_name = $_POST['full_name'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $address = $_POST['address'] ?? '';
            $emergency_contact = $_POST['emergency_contact'] ?? '';
            $emergency_phone = $_POST['emergency_phone'] ?? '';
            
            // Update users table
            $updateStmt = $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?");
            if($updateStmt->execute([$full_name, $user_id])) {
                $user['full_name'] = $full_name;
                
                // Update gym_members table if exists
                if($member) {
                    $updateMemberStmt = $pdo->prepare("
                        UPDATE gym_members 
                        SET Name = ?, ContactNumber = ? 
                        WHERE Email = ?
                    ");
                    $updateMemberStmt->execute([$full_name, $phone, $user['email']]);
                }
                
                $message = "Profile updated successfully!";
                $success = true;
            } else {
                $message = "Error updating profile. Please try again.";
                $success = false;
            }
        }
        
        // Handle password change
        if(isset($_POST['change_password'])) {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if(empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $message = "All password fields are required!";
                $success = false;
            } elseif($new_password !== $confirm_password) {
                $message = "New passwords do not match!";
                $success = false;
            } elseif(strlen($new_password) < 6) {
                $message = "Password must be at least 6 characters long!";
                $success = false;
            } else {
                // Verify current password
                if(password_verify($current_password, $user['password_hash'])) {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $passStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    if($passStmt->execute([$new_hash, $user_id])) {
                        $message = "Password changed successfully!";
                        $success = true;
                    } else {
                        $message = "Error changing password. Please try again.";
                        $success = false;
                    }
                } else {
                    $message = "Current password is incorrect!";
                    $success = false;
                }
            }
        }
    }
    
} catch(PDOException $e) {
    $message = "Database error. Please try again later.";
    error_log("Profile page error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | CONQUER Gym</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    
    <style>
        .profile-content {
            padding: 2rem;
        }
        
        .profile-section {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: bold;
        }
        
        .profile-info h2 {
            margin: 0 0 0.5rem 0;
            color: var(--dark-color);
        }
        
        .profile-info p {
            color: var(--gray);
            margin: 0.25rem 0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .info-item {
            background: var(--light-bg);
            padding: 1.5rem;
            border-radius: var(--radius-sm);
            border-left: 4px solid var(--primary-color);
        }
        
        .info-item h4 {
            margin: 0 0 0.5rem 0;
            color: var(--dark-color);
        }
        
        .info-item p {
            margin: 0;
            color: var(--gray);
        }
        
        .section-title {
            margin: 0 0 1.5rem 0;
            color: var(--dark-color);
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--light-color);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--light-color);
            border-radius: var(--radius-sm);
            background: var(--white);
            color: var(--dark-color);
            font-family: 'Poppins', sans-serif;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .message {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            margin-bottom: 2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message.success {
            background: rgba(46, 213, 115, 0.1);
            color: var(--success);
            border: 2px solid var(--success);
        }
        
        .message.error {
            background: rgba(255, 56, 56, 0.1);
            color: var(--danger);
            border: 2px solid var(--danger);
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
        }
        
        .stat-card h3 {
            margin: 0 0 0.5rem 0;
            color: var(--primary-color);
            font-size: 2rem;
        }
        
        .stat-card p {
            margin: 0;
            color: var(--gray);
        }
        
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
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
                <input type="text" placeholder="Search...">
            </div>
            <div class="top-bar-actions">
                <button class="btn-notification">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </button>
                <button class="btn-primary" onclick="window.location.href='user-bookclass.php'">
                    <i class="fas fa-plus"></i>
                    Book Class
                </button>
            </div>
        </div>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>My Profile 👤</h1>
                    <p>Manage your personal information and account settings</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3><?php echo isset($member['MembershipPlan']) ? htmlspecialchars($member['MembershipPlan']) : 'Basic'; ?></h3>
                        <p>Membership</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo isset($member['JoinDate']) ? date('Y', strtotime($member['JoinDate'])) : date('Y'); ?></h3>
                        <p>Member Since</p>
                    </div>
                </div>
            </div>

            <?php if($message): ?>
                <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                    <i class="fas <?php echo $success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="profile-content">
                <!-- Profile Overview -->
                <div class="profile-section">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php echo isset($user['full_name']) ? strtoupper(substr($user['full_name'], 0, 1)) : 'U'; ?>
                        </div>
                        <div class="profile-info">
                            <h2><?php echo isset($user['full_name']) ? htmlspecialchars($user['full_name']) : 'User'; ?></h2>
                            <p><i class="fas fa-envelope"></i> <?php echo isset($user['email']) ? htmlspecialchars($user['email']) : 'No email'; ?></p>
                            <p><i class="fas fa-id-badge"></i> Member ID: <?php echo isset($user['id']) ? $user['id'] : 'N/A'; ?></p>
                            <p><i class="fas fa-user-tag"></i> <?php echo isset($member['MembershipStatus']) ? htmlspecialchars($member['MembershipStatus']) : 'Active'; ?> Member</p>
                        </div>
                    </div>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3><?php echo isset($member['Age']) ? $member['Age'] : '--'; ?></h3>
                            <p>Age</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo isset($member['MembershipPlan']) ? htmlspecialchars($member['MembershipPlan']) : 'Basic'; ?></h3>
                            <p>Membership Plan</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo isset($member['JoinDate']) ? date('M Y', strtotime($member['JoinDate'])) : '--'; ?></h3>
                            <p>Joined</p>
                        </div>
                        <div class="stat-card">
                            <h3><?php echo isset($member['MembershipStatus']) ? htmlspecialchars($member['MembershipStatus']) : 'Active'; ?></h3>
                            <p>Status</p>
                        </div>
                    </div>
                </div>

                <!-- Edit Profile Form -->
                <div class="profile-section">
                    <h3 class="section-title">Edit Personal Information</h3>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" class="form-control" 
                                       value="<?php echo isset($user['full_name']) ? htmlspecialchars($user['full_name']) : ''; ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" class="form-control" 
                                       value="<?php echo isset($user['email']) ? htmlspecialchars($user['email']) : ''; ?>" 
                                       disabled>
                                <small style="color: var(--gray);">Email cannot be changed</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-control" 
                                       value="<?php echo isset($member['ContactNumber']) ? htmlspecialchars($member['ContactNumber']) : ''; ?>" 
                                       placeholder="Enter phone number">
                            </div>
                            
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" class="form-control" 
                                       value="<?php echo isset($user['username']) ? htmlspecialchars($user['username']) : ''; ?>" 
                                       disabled>
                                <small style="color: var(--gray);">Username cannot be changed</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="emergency_contact">Emergency Contact Name</label>
                                <input type="text" id="emergency_contact" name="emergency_contact" class="form-control" 
                                       placeholder="Name of emergency contact">
                            </div>
                            
                            <div class="form-group">
                                <label for="emergency_phone">Emergency Contact Phone</label>
                                <input type="tel" id="emergency_phone" name="emergency_phone" class="form-control" 
                                       placeholder="Emergency contact phone">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="3" 
                                      placeholder="Enter your address"></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_profile" class="btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="reset" class="btn-secondary">
                                <i class="fas fa-undo"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="profile-section">
                    <h3 class="section-title">Change Password</h3>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="current_password">Current Password *</label>
                                <input type="password" id="current_password" name="current_password" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">New Password *</label>
                                <input type="password" id="new_password" name="new_password" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password *</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="change_password" class="btn-primary">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Account Information -->
                <div class="profile-section">
                    <h3 class="section-title">Account Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <h4><i class="fas fa-user-circle"></i> Account Type</h4>
                            <p><?php echo isset($user['user_type']) ? ucfirst(htmlspecialchars($user['user_type'])) : 'Member'; ?></p>
                        </div>
                        
                        <div class="info-item">
                            <h4><i class="fas fa-calendar-alt"></i> Member Since</h4>
                            <p><?php echo isset($user['created_at']) ? date('F j, Y', strtotime($user['created_at'])) : 'N/A'; ?></p>
                        </div>
                        
                        <div class="info-item">
                            <h4><i class="fas fa-sign-in-alt"></i> Last Login</h4>
                            <p><?php echo isset($user['last_login']) ? date('F j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?></p>
                        </div>
                        
                        <div class="info-item">
                            <h4><i class="fas fa-shield-alt"></i> Account Status</h4>
                            <p>
                                <span class="status-badge <?php echo isset($user['is_active']) && $user['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo isset($user['is_active']) && $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="window.location.href='user-dashboard.php'">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </button>
                        <button type="button" class="btn-danger" onclick="confirmDelete()">
                            <i class="fas fa-trash"></i> Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete() {
            if(confirm("Are you sure you want to delete your account? This action cannot be undone!")) {
                // Redirect to delete account page
                window.location.href = 'delete-account.php';
            }
        }
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    // Check for password change form
                    if(this.querySelector('[name="change_password"]')) {
                        const newPass = document.getElementById('new_password').value;
                        const confirmPass = document.getElementById('confirm_password').value;
                        
                        if(newPass !== confirmPass) {
                            e.preventDefault();
                            alert('New passwords do not match!');
                            return false;
                        }
                        
                        if(newPass.length < 6) {
                            e.preventDefault();
                            alert('Password must be at least 6 characters long!');
                            return false;
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>