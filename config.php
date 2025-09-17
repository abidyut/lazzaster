<?php
// Database configuration - Using MySQL for InfinityFree hosting
// Update these with your InfinityFree database credentials from control panel

// MySQL Database Configuration
// For InfinityFree, typically format is:
// Host: sql###.epizy.com (from your control panel)
// Database: epiz_########_dbname
// Username: epiz_########_username
// Password: your_password_from_control_panel

$db_host = 'localhost';  // Change to your InfinityFree MySQL host (e.g., sql###.epizy.com)
$db_port = '3306';
$db_name = 'lazzaster_gaming';  // Change to your InfinityFree database name (e.g., epiz_########_lazzaster)
$db_user = 'root';              // Change to your InfinityFree username (e.g., epiz_########_user)
$db_pass = '';                  // Change to your InfinityFree database password

// Create connection
function getDB() {
    global $db_host, $db_port, $db_name, $db_user, $db_pass;
    
    try {
        // Build MySQL DSN for PDO with proper options for PHP 8.3
        $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
        return $pdo;
        
    } catch(PDOException $e) {
        // Log error details securely (don't expose to user)
        error_log("Database connection failed: " . $e->getMessage());
        die("Database connection error. Please contact administrator.");
    }
}

// Security functions
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

// Secure token management functions
function generateSecureToken() {
    return bin2hex(random_bytes(64)); // 128 character token
}

function hashToken($token) {
    return hash('sha256', $token);
}

function createSecureToken($db, $userId, $tokenType = 'auth', $expiryHours = 24) {
    $token = generateSecureToken();
    $tokenHash = hashToken($token);
    $expiresAt = date('Y-m-d H:i:s', time() + ($expiryHours * 3600));
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $db->prepare("
        INSERT INTO secure_tokens (user_id, token_hash, token_type, expires_at, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$userId, $tokenHash, $tokenType, $expiresAt, $ipAddress, $userAgent])) {
        return $token;
    }
    return false;
}

function validateSecureToken($db, $token, $tokenType = 'auth') {
    $tokenHash = hashToken($token);
    
    $stmt = $db->prepare("
        SELECT st.user_id, st.expires_at, u.username, u.status, ar.role_name, ar.permissions
        FROM secure_tokens st
        JOIN users u ON st.user_id = u.id
        LEFT JOIN admin_roles ar ON u.role_id = ar.id
        WHERE st.token_hash = ? 
        AND st.token_type = ? 
        AND st.is_revoked = 0 
        AND st.expires_at > NOW()
    ");
    
    $stmt->execute([$tokenHash, $tokenType]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function revokeToken($db, $token, $revokedBy = null) {
    $tokenHash = hashToken($token);
    
    $stmt = $db->prepare("
        UPDATE secure_tokens 
        SET is_revoked = 1, revoked_at = NOW(), revoked_by = ? 
        WHERE token_hash = ?
    ");
    
    return $stmt->execute([$revokedBy, $tokenHash]);
}

function revokeAllUserTokens($db, $userId, $revokedBy = null) {
    $stmt = $db->prepare("
        UPDATE secure_tokens 
        SET is_revoked = 1, revoked_at = NOW(), revoked_by = ? 
        WHERE user_id = ? AND is_revoked = 0
    ");
    
    return $stmt->execute([$revokedBy, $userId]);
}

function cleanupExpiredTokens($db) {
    $stmt = $db->prepare("DELETE FROM secure_tokens WHERE expires_at < NOW() OR created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    return $stmt->execute();
}

function checkPermission($permissions, $resource, $action) {
    if (empty($permissions)) return false;
    
    $perms = is_string($permissions) ? json_decode($permissions, true) : $permissions;
    return isset($perms[$resource][$action]) && $perms[$resource][$action] === true;
}

function hasAdminAccess($permissions) {
    if (empty($permissions)) return false;
    
    $perms = is_string($permissions) ? json_decode($permissions, true) : $permissions;
    
    // Check if user has any admin permissions
    $adminResources = ['users', 'deposits', 'withdrawals', 'balances', 'settings', 'logs'];
    foreach ($adminResources as $resource) {
        if (isset($perms[$resource]) && is_array($perms[$resource])) {
            foreach ($perms[$resource] as $permission) {
                if ($permission === true) return true;
            }
        }
    }
    return false;
}

function logAdminAction($db, $adminId, $action, $details = null, $targetUserId = null) {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    
    $stmt = $db->prepare("
        INSERT INTO admin_logs (admin_id, action, target_user_id, details, ip_address) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $detailsJson = $details ? json_encode($details) : null;
    return $stmt->execute([$adminId, $action, $targetUserId, $detailsJson, $ipAddress]);
}

// Enable CORS for API requests - configuration for InfinityFree and local development
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowed_origins = [
    'http://localhost:5000',
    'https://localhost:5000'
];

// For same-domain requests (typical for InfinityFree), no CORS headers needed
// Allow origin from allowed list or development domains (.replit.dev, .epizy.com, .infinityfreeapp.com)
if (in_array($origin, $allowed_origins) || 
    strpos($origin, '.replit.dev') !== false ||
    strpos($origin, '.epizy.com') !== false ||
    strpos($origin, '.infinityfreeapp.com') !== false) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Vary: Origin');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>