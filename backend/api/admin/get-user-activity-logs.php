<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_GET['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

$userId = $_GET['user_id'];

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && $error['type'] === E_ERROR) {
        file_put_contents(__DIR__ . '/../../admin_debug.log', date('[Y-m-d H:i:s] ') . "FATAL ERROR in get-user-activity-logs.php: " . print_r($error, true) . "\n", FILE_APPEND);
    }
});

try {
    $pdo = getDBConnection();

    // Complex Union Query filtered by User ID
    // We check both customer_id and farmer_id where applicable to capture all interactions
    $stmt = $pdo->prepare("
        (SELECT 
            'order' as type,
            ot.updated_at as timestamp,
            u.full_name as user_name,
            u.role as user_role,
            CASE 
                WHEN ot.status = 'ordered' THEN 'ordered an item'
                WHEN ot.status = 'pending' THEN 'placed a new order'
                WHEN ot.status = 'confirmed' THEN 'confirmed the order'
                WHEN ot.status = 'paid' THEN 'payment confirmed'
                WHEN ot.status = 'processing' THEN 'processing the order'
                WHEN ot.status = 'shipped' THEN 'shipped the order'
                WHEN ot.status = 'delivered' THEN 'delivered the order'
                WHEN ot.status = 'cancelled' THEN 'cancelled the order'
                WHEN ot.status = 'rejected' THEN 'rejected the order'
                ELSE CONCAT('updated order status to ', ot.status)
            END as action,
            CONCAT(p.product_name, ' (', o.quantity, ' ', p.unit, ')') as details,
            o.id as reference_id,
            o.total_price as amount
        FROM order_tracking ot
        JOIN orders o ON ot.order_id = o.id
        JOIN users u ON o.customer_id = u.id
        JOIN products p ON o.product_id = p.id
        WHERE o.customer_id = ? OR o.farmer_id = ?)

        UNION ALL

        (SELECT 
            'product' as type,
            pt.updated_at as timestamp,
            u.full_name as user_name,
            u.role as user_role,
            CASE WHEN pt.action = 'listed' THEN 'listed a new product' ELSE 'updated product details' END as action,
            p.product_name as details,
            p.id as reference_id,
            p.price as amount
        FROM product_tracking pt
        JOIN products p ON pt.product_id = p.id
        JOIN users u ON p.farmer_id = u.id
        WHERE p.farmer_id = ?)

        UNION ALL

        (SELECT 
            'review' as type,
            r.created_at as timestamp,
            u.full_name as user_name,
            u.role as user_role,
            'wrote a review' as action,
            CONCAT('On ', p.product_name, ': ', LEFT(r.review_text, 30), '...') as details,
            r.id as reference_id,
            CAST(r.rating AS DECIMAL(10,2)) as amount
        FROM reviews r
        JOIN users u ON r.customer_id = u.id
        JOIN products p ON r.product_id = p.id
        WHERE r.customer_id = ? OR p.farmer_id = ?)

        UNION ALL

        /* Auctions: Start */
        (SELECT 
            'auction' as type,
            a.start_time as timestamp,
            u.full_name as user_name,
            u.role as user_role,
            'started an auction' as action,
            CONCAT(a.product_name) as details,
            a.id as reference_id,
            a.starting_price as amount
        FROM auctions a
        JOIN users u ON a.farmer_id = u.id
        WHERE a.farmer_id = ?)

        UNION ALL

        /* Auctions: Completed */
        (SELECT 
            'auction' as type,
            a.end_time as timestamp,
            u.full_name as user_name,
            u.role as user_role,
            'auction completed' as action,
            CONCAT(a.product_name, ' won') as details,
            a.id as reference_id,
            a.current_bid as amount
        FROM auctions a
        JOIN users u ON a.farmer_id = u.id
        WHERE (a.farmer_id = ? OR a.winner_id = ?) 
          AND a.status IN ('completed', 'shipped', 'paid') AND a.winner_id IS NOT NULL)

        UNION ALL

        /* Auctions: Paid */
        (SELECT 
            'auction' as type,
            a.updated_at as timestamp,
            u.full_name as user_name,
            u.role as user_role,
            'payment confirmed' as action,
            CONCAT(a.product_name, ' paid') as details,
            a.id as reference_id,
            a.current_bid as amount
        FROM auctions a
        JOIN users u ON a.winner_id = u.id
        WHERE (a.farmer_id = ? OR a.winner_id = ?)
          AND a.payment_status = 'paid')

        UNION ALL

        /* Auctions: Shipped */
        (SELECT 
            'auction' as type,
            a.shipped_at as timestamp,
            u.full_name as user_name,
            u.role as user_role,
            'shipped auction item' as action,
            CONCAT(a.product_name) as details,
            a.id as reference_id,
            a.current_bid as amount
        FROM auctions a
        JOIN users u ON a.farmer_id = u.id
        WHERE (a.farmer_id = ? OR a.winner_id = ?)
          AND a.shipping_status = 'shipped' AND a.shipped_at IS NOT NULL)

        UNION ALL

        (SELECT 
            'bid' as type,
            b.bid_time as timestamp,
            u.full_name as user_name,
            u.role as user_role,
            'placed a bid' as action,
            CONCAT('Bid ', b.bid_amount, ' on ', a.product_name) as details,
            b.id as reference_id,
            b.bid_amount as amount
        FROM bids b
        JOIN users u ON b.customer_id = u.id
        JOIN auctions a ON b.auction_id = a.id
        WHERE b.customer_id = ?)

        UNION ALL

        (SELECT 
            'product' as type,
            al.created_at as timestamp,
            u.full_name as user_name,
            u.role as user_role,
            'deleted a product' as action,
            al.description as details,
            al.target_id as reference_id,
            0 as amount
        FROM admin_logs al
        JOIN users u ON al.admin_id = u.id
        WHERE al.action_type = 'product_deleted' AND al.admin_id = ?)

        ORDER BY timestamp DESC
        LIMIT 50
    ");

    /*
    Param mapping:
    1,2: Orders (cust, farm)
    3: Products (farm)
    4: Users (id)
    5,6: Reviews (cust, farm)
    7: Product Tracking (farm)
    8: Auction Start (farm)
    9,10: Auction End (farm, win)
    11,12: Auction Pay (farm, win)
    13,14: Auction Ship (farm, win)
    15: Bids (cust)
    */

    $stmt->execute([
        $userId,
        $userId, // Orders (2)
        $userId, // Product Tracking (1)
        $userId,
        $userId, // Reviews (2)
        $userId, // Auction Start (1)
        $userId,
        $userId, // Auction End (2)
        $userId,
        $userId, // Auction Pay (2)
        $userId,
        $userId, // Auction Ship (2)
        $userId, // Bids (1)
        $userId  // Product Deletion (1)
    ]);

    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $logs]);

} catch (Exception $e) {
    file_put_contents(__DIR__ . '/../../admin_debug.log', date('[Y-m-d H:i:s] ') . "get-user-activity-logs.php Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>