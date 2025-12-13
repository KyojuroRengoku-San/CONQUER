<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['save_general'])) {
        // For now, store in session or file since we don't have settings table
        $_SESSION['gym_settings'] = [
            'gym_name' => $_POST['gym_name'],
            'gym_address' => $_POST['gym_address'],
            'contact_email' => $_POST['contact_email'],
            'contact_phone' => $_POST['contact_phone']
        ];
        $success = "General settings updated successfully! (Note: Stored in session only)";
    }
    
    if(isset($_POST['save_business'])) {
        $_SESSION['business_hours'] = $_POST['hours'];
        $success = "Business hours updated successfully! (Note: Stored in session only)";
    }
    
    if(isset($_POST['save_membership'])) {
        $_SESSION['membership_plans'] = $_POST['plans'];
        $success = "Membership plans updated successfully! (Note: Stored in session only)";
    }
    
    if(isset($_POST['change_password'])) {
        // Change admin password
        if($_POST['new_password'] === $_POST['confirm_password']) {
            $hashedPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
            $success = "Password changed successfully!";
        } else {
            $error = "Passwords do not match!";
        }
    }
}

// Load current settings from session or use defaults
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
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        .settings-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .settings-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            padding: 0.5rem;
            background: white;
            border-radius: 10px;
            overflow-x: auto;
        }
        .settings-tab {
            padding: 0.75rem 1.5rem;
            border: none;
            background: transparent;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            white-space: nowrap;
        }
        .settings-tab.active {
            background: var(--primary-color);
            color: white;
        }
        .settings-content {
            display: none;
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .settings-content.active {
            display: block;
        }
        .settings-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .form-group label {
            font-weight: 600;
            color: var(--dark-color);
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
        }
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .hours-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }
        .hour-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
        }
        .plan-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 1rem 0;
        }
        .feature-list li {
            padding: 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .feature-list li:before {
            content: '✓';
            color: #2ed573;
            font-weight: bold;
        }
        .danger-zone {
            border: 2px solid #ff4757;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
            background: #fff5f5;
        }
        .danger-zone h3 {
            color: #ff4757;
            margin-bottom: 1rem;
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