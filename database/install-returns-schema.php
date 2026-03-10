<?php
/**
 * Caravan of Flavours — Returns & Wallet Schema Installer
 * Visit this file once in browser to create the required tables.
 * e.g. http://localhost/Caravan%20of%20Flavours/database/install-returns-schema.php
 */

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../backend/config/database.php';

$sql = file_get_contents(__DIR__ . '/returns_schema.sql');

try {
    $pdo = getDBConnection();

    // Split and execute each statement
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => !empty($s) && !preg_match('/^\s*--/', $s)
    );

    $results = [];
    foreach ($statements as $statement) {
        if (empty(trim($statement))) continue;
        try {
            $pdo->exec($statement);
            // Capture SELECT output (e.g. status message)
            if (stripos($statement, 'SELECT') === 0) {
                $row = $pdo->query($statement)->fetch(PDO::FETCH_ASSOC);
                if ($row) $results[] = ['status' => 'info', 'msg' => implode(' | ', $row)];
            } else {
                $preview = substr(trim($statement), 0, 80);
                $results[] = ['status' => 'ok', 'msg' => $preview . '...'];
            }
        } catch (PDOException $e) {
            $results[] = ['status' => 'error', 'msg' => $e->getMessage()];
        }
    }

    echo '<html><head><style>
        body { font-family: monospace; background:#0f172a; color:#e2e8f0; padding:40px; }
        h1   { color:#FF7E21; font-size:22px; margin-bottom:24px; }
        .ok  { color:#4ade80; }
        .error { color:#f87171; }
        .info  { color:#60a5fa; }
        p { margin: 6px 0; font-size:13px; }
        a { color:#FF7E21; }
    </style></head><body>
    <h1>🌶️ Returns & Wallet Schema Installer</h1>';

    foreach ($results as $r) {
        echo '<p class="' . $r['status'] . '">' . ($r['status'] === 'ok' ? '✅' : ($r['status'] === 'error' ? '❌' : 'ℹ️')) . ' ' . htmlspecialchars($r['msg']) . '</p>';
    }

    echo '<br><p class="ok">✅ <b>Done!</b> Returns, Wallet, and Wallet Transactions tables are ready.</p>';
    echo '<p style="margin-top:16px"><a href="../frontend/customer/orders.html">→ Go to Orders</a> &nbsp;|&nbsp; <a href="../frontend/admin/returns-admin.html">→ Admin Returns Panel</a></p>';
    echo '</body></html>';

} catch (Exception $e) {
    http_response_code(500);
    echo '<html><body style="font-family:monospace;background:#0f172a;color:#f87171;padding:40px;">';
    echo '<h2>❌ Database Connection Failed</h2>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</body></html>';
}
?>
