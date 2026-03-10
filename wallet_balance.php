<?php
require 'backend/config/database.php';
$stmt = $pdo->query('SELECT user_id, balance FROM wallet');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['success'=>true, 'balances'=>$rows]);
?>
