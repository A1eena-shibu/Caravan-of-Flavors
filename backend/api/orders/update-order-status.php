<?php
/**
 * Update Order Status API
 * Handles status changes: pending -> confirmed (Accept), pending -> cancelled (Reject), confirmed -> shipped (Shipped)
 */

header('Content-Type: application/json');

require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authorization
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'farmer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Farmer access required.']);
    exit;
}

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);
$order_id = $data['order_id'] ?? null;
$new_status = $data['status'] ?? null;

if (!$order_id || !$new_status) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order ID and new status are required.']);
    exit;
}

try {
    $farmer_id = $_SESSION['user_id'];
    $pdo = getDBConnection();

    // First, verify that this order belongs to the requesting farmer and check its current status
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND farmer_id = ?");
    $stmt->execute([$order_id, $farmer_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found or access denied.']);
        exit;
    }

    $current_status = $order['status'];

    // Validation for state transitions
    $allowed = false;
    if ($new_status === 'confirmed' && $current_status === 'pending')
        $allowed = true; // Accept
    if ($new_status === 'cancelled' && $current_status === 'pending')
        $allowed = true; // Cancel
    if ($new_status === 'rejected' && $current_status === 'pending')
        $allowed = true; // Reject (New)
    if ($new_status === 'shipped' && ($current_status === 'confirmed' || $current_status === 'processing'))
        $allowed = true; // Shipped
    if ($new_status === 'delivered' && $current_status === 'shipped')
        $allowed = true; // Delivered

    if (!$allowed) {
        throw new Exception("Invalid status transition from $current_status to $new_status.");
    }

    // Update status and timestamp
    if (($new_status === 'cancelled' || $new_status === 'rejected') && isset($data['reason'])) {
        $reason = $data['reason'];
        $updateStmt = $pdo->prepare("UPDATE orders SET status = ?, rejection_reason = ?, rejected_at = NOW() WHERE id = ?");
        $result = $updateStmt->execute([$new_status, $reason, $order_id]);
    } else {
        $sql = "UPDATE orders SET status = ?";

        if ($new_status === 'confirmed')
            $sql .= ", accepted_at = NOW()";
        if ($new_status === 'shipped')
            $sql .= ", shipped_at = NOW()";
        if ($new_status === 'delivered')
            $sql .= ", delivered_at = NOW()";
        if ($new_status === 'rejected')
            $sql .= ", rejected_at = NOW()"; // Fallback if no reason provided

        $sql .= " WHERE id = ?";
        $updateStmt = $pdo->prepare($sql);
        $result = $updateStmt->execute([$new_status, $order_id]);
    }

    if ($result) {
        // --- Log to Order Tracking ---
        $comment = "Order status updated to $new_status";
        if (isset($reason))
            $comment .= ". Reason: $reason";

        $trackStmt = $pdo->prepare("INSERT INTO order_tracking (order_id, status, comment) VALUES (?, ?, ?)");
        $trackStmt->execute([$order_id, $new_status, $comment]);
        // -----------------------------

        echo json_encode(['success' => true, 'message' => "Order status updated to $new_status."]);
    } else {
        throw new Exception("Failed to update order status.");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>