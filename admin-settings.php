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
$success = '';
$error = '';

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['save_general'])) {
        // Store settings in database
        $gymName = htmlspecialchars($_POST['gym_name']);
        $gymAddress = htmlspecialchars($_POST['gym_address']);
        $contactEmail = filter_var($_POST['contact_email'], FILTER_SANITIZE_EMAIL);
        $contactPhone = htmlspecialchars($_POST['contact_phone']);
        
        try {
            $stmt = $pdo->prepare("UPDATE gym_settings SET gym_name = ?, gym_address = ?, contact_email = ?, contact_phone = ? WHERE id = 1");
            $stmt->execute([$gymName, $gymAddress, $contactEmail, $contactPhone]);
            $success = "General settings updated successfully!";
        } catch (PDOException $e) {
            // If table doesn't exist, create it
            if($e->getCode() == '42S02') {
                try {
                    $pdo->exec("CREATE TABLE gym_settings (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        gym_name VARCHAR(255) NOT NULL,
                        gym_address TEXT,
                        contact_email VARCHAR(255),
                        contact_phone VARCHAR(50),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )");
                    
                    $stmt = $pdo->prepare("INSERT INTO gym_settings (gym_name, gym_address, contact_email, contact_phone) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$gymName, $gymAddress, $contactEmail, $contactPhone]);
                    $success = "General settings saved successfully!";
                } catch (PDOException $e2) {
                    $error = "Failed to save settings: " . $e2->getMessage();
                }
            } else {
                $error = "Failed to save settings: " . $e->getMessage();
            }
        }
    }
    
    if(isset($_POST['change_password'])) {
        // Change admin password with validation
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if(empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = "All password fields are required!";
        } elseif($newPassword !== $confirmPassword) {
            $error = "New passwords do not match!";
        } elseif(strlen($newPassword) < 8) {
            $error = "Password must be at least 8 characters long!";
        } else {
            try {
                // Verify current password
                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                
                if(!$user || !password_verify($currentPassword, $user['password_hash'])) {
                    $error = "Current password is incorrect!";
                } else {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
                    $success = "Password changed successfully!";
                }
            } catch (PDOException $e) {
                $error = "Failed to change password: " . $e->getMessage();
            }
        }
    }
    
    if(isset($_POST['save_business'])) {
        $hours = $_POST['hours'] ?? [];
        try {
            $hoursJson = json_encode($hours);
            // Store in database
            $stmt = $pdo->prepare("UPDATE gym_settings SET business_hours = ? WHERE id = 1");
            $stmt->execute([$hoursJson]);
            $success = "Business hours updated successfully!";
        } catch (PDOException $e) {
            $error = "Failed to save business hours: " . $e->getMessage();
        }
    }
    
    if(isset($_POST['save_membership'])) {
        $plans = $_POST['plans'] ?? [];
        try {
            $plansJson = json_encode($plans);
            // Store in database
            $stmt = $pdo->prepare("UPDATE gym_settings SET membership_plans = ? WHERE id = 1");
            $stmt->execute([$plansJson]);
            $success = "Membership plans updated successfully!";
        } catch (PDOException $e) {
            $error = "Failed to save membership plans: " . $e->getMessage();
        }
    }
}

// Load settings from database
try {
    $stmt = $pdo->query("SELECT * FROM gym_settings WHERE id = 1");
    $settings = $stmt->fetch();
    
    if($settings) {
        $gymName = htmlspecialchars($settings['gym_name'] ?? 'CONQUER Gym');
        $gymAddress = htmlspecialchars($settings['gym_address'] ?? '');
        $contactEmail = htmlspecialchars($settings['contact_email'] ?? 'admin@conquergym.com');
        $contactPhone = htmlspecialchars($settings['contact_phone'] ?? '');
        
        // Decode JSON data
        $businessHours = json_decode($settings['business_hours'] ?? '[]', true);
        $membershipPlans = json_decode($settings['membership_plans'] ?? '[]', true);
    } else {
        // Default values
        $gymName = 'CONQUER Gym';
        $gymAddress = '';
        $contactEmail = 'admin@conquergym.com';
        $contactPhone = '';
        $businessHours = [];
        $membershipPlans = [];
    }
} catch (PDOException $e) {
    // Use defaults if table doesn't exist
    $gymName = 'CONQUER Gym';
    $gymAddress = '';
    $contactEmail = 'admin@conquergym.com';
    $contactPhone = '';
    $businessHours = [];
    $membershipPlans = [];
}

