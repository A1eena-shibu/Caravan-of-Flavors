<?php
/**
 * Reset Password Confirmation API
 * Confirms password reset in Firebase and updates the MySQL database
 */

header('Content-Type: application/json');

require_once '../../config/database.php';
require_once '../../config/env.php';
require '../../vendor/autoload.php';

use Kreait\Firebase\Factory;

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->oobCode) || !isset($data->password)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid request: Missing parameters"]);
    exit;
}

try {
    // 1. Initialize Firebase Admin SDK
    $serviceAccountPath = __DIR__ . '/../../config/service-account.json';
    if (!file_exists($serviceAccountPath)) {
        throw new Exception("Server configuration error: Service Account missing.");
    }

    $factory = (new Factory)->withServiceAccount($serviceAccountPath);
    $auth = $factory->createAuth();

    // 2. Confirm Password Reset in Firebase
    // This validates the oobCode and updates the password in Firebase
    $email = $auth->confirmPasswordReset($data->oobCode, $data->password);

    if (!$email) {
        throw new Exception("Failed to verify reset code. It may have expired.");
    }

    // 3. Update Password in MySQL Database
    // We use the same hashing algorithm as in register.php (Argon2id)
    $db = getDBConnection();
    $password_hash = password_hash($data->password, PASSWORD_ARGON2ID);

    $stmt = $db->prepare("UPDATE users SET password = :password WHERE email = :email");
    $result = $stmt->execute([
        ':password' => $password_hash,
        ':email' => $email
    ]);

    if (!$result) {
        // Fallback or log if DB update fails but Firebase succeeded (critical sync issue)
        throw new Exception("Firebase updated, but local database update failed. Please contact support.");
    }

    echo json_encode([
        "status" => "success",
        "message" => "Password updated successfully in all systems"
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    error_log("Reset Confirm Error: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>