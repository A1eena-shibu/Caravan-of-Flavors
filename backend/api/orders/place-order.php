<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

require_once '../../config/database.php';
$pdo = getDBConnection();

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in to place an order.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$customer_id = $_SESSION['user_id'];
$product_id = $data['product_id'] ?? null;
$quantity = $data['quantity'] ?? 0;
$delivery_address = $data['delivery_address'] ?? 'Default Address on Profile';

if (!$product_id || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order details.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Check stock
    $stmt = $pdo->prepare("SELECT farmer_id, product_name, price, quantity, unit FROM products WHERE id = ? FOR UPDATE");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product)
        throw new Exception("Product not found.");
    if ($product['quantity'] < $quantity)
        throw new Exception("Insufficient stock available.");

    $total_price = $product['price'] * $quantity;

    $currency_code = $data['currency_code'] ?? 'USD';
    $exchange_rate = $data['exchange_rate'] ?? 1.0;

    // 2. Create Order
    $orderStmt = $pdo->prepare("INSERT INTO orders (customer_id, product_id, farmer_id, quantity, unit_price, total_price, status, delivery_address, payment_status, currency_code, exchange_rate) VALUES (?, ?, ?, ?, ?, ?, 'ordered', ?, 'pending', ?, ?)");
    $orderStmt->execute([$customer_id, $product_id, $product['farmer_id'], $quantity, $product['price'], $total_price, $delivery_address, $currency_code, $exchange_rate]);
    $order_id = $pdo->lastInsertId();

    // 3. Update Product Stock
    $updateStmt = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
    $updateStmt->execute([$quantity, $product_id]);

    // 4a. Log to Order Tracking
    $trackStmt = $pdo->prepare("INSERT INTO order_tracking (order_id, status, comment) VALUES (?, 'ordered', 'Order placed, payment pending')");
    $trackStmt->execute([$order_id]);

    // 4b. Create Notification for Farmer
    $unit = $product['unit'] ?? 'kg';
    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, 'New Order Received!', ?, 'order')");
    $notifStmt->execute([$product['farmer_id'], "You have a new order for {$quantity}{$unit} of {$product['product_name']}. Total: \${$total_price}"]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Order placed successfully!', 'order_id' => $order_id]);

} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>