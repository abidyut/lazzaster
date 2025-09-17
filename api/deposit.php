<?php
header('Content-Type: application/json');
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check for authorization token
    $headers = getallheaders();
    $authToken = null;
    if (isset($headers['Authorization'])) {
        $authToken = str_replace('Bearer ', '', $headers['Authorization']);
    }
    
    // Validate required fields
    if (empty($data['user_id']) || empty($data['username']) || empty($data['method']) || 
        empty($data['transaction_id']) || empty($data['zst_amount'])) {
        echo json_encode(['success' => false, 'message' => 'সব তথ্য পূরণ করুন']);
        exit;
    }
    
    $userId = (int)$data['user_id'];
    $username = sanitizeInput($data['username']);
    $method = sanitizeInput($data['method']);
    $transactionId = sanitizeInput($data['transaction_id']);
    $zstAmount = (float)$data['zst_amount'];
    $bdtAmount = isset($data['bdt_amount']) ? (float)$data['bdt_amount'] : 0;
    $usdAmount = isset($data['usd_amount']) ? (float)$data['usd_amount'] : 0;
    
    // Validate ZST amount
    if ($zstAmount <= 0) {
        echo json_encode(['success' => false, 'message' => 'অবৈধ ডিপোজিট পরিমাণ']);
        exit;
    }
    
    // Check minimum amounts
    if ($method === 'local' && $zstAmount < 2) {
        echo json_encode(['success' => false, 'message' => 'Local payment এর জন্য ন্যূনতম 2 ZST প্রয়োজন']);
        exit;
    }
    
    if ($method === 'binance' && $zstAmount < 5) {
        echo json_encode(['success' => false, 'message' => 'Binance payment এর জন্য ন্যূনতম 5 ZST প্রয়োজন']);
        exit;
    }
    
    try {
        $db = getDB();
        
        // Verify user exists and auth token if provided
        if ($authToken) {
            $stmt = $db->prepare("SELECT id, username, balance FROM users WHERE id = ? AND auth_token = ?");
            $stmt->execute([$userId, $authToken]);
        } else {
            $stmt = $db->prepare("SELECT id, username, balance FROM users WHERE id = ? AND username = ?");
            $stmt->execute([$userId, $username]);
        }
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'অবৈধ ব্যবহারকারী']);
            exit;
        }
        
        // Check if transaction ID already exists
        $stmt = $db->prepare("SELECT id FROM deposits WHERE transaction_id = ?");
        $stmt->execute([$transactionId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'এই Transaction ID ইতিমধ্যে ব্যবহৃত হয়েছে']);
            exit;
        }
        
        // Create deposits table if it doesn't exist
        $createTableSQL = "
            CREATE TABLE IF NOT EXISTS deposits (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL,
                username VARCHAR(50) NOT NULL,
                method VARCHAR(20) NOT NULL,
                transaction_id VARCHAR(100) NOT NULL UNIQUE,
                zst_amount DECIMAL(10,2) NOT NULL,
                bdt_amount DECIMAL(10,2) DEFAULT 0,
                usd_amount DECIMAL(10,2) DEFAULT 0,
                status VARCHAR(20) DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                processed_at TIMESTAMP NULL
            )";
        $db->exec($createTableSQL);
        
        // Insert deposit record
        $stmt = $db->prepare("
            INSERT INTO deposits (user_id, username, method, transaction_id, zst_amount, bdt_amount, usd_amount, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $userId, 
            $username, 
            $method, 
            $transactionId, 
            $zstAmount, 
            $bdtAmount, 
            $usdAmount
        ]);
        
        $depositId = $db->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'ডিপোজিট সফলভাবে জমা দেওয়া হয়েছে! অনুমোদনের অপেক্ষায়।',
            'deposit_id' => $depositId,
            'status' => 'pending',
            'immediate_credit' => false,
            'zst_amount' => $zstAmount
        ]);
        
    } catch(PDOException $e) {
        error_log('Deposit error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'ডাটাবেস ত্রুটি। পুনরায় চেষ্টা করুন।']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'অবৈধ রিকোয়েস্ট পদ্ধতি']);
}
?>