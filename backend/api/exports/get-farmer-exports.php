<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
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

try {
    $stmt = $pdo->prepare("SELECT * FROM exports WHERE farmer_id = ? ORDER BY created_at DESC");
    $stmt->execute([$farmer_id]);
    $exports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch documents for each export
    foreach ($exports as &$export) {
        $docStmt = $pdo->prepare("SELECT * FROM export_documents WHERE export_id = ?");
        $docStmt->execute([$export['id']]);
        $export['documents'] = $docStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(['success' => true, 'exports' => $exports]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>