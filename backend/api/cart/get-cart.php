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
    $stmt = $pdo->prepare("
        SELECT c.*, p.product_name, p.price, p.image_url, p.unit, p.quantity as stock, u.full_name as farmer_name
        FROM cart c
        JOIN products p ON c.product_id = p.id
        JOIN users u ON p.farmer_id = u.id
        WHERE c.customer_id = ?
    ");
    $stmt->execute([$customer_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'items' => $items]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>