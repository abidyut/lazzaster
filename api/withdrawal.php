<?php
header('Content-Type: application/json');
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check for authorization token with fallback
    $authToken = null;
    
    // Try getallheaders() first
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $authToken = str_replace('Bearer ', '', $headers['Authorization']);
        }
    }
    
    // Fallback to $_SERVER if not found
    if (!$authToken && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authToken = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    }
    
    if (!$authToken) {
        echo json_encode(['success' => false, 'message' => 'অনুমোদন টোকেন প্রয়োজন']);
        exit;
    }
    
    // Validate required fields
    if (empty($data['method']) || empty($data['account_details']) || empty($data['zst_amount'])) {
        echo json_encode(['success' => false, 'message' => 'সব তথ্য পূরণ করুন']);
        exit;
    }
    
    $method = sanitizeInput($data['method']);
    $accountDetails = $data['account_details']; // Keep as array for JSON storage
    $zstAmount = (float)$data['zst_amount'];
    
    // Validate method
    if (!in_array($method, ['bank', 'mobile', 'crypto'])) {
        echo json_encode(['success' => false, 'message' => 'অবৈধ উইথড্র পদ্ধতি']);
        exit;
    }
    
    // Validate ZST amount
    if ($zstAmount <= 0) {
        echo json_encode(['success' => false, 'message' => 'অবৈধ উইথড্র পরিমাণ']);
        exit;
    }
    
    // Check minimum withdrawal amount (5 ZST as requested)
    if ($zstAmount < 5) {
        echo json_encode(['success' => false, 'message' => 'ন্যূনতম উইথড্র পরিমাণ ৫ ZST']);
        exit;
    }
    
    try {
        $db = getDB();
        
        // Verify user exists and get user info
        $stmt = $db->prepare("SELECT id, username, balance FROM users WHERE auth_token = ?");
        $stmt->execute([$authToken]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'অবৈধ ব্যবহারকারী']);
            exit;
        }
        
        // Check if user has sufficient balance
        if ($user['balance'] < $zstAmount) {
            echo json_encode(['success' => false, 'message' => 'অপর্যাপ্ত ব্যালেন্স']);
            exit;
        }
        
        // Check for pending withdrawals (prevent multiple pending requests)
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM withdrawals WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$user['id']]);
        $pendingCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($pendingCount > 0) {
            echo json_encode(['success' => false, 'message' => 'আপনার আগের উইথড্র রিকুয়েস্ট পেন্ডিং আছে']);
            exit;
        }
        
        // Calculate BDT and USD amounts based on current rates
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key IN ('zst_to_bdt_rate', 'zst_to_usd_rate')");
        $stmt->execute();
        $rates = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $bdtAmount = $zstAmount * floatval($rates['zst_to_bdt_rate'] ?? 100);
        $usdAmount = $zstAmount * floatval($rates['zst_to_usd_rate'] ?? 0.90);
        
        $db->beginTransaction();
        
        try {
            // Deduct from user balance immediately (reserved for withdrawal)
            $newBalance = $user['balance'] - $zstAmount;
            $stmt = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $stmt->execute([$newBalance, $user['id']]);
            
            // Record balance history
            $stmt = $db->prepare("
                INSERT INTO balance_history (user_id, delta_amount, previous_balance, new_balance, reason) 
                VALUES (?, ?, ?, ?, 'withdrawal_pending')
            ");
            $stmt->execute([$user['id'], -$zstAmount, $user['balance'], $newBalance]);
            
            // Create withdrawal request
            $stmt = $db->prepare("
                INSERT INTO withdrawals (user_id, username, method, account_details, zst_amount, bdt_amount, usd_amount, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP)
            ");
            
            $stmt->execute([
                $user['id'],
                $user['username'],
                $method,
                json_encode($accountDetails),
                $zstAmount,
                $bdtAmount,
                $usdAmount
            ]);
            
            $withdrawalId = $db->lastInsertId();
            
            $db->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'উইথড্র রিকুয়েস্ট সফলভাবে জমা হয়েছে',
                'withdrawal_id' => $withdrawalId,
                'new_balance' => $newBalance
            ]);
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        
    } catch(PDOException $e) {
        error_log("Withdrawal request error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'ডেটাবেস এরর ঘটেছে']);
    }
    
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get user withdrawal history - Check for authorization token with fallback
    $authToken = null;
    
    // Try getallheaders() first
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $authToken = str_replace('Bearer ', '', $headers['Authorization']);
        }
    }
    
    // Fallback to $_SERVER if not found
    if (!$authToken && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authToken = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    }
    
    if (!$authToken) {
        echo json_encode(['success' => false, 'message' => 'অনুমোদন টোকেন প্রয়োজন']);
        exit;
    }
    
    try {
        $db = getDB();
        
        // Get user ID from token
        $stmt = $db->prepare("SELECT id FROM users WHERE auth_token = ?");
        $stmt->execute([$authToken]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'অবৈধ ব্যবহারকারী']);
            exit;
        }
        
        // Get withdrawal history
        $stmt = $db->prepare("
            SELECT id, method, zst_amount, bdt_amount, usd_amount, status, created_at, processed_at, admin_notes
            FROM withdrawals 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 20
        ");
        $stmt->execute([$user['id']]);
        $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'withdrawals' => $withdrawals
        ]);
        
    } catch(PDOException $e) {
        error_log("Get withdrawals error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'ডেটাবেস এরর ঘটেছে']);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>