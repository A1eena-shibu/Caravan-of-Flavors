<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Content-Type: application/json");

require_once '../../config/database.php';
require_once '../services/CurrencyService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getDBConnection();


try {
    // Only fetch auctions that are active and haven't expired yet
    $stmt = $pdo->prepare("
        SELECT a.*, u.full_name as farmer_name 
        FROM auctions a
        JOIN users u ON a.farmer_id = u.id
        WHERE a.status = 'active' AND a.end_time > NOW()
        ORDER BY a.end_time ASC
    ");
    $stmt->execute();
    $auctions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $targetCurrency = $_SESSION['user_currency_code'] ?? 'USD';
    $targetSymbol = $_SESSION['user_currency_symbol'] ?? '$';

    foreach ($auctions as &$auction) {
        $baseStartingPrice = (float) $auction['starting_price'];
        $baseCurrentBid = (float) $auction['current_bid'];
        $baseCurrency = $auction['base_currency'];

        $convStarting = CurrencyService::convert($baseStartingPrice, $baseCurrency, $targetCurrency);
        $convCurrent = CurrencyService::convert($baseCurrentBid, $baseCurrency, $targetCurrency);

        $auction['display_starting_price'] = $convStarting;
        $auction['display_current_bid'] = $convCurrent;
        $auction['display_currency_code'] = $targetCurrency;
        $auction['display_currency_symbol'] = $targetSymbol;
        $auction['formatted_starting_price'] = CurrencyService::formatPrice($convStarting, $targetSymbol, $targetCurrency);
        $auction['formatted_current_bid'] = CurrencyService::formatPrice($convCurrent, $targetSymbol, $targetCurrency);
    }

    echo json_encode(['success' => true, 'auctions' => $auctions]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>