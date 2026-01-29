<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

require_once '../../config/database.php';
$pdo = getDBConnection();


session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in as a customer to bid.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['auction_id']) || !isset($data['bid_amount'])) {
    echo json_encode(['success' => false, 'message' => 'Missing bid parameters.']);
    exit;
}

$auction_id = (int) $data['auction_id'];
$customer_id = $_SESSION['user_id'];
$bid_amount = (float) $data['bid_amount'];

try {
    $pdo->beginTransaction();

    // 1. Fetch current auction state with a lock to prevent race conditions
    $stmt = $pdo->prepare("SELECT * FROM auctions WHERE id = ? FOR UPDATE");
    $stmt->execute([$auction_id]);
    $auction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$auction) {
        throw new Exception("Auction not found.");
    }

    if ($auction['status'] !== 'active' || strtotime($auction['end_time']) < time()) {
        throw new Exception("This auction is no longer active.");
    }

    if ($bid_amount <= $auction['current_bid']) {
        throw new Exception("Your bid must be higher than the current bid ($" . number_format($auction['current_bid'], 2) . ").");
    }

    // 2. Insert the bid
    $bidStmt = $pdo->prepare("INSERT INTO bids (auction_id, customer_id, bid_amount) VALUES (?, ?, ?)");
    $bidStmt->execute([$auction_id, $customer_id, $bid_amount]);

    // 3. Update the auction's current bid
    $updateStmt = $pdo->prepare("UPDATE auctions SET current_bid = ? WHERE id = ?");
    $updateStmt->execute([$bid_amount, $auction_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Bid placed successfully!']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>