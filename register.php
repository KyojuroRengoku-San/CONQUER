<?php
session_start();
require_once 'config/database.php';

if(isset($_SESSION['user_id'])) {
    header('Location: user-dashboard.php');
    exit();
}

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = trim($_POST['phone'] ?? '');
    $plan = $_POST['plan'] ?? 'warrior';
    
    // Validation
    if(empty($full_name) || empty($email) || empty($username) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif(strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        try {
            // Check if email already exists
            $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $checkEmail->execute([$email]);
            if($checkEmail->fetch()) {
                $error = 'Email already registered';
            } else {
                // Check if username exists
                $checkUsername = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $checkUsername->execute([$username]);
                if($checkUsername->fetch()) {
                    $error = 'Username already taken';
                } else {
                    // Hash password
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert user
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password_hash, full_name, user_type) 
                        VALUES (?, ?, ?, ?, 'member')
                    ");
                    
                    if($stmt->execute([$username, $email, $password_hash, $full_name])) {
                        $user_id = $pdo->lastInsertId();
                        
                        // Insert gym member record
                        $memberStmt = $pdo->prepare("
                            INSERT INTO gym_members (Name, Age, MembershipPlan, ContactNumber, Email) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        
                        // For simplicity, set age to 25. You can add age field to form if needed
                        $memberStmt->execute([$full_name, 25, $plan, $phone, $email]);
                        
                        // Auto-login after registration
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['username'] = $username;
                        $_SESSION['email'] = $email;
                        $_SESSION['full_name'] = $full_name;
                        $_SESSION['user_type'] = 'member';
                        
                        $success = 'Registration successful! Welcome to CONQUER Gym!';
                        
                        // Redirect to dashboard after 2 seconds
                        header('Refresh: 2; URL=user-dashboard.php');
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                }
            }
        } catch(PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | CONQUER Gym</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="register-style.css">
</head>
<body>
    <div class="register-container">
        <div class="register-left">
            <div class="register-brand">
                <div class="logo-icon">
                    <i class="fas fa-dumbbell"></i>
                </div>
                <h1>CONQUER</h1>
                <p>Start your fitness journey today</p>
            </div>
            
            <div class="register-benefits">
                <h2><i class="fas fa-check-circle"></i> Why Join CONQUER?</h2>
                
                <div class="benefit">
                    <i class="fas fa-trophy"></i>
                    <div>
                        <h3>Elite Facilities</h3>
                        <p>State-of-the-art equipment and spacious workout areas</p>
                    </div>
                </div>
                
                <div class="benefit">
                    <i class="fas fa-users"></i>
                    <div>
                        <h3>Expert Trainers</h3>
                        <p>Certified professionals to guide your fitness journey</p>
                    </div>
                </div>
                
                <div class="benefit">
                    <i class="fas fa-calendar-check"></i>
                    <div>
                        <h3>Flexible Plans</h3>
                        <p>Choose from various membership options that fit your lifestyle</p>
                    </div>
                </div>
                
                <div class="benefit">
                    <i class="fas fa-heart-pulse"></i>
                    <div>
                        <h3>Health Tracking</h3>
                        <p>Monitor your progress with our advanced fitness tracking</p>
                    </div>
                </div>
            </div>
            
            <div class="testimonial">
                <p>"CONQUER Gym transformed my life. The community and trainers are amazing!"</p>
                <div class="testimonial-author">
                    <img src="https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=80" alt="Sarah M.">
                    <div>
                        <h4>Sarah M.</h4>
                        <span>Member since 2022</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="register-right">
            <div class="register-form-container">
                <div class="form-header">
                    <h2>Create Your Account</h2>
                    <p>Join thousands of fitness enthusiasts</p>
                </div>
                
                <?php if($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <p>Redirecting to your dashboard...</p>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="register-form" id="registerForm">
                    <div class="form-section">
                        <h3><i class="fas fa-user"></i> Personal Information</h3>
                        
                        <div class="input-group">
                            <label for="full_name">
                                <i class="fas fa-user-circle"></i>
                                <span>Full Name *</span>
                            </label>
                            <input type="text" id="full_name" name="full_name" required 
                                   placeholder="Enter your full name"
                                   value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                        </div>
                        
                        <div class="form-row">
                            <div class="input-group">
                                <label for="email">
                                    <i class="fas fa-envelope"></i>
                                    <span>Email Address *</span>
                                </label>
                                <input type="email" id="email" name="email" required 
                                       placeholder="you@example.com"
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                            
                            <div class="input-group">
                                <label for="phone">
                                    <i class="fas fa-phone"></i>
                                    <span>Phone Number</span>
                                </label>
                                <input type="tel" id="phone" name="phone" 
                                       placeholder="(555) 123-4567"
                                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="fas fa-key"></i> Account Details</h3>
                        
                        <div class="input-group">
                            <label for="username">
                                <i class="fas fa-at"></i>
                                <span>Username *</span>
                            </label>
                            <input type="text" id="username" name="username" required 
                                   placeholder="Choose a username"
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                            <small class="hint">This will be your display name</small>
                        </div>
                        
                        <div class="form-row">
                            <div class="input-group">
                                <label for="password">
                                    <i class="fas fa-lock"></i>
                                    <span>Password *</span>
                                </label>
                                <input type="password" id="password" name="password" required 
                                       placeholder="••••••••"
                                       minlength="6">
                                <button type="button" class="toggle-password" onclick="togglePassword('password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <small class="hint">Minimum 6 characters</small>
                            </div>
                            
                            <div class="input-group">
                                <label for="confirm_password">
                                    <i class="fas fa-lock"></i>
                                    <span>Confirm Password *</span>
                                </label>
                                <input type="password" id="confirm_password" name="confirm_password" required 
                                       placeholder="••••••••">
                                <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="fas fa-dumbbell"></i> Membership Plan</h3>
                        
                        <div class="plan-selection">
                            <div class="plan-option <?php echo ($_POST['plan'] ?? 'warrior') === 'warrior' ? 'selected' : ''; ?>" 
                                 onclick="selectPlan('warrior')">
                                <input type="radio" name="plan" value="warrior" id="plan-warrior" 
                                       <?php echo ($_POST['plan'] ?? 'warrior') === 'warrior' ? 'checked' : ''; ?>>
                                <label for="plan-warrior">
                                    <h4>Warrior</h4>
                                    <div class="price">$29<span>/month</span></div>
                                    <ul class="plan-features">
                                        <li><i class="fas fa-check"></i> Full Gym Access</li>
                                        <li><i class="fas fa-check"></i> Locker Facilities</li>
                                        <li><i class="fas fa-check"></i> Free Wi-Fi</li>
                                    </ul>
                                </label>
                            </div>
                            
                            <div class="plan-option popular <?php echo ($_POST['plan'] ?? 'warrior') === 'champion' ? 'selected' : ''; ?>" 
                                 onclick="selectPlan('champion')">
                                <input type="radio" name="plan" value="champion" id="plan-champion" 
                                       <?php echo ($_POST['plan'] ?? 'warrior') === 'champion' ? 'checked' : ''; ?>>
                                <label for="plan-champion">
                                    <span class="popular-badge">MOST POPULAR</span>
                                    <h4>Champion</h4>
                                    <div class="price">$49<span>/month</span></div>
                                    <ul class="plan-features">
                                        <li><i class="fas fa-check"></i> Everything in Warrior</li>
                                        <li><i class="fas fa-check"></i> All Group Classes</li>
                                        <li><i class="fas fa-check"></i> 2 PT Sessions/Month</li>
                                        <li><i class="fas fa-check"></i> Sauna & Steam Room</li>
                                    </ul>
                                </label>
                            </div>
                            
                            <div class="plan-option <?php echo ($_POST['plan'] ?? 'warrior') === 'legend' ? 'selected' : ''; ?>" 
                                 onclick="selectPlan('legend')">
                                <input type="radio" name="plan" value="legend" id="plan-legend" 
                                       <?php echo ($_POST['plan'] ?? 'warrior') === 'legend' ? 'checked' : ''; ?>>
                                <label for="plan-legend">
                                    <h4>Legend</h4>
                                    <div class="price">$79<span>/month</span></div>
                                    <ul class="plan-features">
                                        <li><i class="fas fa-check"></i> Everything in Champion</li>
                                        <li><i class="fas fa-check"></i> 24/7 Unlimited Access</li>
                                        <li><i class="fas fa-check"></i> Unlimited PT Sessions</li>
                                        <li><i class="fas fa-check"></i> Custom Nutrition Plan</li>
                                    </ul>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <div class="terms-agreement">
                            <label class="checkbox">
                                <input type="checkbox" name="terms" id="terms" required>
                                <span>I agree to the <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a> *</span>
                            </label>
                            
                            <label class="checkbox">
                                <input type="checkbox" name="newsletter" id="newsletter" checked>
                                <span>Subscribe to fitness tips and updates</span>
                            </label>
                        </div>
                        
                        <div class="form-submit">
                            <button type="submit" class="btn-register">
                                <i class="fas fa-user-plus"></i>
                                Create Account
                            </button>
                        </div>
                    </div>
                </form>
                
                <div class="form-footer">
                    <p>Already have an account? <a href="login.php">Sign in here</a></p>
                    <div class="divider">
                        <span>or sign up with</span>
                    </div>
                    <div class="social-register">
                        <button type="button" class="social-btn google" onclick="socialSignUp('google')">
                            <i class="fab fa-google"></i>
                            Google
                        </button>
                        <button type="button" class="social-btn facebook" onclick="socialSignUp('facebook')">
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
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleBtn = passwordInput.parentElement.querySelector('.toggle-password i');
            
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
        
        function selectPlan(plan) {
            // Update radio button
            document.getElementById(`plan-${plan}`).checked = true;
            
            // Update visual selection
            document.querySelectorAll('.plan-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
        }
        
        function socialSignUp(provider) {
            alert(`Social sign up with ${provider} would be implemented here.`);
            // In a real application, this would redirect to OAuth flow
        }
        
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        
        if(passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = checkPasswordStrength(password);
                updatePasswordStrength(strength);
                
                // Check password match
                if(confirmPasswordInput.value) {
                    checkPasswordMatch();
                }
            });
            
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        }
        
        function checkPasswordStrength(password) {
            let strength = 0;
            
            if(password.length >= 8) strength++;
            if(/[A-Z]/.test(password)) strength++;
            if(/[0-9]/.test(password)) strength++;
            if(/[^A-Za-z0-9]/.test(password)) strength++;
            
            return strength;
        }
        
        function updatePasswordStrength(strength) {
            const strengthBar = document.getElementById('password-strength');
            if(!strengthBar) return;
            
            strengthBar.className = 'password-strength';
            if(strength === 0) {
                strengthBar.innerHTML = '';
                return;
            }
            
            let strengthText = 'Weak';
            let strengthClass = 'weak';
            
            if(strength >= 3) {
                strengthText = 'Strong';
                strengthClass = 'strong';
            } else if(strength >= 2) {
                strengthText = 'Medium';
                strengthClass = 'medium';
            }
            
            strengthBar.innerHTML = `
                <div class="strength-bar ${strengthClass}"></div>
                <span>${strengthText}</span>
            `;
        }
        
        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            const matchIndicator = document.getElementById('password-match');
            
            if(!matchIndicator) return;
            
            if(!confirmPassword) {
                matchIndicator.innerHTML = '';
                return;
            }
            
            if(password === confirmPassword) {
                matchIndicator.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
                matchIndicator.className = 'password-match match';
            } else {
                matchIndicator.innerHTML = '<i class="fas fa-times-circle"></i> Passwords do not match';
                matchIndicator.className = 'password-match no-match';
            }
        }
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const terms = document.getElementById('terms').checked;
            
            if(!terms) {
                e.preventDefault();
                alert('Please agree to the Terms of Service and Privacy Policy');
                return false;
            }
            
            if(password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match');
                return false;
            }
            
            if(password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long');
                return false;
            }
        });
    </script>
</body>
</html>