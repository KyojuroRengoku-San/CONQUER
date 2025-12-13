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
        // Store settings in database if you have a settings table
        // For now, store in session
        $_SESSION['gym_settings'] = [
            'gym_name' => htmlspecialchars($_POST['gym_name']),
            'gym_address' => htmlspecialchars($_POST['gym_address']),
            'contact_email' => filter_var($_POST['contact_email'], FILTER_SANITIZE_EMAIL),
            'contact_phone' => htmlspecialchars($_POST['contact_phone'])
        ];
        $success = "General settings updated successfully! (Note: Stored in session only)";
    }
    
    if(isset($_POST['change_password'])) {
        // Change admin password with validation
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if(empty($newPassword) || empty($confirmPassword)) {
            $error = "Password fields cannot be empty!";
        } elseif($newPassword !== $confirmPassword) {
            $error = "Passwords do not match!";
        } elseif(strlen($newPassword) < 8) {
            $error = "Password must be at least 8 characters long!";
        } else {
            try {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
                $success = "Password changed successfully!";
            } catch (PDOException $e) {
                $error = "Failed to change password: " . $e->getMessage();
            }
        }
    }
}

// Load settings with defaults
$gymName = $_SESSION['gym_settings']['gym_name'] ?? 'CONQUER Gym';
$gymAddress = $_SESSION['gym_settings']['gym_address'] ?? '';
$contactEmail = $_SESSION['gym_settings']['contact_email'] ?? 'admin@conquergym.com';
$contactPhone = $_SESSION['gym_settings']['contact_phone'] ?? '';

$businessHours = $_SESSION['business_hours'] ?? [
    'Monday' => ['09:00', '22:00'],
    'Tuesday' => ['09:00', '22:00'],
    'Wednesday' => ['09:00', '22:00'],
    'Thursday' => ['09:00', '22:00'],
    'Friday' => ['09:00', '22:00'],
    'Saturday' => ['08:00', '20:00'],
    'Sunday' => ['08:00', '18:00']
];

$membershipPlans = $_SESSION['membership_plans'] ?? [
    'Basic' => ['price' => 49.99, 'features' => ['Access to gym equipment', 'Locker room access']],
    'Premium' => ['price' => 79.99, 'features' => ['All Basic features', 'Group classes access', 'Personal trainer consultation']],
    'Ultimate' => ['price' => 119.99, 'features' => ['All Premium features', 'Unlimited classes', 'Nutrition planning', 'Monthly body analysis']]
];
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
            <?php if(isset($success)): ?>
                <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <?php if(isset($error)): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
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
                                <input type="text" name="gym_name" value="<?php echo htmlspecialchars($gymName); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Contact Phone</label>
                                <input type="tel" name="contact_phone" value="<?php echo htmlspecialchars($contactPhone); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Gym Address</label>
                            <textarea name="gym_address"><?php echo htmlspecialchars($gymAddress); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Contact Email</label>
                            <input type="email" name="contact_email" value="<?php echo htmlspecialchars($contactEmail); ?>" required>
                        </div>
                        <button type="submit" name="save_general" class="btn-primary">
                            Save General Settings
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
                            Save Business Hours
                        </button>
                    </form>
                </div>

                <!-- Membership Plans -->
                <div id="membership" class="settings-content">
                    <h2>Membership Plans</h2>
                    <form method="POST" class="settings-form">
                        <?php foreach($membershipPlans as $planName => $planData): ?>
                            <div class="plan-card">
                                <div class="form-group">
                                    <label>Plan Name</label>
                                    <input type="text" name="plans[<?php echo $planName; ?>][name]" value="<?php echo $planName; ?>">
                                </div>
                                <div class="form-group">
                                    <label>Monthly Price ($)</label>
                                    <input type="number" step="0.01" name="plans[<?php echo $planName; ?>][price]" value="<?php echo $planData['price']; ?>">
                                </div>
                                <div class="form-group">
                                    <label>Features (one per line)</label>
                                    <textarea name="plans[<?php echo $planName; ?>][features]"><?php echo implode("\n", $planData['features']); ?></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <button type="submit" name="save_membership" class="btn-primary">
                            Save Membership Plans
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
                        <button type="button" class="btn-primary">Save Notification Settings</button>
                    </form>
                </div>

                <!-- Security -->
                <div id="security" class="settings-content">
                    <h2>Security Settings</h2>
                    <form method="POST" class="settings-form">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password">
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password">
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
                            Change Password
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
                            <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px;">
                                <p>No backups found.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="danger-zone">
                    <h3><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
                    <p>These actions are irreversible. Proceed with caution.</p>
                    <div style="display: flex; gap: 1rem; margin-top: 1rem;">
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
            event.target.classList.add('active');
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
    </script>
</body>
</html>