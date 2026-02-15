<?php
/**
 * Delete Admin API
 * Allows the Super Admin to permanently delete an administrator account
 */

header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/session.php';

// Strict Admin Check
require_role('admin');

// Super Admin Check (only admin@gmail.com can delete other admins)
if ($_SESSION['user_email'] !== 'admin@gmail.com') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Only the Super Admin can delete administrator accounts.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['admin_id'])) {
        throw new Exception('Admin ID is required');
    }

    $admin_id = $data['admin_id'];

    $pdo = getDBConnection();

    // Verify the target is actually an admin and not the super admin itself
    $stmt = $pdo->prepare("SELECT email, role FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        throw new Exception('Administrator not found');
    }

    if ($target['role'] !== 'admin') {
        throw new Exception('User is not an administrator');
    }

    if ($target['email'] === 'admin@gmail.com') {
        throw new Exception('The Super Admin account cannot be deleted');
    }

    // Delete the admin
    $delete_stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    if ($delete_stmt->execute([$admin_id])) {
        // Log the action
        $current_admin_id = $_SESSION['user_id'];
        $log_stmt = $pdo->prepare("
            INSERT INTO admin_logs (admin_id, action_type, target_table, target_id, description)
            VALUES (?, 'delete_user', 'users', ?, ?)
        ");
        $log_stmt->execute([$current_admin_id, $admin_id, "Deleted administrator: " . $target['email']]);

        echo json_encode([
            'success' => true,
            'message' => 'Administrator account deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete administrator account');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>