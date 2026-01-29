<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

require_once '../../config/database.php';
$pdo = getDBConnection();

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);
$order_id = $data['order_id'] ?? null;

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

try {
    // Check if order exists and belongs to user
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND customer_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found or access denied']);
        exit;
    }

    // Allow cancellation only if pending or confirmed (not yet shipped)
    if (!in_array($order['status'], ['pending', 'confirmed'])) {
        echo json_encode(['success' => false, 'message' => 'Cannot cancel order at this stage (' . $order['status'] . ')']);
        exit;
    }

    // Update status to cancelled
    $updateStmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', rejection_reason = 'Cancelled by Customer' WHERE id = ?");
    $updateStmt->execute([$order_id]);

    echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>