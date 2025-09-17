<?php
// This endpoint has been deprecated for security reasons
// Use /api/apply_balance_delta.php instead
header('Content-Type: application/json');

// Log deprecated endpoint access for monitoring
error_log("DEPRECATED ENDPOINT ACCESSED: /api/update_balance.php from " . $_SERVER['REMOTE_ADDR'] . " at " . date('Y-m-d H:i:s'));

http_response_code(410); // Gone
echo json_encode(['success' => false, 'message' => 'This endpoint has been deprecated. Use /api/apply_balance_delta.php instead']);
?>