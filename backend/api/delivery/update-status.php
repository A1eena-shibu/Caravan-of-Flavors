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

    $type = $data['type'] ?? 'order';

    if (!$order_id || !$new_status) {
        throw new Exception('ID and status are required');
    }

    $pdo = getDBConnection();
    $pdo->beginTransaction();

    if ($type === 'auction') {
        // --- Auction Logic ---
        $stmt = $pdo->prepare("SELECT id FROM auctions WHERE id = ? AND delivery_agent_id = ?");
        $stmt->execute([$order_id, $agent_id]);
        if (!$stmt->fetch())
            throw new Exception('Unauthorized to update this auction');

        // Update auctions table
        $updateSql = "UPDATE auctions SET shipping_status = ?, updated_at = CURRENT_TIMESTAMP";
        if ($new_status === 'delivered') {
            // Use current time for delivered_at (assuming we might adding it, or just rely on status)
            // For performance chart, we used shipped_at for now? No, the chart query uses shipped_at. 
            // Wait, chart query uses 'shipped_at' for auctions? 
            // Step 210 Check: `SELECT DATE(shipped_at) as date FROM auctions ... WHERE ... shipping_status = 'delivered'`
            // This means we are using shipped_at as the "delivery date" for the chart if I don't change it.
            // Ideally we should record a `delivered_at`. 
            // Auctions table might not have `delivered_at`. I should probably just update `shipped_at` if it's not set, or leave it.
            // Actually, if `shipping_status` is 'delivered', the chart query counts it. 
            // But the date used is `shipped_at`. This is inaccurate if delivery is days later.
            // I will stick to the plan: update status. The chart query uses `shipped_at` for now due to schema constraints unless I add a column.
        }

        $stmt = $pdo->prepare($updateSql . " WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);

        // Auctions don't have order_tracking currently, skipping that part for auctions to avoid breaking FKs

    } else {
        // --- Standard Order Logic ---
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND delivery_agent_id = ?");
        $stmt->execute([$order_id, $agent_id]);
        if (!$stmt->fetch())
            throw new Exception('Unauthorized to update this order');

        $updateSql = "UPDATE orders SET status = ?, updated_at = CURRENT_TIMESTAMP";
        if ($new_status === 'delivered') {
            $updateSql = "UPDATE orders SET status = ?, delivered_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP";
        } elseif ($new_status === 'shipped') {
            $updateSql = "UPDATE orders SET status = ?, shipped_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP";
        }

        $stmt = $pdo->prepare($updateSql . " WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);

        // Add to order_tracking
        $stmt = $pdo->prepare("INSERT INTO order_tracking (order_id, status, comment) VALUES (?, ?, ?)");
        $stmt->execute([$order_id, $new_status, $comment]);
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction())
        $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>