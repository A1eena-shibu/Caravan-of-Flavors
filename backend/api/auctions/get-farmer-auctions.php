<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Content-Type: application/json");

require_once '../../config/database.php';
$pdo = getDBConnection();


session_start();

$farmer_id = $_SESSION['user_id'] ?? $_GET['farmer_id'] ?? 2; // Default to 2 for testing

try {
    $stmt = $pdo->prepare("SELECT * FROM auctions WHERE farmer_id = ? ORDER BY created_at DESC");
    $stmt->execute([$farmer_id]);
    $auctions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'auctions' => $auctions]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>