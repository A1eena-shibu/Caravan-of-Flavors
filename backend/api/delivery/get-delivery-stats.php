<?php
/**
 * Get Delivery Stats API
 * Provides summary metrics for the delivery agent dashboard
 */

header('Content-Type: application/json');

require_once '../../config/session.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'delivery_agent') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

try {
    $agent_id = $_SESSION['user_id'];
    $pdo = getDBConnection();

    // Total Deliveries (Orders + Auctions)
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM orders WHERE delivery_agent_id = ? AND status = 'delivered') +
            (SELECT COUNT(*) FROM auctions WHERE delivery_agent_id = ? AND shipping_status = 'delivered')
    ");
    $stmt->execute([$agent_id, $agent_id]);
    $completedCount = $stmt->fetchColumn();

    // Pending Deliveries (Orders + Auctions) - For auctions, 'shipped' implies pending delivery
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM orders WHERE delivery_agent_id = ? AND status IN ('ordered', 'shipped')) +
            (SELECT COUNT(*) FROM auctions WHERE delivery_agent_id = ? AND shipping_status = 'shipped')
    ");
    $stmt->execute([$agent_id, $agent_id]);
    $pendingCount = $stmt->fetchColumn();

    // Monthly Earnings (Orders only for now as auctions might not have commission logic defined yet)
    $monthlyRevenue = 0;

    // Today's Deliveries (Orders + Auctions)
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM orders WHERE delivery_agent_id = ? AND status = 'delivered' AND DATE(delivered_at) = CURRENT_DATE) +
            (SELECT COUNT(*) FROM auctions WHERE delivery_agent_id = ? AND shipping_status = 'delivered' AND DATE(shipped_at) = CURRENT_DATE)
    ");
    $stmt->execute([$agent_id, $agent_id]);
    $todayCompleted = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'stats' => [
            'completed' => (int) $completedCount,
            'pending' => (int) $pendingCount,
            'monthly_revenue' => (float) $monthlyRevenue,
            'today_completed' => (int) $todayCompleted
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>