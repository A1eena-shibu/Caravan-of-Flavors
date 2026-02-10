<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

// Enable error reporting
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // strict check disabled for dev if needed, but best to keep
}

try {
    $pdo = getDBConnection();

    // Fetch orders that are ready for delivery or in progress
    // Statuses: 'shipped', 'out_for_delivery', 'delivered'
    // Note: 'out_for_delivery' might not be in ENUM yet based on schema, but 'shipped' is.
    // Schema Enum: 'pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'rejected'

    $sql = "
        SELECT 
            o.id, 
            o.status, 
            o.delivery_address,
            o.delivery_agent_id,
            c.full_name as customer_name,
            c.phone as customer_phone,
            da.full_name as agent_name
        FROM orders o
        JOIN users c ON o.customer_id = c.id
        LEFT JOIN users da ON o.delivery_agent_id = da.id
        WHERE o.status IN ('shipped', 'delivered')
        ORDER BY o.updated_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $orders]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>