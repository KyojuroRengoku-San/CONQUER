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
    
    // Get user's previous messages
    $userMessages = [];
    try {
        $messagesStmt = $pdo->prepare("
            SELECT * FROM contact_messages 
            WHERE email = ? 
            ORDER BY submitted_at DESC
            LIMIT 10
        ");
        $messagesStmt->execute([$user['email']]);
        $userMessages = $messagesStmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Messages query error: " . $e->getMessage());
    }
    
    // Handle contact form submission
    if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
        $subject = $_POST['subject'] ?? '';
        $message_text = $_POST['message'] ?? '';
        $category = $_POST['category'] ?? 'general';
        
        if(empty($subject) || empty($message_text)) {
            $message = "Subject and message are required!";
            $success = false;
        } else {
            try {
                $insertStmt = $pdo->prepare("
                    INSERT INTO contact_messages (name, email, phone, subject, message, status) 
                    VALUES (?, ?, ?, ?, ?, 'new')
                ");
                
                $phone = isset($member['ContactNumber']) ? $member['ContactNumber'] : '';
                
                if($insertStmt->execute([$user['full_name'], $user['email'], $phone, $subject, $message_text])) {
                    $message = "Message sent successfully! We'll get back to you soon.";
                    $success = true;
                    
                    // Refresh messages list
                    $messagesStmt->execute([$user['email']]);
                    $userMessages = $messagesStmt->fetchAll();
                    
                    // Clear form
                    $_POST = array();
                } else {
                    $message = "Error sending message. Please try again.";
                    $success = false;
                }
            } catch(PDOException $e) {
                $message = "Database error. Please try again later.";
                error_log("Contact form error: " . $e->getMessage());
                $success = false;
            }
        }
    }
    
    // FAQ data
    $faqs = [
        [
            'question' => 'How do I change my membership plan?',
            'answer' => 'You can change your membership plan by visiting the front desk or contacting our membership department. Some changes may require a new contract.'
        ],
        [
            'question' => 'What are your operating hours?',
            'answer' => 'We are open Monday-Friday 5:00 AM - 10:00 PM, Saturday 6:00 AM - 8:00 PM, and Sunday 7:00 AM - 6:00 PM.'
        ],
        [
            'question' => 'How do I cancel a class booking?',
            'answer' => 'You can cancel classes through your dashboard up to 2 hours before the scheduled time. Late cancellations may be subject to fees.'
        ],
        [
            'question' => 'What should I bring for my first class?',
            'answer' => 'Please bring a towel, water bottle, and appropriate workout attire. We provide all necessary equipment.'
        ],
        [
            'question' => 'How do I update my billing information?',
            'answer' => 'You can update your payment methods in the Payments section of your dashboard.'
        ],
        [
            'question' => 'Are personal trainers available?',
            'answer' => 'Yes! We have certified personal trainers available for one-on-one sessions. You can book them through your dashboard.'
        ]
    ];
    
    // Support team
    $supportTeam = [
        ['name' => 'Sarah Johnson', 'role' => 'Membership Manager', 'email' => 'membership@conquergym.com', 'phone' => '555-1001'],
        ['name' => 'Mike Chen', 'role' => 'Personal Training Director', 'email' => 'training@conquergym.com', 'phone' => '555-1002'],
        ['name' => 'Lisa Rodriguez', 'role' => 'Class Schedule Coordinator', 'email' => 'classes@conquergym.com', 'phone' => '555-1003'],
        ['name' => 'David Wilson', 'role' => 'Billing Department', 'email' => 'billing@conquergym.com', 'phone' => '555-1004']
    ];
    
} catch(PDOException $e) {
    $message = "Database error. Please try again later.";
    error_log("Contact page error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support & Contact | CONQUER Gym</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&family=Montserrat:wght@900&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    
    <style>
        .contact-content {
            padding: 2rem;
        }
        
        .contact-section {
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
        
        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
        }
        
        .contact-info {
            background: var(--light-bg);
            padding: 2rem;
            border-radius: var(--radius-md);
        }
        
        .contact-method {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .contact-icon {
            width: 50px;
            height: 50px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }
        
        .contact-details h4 {
            margin: 0 0 0.25rem 0;
            color: var(--dark-color);
        }
        
        .contact-details p {
            margin: 0;
            color: var(--gray);
        }
        
        .contact-form {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--light-color);
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
        
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
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
        
        .faq-section {
            margin: 3rem 0;
        }
        
        .faq-item {
            margin-bottom: 1rem;
            border: 1px solid var(--light-color);
            border-radius: var(--radius-md);
            overflow: hidden;
        }
        
        .faq-question {
            padding: 1.5rem;
            background: var(--light-bg);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .faq-question h4 {
            margin: 0;
            color: var(--dark-color);
        }
        
        .faq-answer {
            padding: 1.5rem;
            display: none;
            color: var(--gray);
            line-height: 1.6;
        }
        
        .faq-answer.active {
            display: block;
        }
        
        .support-team {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        .team-member {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--light-color);
        }
        
        .member-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 1rem auto;
        }
        
        .team-member h4 {
            margin: 0 0 0.5rem 0;
            color: var(--dark-color);
        }
        
        .team-member p {
            margin: 0 0 0.5rem 0;
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .member-contact {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--light-color);
        }
        
        .member-contact a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .member-contact a:hover {
            text-decoration: underline;
        }
        
        .messages-history {
            margin: 2rem 0;
        }
        
        .message-item {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--light-color);
        }
        
        .message-item.new {
            border-left-color: var(--primary-color);
        }
        
        .message-item.replied {
            border-left-color: var(--success);
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .message-subject {
            margin: 0;
            color: var(--dark-color);
            font-weight: 600;
        }
        
        .message-meta {
            display: flex;
            gap: 1rem;
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .message-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-new {
            background: rgba(52, 152, 219, 0.1);
            color: var(--primary-color);
        }
        
        .status-read {
            background: rgba(46, 213, 115, 0.1);
            color: var(--success);
        }
        
        .status-replied {
            background: rgba(155, 89, 182, 0.1);
            color: var(--secondary-color);
        }
        
        .message-content {
            color: var(--gray);
            line-height: 1.6;
        }
        
        .emergency-contact {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            padding: 2rem;
            border-radius: var(--radius-md);
            margin: 2rem 0;
            text-align: center;
        }
        
        .emergency-contact h3 {
            margin: 0 0 1rem 0;
            color: white;
        }
        
        .emergency-contact p {
            margin: 0 0 1.5rem 0;
            opacity: 0.9;
        }
        
        .emergency-btn {
            background: white;
            color: #ff6b6b;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .emergency-btn:hover {
            background: #f8f9fa;
        }
        
        @media (max-width: 768px) {
            .contact-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .message-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
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
                <input type="text" placeholder="Search help topics..." id="searchHelp">
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
                    <h1>Support & Contact 📞</h1>
                    <p>Get help, ask questions, or share feedback with our team</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3><?php echo count($userMessages); ?></h3>
                        <p>Your Messages</p>
                    </div>
                    <div class="stat">
                        <h3>24/7</h3>
                        <p>Support</p>
                    </div>
                </div>
            </div>

            <?php if($message): ?>
                <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                    <i class="fas <?php echo $success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="contact-content">
                <!-- Emergency Contact -->
                <div class="emergency-contact">
                    <h3><i class="fas fa-exclamation-triangle"></i> Emergency Contact</h3>
                    <p>For urgent matters or immediate assistance at the gym</p>
                    <button class="emergency-btn" onclick="callEmergency()">
                        <i class="fas fa-phone"></i> Call Emergency: 555-9111
                    </button>
                </div>

                <!-- Contact Form -->
                <div class="contact-section">
                    <h3 class="section-title">Send Us a Message</h3>
                    <div class="contact-grid">
                        <div class="contact-info">
                            <div class="contact-method">
                                <div class="contact-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="contact-details">
                                    <h4>Phone Support</h4>
                                    <p>555-1000</p>
                                    <small>Mon-Fri, 8AM-8PM</small>
                                </div>
                            </div>
                            
                            <div class="contact-method">
                                <div class="contact-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="contact-details">
                                    <h4>Email</h4>
                                    <p>support@conquergym.com</p>
                                    <small>Response within 24 hours</small>
                                </div>
                            </div>
                            
                            <div class="contact-method">
                                <div class="contact-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="contact-details">
                                    <h4>Visit Us</h4>
                                    <p>123 Fitness Street</p>
                                    <p>Workout City, FC 12345</p>
                                </div>
                            </div>
                            
                            <div class="contact-method">
                                <div class="contact-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="contact-details">
                                    <h4>Hours</h4>
                                    <p>Mon-Fri: 5AM-10PM</p>
                                    <p>Sat: 6AM-8PM, Sun: 7AM-6PM</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="contact-form">
                            <form method="POST">
                                <div class="form-group">
                                    <label for="name">Your Name</label>
                                    <input type="text" id="name" class="form-control" 
                                           value="<?php echo isset($user['full_name']) ? htmlspecialchars($user['full_name']) : ''; ?>" 
                                           readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" class="form-control" 
                                           value="<?php echo isset($user['email']) ? htmlspecialchars($user['email']) : ''; ?>" 
                                           readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label for="category">Category</label>
                                    <select id="category" name="category" class="form-control">
                                        <option value="general">General Inquiry</option>
                                        <option value="membership">Membership</option>
                                        <option value="billing">Billing/Payment</option>
                                        <option value="classes">Classes & Schedule</option>
                                        <option value="trainers">Personal Training</option>
                                        <option value="equipment">Equipment/Facility</option>
                                        <option value="feedback">Feedback/Suggestions</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="subject">Subject *</label>
                                    <input type="text" id="subject" name="subject" class="form-control" 
                                           value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>" 
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="message">Message *</label>
                                    <textarea id="message" name="message" class="form-control" required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" name="send_message" class="btn-primary">
                                        <i class="fas fa-paper-plane"></i> Send Message
                                    </button>
                                    <button type="reset" class="btn-secondary">
                                        <i class="fas fa-redo"></i> Clear Form
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Your Messages -->
                <?php if(count($userMessages) > 0): ?>
                <div class="contact-section">
                    <h3 class="section-title">Your Previous Messages</h3>
                    <div class="messages-history">
                        <?php foreach($userMessages as $msg): 
                            $statusClass = '';
                            switch($msg['status']) {
                                case 'new': $statusClass = 'status-new'; break;
                                case 'read': $statusClass = 'status-read'; break;
                                case 'replied': $statusClass = 'status-replied'; break;
                                default: $statusClass = 'status-new';
                            }
                            
                            $msgClass = $msg['status'] == 'new' ? 'new' : ($msg['status'] == 'replied' ? 'replied' : '');
                        ?>
                            <div class="message-item <?php echo $msgClass; ?>">
                                <div class="message-header">
                                    <h4 class="message-subject"><?php echo htmlspecialchars($msg['subject']); ?></h4>
                                    <div class="message-meta">
                                        <span><?php echo isset($msg['submitted_at']) ? date('M j, Y', strtotime($msg['submitted_at'])) : 'N/A'; ?></span>
                                        <span class="message-status <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($msg['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="message-content">
                                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- FAQ Section -->
                <div class="contact-section">
                    <h3 class="section-title">Frequently Asked Questions</h3>
                    <div class="faq-section">
                        <?php foreach($faqs as $index => $faq): ?>
                            <div class="faq-item">
                                <div class="faq-question" onclick="toggleFAQ(<?php echo $index; ?>)">
                                    <h4><?php echo htmlspecialchars($faq['question']); ?></h4>
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                                <div class="faq-answer" id="faq-<?php echo $index; ?>">
                                    <p><?php echo htmlspecialchars($faq['answer']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Support Team -->
                <div class="contact-section">
                    <h3 class="section-title">Our Support Team</h3>
                    <div class="support-team">
                        <?php foreach($supportTeam as $member): 
                            $firstLetter = strtoupper(substr($member['name'], 0, 1));
                        ?>
                            <div class="team-member">
                                <div class="member-avatar">
                                    <?php echo $firstLetter; ?>
                                </div>
                                <h4><?php echo htmlspecialchars($member['name']); ?></h4>
                                <p><?php echo htmlspecialchars($member['role']); ?></p>
                                <div class="member-contact">
                                    <p><i class="fas fa-envelope"></i> <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>"><?php echo htmlspecialchars($member['email']); ?></a></p>
                                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($member['phone']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="contact-section">
                    <h3 class="section-title">Quick Help Links</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin: 2rem 0;">
                        <a href="membership-terms.pdf" class="action-item" target="_blank">
                            <i class="fas fa-file-contract"></i>
                            <span>Membership Terms</span>
                        </a>
                        <a href="class-schedule.php" class="action-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Class Schedule</span>
                        </a>
                        <a href="trainers.php" class="action-item">
                            <i class="fas fa-users"></i>
                            <span>Meet Our Trainers</span>
                        </a>
                        <a href="facility-tour.php" class="action-item">
                            <i class="fas fa-building"></i>
                            <span>Virtual Facility Tour</span>
                        </a>
                        <a href="pricing.php" class="action-item">
                            <i class="fas fa-tag"></i>
                            <span>Pricing & Plans</span>
                        </a>
                        <a href="safety-guidelines.pdf" class="action-item" target="_blank">
                            <i class="fas fa-shield-alt"></i>
                            <span>Safety Guidelines</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // FAQ toggle functionality
        function toggleFAQ(index) {
            const answer = document.getElementById(`faq-${index}`);
            const icon = answer.previousElementSibling.querySelector('i');
            
            if(answer.classList.contains('active')) {
                answer.classList.remove('active');
                icon.className = 'fas fa-chevron-down';
            } else {
                // Close all other FAQs
                document.querySelectorAll('.faq-answer').forEach(faq => {
                    faq.classList.remove('active');
                    faq.previousElementSibling.querySelector('i').className = 'fas fa-chevron-down';
                });
                
                answer.classList.add('active');
                icon.className = 'fas fa-chevron-up';
            }
        }
        
        // Emergency call function
        function callEmergency() {
            if(confirm('Call emergency number: 555-9111?')) {
                window.location.href = 'tel:5559111';
            }
        }
        
        // Search functionality
        document.getElementById('searchHelp').addEventListener('input', function() {
            const searchValue = this.value.toLowerCase();
            const faqItems = document.querySelectorAll('.faq-item');
            
            if(searchValue === '') {
                faqItems.forEach(item => item.style.display = 'block');
                return;
            }
            
            faqItems.forEach(item => {
                const question = item.querySelector('h4').textContent.toLowerCase();
                const answer = item.querySelector('p').textContent.toLowerCase();
                
                if(question.includes(searchValue) || answer.includes(searchValue)) {
                    item.style.display = 'block';
                    
                    // Expand if matches
                    const index = Array.from(item.parentNode.children).indexOf(item);
                    const answerDiv = document.getElementById(`faq-${index}`);
                    if(!answerDiv.classList.contains('active')) {
                        toggleFAQ(index);
                    }
                } else {
                    item.style.display = 'none';
                }
            });
        });
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const subject = document.getElementById('subject').value.trim();
                const message = document.getElementById('message').value.trim();
                
                if(subject.length < 5) {
                    e.preventDefault();
                    alert('Subject must be at least 5 characters long.');
                    return false;
                }
                
                if(message.length < 20) {
                    e.preventDefault();
                    alert('Message must be at least 20 characters long.');
                    return false;
                }
                
                return true;
            });
        });
        
        // Auto-expand FAQ if URL has hash
        document.addEventListener('DOMContentLoaded', function() {
            if(window.location.hash) {
                const hash = window.location.hash.substring(1);
                if(hash.startsWith('faq-')) {
                    const index = hash.split('-')[1];
                    toggleFAQ(index);
                }
            }
        });
        
        // Style for action items
        const style = document.createElement('style');
        style.textContent = `
            .action-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 1.5rem;
                background: var(--light-bg);
                border-radius: var(--radius-md);
                text-decoration: none;
                color: var(--dark-color);
                transition: var(--transition);
                text-align: center;
            }
            
            .action-item:hover {
                background: var(--primary-color);
                color: white;
                transform: translateY(-3px);
            }
            
            .action-item i {
                font-size: 2rem;
                margin-bottom: 1rem;
            }
            
            .action-item span {
                font-weight: 600;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>