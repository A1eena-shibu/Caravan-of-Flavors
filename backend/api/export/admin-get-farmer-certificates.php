<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Content-Type: application/json");

require_once '../../config/database.php';
require_once '../../config/session.php';

require_role('admin');

try {
    $pdo = getDBConnection();

    // Check table exists
    $check = $pdo->query("SHOW TABLES LIKE 'farmer_certificates'")->fetch();
    if (!$check) {
        echo json_encode(['success' => true, 'farmers' => [], 'stats' => ['total_pending' => 0, 'total_verified' => 0, 'total_rejected' => 0]]);
        exit;
    }

    $allTypes = [
        'organic_certificate',
        'fssai_license',
        'spice_board_registration',
        'gst_certificate',
        'farm_ownership_proof',
        'quality_testing_report'
    ];

    // Get all farmers who have uploaded at least one certificate
    $stmt = $pdo->prepare("
        SELECT DISTINCT fc.farmer_id, u.full_name, u.email, u.profile_image,
               COUNT(fc.id) AS total_uploaded,
               SUM(CASE WHEN fc.verification_status = 'verified' THEN 1 ELSE 0 END) AS total_verified,
               SUM(CASE WHEN fc.verification_status = 'pending' THEN 1 ELSE 0 END) AS total_pending,
               SUM(CASE WHEN fc.verification_status = 'rejected' THEN 1 ELSE 0 END) AS total_rejected,
               MAX(fc.uploaded_at) AS last_uploaded
        FROM farmer_certificates fc
        JOIN users u ON fc.farmer_id = u.id
        GROUP BY fc.farmer_id, u.full_name, u.email, u.profile_image
        ORDER BY MAX(fc.uploaded_at) DESC
    ");
    $stmt->execute();
    $farmers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each farmer, fetch their certificates
    foreach ($farmers as &$farmer) {
        $cStmt = $pdo->prepare("
            SELECT cert_type, original_filename, file_path, verification_status, admin_notes, uploaded_at, verified_at
            FROM farmer_certificates
            WHERE farmer_id = ?
        ");
        $cStmt->execute([$farmer['farmer_id']]);
        $rows = $cStmt->fetchAll(PDO::FETCH_ASSOC);
        $byType = [];
        foreach ($rows as $row) {
            $byType[$row['cert_type']] = $row;
        }
        $farmer['certificates']    = $byType;
        $farmer['total_required']  = count($allTypes);
        $farmer['is_fully_verified'] = ((int)$farmer['total_verified']) === count($allTypes);
    }

    // Global stats
    $statsStmt = $pdo->query("
        SELECT
            SUM(CASE WHEN verification_status='pending' THEN 1 ELSE 0 END) AS total_pending,
            SUM(CASE WHEN verification_status='verified' THEN 1 ELSE 0 END) AS total_verified,
            SUM(CASE WHEN verification_status='rejected' THEN 1 ELSE 0 END) AS total_rejected
        FROM farmer_certificates
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'farmers' => $farmers,
        'stats'   => $stats
    ]);

} catch (PDOException $e) {
    error_log("Admin get farmer certificates error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>
