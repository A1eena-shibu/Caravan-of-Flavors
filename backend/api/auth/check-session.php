<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    // Set cookie parameters to match login.php for consistency
    $duration = 30 * 24 * 60 * 60;
    session_set_cookie_params([
        'lifetime' => $duration,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? 'customer';
    $redirectMap = [
        'farmer' => '../farmer/farmer-dashboard.html',
        'customer' => '../customer/customer-dashboard.html',
        'admin' => '../admin/admin-dashboard.html'
    ];
    $redirect = $redirectMap[$role] ?? '../customer/customer-dashboard.html';

    echo json_encode(['logged_in' => true, 'redirect' => $redirect, 'role' => $role]);
} else {
    echo json_encode(['logged_in' => false]);
}
?>