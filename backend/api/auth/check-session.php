<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

require_once '../../config/session.php';

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? 'customer';
    $redirectMap = [
        'farmer' => '../farmer/farmer-dashboard.html',
        'customer' => '../customer/customer-dashboard.html',
        'admin' => '../admin/admin-dashboard.html',
        'delivery_agent' => '../delivery/delivery-dashboard.html'
    ];
    $redirect = $redirectMap[$role] ?? '../customer/customer-dashboard.html';

    echo json_encode(['logged_in' => true, 'redirect' => $redirect, 'role' => $role]);
} else {
    echo json_encode(['logged_in' => false]);
}
?>