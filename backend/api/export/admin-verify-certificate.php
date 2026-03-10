<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/database.php';
require_once '../../config/session.php';

require_role('admin');

$admin_id = $_SESSION['user_id'];

$body = json_decode(file_get_contents('php://input'), true);

$farmer_id  = (int)($body['farmer_id']  ?? 0);
$cert_type  = trim($body['cert_type']   ?? '');
$action     = trim($body['action']      ?? ''); // 'verify' or 'reject'
$admin_notes = trim($body['admin_notes'] ?? '');

$allowed_cert_types = [
    'organic_certificate', 'fssai_license', 'spice_board_registration',
    'gst_certificate', 'farm_ownership_proof', 'quality_testing_report'
];

if (!$farmer_id || !in_array($cert_type, $allowed_cert_types) || !in_array($action, ['verify', 'reject'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

try {
    $pdo = getDBConnection();

    $newStatus  = ($action === 'verify') ? 'verified' : 'rejected';
    $verifiedAt = ($action === 'verify') ? 'NOW()' : 'NULL';

    $stmt = $pdo->prepare("
        UPDATE farmer_certificates
        SET verification_status = ?,
            admin_notes         = ?,
            verified_by         = ?,
            verified_at         = " . ($action === 'verify' ? 'NOW()' : 'NULL') . ",
            updated_at          = NOW()
        WHERE farmer_id = ? AND cert_type = ?
    ");
    $stmt->execute([$newStatus, $admin_notes ?: null, $admin_id, $farmer_id, $cert_type]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Certificate record not found.']);
        exit;
    }

    // Check if farmer is now fully verified (all 6 certs verified)
    $allTypes = [
        'organic_certificate', 'fssai_license', 'spice_board_registration',
        'gst_certificate', 'farm_ownership_proof', 'quality_testing_report'
    ];
    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) as cnt FROM farmer_certificates
        WHERE farmer_id = ? AND verification_status = 'verified'
    ");
    $checkStmt->execute([$farmer_id]);
    $verifiedCount = (int)$checkStmt->fetchColumn();
    $isFullyVerified = $verifiedCount === count($allTypes);

    echo json_encode([
        'success'          => true,
        'message'          => $action === 'verify' ? 'Certificate verified successfully.' : 'Certificate rejected.',
        'new_status'       => $newStatus,
        'is_fully_verified' => $isFullyVerified,
        'verified_count'   => $verifiedCount
    ]);

} catch (PDOException $e) {
    error_log("Admin verify certificate error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
?>
