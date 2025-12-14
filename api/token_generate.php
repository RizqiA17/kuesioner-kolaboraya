<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['facilitator_id'])) {
    http_response_code(401);
    exit;
}

$token = bin2hex(random_bytes(32));

$stmt = $pdo->prepare("
    INSERT INTO tokens 
    (token, user_id, expires_at)
    VALUES (:t, :u, DATE_ADD(NOW(), INTERVAL 5 MINUTE))
");

$stmt->execute([
    ':t' => $token,
    ':u' => $_SESSION['facilitator_id']
]);

echo json_encode([
    'success' => true,
    'token' => $token
]);
