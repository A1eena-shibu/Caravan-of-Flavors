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

    // Total Deliveries (Completed)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE delivery_agent_id = ? AND status = 'delivered'");
    $stmt->execute([$agent_id]);
    $completedCount = $stmt->fetchColumn();

    // Pending Deliveries (Assigned/Ordered/Shipped)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE delivery_agent_id = ? AND status IN ('ordered', 'shipped')");
    $stmt->execute([$agent_id]);
    $pendingCount = $stmt->fetchColumn();

    // Monthly Earnings (Example metric)
    $stmt = $pdo->prepare("SELECT SUM(total_price) FROM orders WHERE delivery_agent_id = ? AND status = 'delivered' AND MONTH(delivered_at) = MONTH(CURRENT_DATE())");
    $stmt->execute([$agent_id]);
    $monthlyRevenue = $stmt->fetchColumn() ?: 0;

    echo json_encode([
        'success' => true,
        'stats' => [
            'completed' => (int) $completedCount,
            'pending' => (int) $pendingCount,
            'monthly_revenue' => (float) $monthlyRevenue
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>