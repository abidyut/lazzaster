<?php
// Database configuration - Using Replit PostgreSQL
$db_url = getenv('DATABASE_URL');
if (!$db_url) {
    die("Database URL not found in environment variables");
}

// Create connection
function getDB() {
    try {
        $db_url = getenv('DATABASE_URL');
        
        // Parse the URL components
        $url_parts = parse_url($db_url);
        $host = $url_parts['host'];
        $port = $url_parts['port'] ?? 5432;
        $dbname = ltrim($url_parts['path'], '/');
        $user = $url_parts['user'];
        $password = $url_parts['pass'];
        
        // Build PostgreSQL DSN for PDO
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        
        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
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
        AND st.is_revoked = FALSE 
        AND st.expires_at > NOW()
    ");
    
    $stmt->execute([$tokenHash, $tokenType]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function revokeToken($db, $token, $revokedBy = null) {
    $tokenHash = hashToken($token);
    
    $stmt = $db->prepare("
        UPDATE secure_tokens 
        SET is_revoked = TRUE, revoked_at = NOW(), revoked_by = ? 
        WHERE token_hash = ?
    ");
    
    return $stmt->execute([$revokedBy, $tokenHash]);
}

function revokeAllUserTokens($db, $userId, $revokedBy = null) {
    $stmt = $db->prepare("
        UPDATE secure_tokens 
        SET is_revoked = TRUE, revoked_at = NOW(), revoked_by = ? 
        WHERE user_id = ? AND is_revoked = FALSE
    ");
    
    return $stmt->execute([$revokedBy, $userId]);
}

function cleanupExpiredTokens($db) {
    $stmt = $db->prepare("DELETE FROM secure_tokens WHERE expires_at < NOW() OR created_at < NOW() - INTERVAL '30 days'");
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

// Enable CORS for API requests - secure configuration
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowed_origins = [
    'http://localhost:5000',
    'https://localhost:5000'
];

// Allow origin from same domain or for development (.replit.dev domains)
if (in_array($origin, $allowed_origins) || strpos($origin, '.replit.dev') !== false) {
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