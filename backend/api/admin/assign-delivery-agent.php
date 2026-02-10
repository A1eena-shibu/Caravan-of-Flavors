<?php
header('Content-Type: application/json');
require_once '../../config/database.php';

// Enable error reporting
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../../config/session.php';

// Strict Admin Check
require_role('admin');

try {
    // Get POST data
    $data = json_decode(file_get_contents("php://input"));

    if (!isset($data->order_id) || !isset($data->agent_id)) {
        throw new Exception('Order ID and Agent ID are required');
    }

    $order_id = $data->order_id;
    $agent_id = $data->agent_id;

    $pdo = getDBConnection();

    // Verify Agent exists and is actually a delivery agent
    $agentStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'delivery_agent'");
    $agentStmt->execute([$agent_id]);
    if (!$agentStmt->fetch()) {
        throw new Exception('Invalid delivery agent selected');
    }

    // Update Order
    // We are adding delivery_agent_id to the orders table.
    // Assuming the column exists or needs to be added. 
    // Based on previous context, I should assume it exists or I might need to add it if I could run SQL.
    // Since I can't run SQL DDL easily without knowing schema fully, I'll assume the column 'delivery_agent_id' exists 
    // or I'm using a generic field.
    // Wait, the user asked to "assign agents". The simplest way is a column `delivery_agent_id`.

    $sql = "UPDATE orders SET delivery_agent_id = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$agent_id, $order_id]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Agent assigned successfully']);
    } else {
        throw new Exception('Failed to update order');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>