<?php
/**
 * Update Admin Profile API
 */

header('Content-Type: application/json');
require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $pdo = getDBConnection();
    $userId = $_SESSION['user_id'];

    // Get JSON data
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        throw new Exception('No data provided');
    }

    $fullName = trim($data['full_name'] ?? '');
    $email = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $address = trim($data['address'] ?? '');
    $country = trim($data['country'] ?? '');
    $currency_code = trim($data['currency_code'] ?? '');
    $currency_symbol = trim($data['currency_symbol'] ?? '');

    if (empty($fullName) || empty($email)) {
        throw new Exception('Full name and email are required');
    }

    // Check if email already exists for a different user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch()) {
        throw new Exception('Email address is already in use by another account');
    }

    $stmt = $pdo->prepare("
        UPDATE users 
        SET full_name = ?, email = ?, phone = ?, address = ?, country = ?, currency_code = ?, currency_symbol = ?
        WHERE id = ? AND role = 'admin'
    ");
    $result = $stmt->execute([$fullName, $email, $phone, $address, $country, $currency_code, $currency_symbol, $userId]);

    if ($result) {
        // Update Session Immediately
        $_SESSION['user_country'] = $country;
        $_SESSION['user_currency_code'] = $currency_code;
        $_SESSION['user_currency_symbol'] = $currency_symbol;
        $_SESSION['user_name'] = $fullName;
        $_SESSION['user_email'] = $email;

        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>