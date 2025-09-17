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
    if (!isset($data['balance']) || !isset($data['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Balance and user_id are required']);
        exit;
    }
    
    $new_balance = floatval($data['balance']);
    $user_id = intval($data['user_id']);
    
    try {
        $db = getDB();
        
        // Verify token and get user
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND auth_token = ?");
        $stmt->execute([$user_id, $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Invalid token or user']);
            exit;
        }
        
        // Update balance
        $stmt = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
        $stmt->execute([$new_balance, $user_id]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Balance updated successfully',
            'new_balance' => $new_balance
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>