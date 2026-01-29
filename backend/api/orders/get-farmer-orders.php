<?php
/**
 * Get Farmer Orders API
 * Fetches all orders received by the logged-in farmer
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

try {
    $farmer_id = $_SESSION['user_id'];
    $pdo = getDBConnection();

    // Join with users (customer) and products
    $stmt = $pdo->prepare("
        SELECT 
            o.*, 
            u.full_name as customer_name, 
            u.email as customer_email,
            p.product_name,
            p.image_url as product_image
        FROM orders o
        JOIN users u ON o.customer_id = u.id
        JOIN products p ON o.product_id = p.id
        WHERE o.farmer_id = ?
        ORDER BY o.order_date DESC
    ");

    $stmt->execute([$farmer_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $orders]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>