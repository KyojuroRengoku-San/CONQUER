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

// If no messages in database, create sample data
try {
    $messageCount = $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
    
    if($messageCount == 0) {
        // Insert sample messages if none exist
        $sampleMessages = [
            [
                'id' => 1,
                'name' => 'John Smith',
                'email' => 'john.smith@email.com',
                'phone' => '(555) 123-4567',
                'subject' => 'Membership Inquiry',
                'message' => "Hello, I'm interested in joining your gym. Could you please send me information about membership plans and pricing? Also, do you offer any trial sessions?",
                'status' => 'new',
                'submitted_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 2,
                'name' => 'Sarah Johnson',
                'email' => 'sarah.j@email.com',
                'phone' => '(555) 234-5678',
                'subject' => 'Personal Training',
                'message' => "I saw that you have personal trainers available. I'm looking for someone to help me with weight loss and strength training. Could you recommend a trainer and tell me about rates?",
                'status' => 'read',
                'submitted_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ],
            [
                'id' => 3,
                'name' => 'Mike Rodriguez',
                'email' => 'mike.r@email.com',
                'phone' => '(555) 345-6789',
                'subject' => 'Class Schedule',
                'message' => "When are the yoga and HIIT classes scheduled? I work 9-5 so I need evening or weekend classes. Also, do I need to book in advance?",
                'status' => 'new',
                'submitted_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
            ]
        ];
        
        // Try to insert sample data
        foreach($sampleMessages as $message) {
            $insertStmt = $pdo->prepare("
                INSERT INTO contact_messages (name, email, phone, subject, message, status, submitted_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $insertStmt->execute([
                $message['name'],
                $message['email'],
                $message['phone'],
                $message['subject'],
                $message['message'],
                $message['status'],
                $message['submitted_at']
            ]);
        }
    }
    
    // Get messages
    $statusFilter = isset($_GET['status']) ? $_GET['status'] : 'unread';

    // Using status column
    switch($statusFilter) {
        case 'read':
            $whereClause = "WHERE cm.status IN ('read', 'replied', 'closed')";
            break;
        case 'all':
            $whereClause = "";
            break;
        case 'unread':
        default:
            $whereClause = "WHERE cm.status IN ('new', 'unread')";
            break;
    }

    $stmt = $pdo->query("
        SELECT cm.*, COALESCE(u.full_name, cm.name) as display_name, cm.email
        FROM contact_messages cm
        LEFT JOIN users u ON cm.email = u.email
        $whereClause
        ORDER BY 
            CASE WHEN cm.status IN ('new', 'unread') THEN 0 ELSE 1 END,
            cm.submitted_at DESC
    ");
    
    if($stmt) {
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $messages = [];
    }

    // Counts
    $unreadCount = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status IN ('new', 'unread')")->fetchColumn();
    $totalMessages = $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
    
} catch (PDOException $e) {
    // Use sample data if database fails
    $messages = $sampleMessages ?? [];
    $unreadCount = 2; // John and Mike's messages
    $totalMessages = 3;
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
    
    <!-- CSS -->
    <link rel="stylesheet" href="dashboard-style.css">
    <style>
        /* Messages container */
        .messages-container {
            display: flex;
            gap: 1.5rem;
            margin-top: 1rem;
            min-height: 600px;
        }
        
        .message-list {
            flex: 1;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #e8e8e8;
            display: flex;
            flex-direction: column;
            max-height: 700px;
            overflow-y: auto;
        }
        
        .message-detail {
            flex: 2;
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #e8e8e8;
            display: flex;
            flex-direction: column;
        }
        
        .message-item {
            padding: 1.25rem;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }
        
        .message-item:hover {
            background: #f8f9fa;
        }
        
        .message-item.active {
            background: linear-gradient(135deg, rgba(255, 71, 87, 0.1) 0%, rgba(255, 71, 87, 0.05) 100%);
            border-left: 3px solid #ff4757;
        }
        
        .message-item.unread {
            background: linear-gradient(135deg, rgba(30, 144, 255, 0.1) 0%, rgba(30, 144, 255, 0.05) 100%);
            font-weight: 600;
            border-left: 3px solid #1e90ff;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .message-header span:first-child {
            font-weight: 600;
            color: #2f3542;
            font-size: 1rem;
        }
        
        .message-timestamp {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .message-subject {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: #495057;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .message-preview {
            color: #6c757d;
            font-size: 0.9rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.5;
        }
        
        /* Message detail styling */
        .message-detail .message-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .message-detail .message-header h3 {
            font-size: 1.5rem;
            color: #2f3542;
            margin: 0;
            flex: 1;
        }
        
        .message-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }
        
        .message-info p {
            margin-bottom: 0.75rem;
            color: #495057;
            font-size: 0.95rem;
        }
        
        .message-info strong {
            color: #2f3542;
            min-width: 80px;
            display: inline-block;
        }
        
        .message-body {
            line-height: 1.8;
            color: #495057;
            font-size: 1rem;
            flex-grow: 1;
            white-space: pre-wrap;
        }
        
        .message-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
        }
        
        /* Message status badges */
        .message-status {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
        }
        
        .status-new, .status-unread { 
            background: rgba(30, 144, 255, 0.15);
            color: #1e90ff;
            border: 1px solid rgba(30, 144, 255, 0.3);
        }
        
        .status-read { 
            background: rgba(108, 117, 125, 0.15);
            color: #6c757d;
            border: 1px solid rgba(108, 117, 125, 0.3);
        }
        
        .status-replied { 
            background: rgba(46, 213, 115, 0.15);
            color: #2ed573;
            border: 1px solid rgba(46, 213, 115, 0.3);
        }
        
        /* Button styles */
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: #f8f9fa;
            color: #495057;
            transition: all 0.3s;
            text-decoration: none;
            font-family: inherit;
            font-weight: 500;
            border: 1px solid #dee2e6;
            flex: 1;
        }
        
        .btn-sm:hover {
            background: #e9ecef;
            transform: translateY(-1px);
            text-decoration: none;
        }
        
        .btn-sm.btn-success {
            background: #2ed573;
            color: white;
            border-color: #2ed573;
        }
        
        .btn-sm.btn-success:hover {
            background: #25c464;
            border-color: #25c464;
        }
        
        .btn-sm.btn-danger {
            background: #ff4757;
            color: white;
            border-color: #ff4757;
        }
        
        .btn-sm.btn-danger:hover {
            background: #ff2e43;
            border-color: #ff2e43;
        }
        
        /* Status tabs */
        .status-tabs {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .status-tab {
            padding: 0.75rem 1.5rem;
            border: none;
            background: #f8f9fa;
            border-radius: 8px;
            font-family: inherit;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
            color: #495057;
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-tab:hover {
            background: #e9ecef;
            transform: translateY(-1px);
        }
        
        .status-tab.active {
            background: #ff4757;
            color: white;
            border-color: #ff4757;
            box-shadow: 0 4px 12px rgba(255, 71, 87, 0.2);
        }
        
        .status-tab.active i {
            color: white;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem 0;
            color: #6c757d;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #e9ecef;
            margin-bottom: 1rem;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .messages-container {
                flex-direction: column;
            }
            
            .message-list,
            .message-detail {
                width: 100%;
                max-height: none;
            }
            
            .message-list {
                max-height: 400px;
            }
        }
        
        @media (max-width: 768px) {
            .status-tabs {
                flex-direction: column;
                align-items: stretch;
            }
            
            .status-tab {
                width: 100%;
                justify-content: center;
            }
            
            .message-actions {
                flex-direction: column;
            }
        }
        
        /* Search bar */
        #searchInput {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        #searchInput:focus {
            outline: none;
            border-color: #ff4757;
            box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.1);
        }
    </style>
</head>
<body>
    <?php include 'admin-sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search messages by name, subject..." id="searchInput">
            </div>
            <div class="top-bar-actions">
                <?php if($unreadCount > 0): ?>
                    <div style="position: relative;">
                        <button class="btn-notification">
                            <i class="fas fa-bell"></i>
                        </button>
                        <span class="notification-badge"><?php echo $unreadCount; ?></span>
                    </div>
                <?php endif; ?>
                <button class="btn-primary" onclick="window.location.href='admin-broadcast.php'">
                    <i class="fas fa-bullhorn"></i> Broadcast Message
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
                    <i class="fas fa-envelope"></i> Unread (<?php echo $unreadCount; ?>)
                </button>
                <button class="status-tab <?php echo $statusFilter === 'read' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?status=read'">
                    <i class="fas fa-envelope-open"></i> Read
                </button>
                <button class="status-tab <?php echo $statusFilter === 'all' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?status=all'">
                    <i class="fas fa-list"></i> All Messages
                </button>
            </div>

            <!-- Messages Container -->
            <div class="messages-container">
                <!-- Message List -->
                <div class="message-list">
                    <?php if(count($messages) > 0): ?>
                        <?php foreach($messages as $index => $message): 
                            $displayName = isset($message['display_name']) && $message['display_name'] ? $message['display_name'] : $message['name'];
                            $isUnread = in_array($message['status'], ['new', 'unread']);
                        ?>
                            <div class="message-item <?php echo $isUnread ? 'unread' : ''; ?> <?php echo $index === 0 ? 'active' : ''; ?>" 
                                 onclick="selectMessage(<?php echo $message['id']; ?>, this)"
                                 data-message-id="<?php echo $message['id']; ?>"
                                 data-message-subject="<?php echo htmlspecialchars($message['subject']); ?>"
                                 data-sender-name="<?php echo htmlspecialchars($displayName); ?>"
                                 data-message-status="<?php echo $message['status']; ?>">
                                <div class="message-header">
                                    <span><?php echo htmlspecialchars($displayName); ?></span>
                                    <span class="message-timestamp">
                                        <?php echo date('M j, g:i A', strtotime($message['submitted_at'])); ?>
                                    </span>
                                </div>
                                <div class="message-subject">
                                    <?php echo htmlspecialchars($message['subject']); ?>
                                </div>
                                <div class="message-preview">
                                    <?php echo htmlspecialchars(substr($message['message'], 0, 120)); ?>...
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3 style="color: #495057; margin: 1rem 0 0.5rem;">No Messages Found</h3>
                            <p>No <?php echo $statusFilter; ?> messages to display.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Message Detail -->
                <div class="message-detail" id="messageDetail">
                    <?php if(count($messages) > 0): 
                        $firstMessage = $messages[0];
                        $displayName = isset($firstMessage['display_name']) && $firstMessage['display_name'] ? $firstMessage['display_name'] : $firstMessage['name'];
                    ?>
                        <div class="message-header">
                            <h3><?php echo htmlspecialchars($firstMessage['subject']); ?></h3>
                            <span class="message-status status-<?php echo $firstMessage['status']; ?>">
                                <?php echo ucfirst($firstMessage['status']); ?>
                            </span>
                        </div>
                        <div class="message-info">
                            <p><strong>From:</strong> <?php echo htmlspecialchars($displayName); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($firstMessage['email']); ?></p>
                            <?php if(!empty($firstMessage['phone'])): ?>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($firstMessage['phone']); ?></p>
                            <?php endif; ?>
                            <p><strong>Date:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($firstMessage['submitted_at'])); ?></p>
                        </div>
                        <div class="message-body">
                            <?php echo nl2br(htmlspecialchars($firstMessage['message'])); ?>
                        </div>
                        
                        <div class="message-actions">
                            <?php if(in_array($firstMessage['status'], ['new', 'unread'])): ?>
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
                        <div class="empty-state">
                            <i class="fas fa-envelope-open-text"></i>
                            <h3 style="color: #495057; margin: 1rem 0 0.5rem;">No Message Selected</h3>
                            <p>Select a message from the list to view details</p>
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
            
            // In a real application, you would make an AJAX call here
            // For demo purposes, we'll just show an alert and update status
            const senderName = element.getAttribute('data-sender-name');
            const subject = element.getAttribute('data-message-subject');
            
            // Simulate loading message details
            document.getElementById('messageDetail').innerHTML = `
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin fa-2x" style="color: #667eea;"></i>
                    <p style="margin-top: 1rem;">Loading message...</p>
                </div>
            `;
            
            setTimeout(() => {
                // In real app, this would be from AJAX response
                document.getElementById('messageDetail').innerHTML = `
                    <div class="message-header">
                        <h3>${subject}</h3>
                        <span class="message-status status-read">
                            Read
                        </span>
                    </div>
                    <div class="message-info">
                        <p><strong>From:</strong> ${senderName}</p>
                        <p><strong>Email:</strong> user@example.com</p>
                        <p><strong>Date:</strong> ${new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })} at ${new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</p>
                    </div>
                    <div class="message-body">
                        This is a sample message content. In a real application, this would be loaded from the server for message ID: ${messageId}
                    </div>
                    <div class="message-actions">
                        <button class="btn-sm" onclick="replyToMessage('user@example.com')">
                            <i class="fas fa-reply"></i> Reply
                        </button>
                        <button class="btn-sm btn-danger" onclick="deleteMessage(${messageId})">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                `;
                
                // Remove unread styling if applicable
                if(element.classList.contains('unread')) {
                    element.classList.remove('unread');
                    element.style.borderLeft = '3px solid #dee2e6';
                    element.style.background = 'white';
                }
            }, 500);
        }
        
        function markAsRead(messageId) {
            if(confirm('Mark this message as read?')) {
                // In real implementation, you would make an AJAX call
                alert('Message #' + messageId + ' marked as read (demo only).');
                // Then reload or update UI
            }
        }
        
        function replyToMessage(email) {
            window.location.href = 'mailto:' + email + '?subject=Re: Your Inquiry';
        }
        
        function deleteMessage(messageId) {
            if(confirm('Are you sure you want to delete this message?')) {
                // In real implementation, you would make an AJAX call
                alert('Message #' + messageId + ' deleted (demo only).');
                // Then remove from UI
                const messageItem = document.querySelector(`[data-message-id="${messageId}"]`);
                if(messageItem) {
                    messageItem.remove();
                }
                document.getElementById('messageDetail').innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-trash-alt"></i>
                        <h3 style="color: #495057; margin: 1rem 0 0.5rem;">Message Deleted</h3>
                        <p>The message has been deleted.</p>
                    </div>
                `;
            }
        }
        
        // Live search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            const messageItems = document.querySelectorAll('.message-item');
            
            messageItems.forEach(item => {
                const subject = item.getAttribute('data-message-subject').toLowerCase();
                const senderName = item.getAttribute('data-sender-name').toLowerCase();
                const itemText = item.textContent.toLowerCase();
                
                if (subject.includes(searchTerm) || 
                    senderName.includes(searchTerm) ||
                    itemText.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>