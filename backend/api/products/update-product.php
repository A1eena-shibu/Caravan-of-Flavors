<?php
/**
 * Update Product API
 */

header('Content-Type: application/json');
require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'farmer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $pdo = getDBConnection();
    $farmer_id = $_SESSION['user_id'];

    $product_id = $_POST['product_id'] ?? null;
    $product_name = $_POST['product_name'] ?? '';
    $price = $_POST['price'] ?? 0;
    $description = $_POST['description'] ?? '';
    $quantity = $_POST['quantity'] ?? 0;

    if (!$product_id || strlen(trim($product_name)) === 0 || $price === '' || strlen(trim($description)) === 0) {
        throw new Exception('Product name, price, and description are required.');
    }

    // Verify ownership
    $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ? AND farmer_id = ?");
    $stmt->execute([$product_id, $farmer_id]);
    $existingProduct = $stmt->fetch();

    if (!$existingProduct) {
        throw new Exception('Product not found or unauthorized');
    }

    $image_url = $existingProduct['image_url'];

    // Handle Image Upload if provided
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];

        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('File is too large. Max size is 10MB.');
        }

        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Invalid file type.');
        }

        $uploadDir = '../../../uploads/products/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = 'prod_' . uniqid() . '.' . $extension;
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $image_url = 'uploads/products/' . $fileName;
        }
    }

    $stmt = $pdo->prepare("
        UPDATE products 
        SET product_name = ?, description = ?, price = ?, quantity = ?, image_url = ?
        WHERE id = ? AND farmer_id = ?
    ");

    if ($stmt->execute([$product_name, $description, $price, $quantity, $image_url, $product_id, $farmer_id])) {
        echo json_encode(['success' => true, 'message' => 'Product updated successfully!']);
    } else {
        throw new Exception('Failed to update product.');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>