<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Content-Type: application/json");

require_once '../../config/database.php';
$pdo = getDBConnection();


try {
    // Only fetch auctions that are active and haven't expired yet
    $stmt = $pdo->prepare("
        SELECT a.*, u.full_name as farmer_name 
        FROM auctions a
        JOIN users u ON a.farmer_id = u.id
        WHERE a.status = 'active' AND a.end_time > NOW()
        ORDER BY a.end_time ASC
    ");
    $stmt->execute();
    $auctions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'auctions' => $auctions]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>