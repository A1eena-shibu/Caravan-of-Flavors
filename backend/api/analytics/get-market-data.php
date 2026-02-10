<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

try {
    $pdo = getDBConnection();

    // 1. Card Stats
    // Total Orders
    $stmtTotal = $pdo->query("SELECT COUNT(*) as val FROM orders WHERE status != 'cancelled'");
    $totalOrders = $stmtTotal->fetch(PDO::FETCH_ASSOC)['val'] ?? 0;

    // Shipped Orders
    $stmtShipped = $pdo->query("SELECT COUNT(*) as val FROM orders WHERE status = 'shipped'");
    $shippedOrders = $stmtShipped->fetch(PDO::FETCH_ASSOC)['val'] ?? 0;

    // Delivered Orders
    $stmtDelivered = $pdo->query("SELECT COUNT(*) as val FROM orders WHERE status = 'delivered'");
    $deliveredOrders = $stmtDelivered->fetch(PDO::FETCH_ASSOC)['val'] ?? 0;

    // 2. Chart: Orders by Status (Pie)
    $stmtStatus = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
    $statusData = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);

    // 3. Chart: Top Ordered Products (Bar)
    $stmtTop = $pdo->query("
        SELECT p.product_name, COUNT(o.id) as count
        FROM orders o
        JOIN products p ON o.product_id = p.id
        WHERE o.status != 'cancelled'
        GROUP BY p.product_name
        ORDER BY count DESC
        LIMIT 5
    ");
    $topProducts = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'cards' => [
                'total' => $totalOrders,
                'shipped' => $shippedOrders,
                'delivered' => $deliveredOrders
            ],
            'charts' => [
                'status_distribution' => $statusData,
                'top_products' => $topProducts
            ]
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>