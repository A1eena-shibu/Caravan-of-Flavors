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
        'delivery_agent' => '../delivery/delivery-dashboard.html',
        'delivery_staff' => '../delivery/staff-dashboard.html'
    ];
    $redirect = $redirectMap[$role] ?? '../customer/customer-dashboard.html';

    // For admins, include their access level
    $admin_access = $_SESSION['admin_access'] ?? 'all';
    echo json_encode(['logged_in' => true, 'redirect' => $redirect, 'role' => $role, 'admin_access' => $admin_access]);
} else {
    echo json_encode(['logged_in' => false]);
}
?>