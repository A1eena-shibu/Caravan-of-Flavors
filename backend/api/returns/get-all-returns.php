<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

require_once '../../config/database.php';
require_once '../../config/session.php';

require_role('admin');

try {
    $pdo = getDBConnection();

    $status = $_GET['status'] ?? 'all';
    $where = $status !== 'all' ? "WHERE r.status = " . $pdo->quote($status) : "";

    $stmt = $pdo->query("
        SELECT r.*,
               cu.full_name AS customer_name, cu.email AS customer_email,
               fu.full_name AS farmer_name,
               p.image_url,
               o.order_date, o.delivered_at
        FROM returns r
        JOIN users cu ON r.customer_id = cu.id
        JOIN users fu ON r.farmer_id = fu.id
        LEFT JOIN products p ON r.product_id = p.id
        JOIN orders o ON r.order_id = o.id
        $where
        ORDER BY r.created_at DESC
    ");
    $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $counts = $pdo->query("
        SELECT status, COUNT(*) as count FROM returns GROUP BY status
    ")->fetchAll(PDO::FETCH_ASSOC);

    $summary = ['total' => 0];
    foreach ($counts as $c) {
        $summary[$c['status']] = (int)$c['count'];
        $summary['total'] += (int)$c['count'];
    }

    echo json_encode(['success' => true, 'returns' => $returns, 'summary' => $summary]);
} catch (PDOException $e) {
    error_log('Get all returns error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>
