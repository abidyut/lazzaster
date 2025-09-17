<?php
header('Content-Type: application/json');
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get authorization header
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        echo json_encode(['success' => false, 'message' => 'Missing or invalid authorization header']);
        exit;
    }
    
    $token = substr($authHeader, 7); // Remove 'Bearer ' prefix
    
    try {
        $db = getDB();
        
        // Verify token and get user balance
        $stmt = $db->prepare("SELECT id, username, balance FROM users WHERE auth_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
            exit;
        }
        
        echo json_encode([
            'success' => true, 
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'balance' => $user['balance']
            ]
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>