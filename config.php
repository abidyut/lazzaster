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

// Enable CORS for API requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
?>