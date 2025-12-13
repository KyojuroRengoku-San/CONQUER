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

try {
    // Get messages
    $statusFilter = isset($_GET['status']) ? $_GET['status'] : 'unread';

    // FIXED: Using status column instead of is_read
    switch($statusFilter) {
        case 'read':
            $whereClause = "WHERE cm.status IN ('read', 'replied', 'closed')";
            break;
        case 'all':
            $whereClause = "";
            break;
        case 'unread':
        default:
            $whereClause = "WHERE cm.status = 'new' OR cm.status = 'unread'";
            break;
    }

    $stmt = $pdo->query("
        SELECT cm.*, COALESCE(u.full_name, cm.name) as display_name, cm.email
        FROM contact_messages cm
        LEFT JOIN users u ON cm.email = u.email
        $whereClause
        ORDER BY cm.submitted_at DESC
    ");
    
    if($stmt) {
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $messages = [];
    }

    // Counts - FIXED: Check if table exists
    try {
        $unreadCount = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status IN ('new', 'unread')")->fetchColumn();
    } catch (Exception $e) {
        $unreadCount = 0;
    }
    
    $totalMessages = count($messages);
    
} catch (PDOException $e) {
    $messages = [];
    $unreadCount = 0;
    $totalMessages = 0;
    error_log("Messages query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Admin Dashboard</title>
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
                <input type="text" placeholder="Search messages...">
            </div>
            <div class="top-bar-actions">
                <button class="btn-primary" onclick="window.location.href='admin-broadcast.php'">
                    <i class="fas fa-bullhorn"></i> Broadcast
                </button>
            </div>
        </div>

        <div class="dashboard-content">
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>Messages</h1>
                    <p>Manage member inquiries and communications</p>
                </div>
                <div class="welcome-stats">
                    <div class="stat">
                        <h3><?php echo $unreadCount; ?></h3>
                        <p>Unread Messages</p>
                    </div>
                    <div class="stat">
                        <h3><?php echo $totalMessages; ?></h3>
                        <p>Total Messages</p>
                    </div>
                </div>
            </div>

            <!-- Status Tabs -->
            <div class="status-tabs">
                <button class="status-tab <?php echo $statusFilter === 'unread' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?status=unread'">
                    Unread (<?php echo $unreadCount; ?>)
                </button>
                <button class="status-tab <?php echo $statusFilter === 'read' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?status=read'">
                    Read
                </button>
                <button class="status-tab <?php echo $statusFilter === 'all' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?status=all'">
                    All Messages
                </button>
            </div>

            <!-- Messages Container -->
            <div class="messages-container">
                <!-- Message List -->
                <div class="message-list">
                    <?php if(count($messages) > 0): ?>
                        <?php foreach($messages as $index => $message): ?>
                            <div class="message-item <?php echo $message['status'] === 'new' ? 'unread' : ''; ?> <?php echo $index === 0 ? 'active' : ''; ?>" 
                                 onclick="selectMessage(<?php echo $message['id']; ?>, this)">
                                <div class="message-header">
                                    <span><?php echo htmlspecialchars($message['full_name'] ?: $message['name']); ?></span>
                                    <span class="message-timestamp">
                                        <?php echo date('M j, g:i A', strtotime($message['submitted_at'])); ?>
                                    </span>
                                </div>
                                <div class="message-subject">
                                    <?php echo htmlspecialchars($message['subject']); ?>
                                </div>
                                <div class="message-preview">
                                    <?php echo htmlspecialchars(substr($message['message'], 0, 100)); ?>...
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 2rem; text-align: center; color: #999;">
                            <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                            <p>No messages found</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Message Detail -->
                <div class="message-detail" id="messageDetail">
                    <?php if(count($messages) > 0): ?>
                        <?php $firstMessage = $messages[0]; ?>
                        <div class="message-header">
                            <h3><?php echo htmlspecialchars($firstMessage['subject']); ?></h3>
                            <span class="message-status status-<?php echo $firstMessage['status']; ?>">
                                <?php echo ucfirst($firstMessage['status']); ?>
                            </span>
                        </div>
                        <div style="margin: 1.5rem 0; padding: 1.5rem; background: #f8f9fa; border-radius: 5px;">
                            <p><strong>From:</strong> <?php echo htmlspecialchars($firstMessage['full_name'] ?: $firstMessage['name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($firstMessage['email']); ?></p>
                            <?php if(!empty($firstMessage['phone'])): ?>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($firstMessage['phone']); ?></p>
                            <?php endif; ?>
                            <p><strong>Date:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($firstMessage['submitted_at'])); ?></p>
                        </div>
                        <div style="line-height: 1.8; margin: 1.5rem 0;">
                            <?php echo nl2br(htmlspecialchars($firstMessage['message'])); ?>
                        </div>
                        
                        <div class="message-actions">
                            <?php if($firstMessage['status'] === 'new'): ?>
                                <button class="btn-sm btn-success" onclick="markAsRead(<?php echo $firstMessage['id']; ?>)">
                                    <i class="fas fa-check"></i> Mark as Read
                                </button>
                            <?php endif; ?>
                            <button class="btn-sm" onclick="replyToMessage('<?php echo htmlspecialchars($firstMessage['email']); ?>')">
                                <i class="fas fa-reply"></i> Reply
                            </button>
                            <button class="btn-sm btn-danger" onclick="deleteMessage(<?php echo $firstMessage['id']; ?>)">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 3rem; color: #999;">
                            <i class="fas fa-envelope-open-text" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                            <p>Select a message to view details</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function selectMessage(messageId, element) {
            // Remove active class from all messages
            document.querySelectorAll('.message-item').forEach(item => {
                item.classList.remove('active');
            });
            // Add active class to clicked message
            element.classList.add('active');
            
            // Load message details via AJAX
            fetch('admin-get-message.php?id=' + messageId)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('messageDetail').innerHTML = html;
                });
        }
        
        function markAsRead(messageId) {
            fetch('admin-mark-read.php?id=' + messageId)
                .then(() => {
                    location.reload();
                });
        }
        
        function replyToMessage(email) {
            window.location.href = 'mailto:' + email;
        }
        
        function deleteMessage(messageId) {
            if(confirm('Delete this message?')) {
                window.location.href = 'admin-delete-message.php?id=' + messageId;
            }
        }
    </script>
</body>
</html>