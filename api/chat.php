<?php
header('Content-Type: application/json');
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    
    switch ($action) {
        case 'start_conversation':
            startConversation($data);
            break;
        case 'send_message':
            sendMessage($data);
            break;
        case 'get_messages':
            getMessages($data);
            break;
        case 'get_conversations':
            getConversations($data);
            break;
        case 'close_conversation':
            closeConversation($data);
            break;
        case 'assign_admin':
            assignAdmin($data);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

function startConversation($data) {
    try {
        $db = getDB();
        
        $userId = $data['user_id'] ?? null;
        $sessionId = $data['session_id'] ?? uniqid('chat_');
        $category = sanitizeInput($data['category'] ?? 'general');
        $priority = sanitizeInput($data['priority'] ?? 'normal');
        
        // Check if user exists (if user_id provided)
        if ($userId) {
            $stmt = $db->prepare("SELECT id, username FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'Invalid user']);
                return;
            }
        }
        
        // Create conversation
        $stmt = $db->prepare("
            INSERT INTO chat_conversations (user_id, session_id, status, priority, category, started_at) 
            VALUES (?, ?, 'waiting', ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$userId, $sessionId, $priority, $category]);
        
        $conversationId = $db->lastInsertId();
        
        // Send welcome message
        $stmt = $db->prepare("
            INSERT INTO chat_messages (conversation_id, sender_id, sender_type, message, message_type, created_at) 
            VALUES (?, NULL, 'system', ?, 'text', CURRENT_TIMESTAMP)
        ");
        $welcomeMsg = "স্বাগতম! আমাদের সাপোর্ট টিম শীঘ্রই আপনার সাথে যোগাযোগ করবে।";
        $stmt->execute([$conversationId, $welcomeMsg]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Conversation started successfully',
            'conversation_id' => $conversationId,
            'session_id' => $sessionId
        ]);
        
    } catch(PDOException $e) {
        error_log("Chat conversation error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function sendMessage($data) {
    try {
        $db = getDB();
        
        $conversationId = (int)$data['conversation_id'];
        $senderId = $data['sender_id'] ?? null;
        $senderType = sanitizeInput($data['sender_type'] ?? 'user');
        $message = sanitizeInput($data['message'] ?? '');
        $messageType = sanitizeInput($data['message_type'] ?? 'text');
        
        if (empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
            return;
        }
        
        // Validate conversation exists
        $stmt = $db->prepare("SELECT id, status FROM chat_conversations WHERE id = ?");
        $stmt->execute([$conversationId]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$conversation) {
            echo json_encode(['success' => false, 'message' => 'Conversation not found']);
            return;
        }
        
        if ($conversation['status'] === 'closed') {
            echo json_encode(['success' => false, 'message' => 'Conversation is closed']);
            return;
        }
        
        // Insert message
        $stmt = $db->prepare("
            INSERT INTO chat_messages (conversation_id, sender_id, sender_type, message, message_type, created_at) 
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$conversationId, $senderId, $senderType, $message, $messageType]);
        
        $messageId = $db->lastInsertId();
        
        // Update conversation status to active if it was waiting
        if ($conversation['status'] === 'waiting' && $senderType === 'admin') {
            $stmt = $db->prepare("UPDATE chat_conversations SET status = 'active' WHERE id = ?");
            $stmt->execute([$conversationId]);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Message sent successfully',
            'message_id' => $messageId
        ]);
        
    } catch(PDOException $e) {
        error_log("Send message error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function getMessages($data) {
    try {
        $db = getDB();
        
        $conversationId = (int)$data['conversation_id'];
        $lastMessageId = (int)($data['last_message_id'] ?? 0);
        
        // Get messages
        $stmt = $db->prepare("
            SELECT 
                cm.id,
                cm.sender_id,
                cm.sender_type,
                cm.message,
                cm.message_type,
                cm.is_read,
                cm.created_at,
                u.username as sender_name
            FROM chat_messages cm
            LEFT JOIN users u ON cm.sender_id = u.id
            WHERE cm.conversation_id = ? AND cm.id > ?
            ORDER BY cm.created_at ASC
        ");
        $stmt->execute([$conversationId, $lastMessageId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mark messages as read (for user messages)
        if (!empty($messages)) {
            $messageIds = array_column($messages, 'id');
            $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
            $stmt = $db->prepare("UPDATE chat_messages SET is_read = 1 WHERE id IN ($placeholders)");
            $stmt->execute($messageIds);
        }
        
        echo json_encode([
            'success' => true, 
            'messages' => $messages
        ]);
        
    } catch(PDOException $e) {
        error_log("Get messages error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function getConversations($data) {
    try {
        $db = getDB();
        
        // Check if admin request
        $isAdmin = isset($data['admin_token']);
        $userId = $data['user_id'] ?? null;
        $status = sanitizeInput($data['status'] ?? '');
        
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if (!$isAdmin && $userId) {
            $whereClause .= " AND cc.user_id = ?";
            $params[] = $userId;
        }
        
        if ($status) {
            $whereClause .= " AND cc.status = ?";
            $params[] = $status;
        }
        
        // Get conversations with latest message
        $stmt = $db->prepare("
            SELECT 
                cc.id,
                cc.user_id,
                cc.session_id,
                cc.status,
                cc.priority,
                cc.category,
                cc.assigned_admin,
                cc.started_at,
                cc.closed_at,
                u.username,
                admin.username as admin_name,
                (SELECT COUNT(*) FROM chat_messages WHERE conversation_id = cc.id AND is_read = 0 AND sender_type != 'admin') as unread_count,
                (SELECT message FROM chat_messages WHERE conversation_id = cc.id ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT created_at FROM chat_messages WHERE conversation_id = cc.id ORDER BY created_at DESC LIMIT 1) as last_message_time
            FROM chat_conversations cc
            LEFT JOIN users u ON cc.user_id = u.id
            LEFT JOIN users admin ON cc.assigned_admin = admin.id
            $whereClause
            ORDER BY cc.started_at DESC
        ");
        $stmt->execute($params);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'conversations' => $conversations
        ]);
        
    } catch(PDOException $e) {
        error_log("Get conversations error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function closeConversation($data) {
    try {
        $db = getDB();
        
        $conversationId = (int)$data['conversation_id'];
        $rating = (int)($data['rating'] ?? 0);
        $feedback = sanitizeInput($data['feedback'] ?? '');
        
        $stmt = $db->prepare("
            UPDATE chat_conversations 
            SET status = 'closed', closed_at = CURRENT_TIMESTAMP, rating = ?, feedback = ? 
            WHERE id = ?
        ");
        $stmt->execute([$rating > 0 ? $rating : null, $feedback ?: null, $conversationId]);
        
        // Send closing message
        $stmt = $db->prepare("
            INSERT INTO chat_messages (conversation_id, sender_id, sender_type, message, message_type, created_at) 
            VALUES (?, NULL, 'system', ?, 'text', CURRENT_TIMESTAMP)
        ");
        $closingMsg = "চ্যাট সেশন বন্ধ হয়েছে। আমাদের সেবা নেওয়ার জন্য ধন্যবাদ!";
        $stmt->execute([$conversationId, $closingMsg]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Conversation closed successfully'
        ]);
        
    } catch(PDOException $e) {
        error_log("Close conversation error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function assignAdmin($data) {
    try {
        $db = getDB();
        
        $conversationId = (int)$data['conversation_id'];
        $adminId = (int)$data['admin_id'];
        
        // Validate admin exists and has chat permissions
        $stmt = $db->prepare("
            SELECT u.id, u.username, ar.permissions 
            FROM users u 
            LEFT JOIN admin_roles ar ON u.role_id = ar.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
            echo json_encode(['success' => false, 'message' => 'Invalid admin']);
            return;
        }
        
        $permissions = json_decode($admin['permissions'], true);
        if (!isset($permissions['chat']['write']) || !$permissions['chat']['write']) {
            echo json_encode(['success' => false, 'message' => 'Admin does not have chat permissions']);
            return;
        }
        
        $stmt = $db->prepare("UPDATE chat_conversations SET assigned_admin = ? WHERE id = ?");
        $stmt->execute([$adminId, $conversationId]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Admin assigned successfully'
        ]);
        
    } catch(PDOException $e) {
        error_log("Assign admin error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
?>