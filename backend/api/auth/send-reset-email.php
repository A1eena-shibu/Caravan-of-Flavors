<?php
header('Content-Type: application/json');
require_once '../../config/database.php';
require_once '../../config/env.php';
require '../../vendor/autoload.php';

use Kreait\Firebase\Factory;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Get POST data
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->email)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email is required"]);
    exit;
}

$email = $data->email;

try {
    // 1. Verify User exists in MySQL 
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id, full_name FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("User check failed. Please contact support."); // Should be caught by frontend check first
    }

    // 2. Initialize Firebase Admin SDK
    // Looks for service-account.json in backend/config
    $serviceAccountPath = __DIR__ . '/../../config/service-account.json';

    if (!file_exists($serviceAccountPath)) {
        throw new Exception("Server configuration error: Service Account missing.");
    }

    $factory = (new Factory)->withServiceAccount($serviceAccountPath);
    $auth = $factory->createAuth();

    // 3. Generate Password Reset Link
    $firebaseLink = $auth->getPasswordResetLink($email);

    // Extract oobCode from the link to create our custom local link
    parse_str(parse_url($firebaseLink, PHP_URL_QUERY), $query);
    $oobCode = $query['oobCode'] ?? '';

    // Redirect to local frontend reset page
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $localResetLink = $protocol . "://" . $host . "/Caravan%20of%20Flavours/frontend/auth/reset-password.html?oobCode=" . $oobCode;

    // 4. Send Custom Email via PHPMailer
    $mail = new PHPMailer(true);

    // Server settings
    $mail->isSMTP();
    $mail->Host = getenv('SMTP_HOST');
    $mail->SMTPAuth = true;
    $mail->Username = getenv('SMTP_EMAIL');
    $mail->Password = getenv('SMTP_PASSWORD');
    $mail->SMTPSecure = getenv('SMTP_SECURE');
    $mail->Port = getenv('SMTP_PORT');

    // Recipients
    $mail->setFrom(getenv('SMTP_EMAIL'), getenv('SMTP_FROM_NAME'));
    $mail->addAddress($email, $user['full_name']);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Reset Your Password | Caravan of Flavours';

    // Custom HTML Template (Clean Simple Theme)
    $mail->Body = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 40px auto; background: #ffffff; border: 1px solid #000000; border-radius: 12px; overflow: hidden; }
            .header { padding: 40px; text-align: center; border-bottom: 1px solid #000000; }
            .content { padding: 40px; color: #333333; line-height: 1.6; }
            .content p { font-size: 16px; margin-bottom: 20px; color: #555555; }
            .button-container { text-align: center; margin: 30px 0; }
            .button { display: inline-block; padding: 16px 32px; background-color: #FF7E21; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; }
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
                <p>Hello <strong>' . htmlspecialchars($user['full_name']) . '</strong>,</p>
                <p>We received a request to reset the password for your account associated with <strong>' . htmlspecialchars($email) . '</strong>.</p>
                <div class="button-container">
                    <a href="' . $localResetLink . '" class="button">RESET PASSWORD</a>
                </div>
                <p>If you did not request a password reset, you can safely ignore this email. This link will expire in 1 hour.</p>
            </div>
            <div class="footer">
                &copy; ' . date("Y") . ' Caravan of Flavours. All rights reserved.
            </div>
        </div>
    </body>
    </html>';

    $mail->send();

    echo json_encode([
        "status" => "success",
        "message" => "Custom reset email sent"
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    // Log detailed error but show generic to user
    error_log("Mail/Firebase Error: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "Processing failed: " . $e->getMessage()]);
}
?>