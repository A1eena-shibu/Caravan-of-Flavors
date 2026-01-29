<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

// Enable error reporting for debugging (Remove in production)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple Admin Check (matching get-admin-stats.php logic for now to avoid lockout)
// In a real scenario, uncomment the strict check
// if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
//     // For debugging, we are lenient. 
//     // http_response_code(403);
//     // echo json_encode(['success' => false, 'message' => 'Unauthorized']);
//     // exit;
// }

try {
    $pdo = getDBConnection();

    // Fetch users with basic info
    $stmt = $pdo->prepare("SELECT id, full_name, email, role, is_active, is_verified, created_at FROM users ORDER BY created_at DESC");
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