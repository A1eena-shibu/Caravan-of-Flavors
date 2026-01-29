<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

require_once '../../config/database.php';
$pdo = getDBConnection();

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'farmer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$farmer_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$product_name = $data['product_name'] ?? '';
$destination_country = $data['destination_country'] ?? '';
$quantity = $data['quantity'] ?? 0;
$unit = $data['unit'] ?? 'kg';
$shipping_carrier = $data['shipping_carrier'] ?? '';
$tracking_number = $data['tracking_number'] ?? '';
$shipment_date = $data['shipment_date'] ?? null;
$estimated_arrival = $data['estimated_arrival'] ?? null;
$notes = $data['notes'] ?? '';

if (empty($product_name) || empty($destination_country) || empty($quantity)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO exports (farmer_id, product_name, destination_country, quantity, unit, shipping_carrier, tracking_number, shipment_date, estimated_arrival, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$farmer_id, $product_name, $destination_country, $quantity, $unit, $shipping_carrier, $tracking_number, $shipment_date, $estimated_arrival, $notes]);

    echo json_encode(['success' => true, 'message' => 'Export registered successfully', 'export_id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>