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

    $id = $data->order_id; // Keeping the name 'order_id' from frontend for compatibility, but it's the item ID
    $agent_id = $data->agent_id;
    $type = isset($data->type) ? $data->type : 'order'; // Default to 'order'

    $pdo = getDBConnection();

    // Verify Agent exists and is actually a delivery agent
    $agentStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'delivery_agent'");
    $agentStmt->execute([$agent_id]);
    if (!$agentStmt->fetch()) {
        throw new Exception('Invalid delivery agent selected');
    }

    // Update corresponding table
    if ($type === 'auction') {
        $sql = "UPDATE auctions SET delivery_agent_id = ? WHERE id = ?";
    } else {
        $sql = "UPDATE orders SET delivery_agent_id = ? WHERE id = ?";
    }

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$agent_id, $id]);

    if ($result) {
        // Log the assignment
        $admin_id = $_SESSION['user_id'];
        $logStmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action_type, target_table, target_id, description) VALUES (?, 'agent_assigned', ?, ?, ?)");
        $targetTable = ($type === 'auction') ? 'auctions' : 'orders';

        // Fetch agent name
        $agentNameStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $agentNameStmt->execute([$agent_id]);
        $agentName = $agentNameStmt->fetchColumn();

        $formattedId = ($type === 'auction') ? 'AUC-' . str_pad($id, 5, '0', STR_PAD_LEFT) : 'ORD-' . str_pad($id, 5, '0', STR_PAD_LEFT);
        $description = "Assigned agent {$agentName} to {$formattedId}";
        $logStmt->execute([$admin_id, $targetTable, $id, $description]);

        echo json_encode(['success' => true, 'message' => 'Agent assigned successfully to ' . $type]);
    } else {
        throw new Exception('Failed to update ' . $type);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>