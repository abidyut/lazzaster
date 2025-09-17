<?php
header('Content-Type: application/json');
require_once '../config.php';

// Admin credentials - In production, these should be stored securely in database
$ADMIN_CREDENTIALS = [
    'admin' => password_hash('admin123', PASSWORD_DEFAULT), // Default admin/admin123
    // Add more admin users as needed
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    try {
        $db = getDB();
        
        // Handle login separately (doesn't require token)
        if ($action === 'login') {
            handleLogin($data, $ADMIN_CREDENTIALS, $db);
            exit;
        }

        // For all other actions, verify admin token
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            echo json_encode(['success' => false, 'message' => 'Missing authorization header']);
            exit;
        }
        
        $token = substr($authHeader, 7); // Remove 'Bearer ' prefix
        
        // Verify admin token (you might want to store admin tokens in database)
        $stmt = $db->prepare("SELECT id, username FROM users WHERE auth_token = ? AND username IN ('admin')");
        $stmt->execute([$token]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
            exit;
        }

        // Log admin action
        logAdminAction($db, $admin['id'], $action, $data);
        
        // Route to appropriate handler
        switch ($action) {
            case 'get_users':
                getUsersData($db);
                break;
            case 'update_user_status':
                updateUserStatus($db, $data, $admin['id']);
                break;
            case 'update_user':
                updateUser($db, $data, $admin['id']);
                break;
            case 'get_deposits':
                getDepositsData($db);
                break;
            case 'update_deposit_status':
                updateDepositStatus($db, $data, $admin['id']);
                break;
            case 'get_withdrawals':
                getWithdrawalsData($db);
                break;
            case 'update_withdrawal_status':
                updateWithdrawalStatus($db, $data, $admin['id']);
                break;
            case 'adjust_balance':
                adjustUserBalance($db, $data, $admin['id']);
                break;
            case 'get_balance_history':
                getBalanceHistory($db);
                break;
            case 'get_settings':
                getSystemSettings($db);
                break;
            case 'update_settings':
                updateSystemSettings($db, $data, $admin['id']);
                break;
            case 'get_logs':
                getAdminLogs($db);
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

function handleLogin($data, $adminCredentials, $db) {
    $username = sanitizeInput($data['username'] ?? '');
    $password = $data['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        return;
    }
    
    // Check admin credentials
    if (!isset($adminCredentials[$username])) {
        echo json_encode(['success' => false, 'message' => 'Invalid admin credentials']);
        return;
    }
    
    if (!password_verify($password, $adminCredentials[$username])) {
        echo json_encode(['success' => false, 'message' => 'Invalid admin credentials']);
        return;
    }
    
    // Check if admin user exists in database, create if not
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        // Create admin user in database
        $token = generateToken();
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $referralCode = strtoupper(bin2hex(random_bytes(5)));
        
        $stmt = $db->prepare("INSERT INTO users (username, email, password, auth_token, referral_code, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$username, $username . '@admin.local', $hashedPassword, $token, $referralCode]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Admin login successful',
            'token' => $token
        ]);
    } else {
        // Update existing admin token
        $token = generateToken();
        $stmt = $db->prepare("UPDATE users SET auth_token = ? WHERE id = ?");
        $stmt->execute([$token, $admin['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Admin login successful',
            'token' => $token
        ]);
    }
}

function getUsersData($db) {
    $stmt = $db->prepare("
        SELECT id, username, email, balance, status, created_at, last_login,
               (SELECT COUNT(*) FROM deposits WHERE user_id = users.id AND status = 'approved') as total_deposits,
               (SELECT COUNT(*) FROM game_sessions WHERE user_id = users.id) as total_games
        FROM users 
        WHERE username != 'admin'
        ORDER BY created_at DESC 
        LIMIT 100
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $users]);
}

