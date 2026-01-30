<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

// Enable error reporting
ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $pdo = getDBConnection();

    // Fetch Exports with Farmer details
    $sql = "
        SELECT 
            e.id, 
            e.product_name, 
            e.destination_country, 
            e.quantity, 
            e.unit, 
            e.status, 
            e.shipment_date,
            e.tracking_number,
            u.full_name as farmer_name
        FROM exports e
        JOIN users u ON e.farmer_id = u.id
        ORDER BY e.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $exports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $exports]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>