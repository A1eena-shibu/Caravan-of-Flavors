<?php
/**
 * Send Delivery OTP API
 * Generates and sends a 4-digit OTP to the customer's email for delivery confirmation
 */

header('Content-Type: application/json');
require_once '../../../config/database.php';
require_once '../../../config/session.php';
require_once '../../../config/env.php';
require_once '../../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Only delivery agents and staff can trigger this
require_role(['delivery_agent', 'delivery_staff']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $order_id = $data['order_id'] ?? null;
    $type = $data['type'] ?? 'order';
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['user_role'];

    if (!$order_id) {
        throw new Exception('Order ID is required');
    }

    $pdo = getDBConnection();

    // 1. Verify access and fetch customer details
    if ($type === 'auction') {
        $stmt = $pdo->prepare("
            SELECT a.id, u.email, u.full_name, a.product_name, a.delivery_staff_id, a.delivery_agent_id
            FROM auctions a
            JOIN users u ON a.winner_id = u.id
            WHERE a.id = ?
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT o.id, u.email, u.full_name, p.product_name, o.delivery_staff_id, o.delivery_agent_id
            FROM orders o
            JOIN users u ON o.customer_id = u.id
            JOIN products p ON o.product_id = p.id
            WHERE o.id = ?
        ");
    }

    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        throw new Exception('Order not found');
    }

    // Authorization check
    if ($user_role === 'delivery_staff' && $order['delivery_staff_id'] != $user_id) {
        throw new Exception('Unauthorized to send OTP for this task');
    }
    if ($user_role === 'delivery_agent' && $order['delivery_agent_id'] != $user_id) {
        throw new Exception('Unauthorized to send OTP for this task');
    }

    // 2. Generate and store OTP
    $otp = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    $updateSql = ($type === 'auction')
        ? "UPDATE auctions SET delivery_otp = ?, delivery_otp_sent_at = CURRENT_TIMESTAMP WHERE id = ?"
        : "UPDATE orders SET delivery_otp = ?, delivery_otp_sent_at = CURRENT_TIMESTAMP WHERE id = ?";
    $stmt = $pdo->prepare($updateSql);
    $stmt->execute([$otp, $order_id]);

    // 3. Send Email
    $mail = new PHPMailer(true);

    // SMTP Settings
    $mail->isSMTP();
    $mail->Host = getenv('SMTP_HOST');
    $mail->SMTPAuth = true;
    $mail->Username = getenv('SMTP_EMAIL');
    $mail->Password = getenv('SMTP_PASSWORD');
    $mail->SMTPSecure = getenv('SMTP_SECURE');
    $mail->Port = getenv('SMTP_PORT');

    $mail->setFrom(getenv('SMTP_EMAIL'), getenv('SMTP_FROM_NAME'));
    $mail->addAddress($order['email'], $order['full_name']);

    $mail->isHTML(true);
    $mail->Subject = 'Delivery OTP | Caravan of Flavours';

    // Custom HTML Template (Clean Simple Theme - matching send-reset-email.php)
    $mail->Body = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 40px auto; background: #ffffff; border: 1px solid #000000; border-radius: 12px; overflow: hidden; }
            .header { padding: 40px; text-align: center; border-bottom: 1px solid #000000; }
            .content { padding: 40px; color: #333333; line-height: 1.6; text-align: center; }
            .content p { font-size: 16px; margin-bottom: 20px; color: #555555; }
            .otp-container { margin: 30px 0; padding: 20px; background-color: #fffaf0; border: 2px dashed #FF7E21; border-radius: 8px; display: inline-block; }
            .otp-code { font-size: 32px; font-weight: bold; color: #FF7E21; letter-spacing: 8px; margin-left: 8px; }
            .footer { padding: 20px; text-align: center; color: #999999; font-size: 12px; }
            h1 { color: #000000; margin: 0; font-size: 24px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                 <h1>Caravan <span style="color: #000000">of Flavours</span></h1>
            </div>
            <div class="content">
                <p>Hello <strong>' . htmlspecialchars($order['full_name']) . '</strong>,</p>
                <p>Your delivery for <strong>' . htmlspecialchars($order['product_name']) . '</strong> has arrived!</p>
                <p>Please share the 4-digit verification code below with our delivery partner to confirm receipt.</p>
                <div class="otp-container">
                    <span class="otp-code">' . $otp . '</span>
                </div>
                <p>If you were not expecting a delivery, you can safely ignore this email.</p>
            </div>
            <div class="footer">
                &copy; ' . date("Y") . ' Caravan of Flavours. All rights reserved.
            </div>
        </div>
    </body>
    </html>';

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => 'OTP sent successfully to ' . substr($order['email'], 0, 3) . '***' . substr($order['email'], strpos($order['email'], '@'))
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>