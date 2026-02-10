<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Content-Type: application/json");

require_once '../../config/database.php';
$pdo = getDBConnection();

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$customer_id = $_SESSION['user_id'];

try {
    // 1. Total spent
    $stmtSpent = $pdo->prepare("SELECT SUM(total_price) as total FROM orders WHERE customer_id = ? AND status != 'cancelled'");
    $stmtSpent->execute([$customer_id]);
    $totalSpent = $stmtSpent->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 2. Active Shipments count (Only 'shipped' status)
    $stmtActive = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE customer_id = ? AND status = 'shipped'");
    $stmtActive->execute([$customer_id]);
    $activeShipments = $stmtActive->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 3. Active Orders List (For dashboard list)
    $stmtActiveList = $pdo->prepare("
        SELECT o.id, o.status, o.total_price, o.order_date, o.quantity, p.product_name, p.image_url 
        FROM orders o 
        JOIN products p ON o.product_id = p.id 
        WHERE o.customer_id = ? AND o.status NOT IN ('delivered', 'cancelled')
        ORDER BY o.order_date DESC 
        LIMIT 2
    ");
    $stmtActiveList->execute([$customer_id]);
    $activeOrdersList = $stmtActiveList->fetchAll(PDO::FETCH_ASSOC);

    // 4. Transaction History (For table)
    $stmtHistory = $pdo->prepare("
        SELECT o.id, o.status, o.total_price, o.order_date, o.quantity, p.product_name 
        FROM orders o 
        JOIN products p ON o.product_id = p.id 
        WHERE o.customer_id = ?
        ORDER BY o.order_date DESC 
        LIMIT 5
    ");
    $stmtHistory->execute([$customer_id]);
    $transactionHistory = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

    // 5. User data
    $stmtUser = $pdo->prepare("SELECT full_name, profile_image FROM users WHERE id = ?");
    $stmtUser->execute([$customer_id]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_spent' => (float) $totalSpent,
            'active_orders' => (int) $activeShipments, // Now strictly 'shipped' count for the card
            'spice_points' => (int) ($totalSpent * 0.5), // 5 points per $10 = 0.5 pts per $1
            'full_name' => $user['full_name'],
            'profile_image' => $user['profile_image'] ?? null
        ],
        'recent_orders' => $activeOrdersList,
        'transaction_history' => $transactionHistory
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>