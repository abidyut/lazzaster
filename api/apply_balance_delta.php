<?php
header('Content-Type: application/json');
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get authorization header
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        echo json_encode(['success' => false, 'message' => 'Missing or invalid authorization header']);
        exit;
    }
    
    $token = substr($authHeader, 7); // Remove 'Bearer ' prefix
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (!isset($data['delta'])) {
        echo json_encode(['success' => false, 'message' => 'Delta amount is required']);
        exit;
    }
    
    $delta = floatval($data['delta']);
    $reason = isset($data['reason']) ? sanitizeInput($data['reason']) : 'balance_adjustment';
    
    try {
        $db = getDB();
        
        // Start transaction for atomic balance update
        $db->beginTransaction();
        
        try {
            // Verify token and get user with current balance (lock row for update)
            $stmt = $db->prepare("SELECT id, username, balance FROM users WHERE auth_token = ? FOR UPDATE");
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Invalid token']);
                exit;
            }
            
            $current_balance = floatval($user['balance']);
            $new_balance = $current_balance + $delta;
            
            // Ensure balance doesn't go negative
            if ($new_balance < 0) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Insufficient balance for this operation']);
                exit;
            }
            
            // Update balance atomically
            $stmt = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $stmt->execute([$new_balance, $user['id']]);
            
            // Log the transaction for audit trail
            $stmt = $db->prepare("INSERT INTO balance_history (user_id, delta_amount, previous_balance, new_balance, reason, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$user['id'], $delta, $current_balance, $new_balance, $reason]);
            
            // Commit transaction
            $db->commit();
        
            echo json_encode([
                'success' => true, 
                'message' => 'Balance updated successfully',
                'previous_balance' => $current_balance,
                'delta' => $delta,
                'new_balance' => $new_balance
            ]);
            
        } catch(PDOException $e) {
            $db->rollBack();
            throw $e;
        }
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>