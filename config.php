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