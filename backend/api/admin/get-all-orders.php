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
    // http_response_code(403);
    // echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    // exit;
}

try {
    $pdo = getDBConnection();

    // Fetch ALL orders with Customer and Farmer details
    $sql = "
        SELECT 
            o.id, 
            o.order_date, 
            o.quantity, 
            o.total_price, 
            o.status, 
            o.payment_status,
            o.payment_method,
            p.product_name, 
            p.image_url as product_image,
            c.full_name as customer_name,
            f.full_name as farmer_name
        FROM orders o
        JOIN products p ON o.product_id = p.id
        JOIN users c ON o.customer_id = c.id
        JOIN users f ON o.farmer_id = f.id
        ORDER BY o.order_date DESC
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