<?php
/**
 * Centralized Session Management
 * This file should be included in every API endpoint that requires session access.
 */

// Prevent browser caching for session-dependent pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Set secure session cookie parameters
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session cookie parameters
    $duration = 30 * 24 * 60 * 60; // 30 days
    session_set_cookie_params([
        'lifetime' => $duration,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

/**
 * Check if a user is logged in
 */
function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

/**
 * Require a user to be logged in, otherwise return 401 Unauthorized
 */
function require_login()
{
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Please login to continue']);
        exit;
    }
}

/**
 * Require a user to have a specific role, otherwise return 403 Forbidden
 * @param string|array $role The required role or an array of roles
 */
function require_role($role)
{
    require_login();

    $user_role = $_SESSION['user_role'] ?? '';
    $has_role = is_array($role) ? in_array($user_role, $role) : ($user_role === $role);

    if (!$has_role) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to access this resource']);
        exit;
    }
}