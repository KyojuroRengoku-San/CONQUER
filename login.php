<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session with secure parameters
session_set_cookie_params([
    'lifetime' => 86400, // 24 hours
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();

// Redirect if already logged in
if(isset($_SESSION['user_id'])) {
    // Check user type and redirect accordingly
    if(isset($_SESSION['user_type'])) {
        if($_SESSION['user_type'] === 'admin') {
            header('Location: admin-dashboard.php');
        } else {
            header('Location: user-dashboard.php');
        }
    } else {
        header('Location: index.php');
    }
    exit();
}

// Database connection
require_once 'config/database.php';

$error = ''; // Initialize error variable

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validate inputs
    if(empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        try {
            // Prepare SQL statement - check by email OR username
            $stmt = $pdo->prepare("SELECT id, username, email, password_hash, full_name, user_type, is_active FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // DEBUG: Remove or comment this out after debugging
            /*
            echo "<pre style='background: #f0f0f0; padding: 10px; border: 1px solid red;'>";
            echo "DEBUG INFO:\n";
            echo "Email/Username entered: " . $email . "\n";
            echo "User found in DB: " . ($user ? 'YES' : 'NO') . "\n";
            if ($user) {
                echo "User data:\n";
                print_r($user);
                echo "\nPassword verify result: " . (password_verify($password, $user['password_hash']) ? 'TRUE' : 'FALSE') . "\n";
                echo "Password hash from DB: " . $user['password_hash'] . "\n";
                echo "Expected password: 'password' (without quotes)\n";
            }
            echo "</pre>";
            */
            
            if($user) {
                // Check if user is active
                if(!$user['is_active']) {
                    $error = 'Your account has been deactivated. Please contact support.';
                }
                // Verify password
                elseif(password_verify($password, $user['password_hash'])) {
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['login_time'] = time();
                    
                    // Set remember me cookie if requested
                    if($remember) {
                        $token = bin2hex(random_bytes(32));
                        $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                        
                        setcookie('remember_token', $token, $expiry, '/', '', isset($_SERVER['HTTPS']), true);
                        
                        // Store token in database (you'll need to add this functionality)
                        // $stmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                        // $stmt->execute([$user['id'], hash('sha256', $token), date('Y-m-d H:i:s', $expiry)]);
                    }
                    
                    // Update last login
                    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->execute([$user['id']]);
                    
                    // Set success message
                    $_SESSION['login_success'] = true;
                    
                    // Redirect based on user type
                    if($user['user_type'] === 'admin') {
                        header('Location: admin-dashboard.php');
                    } else {
                        header('Location: user-dashboard.php');
                    }
                    exit();
                } else {
                    $error = 'Invalid email or password';
                    // Log failed login attempt
                    error_log("Failed login attempt for email: $email from IP: " . $_SERVER['REMOTE_ADDR']);
                }
            } else {
                $error = 'Invalid email or password';
            }
        } catch(PDOException $e) {
            error_log("Database error in login.php: " . $e->getMessage());
            $error = 'Unable to process your request. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | CONQUER Gym</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="login-styles.css">
    <style>
        /* Additional inline styles for login page only */
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .login-container {
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>
    
    <div class="login-container">
        <div class="login-left">
            <div class="login-brand">
                <div class="logo-icon">
                    <i class="fas fa-dumbbell"></i>
                </div>
                <h1>CONQUER</h1>
                <p>Welcome back to your fitness journey</p>
            </div>
            
            <div class="login-features">
                <div class="feature">
                    <i class="fas fa-chart-line"></i>
                    <div>
                        <h3>Track Progress</h3>
                        <p>Monitor your fitness journey with detailed analytics</p>
                    </div>
                </div>
                <div class="feature">
                    <i class="fas fa-calendar-alt"></i>
                    <div>
                        <h3>Book Classes</h3>
                        <p>Reserve spots in your favorite group sessions</p>
                    </div>
                </div>
                <div class="feature">
                    <i class="fas fa-users"></i>
                    <div>
                        <h3>Join Community</h3>
                        <p>Connect with fellow fitness enthusiasts</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="login-right">
            <div class="login-form-container">
                <div class="form-header">
                    <h2>Member Login</h2>
                    <p>Enter your credentials to access your account</p>
                </div>
                
                <?php if($error): ?>
                    <div class="alert alert-error" id="errorAlert">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($_GET['success']) && $_GET['success'] == 'registered'): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span>Registration successful! Please login.</span>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($_GET['message']) && $_GET['message'] == 'loggedout'): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span>You have been successfully logged out.</span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="login-form" id="loginForm">
                    <div class="input-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i>
                            <span>Email Address</span>
                        </label>
                        <input type="email" id="email" name="email" required 
                               placeholder="you@example.com"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               autocomplete="email">
                    </div>
                    
                    <div class="input-group">
                        <label for="password">
                            <i class="fas fa-lock"></i>
                            <span>Password</span>
                        </label>
                        <input type="password" id="password" name="password" required 
                               placeholder="••••••••"
                               autocomplete="current-password">
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye"></i>
                        </button>
                        <div class="password-strength" id="passwordStrength">
                            <div class="strength-meter">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                            <div class="strength-text" id="strengthText"></div>
                        </div>
                    </div>
                    
                    <div class="form-options">
                        <label class="checkbox">
                            <input type="checkbox" name="remember" id="remember">
                            <span>Remember me</span>
                        </label>
                        <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
                    </div>
                    
                    <button type="submit" class="btn-login" id="loginButton">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Sign In</span>
                    </button>
                </form>
                
                <div class="form-footer">
                    <p>Don't have an account? <a href="register.php">Sign up now</a></p>
                    <div class="divider">
                        <span>or continue with</span>
                    </div>
                    <div class="social-login">
                        <button type="button" class="social-btn google" onclick="socialLogin('google')">
                            <i class="fab fa-google"></i>
                            Google
                        </button>
                        <button type="button" class="social-btn facebook" onclick="socialLogin('facebook')">
                            <i class="fab fa-facebook"></i>
                            Facebook
                        </button>
                    </div>
                    <a href="index.html" class="back-home">
                        <i class="fas fa-arrow-left"></i>
                        Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password visibility toggle
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.toggle-password i');
            
            if(passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.classList.remove('fa-eye');
                toggleBtn.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleBtn.classList.remove('fa-eye-slash');
                toggleBtn.classList.add('fa-eye');
            }
        }
        
        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const loginButton = document.getElementById('loginButton');
            
            // Basic validation
            if(!email || !password) {
                e.preventDefault();
                showAlert('Please fill in all fields', 'error');
                return false;
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if(!emailRegex.test(email)) {
                e.preventDefault();
                showAlert('Please enter a valid email address', 'error');
                return false;
            }
            
            // Show loading state
            loginButton.classList.add('loading');
            loginButton.disabled = true;
            loginButton.querySelector('span').textContent = 'Signing in...';
            
            // Show loading overlay
            document.getElementById('loadingOverlay').style.display = 'flex';
        });
        
        // Show alert message
        function showAlert(message, type) {
            // Remove existing alerts
            const existingAlert = document.querySelector('.alert');
            if(existingAlert) {
                existingAlert.remove();
            }
            
            // Create new alert
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i>
                <span>${message}</span>
            `;
            
            // Insert alert
            const formHeader = document.querySelector('.form-header');
            formHeader.parentNode.insertBefore(alertDiv, formHeader.nextSibling);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if(alertDiv.parentNode) {
                    alertDiv.style.opacity = '0';
                    alertDiv.style.transform = 'translateY(-10px)';
                    setTimeout(() => alertDiv.remove(), 300);
                }
            }, 5000);
        }
        
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthDiv = document.getElementById('passwordStrength');
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            if(password.length > 0) {
                strengthDiv.style.display = 'block';
                
                let strength = 0;
                let text = 'Very Weak';
                let color = 'var(--danger-color)';
                
                // Length check
                if(password.length >= 8) strength += 25;
                
                // Complexity checks
                if(/[A-Z]/.test(password)) strength += 25;
                if(/[0-9]/.test(password)) strength += 25;
                if(/[^A-Za-z0-9]/.test(password)) strength += 25;
                
                // Set strength level
                strengthFill.style.width = strength + '%';
                
                if(strength >= 75) {
                    color = 'var(--success-color)';
                    text = 'Strong';
                } else if(strength >= 50) {
                    color = 'var(--warning-color)';
                    text = 'Medium';
                } else if(strength >= 25) {
                    color = 'var(--warning-color)';
                    text = 'Weak';
                }
                
                strengthFill.style.backgroundColor = color;
                strengthText.textContent = text;
                strengthText.style.color = color;
            } else {
                strengthDiv.style.display = 'none';
            }
        });
        
        // Social login functions
        function socialLogin(provider) {
            showAlert(`Redirecting to ${provider} login...`, 'success');
            // Implement social login redirect here
            // window.location.href = `auth/${provider}.php`;
        }
        
        // Auto-hide error alert after 5 seconds
        const errorAlert = document.getElementById('errorAlert');
        if(errorAlert) {
            setTimeout(() => {
                errorAlert.style.opacity = '0';
                errorAlert.style.transform = 'translateY(-10px)';
                setTimeout(() => errorAlert.remove(), 300);
            }, 5000);
        }
        
        // Focus email field on page load
        document.addEventListener('DOMContentLoaded', function() {
            const emailField = document.getElementById('email');
            if(emailField && !emailField.value) {
                emailField.focus();
            }
        });
    </script>
</body>
</html>