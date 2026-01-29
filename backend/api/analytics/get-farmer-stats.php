<?php
/**
 * Farmer Analytics API
 * Returns aggregated stats, recent activity, and sales performance data
 */

header('Content-Type: application/json');
require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a farmer
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'farmer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$farmer_id = $_SESSION['user_id'];
$pdo = getDBConnection();

try {
    // 1. Total Revenue
    $stmt = $pdo->prepare("SELECT SUM(total_price) as total FROM orders WHERE farmer_id = ? AND status != 'cancelled' AND payment_status = 'paid'");
    $stmt->execute([$farmer_id]);
    $totalRevenue = $stmt->fetchColumn() ?: 0;

    // 2. Active Orders
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE farmer_id = ? AND status IN ('pending', 'confirmed', 'processing', 'shipped')");
    $stmt->execute([$farmer_id]);
    $activeOrders = $stmt->fetchColumn() ?: 0;

    // 3. Total Customers
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT customer_id) FROM orders WHERE farmer_id = ?");
    $stmt->execute([$farmer_id]);
    $totalCustomers = $stmt->fetchColumn() ?: 0;

    // 4. Avg Rating
    $stmt = $pdo->prepare("SELECT AVG(r.rating) FROM reviews r JOIN products p ON r.product_id = p.id WHERE p.farmer_id = ?");
    $stmt->execute([$farmer_id]);
    $avgRating = round($stmt->fetchColumn() ?: 0, 1);

    // 5. Recent Activity (Last 5 orders)
    $stmt = $pdo->prepare("
        SELECT o.id, o.status, o.total_price, o.order_date, u.full_name as customer_name, p.product_name
        FROM orders o
        JOIN users u ON o.customer_id = u.id
        JOIN products p ON o.product_id = p.id
        WHERE o.farmer_id = ?
        ORDER BY o.order_date DESC
        LIMIT 5
    ");
    $stmt->execute([$farmer_id]);
    $recentActivity = $stmt->fetchAll();

    // 6. Sales Performance (Weekly for last 4 weeks)
    $stmt = $pdo->prepare("
        SELECT 
            WEEK(order_date, 1) as week_num,
            SUM(total_price) as weekly_total
        FROM orders 
        WHERE farmer_id = ? AND order_date >= DATE_SUB(NOW(), INTERVAL 4 WEEK) AND status != 'cancelled' AND payment_status = 'paid'
        GROUP BY WEEK(order_date, 1)
        ORDER BY week_num ASC
    ");
    $stmt->execute([$farmer_id]);
    $rawWeeklyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fill in missing weeks to ensure 4 bars
    $weeklyStats = [];
    $currentWeek = (int) date('W');
    // We want last 4 weeks: current, current-1, current-2, current-3
    // Note: This logic handles simple week subtraction. For edge cases (year crossover), 
    // using timestamps or specific dates is more robust, but this suffices for the 'Last 4 Weeks' visual.
    // A better approach is to simply iterate 0 to 3:
    for ($i = 3; $i >= 0; $i--) {
        $timestamp = strtotime("-$i weeks");
        $targetWeek = (int) date('W', $timestamp);

        // Calculate start of that week for label
        // If today is Monday, -1 week is last Monday. 
        // We want the label to represent the week start.
        // Approximate for simplicity: just use the date of (Now - i weeks) or proper week start calculation.
        // Let's use proper week start (Monday) for that week number in current year.
        $dto = new DateTime();
        $dto->setISODate((int) date('Y'), $targetWeek);
        $label = $dto->format('M j');

        $found = false;
        foreach ($rawWeeklyStats as $stat) {
            if ((int) $stat['week_num'] === $targetWeek) {
                $weeklyStats[] = [
                    'week_num' => $targetWeek,
                    'weekly_total' => (float) $stat['weekly_total'],
                    'label' => $label
                ];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $weeklyStats[] = [
                'week_num' => $targetWeek,
                'weekly_total' => 0,
                'label' => $label
            ];
        }
    }

    // 7. Low Stock Products
    $stmt = $pdo->prepare("SELECT id, product_name, quantity, unit FROM products WHERE farmer_id = ? AND quantity < 20 ORDER BY quantity ASC LIMIT 3");
    $stmt->execute([$farmer_id]);
    $lowStock = $stmt->fetchAll();

    // 8. Top Selling Products (by revenue)
    $stmt = $pdo->prepare("
        SELECT p.product_name, SUM(o.total_price) as revenue, COUNT(o.id) as sales_count
        FROM products p
        JOIN orders o ON p.id = o.product_id
        WHERE p.farmer_id = ? AND o.status != 'cancelled' AND o.payment_status = 'paid'
        GROUP BY p.id
        ORDER BY revenue DESC
        LIMIT 5
    ");
    $stmt->execute([$farmer_id]);
    $topProducts = $stmt->fetchAll();

    // 9. Order Status Distribution
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM orders WHERE farmer_id = ? GROUP BY status");
    $stmt->execute([$farmer_id]);
    $statusDist = $stmt->fetchAll();

    // 10. Rating Distribution (Customer Satisfaction)
    $stmt = $pdo->prepare("
        SELECT r.rating, COUNT(*) as count 
        FROM reviews r 
        JOIN products p ON r.product_id = p.id 
        WHERE p.farmer_id = ? 
        GROUP BY r.rating 
        ORDER BY r.rating DESC
    ");
    $stmt->execute([$farmer_id]);
    $ratingDist = $stmt->fetchAll();

    // 11. Top Customers (Loyalty)
    $stmt = $pdo->prepare("
        SELECT u.full_name, COUNT(o.id) as order_count, SUM(o.total_price) as total_spent
        FROM orders o
        JOIN users u ON o.customer_id = u.id
        WHERE o.farmer_id = ? AND o.status != 'cancelled'
        GROUP BY o.customer_id
        ORDER BY total_spent DESC
        LIMIT 3
    ");
    $stmt->execute([$farmer_id]);
    $topCustomers = $stmt->fetchAll();

    // 12. Process expired auctions (Trigger)
    require_once __DIR__ . '/../services/process-expired-auctions.php';
    processExpiredAuctions($pdo);

    // 13. Get Completed Auctions for this farmer
    $stmt = $pdo->prepare("
        SELECT a.id, a.product_name, a.current_bid, a.status, u.full_name as winner_name
        FROM auctions a
        LEFT JOIN users u ON a.winner_id = u.id
        WHERE a.farmer_id = ? AND a.status IN ('completed', 'cancelled')
        ORDER BY a.updated_at DESC
        LIMIT 5
    ");
    $stmt->execute([$farmer_id]);
    $auctionResults = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'stats' => [
            'revenue' => (float) $totalRevenue,
            'active_orders' => (int) $activeOrders,
            'customers' => (int) $totalCustomers,
            'rating' => (float) $avgRating
        ],
        'recent_activity' => $recentActivity,
        'weekly_performance' => $weeklyStats,
        'low_stock' => $lowStock,
        'top_products' => $topProducts,
        'status_distribution' => $statusDist,
        'rating_distribution' => $ratingDist,
        'top_customers' => $topCustomers,
        'auction_results' => $auctionResults
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>