<?php
// Database configuration - Replace with your Infinity Free details
define('DB_HOST', 'sql100.infinityfree.com'); // Your Infinity Free MySQL host
define('DB_NAME', 'if0_39588465_user_auth'); // Your database name
define('DB_USER', 'if0_39588465'); // Your database username
define('DB_PASS', '37aL37aL'); // Your database password

// Create connection
function getDB() {
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
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