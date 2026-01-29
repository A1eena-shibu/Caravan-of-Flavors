<?php
/**
 * Process Payment API (Simulation)
 * Updates order payment status to 'paid'
 */

header('Content-Type: application/json');
require_once '../../config/database.php';

// Allow any origin for demo purposes, or restrict as needed
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $pdo = getDBConnection(); // Moved up

    if ((empty($data['order_id']) && empty($data['order_ids'])) || empty($data['address']) || empty($data['payment_method'])) {
        throw new Exception('Order ID(s), Address, and Payment Method are required');
    }

    $order_ids_input = $data['order_ids'] ?? $data['order_id'];
    $address = $data['address'];
    $phone = isset($data['phone']) ? $data['phone'] : '';
    $payment_method = $data['payment_method'];

    // Append phone to address for storage
    if ($phone) {
        $address .= "\nContact: " . $phone;
    }

    // --- Auto-Save to User Profile if missing ---
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];

        // Fetch current user details to check what's missing
        $stmtUser = $pdo->prepare("SELECT phone, address FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $currentUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if ($currentUser) {
            $updateUserFields = [];
            $updateUserParams = [];

            // If profile phone is empty and we have a new phone
            if (empty($currentUser['phone']) && !empty($phone)) {
                $updateUserFields[] = "phone = ?";
                $updateUserParams[] = $phone;
            }

            // If profile address is empty and we have a new address
            // Note: We use the raw address from input, not the one with appended phone
            if (empty($currentUser['address']) && !empty($data['address'])) {
                $updateUserFields[] = "address = ?";
                $updateUserParams[] = $data['address'];
            }

            if (!empty($updateUserFields)) {
                $updateUserParams[] = $userId;
                $sqlUser = "UPDATE users SET " . implode(", ", $updateUserFields) . " WHERE id = ?";
                $stmtUpdate = $pdo->prepare($sqlUser);
                $stmtUpdate->execute($updateUserParams);
            }
        }
    }
    // --------------------------------------------

    $transaction_details = isset($data['transaction_details']) ? $data['transaction_details'] : null;

    // $pdo is already initialized above

    // Prepare notes update if transaction details exist
    $transaction_note = "";
    if ($transaction_details) {
        if (isset($transaction_details['payment_id'])) {
            $transaction_note = " | Ref: " . $transaction_details['payment_id'];
        } else if (isset($transaction_details['id'])) { // PayPal
            $transaction_note = " | Ref: " . $transaction_details['id'];
        }
    }

    // Convert to array of IDs
    $ids_array = explode(',', (string) $order_ids_input);
    $placeholders = implode(',', array_fill(0, count($ids_array), '?'));

    // Update orders with payment status, method, delivery address, and append transaction ref to notes
    // We need to construct parameters: address, method, note, then all IDs
    $params = [$address, $payment_method, $transaction_note];
    $params = array_merge($params, $ids_array);

    $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'paid', delivery_address = ?, payment_method = ?, notes = CONCAT(IFNULL(notes, ''), ?) WHERE id IN ($placeholders)");
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Payment processed successfully']);
    } else {
        // Use a select to check if it was already paid or orders don't exist
        $check = $pdo->prepare("SELECT id, payment_status FROM orders WHERE id IN ($placeholders)");
        $check->execute($ids_array);
        $orders = $check->fetchAll(PDO::FETCH_ASSOC);

        if (empty($orders)) {
            throw new Exception('Orders not found');
        }

        // If at least one is already paid, we consider it a success state (idempotency)
        echo json_encode(['success' => true, 'message' => 'Payment processed (orders updated or already paid)']);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>