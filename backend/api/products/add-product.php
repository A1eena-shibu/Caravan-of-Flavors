<?php
/**
 * Add Product API
 * Handles product creation and image upload for farmers
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a farmer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'farmer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Farmer access required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $farmer_id = $_SESSION['user_id'];
    $product_name = $_POST['product_name'] ?? '';
    $price = $_POST['price'] ?? 0;
    $description = $_POST['description'] ?? '';
    $category = 'Spices';
    $quantity = $_POST['quantity'] ?? 0;
    $unit = $_POST['unit'] ?? 'kg';

    // Validation: Use strlen check for strings and isset for numbers to allow "0"
    if (strlen(trim($product_name)) === 0 || $price === '' || strlen(trim($description)) === 0) {
        throw new Exception('Product name, price, and description are required.');
    }

    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Product image is required.');
    }

    // Handle Image Upload
    $image_url = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];

        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('File is too large. Max size is 10MB.');
        }

        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Invalid file type. Only JPG, PNG, WEBP, GIF, and SVG are allowed.');
        }

        $uploadDir = '../../../uploads/products/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = 'prod_' . uniqid() . '.' . $extension;
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $image_url = 'uploads/products/' . $fileName; // Path relative to root
        } else {
            throw new Exception('Failed to upload image.');
        }
    }

    $pdo = getDBConnection();

    $stmt = $pdo->prepare("
        INSERT INTO products (farmer_id, product_name, category, description, price, base_currency, farmer_country, quantity, unit, image_url, quality_status, is_available) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', 1)
    ");

    $base_currency = $_SESSION['user_currency_code'] ?? 'USD';
    $farmer_country = $_SESSION['user_country'] ?? 'Unknown';

    if ($stmt->execute([$farmer_id, $product_name, $category, $description, $price, $base_currency, $farmer_country, $quantity, $unit, $image_url])) {
        echo json_encode(['success' => true, 'message' => 'Product added successfully!']);
    } else {
        throw new Exception('Failed to save product to database.');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>