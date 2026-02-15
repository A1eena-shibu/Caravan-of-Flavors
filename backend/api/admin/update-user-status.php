<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

// Enable error reporting for debugging
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check: Ensure user is admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['user_id']) || !isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$action = $data['action'];

// Super Admin Check for sensitive actions
if (in_array($action, ['block', 'unblock']) && $_SESSION['user_email'] !== 'admin@gmail.com') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Only the Super Admin can block or unblock users.']);
    exit;
}

$userId = $data['user_id'];
$action = $data['action']; // 'block', 'unblock', 'verify', 'unverify'

try {
    $pdo = getDBConnection();

    $query = "";
    $params = [':id' => $userId];

    switch ($action) {
        case 'block':
            $query = "UPDATE users SET is_active = 0 WHERE id = :id";
            break;
        case 'unblock':
            $query = "UPDATE users SET is_active = 1 WHERE id = :id";
            break;
        case 'verify':
            $query = "UPDATE users SET is_verified = 1 WHERE id = :id";
            break;
        case 'unverify':
            $query = "UPDATE users SET is_verified = 0 WHERE id = :id";
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }

    $stmt = $pdo->prepare($query);
    $result = $stmt->execute($params);

    if ($result) {
        // Log the action (Optional but good for admin)
        // $logStmt = $pdo->prepare("INSERT INTO admin_logs ...");

        echo json_encode(['success' => true, 'message' => 'User status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>