function updateUserStatus($db, $data, $adminId) {
    $userId = (int)($data['user_id'] ?? 0);
    $status = sanitizeInput($data['status'] ?? '');
    
    if (!$userId || !in_array($status, ['active', 'suspended', 'banned'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID or status']);
        return;
    }
    
    $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ? AND username != 'admin'");
    $result = $stmt->execute([$status, $userId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'User status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
    }
}

function updateUser($db, $data, $adminId) {
    $userId = (int)($data['user_id'] ?? 0);
    $username = sanitizeInput($data['username'] ?? '');
    $email = sanitizeInput($data['email'] ?? '');
    $status = sanitizeInput($data['status'] ?? '');
    
    if (!$userId || !$username || !$email || !in_array($status, ['active', 'suspended', 'banned'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        return;
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        return;
    }
    
    // Check if username/email already exists for other users
    $stmt = $db->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $stmt->execute([$username, $email, $userId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
        return;
    }
    
    $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, status = ? WHERE id = ? AND username != 'admin'");
    $result = $stmt->execute([$username, $email, $status, $userId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user']);
    }
}

function getDepositsData($db) {
    $stmt = $db->prepare("
        SELECT d.*, u.username 
        FROM deposits d 
        JOIN users u ON d.user_id = u.id 
        ORDER BY d.created_at DESC 
        LIMIT 100
    ");
    $stmt->execute();
    $deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $deposits]);
}

function updateDepositStatus($db, $data, $adminId) {
    $depositId = (int)($data['deposit_id'] ?? 0);
    $status = sanitizeInput($data['status'] ?? '');
    $adminNotes = sanitizeInput($data['admin_notes'] ?? '');
    
    if (!$depositId || !in_array($status, ['approved', 'rejected', 'cancelled'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid deposit ID or status']);
        return;
    }
    
    $db->beginTransaction();
    
    try {
        // Get deposit details
        $stmt = $db->prepare("SELECT * FROM deposits WHERE id = ?");
        $stmt->execute([$depositId]);
        $deposit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deposit) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Deposit not found']);
            return;
        }
        
        // Update deposit status
        $stmt = $db->prepare("UPDATE deposits SET status = ?, admin_notes = ?, processed_at = NOW(), processed_by = ? WHERE id = ?");
        $stmt->execute([$status, $adminNotes, $adminId, $depositId]);
        
        // If approved, add balance to user
        if ($status === 'approved' && $deposit['status'] === 'pending') {
            $stmt = $db->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$deposit['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $newBalance = $user['balance'] + $deposit['zst_amount'];
                
                // Update user balance
                $stmt = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
                $stmt->execute([$newBalance, $deposit['user_id']]);
                
                // Log balance change
                $stmt = $db->prepare("INSERT INTO balance_history (user_id, delta_amount, previous_balance, new_balance, reason, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$deposit['user_id'], $deposit['zst_amount'], $user['balance'], $newBalance, 'deposit_approved']);
            }
        }
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Deposit status updated successfully']);
        
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to update deposit status: ' . $e->getMessage()]);
    }
}

function getWithdrawalsData($db) {
    $stmt = $db->prepare("
        SELECT w.*, u.username 
        FROM withdrawals w 
        JOIN users u ON w.user_id = u.id 
        ORDER BY w.created_at DESC 
        LIMIT 100
    ");
    $stmt->execute();
    $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $withdrawals]);
}

function updateWithdrawalStatus($db, $data, $adminId) {
    $withdrawalId = (int)($data['withdrawal_id'] ?? 0);
    $status = sanitizeInput($data['status'] ?? '');
    $adminNotes = sanitizeInput($data['admin_notes'] ?? '');
    
    if (!$withdrawalId || !in_array($status, ['approved', 'rejected', 'cancelled', 'completed'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid withdrawal ID or status']);
        return;
    }
    
    $stmt = $db->prepare("UPDATE withdrawals SET status = ?, admin_notes = ?, processed_at = NOW(), processed_by = ? WHERE id = ?");
    $result = $stmt->execute([$status, $adminNotes, $adminId, $withdrawalId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Withdrawal status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update withdrawal status']);
    }
}

function adjustUserBalance($db, $data, $adminId) {
    $userId = (int)($data['user_id'] ?? 0);
    $delta = floatval($data['delta'] ?? 0);
    $reason = sanitizeInput($data['reason'] ?? 'admin_adjustment');
    
    if (!$userId || $delta == 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID or delta amount']);
        return;
    }
    
    $db->beginTransaction();
    
    try {
        // Get user current balance (with lock)
        $stmt = $db->prepare("SELECT id, username, balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }
        
        $currentBalance = floatval($user['balance']);
        $newBalance = $currentBalance + $delta;
        
        // Ensure balance doesn't go negative
        if ($newBalance < 0) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Insufficient balance for this adjustment']);
            return;
        }
        
        // Update user balance
        $stmt = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $userId]);
        
        // Log the transaction
        $stmt = $db->prepare("INSERT INTO balance_history (user_id, delta_amount, previous_balance, new_balance, reason, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $delta, $currentBalance, $newBalance, $reason]);
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Balance adjusted successfully']);
        
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to adjust balance: ' . $e->getMessage()]);
    }
}

function getBalanceHistory($db) {
    $stmt = $db->prepare("
        SELECT bh.*, u.username 
        FROM balance_history bh 
        JOIN users u ON bh.user_id = u.id 
        ORDER BY bh.created_at DESC 
        LIMIT 50
    ");
    $stmt->execute();
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $history]);
}

function getSystemSettings($db) {
    $stmt = $db->prepare("SELECT * FROM system_settings ORDER BY setting_key");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $settings]);
}

function updateSystemSettings($db, $data, $adminId) {
    $settings = $data['settings'] ?? [];
    
    if (empty($settings)) {
        echo json_encode(['success' => false, 'message' => 'No settings provided']);
        return;
    }
    
    $db->beginTransaction();
    
    try {
        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
        }
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
        
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to update settings: ' . $e->getMessage()]);
    }
}

function getAdminLogs($db) {
    $stmt = $db->prepare("
        SELECT al.*, u.username as admin_username 
        FROM admin_logs al 
        LEFT JOIN users u ON al.admin_id = u.id 
        ORDER BY al.created_at DESC 
        LIMIT 100
    ");
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $logs]);
}

function logAdminAction($db, $adminId, $action, $data) {
    try {
        $targetUserId = null;
        $details = null;
        
        // Extract target user ID and relevant details based on action
        switch ($action) {
            case 'update_user_status':
            case 'update_user':
                $targetUserId = $data['user_id'] ?? null;
                break;
            case 'update_deposit_status':
                $targetUserId = null; // Could query deposit to get user_id if needed
                $details = json_encode(['deposit_id' => $data['deposit_id'] ?? null, 'status' => $data['status'] ?? null]);
                break;
            case 'adjust_balance':
                $targetUserId = $data['user_id'] ?? null;
                $details = json_encode(['delta' => $data['delta'] ?? null, 'reason' => $data['reason'] ?? null]);
                break;
        }
        
        $stmt = $db->prepare("INSERT INTO admin_logs (admin_id, action, target_user_id, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$adminId, $action, $targetUserId, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
        
    } catch (Exception $e) {
        // Log error but don't break the main flow
        error_log("Failed to log admin action: " . $e->getMessage());
    }
}
?>