// Set default business hours if empty
if(empty($businessHours)) {
    $businessHours = [
        'Monday' => ['09:00', '22:00'],
        'Tuesday' => ['09:00', '22:00'],
        'Wednesday' => ['09:00', '22:00'],
        'Thursday' => ['09:00', '22:00'],
        'Friday' => ['09:00', '22:00'],
        'Saturday' => ['08:00', '20:00'],
        'Sunday' => ['08:00', '18:00']
    ];
}

// Set default membership plans if empty
if(empty($membershipPlans)) {
    $membershipPlans = [
        'Basic' => ['price' => 49.99, 'features' => 'Access to gym equipment|Locker room access'],
        'Premium' => ['price' => 79.99, 'features' => 'All Basic features|Group classes access|Personal trainer consultation'],
        'Ultimate' => ['price' => 119.99, 'features' => 'All Premium features|Unlimited classes|Nutrition planning|Monthly body analysis']
    ];
}
?>  
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Admin Dashboard</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        /* Settings Page Specific Styles */
        .settings-container {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: 2rem;
            margin-top: 1.5rem;
        }
        
        .settings-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        
        .settings-tab {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            background: var(--light-color);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            color: var(--text-color);
            transition: var(--transition);
            font-size: 0.9rem;
        }
        
        .settings-tab:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        .settings-tab.active {
            background: var(--gradient-primary);
            color: white;
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(255, 71, 87, 0.3);
        }
        
        .settings-tab i {
            font-size: 1rem;
        }
        
        .settings-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        
        .settings-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .settings-content h2 {
            color: var(--dark-color);
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border-color);
        }
        
        /* Settings Form */
        .settings-form {
            max-width: 800px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 0.9rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            transition: var(--transition);
            background: var(--white);
            font-family: 'Poppins', sans-serif;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.1);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        /* Business Hours Grid */
        .hours-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .hour-item {
            background: var(--light-color);
            padding: 1.25rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
        }
        
        .hour-item label {
            display: block;
            margin-bottom: 0.75rem;
            color: var(--dark-color);
            font-size: 0.95rem;
        }
        
        .hour-item input {
            flex: 1;
            padding: 0.5rem;
            border: 1px solid #cbd5e0;
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            font-family: 'Poppins', sans-serif;
        }
        
        .hour-item span {
            display: flex;
            align-items: center;
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        /* Membership Plans */
        .plan-card {
            background: linear-gradient(135deg, var(--light-color) 0%, #f1f5f9 100%);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: var(--transition);
        }
        
        .plan-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .plan-card .form-group:last-child {
            margin-bottom: 0;
        }
        
        /* Notification Checkboxes */
        .settings-form .form-group label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            padding: 0.75rem;
            border-radius: var(--radius-md);
            transition: var(--transition);
            margin-bottom: 0;
        }
        
        .settings-form .form-group label:hover {
            background: #f7fafc;
        }
        
        .settings-form .form-group input[type="checkbox"] {
            width: auto;
            transform: scale(1.2);
            accent-color: var(--primary-color);
        }
        
        /* Danger Zone */
        .danger-zone {
            margin-top: 3rem;
            padding: 2rem;
            background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%);
            border: 2px solid var(--danger-color);
            border-radius: var(--radius-lg);
        }
        
        .danger-zone h3 {
            color: var(--danger-color);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .danger-zone p {
            color: #9b2c2c;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .danger-zone div {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        /* Alert Messages */
        .alert-message {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.5s ease;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
            color: #22543d;
            border-left-color: var(--success-color);
        }
        
        .alert-error {
            background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
            color: #742a2a;
            border-left-color: var(--danger-color);
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .settings-container {
                padding: 1rem;
                margin: 1rem;
            }
            
            .settings-tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
                padding-bottom: 0.5rem;
            }
            
            .settings-tab {
                white-space: nowrap;
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .hours-grid {
                grid-template-columns: 1fr;
            }
            
            .danger-zone {
                padding: 1.5rem;
            }
            
            .danger-zone div {
                flex-direction: column;
            }
            
            .btn-danger {
                width: 100%;
                justify-content: center;
                margin-bottom: 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .settings-content h2 {
                font-size: 1.25rem;
            }
            
            .btn-primary,
            .btn-secondary {
                width: 100%;
                justify-content: center;
                margin-bottom: 0.5rem;
            }
        }
        
        /* Time Input Styling */
        input[type="time"] {
            padding: 0.5rem;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php include 'admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search settings...">
            </div>
        </div>

        <div class="dashboard-content">
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>Settings</h1>
                    <p>Configure your gym management system</p>
                </div>
            </div>

            <!-- Messages -->
            <?php if(!empty($success)): ?>
                <div class="alert-message alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <?php if(!empty($error)): ?>
                <div class="alert-message alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="settings-container">
                <!-- Settings Tabs -->
                <div class="settings-tabs">
                    <button class="settings-tab active" onclick="showTab('general')">
                        <i class="fas fa-cog"></i> General
                    </button>
                    <button class="settings-tab" onclick="showTab('business')">
                        <i class="fas fa-clock"></i> Business Hours
                    </button>
                    <button class="settings-tab" onclick="showTab('membership')">
                        <i class="fas fa-credit-card"></i> Membership Plans
                    </button>
                    <button class="settings-tab" onclick="showTab('notifications')">
                        <i class="fas fa-bell"></i> Notifications
                    </button>
                    <button class="settings-tab" onclick="showTab('security')">
                        <i class="fas fa-shield-alt"></i> Security
                    </button>
                    <button class="settings-tab" onclick="showTab('backup')">
                        <i class="fas fa-database"></i> Backup
                    </button>
                </div>

                <!-- General Settings -->
                <div id="general" class="settings-content active">
                    <h2>General Settings</h2>
                    <form method="POST" class="settings-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Gym Name</label>
                                <input type="text" name="gym_name" value="<?php echo $gymName; ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Contact Phone</label>
                                <input type="tel" name="contact_phone" value="<?php echo $contactPhone; ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Gym Address</label>
                            <textarea name="gym_address"><?php echo $gymAddress; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Contact Email</label>
                            <input type="email" name="contact_email" value="<?php echo $contactEmail; ?>" required>
                        </div>
                        <button type="submit" name="save_general" class="btn-primary">
                            <i class="fas fa-save"></i> Save General Settings
                        </button>
                    </form>
                </div>

                <!-- Business Hours -->
                <div id="business" class="settings-content">
                    <h2>Business Hours</h2>
                    <form method="POST" class="settings-form">
                        <div class="hours-grid">
                            <?php foreach($businessHours as $day => $hours): ?>
                                <div class="hour-item">
                                    <label><strong><?php echo $day; ?></strong></label>
                                    <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                                        <input type="time" name="hours[<?php echo $day; ?>][]" value="<?php echo $hours[0]; ?>">
                                        <span>to</span>
                                        <input type="time" name="hours[<?php echo $day; ?>][]" value="<?php echo $hours[1]; ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" name="save_business" class="btn-primary">
                            <i class="fas fa-save"></i> Save Business Hours
                        </button>
                    </form>
                </div>

                <!-- Membership Plans -->
                <div id="membership" class="settings-content">
                    <h2>Membership Plans</h2>
                    <form method="POST" class="settings-form">
                        <?php foreach($membershipPlans as $planName => $planData): 
                            $featuresText = is_array($planData['features']) ? implode("\n", $planData['features']) : str_replace('|', "\n", $planData['features']);
                        ?>
                            <div class="plan-card">
                                <div class="form-group">
                                    <label>Plan Name</label>
                                    <input type="text" name="plans[<?php echo $planName; ?>][name]" value="<?php echo $planName; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Monthly Price ($)</label>
                                    <input type="number" step="0.01" name="plans[<?php echo $planName; ?>][price]" value="<?php echo $planData['price']; ?>" required min="0">
                                </div>
                                <div class="form-group">
                                    <label>Features (one per line)</label>
                                    <textarea name="plans[<?php echo $planName; ?>][features]" rows="4"><?php echo $featuresText; ?></textarea>
                                    <small class="text-light">Enter each feature on a new line or separated by |</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <button type="submit" name="save_membership" class="btn-primary">
                            <i class="fas fa-save"></i> Save Membership Plans
                        </button>
                    </form>
                </div>

                <!-- Notifications -->
                <div id="notifications" class="settings-content">
                    <h2>Notification Settings</h2>
                    <form class="settings-form">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" checked> Email notifications for new members
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" checked> Payment reminders
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox"> Class cancellation alerts
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" checked> Maintenance alerts
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox"> Daily summary report
                            </label>
                        </div>
                        <button type="button" class="btn-primary">
                            <i class="fas fa-save"></i> Save Notification Settings
                        </button>
                    </form>
                </div>

                <!-- Security -->
                <div id="security" class="settings-content">
                    <h2>Security Settings</h2>
                    <form method="POST" class="settings-form">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" required minlength="8">
                                <small class="text-light">Minimum 8 characters</small>
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" required minlength="8">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Two-Factor Authentication</label>
                            <select>
                                <option>Disabled</option>
                                <option>Email</option>
                                <option>Authenticator App</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Session Timeout (minutes)</label>
                            <input type="number" value="30" min="5" max="240">
                        </div>
                        <button type="submit" name="change_password" class="btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>
                </div>

                <!-- Backup -->
                <div id="backup" class="settings-content">
                    <h2>Backup & Restore</h2>
                    <div class="settings-form">
                        <div class="form-group">
                            <label>Automatic Backups</label>
                            <select>
                                <option>Daily</option>
                                <option>Weekly</option>
                                <option>Monthly</option>
                                <option>Disabled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Backup Retention</label>
                            <input type="number" value="30" min="1" max="365"> days
                        </div>
                        <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                            <button type="button" class="btn-primary" onclick="createBackup()">
                                <i class="fas fa-database"></i> Create Backup Now
                            </button>
                            <button type="button" class="btn-secondary">
                                <i class="fas fa-upload"></i> Restore Backup
                            </button>
                        </div>
                        <div class="form-group" style="margin-top: 2rem;">
                            <label>Recent Backups</label>
                            <div style="background: var(--light-color); padding: 1rem; border-radius: var(--radius-md);">
                                <p>No backups found.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="danger-zone">
                    <h3><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
                    <p>These actions are irreversible. Proceed with caution.</p>
                    <div>
                        <button type="button" class="btn-danger" onclick="clearAllData()">
                            <i class="fas fa-trash"></i> Clear All Test Data
                        </button>
                        <button type="button" class="btn-danger" onclick="resetSystem()">
                            <i class="fas fa-redo"></i> Reset System
                        </button>
                        <button type="button" class="btn-danger" onclick="deleteAccount()">
                            <i class="fas fa-user-slash"></i> Delete Admin Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.settings-content').forEach(tab => {
                tab.classList.remove('active');
            });
            // Remove active class from all buttons
            document.querySelectorAll('.settings-tab').forEach(button => {
                button.classList.remove('active');
            });
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            // Set active button
            event.currentTarget.classList.add('active');
        }
        
        function createBackup() {
            if(confirm('Create a database backup now?')) {
                window.location.href = 'admin-backup-now.php';
            }
        }
        
        function clearAllData() {
            if(confirm('WARNING: This will delete ALL test data. This action cannot be undone. Continue?')) {
                window.location.href = 'admin-clear-data.php';
            }
        }
        
        function resetSystem() {
            if(confirm('Reset system to factory settings? All data will be lost.')) {
                window.location.href = 'admin-reset-system.php';
            }
        }
        
        function deleteAccount() {
            if(confirm('Delete your admin account? You will lose all access.')) {
                window.location.href = 'admin-delete-account.php';
            }
        }
        
        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.alert-message');
            messages.forEach(msg => {
                msg.style.transition = 'opacity 0.5s ease';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>