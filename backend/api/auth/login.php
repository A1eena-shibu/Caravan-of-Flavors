<?php
/**
 * Advanced User Login API
 * Handles secure email/password authentication
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        throw new Exception('Invalid input data');
    }

    if (empty($data['email']) || empty($data['password'])) {
        throw new Exception('Email and password are required');
    }

    $email = filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL);
    if (!$email) {
        throw new Exception('Invalid email format');
    }

    $pdo = getDBConnection();

    // Fetch user including password hash and role
    $stmt = $pdo->prepare("SELECT id, email, password, full_name, role, is_active FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($data['password'], $user['password'])) {
        // Use generic error for security
        throw new Exception('Invalid email or password');
    }

    if (isset($user['is_active']) && !$user['is_active']) {
<<<<<<< HEAD
        throw new Exception('Your account is inactive. Please contact support.');
=======
        throw new Exception('User is blocked by admin. Contact admin for enquire');
>>>>>>> 7a93d84e57fb4b8a4284292b9e5f4cf08fc28c30
    }

    // Start session
    if (session_status() === PHP_SESSION_NONE) {
        if (!empty($data['keep_logged'])) {
            // 30 days in seconds
            $duration = 30 * 24 * 60 * 60;
            // Set garbage collection max lifetime
            ini_set('session.gc_maxlifetime', $duration);
            // Set session cookie parameters
            session_set_cookie_params([
                'lifetime' => $duration,
                'path' => '/',
                'domain' => '', // Current domain
                'secure' => isset($_SERVER['HTTPS']), // Secure only if HTTPS is on
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
        session_start();
        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['remember_me'] = !empty($data['keep_logged']);

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'redirect' => getDashboardUrl($user['role'])
    ]);

} catch (Exception $e) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getDashboardUrl($role)
{
    if ($role === 'admin')
        return '../admin/admin-dashboard.html';
    return $role === 'farmer'
        ? '../farmer/farmer-dashboard.html'
        : '../customer/customer-dashboard.html';
}
?>