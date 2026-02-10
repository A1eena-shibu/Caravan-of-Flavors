<?php
/**
 * Get Assigned Orders API for Delivery Agent
 * Fetches all orders assigned to the logged-in delivery agent
 */

header('Content-Type: application/json');

require_once '../../config/session.php';
require_once '../../config/database.php';

// Check authorization
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'delivery_agent') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $agent_id = $_SESSION['user_id'];
    $pdo = getDBConnection();

    // Fetch assigned orders with customer info and product info
    $stmt = $pdo->prepare("
        SELECT 
            o.id,
            o.status,
            o.total_price,
            o.quantity,
            p.unit,
            p.product_name,
            p.image_url as product_image,
            o.delivery_address,
            o.order_date,
            u.full_name as customer_name,
            u.phone as customer_phone
        FROM orders o
        JOIN users u ON o.customer_id = u.id
        JOIN products p ON o.product_id = p.id
        WHERE o.delivery_agent_id = ? AND o.status IN ('ordered', 'shipped')
        ORDER BY o.order_date DESC
    ");

    $stmt->execute([$agent_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $orders]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>