<?php
header('Content-Type: application/json');
require_once '../config.php';

// Enable detailed error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to client

// Cleanup expired tokens on each request
try {
    $db = getDB();
    cleanupExpiredTokens($db);
} catch (Exception $e) {
    error_log("Token cleanup failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit;
    }
    
    $action = sanitizeInput($data['action'] ?? '');

    try {
        $db = getDB();
        
        // Handle login separately (doesn't require token)
        if ($action === 'login') {
            handleSecureLogin($db, $data);
            exit;
        }

        // For all other actions, verify admin token with role-based permissions
        $authResult = verifyAdminAuth($db);
        if (!$authResult['success']) {
            echo json_encode($authResult);
            exit;
        }
        
        $adminData = $authResult['admin'];
        
        // Log all admin actions
        logAdminAction($db, $adminData['user_id'], $action, $data, $data['user_id'] ?? null);
        
        // Route to appropriate handler with permission checks
        switch ($action) {
            case 'logout':
                handleLogout($db, $adminData);
                break;
            case 'get_users':
                requirePermission($adminData['permissions'], 'users', 'read');
                getUsersData($db);
                break;
            case 'update_user_status':
                requirePermission($adminData['permissions'], 'users', 'write');
                updateUserStatus($db, $data, $adminData['user_id']);
                break;
            case 'update_user':
                requirePermission($adminData['permissions'], 'users', 'write');
                updateUser($db, $data, $adminData['user_id']);
                break;
            case 'get_deposits':
                requirePermission($adminData['permissions'], 'deposits', 'read');
                getDepositsData($db);
                break;
            case 'update_deposit_status':
                requirePermission($adminData['permissions'], 'deposits', 'approve');
                updateDepositStatus($db, $data, $adminData['user_id']);
                break;
            case 'get_withdrawals':
                requirePermission($adminData['permissions'], 'withdrawals', 'read');
                getWithdrawalsData($db);
                break;
            case 'update_withdrawal_status':
                requirePermission($adminData['permissions'], 'withdrawals', 'approve');
                updateWithdrawalStatus($db, $data, $adminData['user_id']);
                break;
            case 'adjust_balance':
                requirePermission($adminData['permissions'], 'balances', 'adjust');
                adjustUserBalance($db, $data, $adminData['user_id']);
                break;
            case 'get_balance_history':
                requirePermission($adminData['permissions'], 'balances', 'read');
                getBalanceHistory($db);
                break;
            case 'get_settings':
                requirePermission($adminData['permissions'], 'settings', 'read');
                getSystemSettings($db);
                break;
            case 'update_settings':
                requirePermission($adminData['permissions'], 'settings', 'write');
                updateSystemSettings($db, $data, $adminData['user_id']);
                break;
            case 'get_logs':
                requirePermission($adminData['permissions'], 'logs', 'read');
                getAdminLogs($db);
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
        
    } catch (PermissionException $e) {
        echo json_encode(['success' => false, 'message' => 'Access denied: ' . $e->getMessage()]);
    } catch (PDOException $e) {
        error_log("Database error in admin.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    } catch (Exception $e) {
        error_log("General error in admin.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Custom exception for permission errors
class PermissionException extends Exception {}

function requirePermission($permissions, $resource, $action) {
    if (!checkPermission($permissions, $resource, $action)) {
        throw new PermissionException("Insufficient permissions for $resource:$action");
    }
}

function verifyAdminAuth($db) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        return ['success' => false, 'message' => 'Missing authorization header'];
    }
    
    $token = substr($authHeader, 7); // Remove 'Bearer ' prefix
    
    // Validate token using secure token system
    $tokenData = validateSecureToken($db, $token, 'admin');
    
    if (!$tokenData) {
        return ['success' => false, 'message' => 'Invalid or expired token'];
    }
    
    if ($tokenData['status'] !== 'active') {
        return ['success' => false, 'message' => 'Admin account is not active'];
    }
    
    if (!hasAdminAccess($tokenData['permissions'])) {
        return ['success' => false, 'message' => 'User does not have admin access'];
    }
    
    return [
        'success' => true,
        'admin' => [
            'user_id' => $tokenData['user_id'],
            'username' => $tokenData['username'],
            'role_name' => $tokenData['role_name'],
            'permissions' => $tokenData['permissions']
        ]
    ];
}

function handleSecureLogin($db, $data) {
    $username = sanitizeInput($data['username'] ?? '');
    $password = $data['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        return;
    }
    
    // Rate limiting - check recent failed attempts
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $db->prepare("
        SELECT COUNT(*) as failed_attempts 
        FROM admin_logs 
        WHERE action = 'failed_login' 
        AND ip_address = ? 
        AND created_at > NOW() - INTERVAL '15 minutes'
    ");
    $stmt->execute([$ipAddress]);
    $failedAttempts = $stmt->fetch(PDO::FETCH_ASSOC)['failed_attempts'];
    
    if ($failedAttempts >= 5) {
        logAdminAction($db, null, 'rate_limited_login', ['ip' => $ipAddress, 'username' => $username]);
        echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please try again later.']);
        return;
    }
    
    $db->beginTransaction();
    
    try {
        // Get user with admin role
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.password, u.status, ar.role_name, ar.permissions
            FROM users u
            JOIN admin_roles ar ON u.role_id = ar.id
            WHERE u.username = ? AND u.status = 'active'
        ");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin || !password_verify($password, $admin['password'])) {
            logAdminAction($db, null, 'failed_login', ['username' => $username, 'ip' => $ipAddress]);
            $db->commit();
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            return;
        }
        
        if (!hasAdminAccess($admin['permissions'])) {
            logAdminAction($db, $admin['id'], 'unauthorized_access', ['username' => $username]);
            $db->commit();
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        
        // Revoke existing admin tokens for this user
        revokeAllUserTokens($db, $admin['id']);
        
        // Create new secure admin token (24 hour expiry)
        $token = createSecureToken($db, $admin['id'], 'admin', 24);
        
        if (!$token) {
            throw new Exception('Failed to create secure token');
        }
        
        // Update last login
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$admin['id']]);
        
        logAdminAction($db, $admin['id'], 'successful_login', ['ip' => $ipAddress]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'admin' => [
                'username' => $admin['username'],
                'role' => $admin['role_name'],
                'permissions' => json_decode($admin['permissions'], true)
            ]
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Admin login error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Login failed']);
    }
}

function handleLogout($db, $adminData) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    $token = substr($authHeader, 7);
    
    revokeToken($db, $token, $adminData['user_id']);
    logAdminAction($db, $adminData['user_id'], 'logout');
    
    echo json_encode(['success' => true, 'message' => 'Logout successful']);
}

function getUsersData($db) {
    $stmt = $db->prepare("
        SELECT id, username, email, balance, status, created_at, last_login,
               (SELECT COUNT(*) FROM deposits WHERE user_id = users.id AND status = 'approved') as total_deposits,
               (SELECT COUNT(*) FROM game_sessions WHERE user_id = users.id) as total_games
        FROM users 
        WHERE role_id IS NULL OR role_id NOT IN (SELECT id FROM admin_roles WHERE role_name IN ('super_admin', 'admin'))
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
    
    $db->beginTransaction();
    
    try {
        // Prevent modifying admin users
        $stmt = $db->prepare("
            SELECT username FROM users 
            WHERE id = ? AND (role_id IS NULL OR role_id NOT IN (SELECT id FROM admin_roles))
        ");
        $stmt->execute([$userId]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Cannot modify admin users');
        }
        
        $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
        $result = $stmt->execute([$status, $userId]);
        
        if ($result && $stmt->rowCount() > 0) {
            // Revoke all tokens if user is suspended or banned
            if (in_array($status, ['suspended', 'banned'])) {
                revokeAllUserTokens($db, $userId, $adminId);
            }
            
            $db->commit();
            echo json_encode(['success' => true, 'message' => 'User status updated successfully']);
        } else {
            throw new Exception('No rows affected');
        }
        
    } catch (Exception $e) {
        $db->rollBack();
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
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        return;
    }
    
    $db->beginTransaction();
    
    try {
        // Prevent modifying admin users
        $stmt = $db->prepare("
            SELECT username FROM users 
            WHERE id = ? AND (role_id IS NULL OR role_id NOT IN (SELECT id FROM admin_roles))
        ");
        $stmt->execute([$userId]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Cannot modify admin users');
        }
        
        // Check if username/email already exists for other users
        $stmt = $db->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $userId]);
        
        if ($stmt->rowCount() > 0) {
            throw new Exception('Username or email already exists');
        }
        
        $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, status = ? WHERE id = ?");
        $result = $stmt->execute([$username, $email, $status, $userId]);
        
        if ($result && $stmt->rowCount() > 0) {
            $db->commit();
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        } else {
            throw new Exception('No rows affected');
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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
        // Get deposit details with FOR UPDATE lock
        $stmt = $db->prepare("SELECT * FROM deposits WHERE id = ? FOR UPDATE");
        $stmt->execute([$depositId]);
        $deposit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deposit) {
            throw new Exception('Deposit not found');
        }
        
        if ($deposit['status'] !== 'pending') {
            throw new Exception('Deposit has already been processed');
        }
        
        // Update deposit status
        $stmt = $db->prepare("
            UPDATE deposits 
            SET status = ?, admin_notes = ?, processed_at = NOW(), processed_by = ? 
            WHERE id = ?
        ");
        $stmt->execute([$status, $adminNotes, $adminId, $depositId]);
        
        // If approved, update user balance
        if ($status === 'approved') {
            $stmt = $db->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$deposit['user_id']]);
            $currentBalance = $stmt->fetch(PDO::FETCH_ASSOC)['balance'];
            
            $newBalance = $currentBalance + $deposit['zst_amount'];
            
            $stmt = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $stmt->execute([$newBalance, $deposit['user_id']]);
            
            // Record balance history
            $stmt = $db->prepare("
                INSERT INTO balance_history (user_id, delta_amount, previous_balance, new_balance, reason, transaction_ref) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $deposit['user_id'],
                $deposit['zst_amount'],
                $currentBalance,
                $newBalance,
                'deposit_approved',
                'DEP_' . $depositId
            ]);
        }
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Deposit status updated successfully']);
        
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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
    
    $db->beginTransaction();
    
    try {
        // Get withdrawal details with FOR UPDATE lock
        $stmt = $db->prepare("SELECT * FROM withdrawals WHERE id = ? FOR UPDATE");
        $stmt->execute([$withdrawalId]);
        $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$withdrawal) {
            throw new Exception('Withdrawal not found');
        }
        
        if (!in_array($withdrawal['status'], ['pending', 'approved'])) {
            throw new Exception('Withdrawal cannot be modified in current status');
        }
        
        // Update withdrawal status
        $stmt = $db->prepare("
            UPDATE withdrawals 
            SET status = ?, admin_notes = ?, processed_at = NOW(), processed_by = ? 
            WHERE id = ?
        ");
        $stmt->execute([$status, $adminNotes, $adminId, $withdrawalId]);
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Withdrawal status updated successfully']);
        
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function adjustUserBalance($db, $data, $adminId) {
    $userId = (int)($data['user_id'] ?? 0);
    $amount = (float)($data['amount'] ?? 0);
    $reason = sanitizeInput($data['reason'] ?? '');
    
    if (!$userId || $amount == 0 || empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        return;
    }
    
    $db->beginTransaction();
    
    try {
        // Get current balance with FOR UPDATE lock
        $stmt = $db->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $currentBalance = $stmt->fetch(PDO::FETCH_ASSOC)['balance'];
        
        if ($currentBalance === null) {
            throw new Exception('User not found');
        }
        
        $newBalance = $currentBalance + $amount;
        
        if ($newBalance < 0) {
            throw new Exception('Insufficient balance');
        }
        
        // Update user balance
        $stmt = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $userId]);
        
        // Record balance history
        $stmt = $db->prepare("
            INSERT INTO balance_history (user_id, delta_amount, previous_balance, new_balance, reason, transaction_ref) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $amount,
            $currentBalance,
            $newBalance,
            'admin_adjustment: ' . $reason,
            'ADJ_' . time() . '_' . $adminId
        ]);
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Balance adjusted successfully']);
        
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getBalanceHistory($db) {
    $stmt = $db->prepare("
        SELECT bh.*, u.username 
        FROM balance_history bh 
        JOIN users u ON bh.user_id = u.id 
        ORDER BY bh.created_at DESC 
        LIMIT 200
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
    if (!isset($data['settings']) || !is_array($data['settings'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid settings data']);
        return;
    }
    
    $db->beginTransaction();
    
    try {
        foreach ($data['settings'] as $setting) {
            $key = sanitizeInput($setting['key'] ?? '');
            $value = sanitizeInput($setting['value'] ?? '');
            
            if (empty($key)) continue;
            
            $stmt = $db->prepare("
                UPDATE system_settings 
                SET setting_value = ?, updated_at = NOW() 
                WHERE setting_key = ?
            ");
            $stmt->execute([$value, $key]);
        }
        
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
        
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to update settings']);
    }
}

function getAdminLogs($db) {
    $stmt = $db->prepare("
        SELECT al.*, u.username as admin_username, tu.username as target_username
        FROM admin_logs al 
        LEFT JOIN users u ON al.admin_id = u.id
        LEFT JOIN users tu ON al.target_user_id = tu.id
        ORDER BY al.created_at DESC 
        LIMIT 500
    ");
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $logs]);
}
?>