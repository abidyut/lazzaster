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
        $pdo = new PDO($db_url);
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