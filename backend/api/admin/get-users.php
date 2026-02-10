<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

// Enable error reporting for debugging (Remove in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../../config/session.php';

// Strict Admin Check
require_role('admin');

try {
    $pdo = getDBConnection();

    // Fetch users with extended info for detail view
    $stmt = $pdo->prepare("SELECT id, full_name, email, role, phone, address, is_active, is_verified, created_at, country, currency_code FROM users WHERE role != 'admin' ORDER BY created_at DESC");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'users' => $users
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>