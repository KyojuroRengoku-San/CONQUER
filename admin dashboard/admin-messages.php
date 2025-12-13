<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

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
        $whereClause = "WHERE cm.status = 'new'";
        break;
}

$messages = $pdo->query("
    SELECT cm.*, u.full_name, u.email
    FROM contact_messages cm
    LEFT JOIN users u ON cm.email = u.email
    $whereClause
    ORDER BY cm.submitted_at DESC
")->fetchAll();

// Counts
$unreadCount = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn();
$totalMessages = count($messages);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Admin Dashboard</title>
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        .messages-container {
            display: flex;
            gap: 1.5rem;
            margin-top: 1rem;
        }
        .message-list {
            flex: 1;
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        .message-detail {
            flex: 2;
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
        }
        .message-item {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .message-item:hover {
            background: #f8f9fa;
        }
        .message-item.unread {
            background: #e3f2fd;
            font-weight: 500;
        }
        .message-item.active {
            background: var(--primary-color);
            color: white;
        }
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .message-subject {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        .message-preview {
            color: #666;
            font-size: 0.9rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .message-timestamp {
            font-size: 0.8rem;
            color: #999;
        }
        .message-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        .message-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
        }
        .status-new { background: #cce5ff; color: #004085; }
        .status-read { background: #e2e3e5; color: #383d41; }
        .status-replied { background: #d4edda; color: #155724; }
        .status-closed { background: #d1ecf1; color: #0c5460; }
    </style>
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