<?php
header('Content-Type: application/json');
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    $username = sanitizeInput($data['username']);
    $email = sanitizeInput($data['email']);
    $password = password_hash($data['password'], PASSWORD_DEFAULT);
    $referral = isset($data['referral']) ? sanitizeInput($data['referral']) : null;
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
    // Validate username (no spaces)
    if (preg_match('/\s/', $username)) {
        echo json_encode(['success' => false, 'message' => 'Username cannot contain spaces']);
        exit;
    }
    
    try {
        $db = getDB();
        
        // Check if user exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
            exit;
        }
        
        // Create user
        $referral_code = strtoupper(bin2hex(random_bytes(5)));
        $stmt = $db->prepare("INSERT INTO users (username, email, password, referral_code) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $password, $referral_code]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Registration successful. You can now login.'
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>