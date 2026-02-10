<?php
/**
 * Update Delivery Status API
 * Changes the status of an order and logs tracking info
 */

header('Content-Type: application/json');

require_once '../../config/session.php';
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'delivery_agent') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $order_id = $data['order_id'] ?? null;
    $new_status = $data['status'] ?? null;
    $comment = $data['comment'] ?? 'Status updated by delivery agent';
    $agent_id = $_SESSION['user_id'];

    if (!$order_id || !$new_status) {
        throw new Exception('Order ID and status are required');
    }

    $pdo = getDBConnection();

    // Check if this agent is actually assigned to this order
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND delivery_agent_id = ?");
    $stmt->execute([$order_id, $agent_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Unauthorized to update this order');
    }

    $pdo->beginTransaction();

    // Update orders table
    $updateSql = "UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP";
    $params = [$new_status, $order_id];

    if ($new_status === 'delivered') {
        $updateSql = "UPDATE orders SET status = ?, delivered_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP";
    } elseif ($new_status === 'shipped') {
        $updateSql = "UPDATE orders SET status = ?, shipped_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP";
    }

    $stmt = $pdo->prepare($updateSql . " WHERE id = ?");
    $stmt->execute($params);

    // Add to order_tracking
    $stmt = $pdo->prepare("INSERT INTO order_tracking (order_id, status, comment) VALUES (?, ?, ?)");
    $stmt->execute([$order_id, $new_status, $comment]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction())
        $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>