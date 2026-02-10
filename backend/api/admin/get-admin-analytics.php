<?php
/**
 * Advanced Admin Analysis API
 * Provides meaningful metrics for platform growth and performance
 */

header('Content-Type: application/json');
ob_start();
require_once '../../config/session.php';
require_once '../../config/database.php';

// Strict Admin Check
require_role('admin');

try {
    $pdo = getDBConnection();

    // 1. User Growth (Last 30 Days)
    // Counts signups per day for farmers and customers
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, role, COUNT(*) as count 
        FROM users 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND role IN ('farmer', 'customer')
        GROUP BY DATE(created_at), role
        ORDER BY date ASC
    ");
    $stmt->execute();
    $userGrowth = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Farmer Leaderboard (Top 5 by Revenue)
    $stmt = $pdo->prepare("
        SELECT u.full_name as farmer_name, SUM(o.total_price) as total_revenue
        FROM orders o
        JOIN users u ON o.farmer_id = u.id
        WHERE o.payment_status = 'paid' AND o.status != 'cancelled'
        GROUP BY o.farmer_id
        ORDER BY total_revenue DESC
        LIMIT 5
    ");
    $stmt->execute();
    $farmerLeaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Revenue Trend (Last 30 Days)
    $stmt = $pdo->prepare("
        SELECT DATE(order_date) as date, SUM(total_price) as daily_revenue
        FROM orders
        WHERE payment_status = 'paid' AND status != 'cancelled'
        AND order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(order_date)
        ORDER BY date ASC
    ");
    $stmt->execute();
    $revenueTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Order Status Distribution
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM orders 
        GROUP BY status
        ORDER BY count DESC
    ");
    $stmt->execute();
    $orderStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => [
            'user_growth' => $userGrowth,
            'farmer_leaderboard' => $farmerLeaderboard,
            'revenue_trend' => $revenueTrend,
            'order_status' => $orderStatus
        ]
    ]);

} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>