<?php
/**
 * Admin API: Get All Activity Logs
 * Aggregates activities from orders, products, users, and reviews with details.
 */

header('Content-Type: application/json');
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../config/database.php';

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        (SELECT 
            'order' as type,
            ot.updated_at as timestamp,
            u.full_name as user_name,
            u.role as user_role,
            CASE 
                WHEN ot.status = 'pending' THEN 'placed a new order'
                WHEN ot.status = 'confirmed' THEN 'confirmed the order'
                WHEN ot.status = 'paid' THEN 'paid for the order'
                WHEN ot.status = 'processing' THEN 'is processing the order'
                WHEN ot.status = 'shipped' THEN 'shipped the order'
                WHEN ot.status = 'delivered' THEN 'delivered the order'
                WHEN ot.status = 'cancelled' THEN 'cancelled the order'
                WHEN ot.status = 'rejected' THEN 'rejected the order'
                ELSE CONCAT('updated order status to ', ot.status)
            END as action,
            CONCAT(p.product_name, ' (', o.quantity, ' ', p.unit, ') from farmer ', f.full_name) as details,
            o.id as reference_id,
            o.total_price as amount
        FROM order_tracking ot
        JOIN orders o ON ot.order_id = o.id
        JOIN users u ON o.customer_id = u.id
        JOIN products p ON o.product_id = p.id
        JOIN users f ON o.farmer_id = f.id)

        UNION ALL

        (SELECT 
            'product' as type,
            p.created_at as timestamp,
            u.full_name as user_name,
            u.role as user_role,
            'listed a new product' as action,
            CONCAT(p.product_name, ' in ', p.category, ' - ', p.quantity, ' ', p.unit, ' @ ', p.price, '/', p.unit) as details,
            p.id as reference_id,
            p.price as amount
        FROM products p
        JOIN users u ON p.farmer_id = u.id)

        UNION ALL

        (SELECT 
            'user' as type,
            created_at as timestamp,
            full_name as user_name,
            role as user_role,
            CONCAT('registered as a ', role) as action,
            CONCAT('Email: ', email) as details,
            id as reference_id,
            0 as amount
        FROM users
        WHERE role != 'admin')

        UNION ALL

        (SELECT 
            'review' as type,
            r.created_at as timestamp,
            u.full_name as user_name,
            u.role as user_role,
            'wrote a review' as action,
            CONCAT('On ', p.product_name, ': \"', LEFT(r.review_text, 50), '...\" (', r.rating, '/5 stars)') as details,
            r.id as reference_id,
            CAST(r.rating AS DECIMAL(10,2)) as amount
        FROM reviews r
        JOIN users u ON r.customer_id = u.id
        JOIN products p ON r.product_id = p.id)

        ORDER BY timestamp DESC
        LIMIT 100
    ");

    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $logs]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>