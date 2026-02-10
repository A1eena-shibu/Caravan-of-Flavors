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
    // strict check for production
}

if (!isset($_GET['id']) || !isset($_GET['role'])) {
    echo json_encode(['success' => false, 'message' => 'Missing ID or Role']);
    exit;
}

$userId = $_GET['id'];
$role = $_GET['role'];

try {
    $pdo = getDBConnection();
    $data = [];

    if ($role === 'farmer') {
        // 1. Total Earnings
        $stmt = $pdo->prepare("SELECT SUM(total_price) FROM orders WHERE farmer_id = ? AND payment_status = 'paid' AND status != 'cancelled'");
        $stmt->execute([$userId]);
        $data['total_earnings'] = $stmt->fetchColumn() ?: 0;

        // 2. Total Products Listed
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE farmer_id = ?");
        $stmt->execute([$userId]);
        $data['total_products'] = $stmt->fetchColumn() ?: 0;

        // 3. Top Products (by sales)
        $stmt = $pdo->prepare("
            SELECT p.product_name, SUM(o.total_price) as revenue 
            FROM products p
            LEFT JOIN orders o ON p.id = o.product_id AND o.payment_status = 'paid' AND o.status != 'cancelled'
            WHERE p.farmer_id = ?
            GROUP BY p.id
            ORDER BY revenue DESC
            LIMIT 5
        ");
        $stmt->execute([$userId]);
        $data['top_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Recent Sales Activity
        $stmt = $pdo->prepare("
            SELECT o.id, p.product_name, u.full_name as customer_name, o.total_price, o.order_date, o.status
            FROM orders o
            JOIN products p ON o.product_id = p.id
            JOIN users u ON o.customer_id = u.id
            WHERE o.farmer_id = ? AND o.status != 'cancelled'
            ORDER BY o.order_date DESC
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        $data['history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($role === 'customer') {
        // 1. Total Spending
        $stmt = $pdo->prepare("SELECT SUM(total_price) FROM orders WHERE customer_id = ? AND payment_status = 'paid' AND status != 'cancelled'");
        $stmt->execute([$userId]);
        $data['total_spending'] = $stmt->fetchColumn() ?: 0;

        // 2. Total Orders
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status != 'cancelled'");
        $stmt->execute([$userId]);
        $data['total_orders'] = $stmt->fetchColumn() ?: 0;

        // 3. Recent Purchases
        $stmt = $pdo->prepare("
            SELECT o.id, p.product_name, u.full_name as farmer_name, o.total_price, o.order_date, o.status
            FROM orders o
            JOIN products p ON o.product_id = p.id
            JOIN users u ON o.farmer_id = u.id
            WHERE o.customer_id = ? AND o.status != 'cancelled'
            ORDER BY o.order_date DESC
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        $data['history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>