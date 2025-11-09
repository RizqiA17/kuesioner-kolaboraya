<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['facilitator_id'])) {
    http_response_code(401);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$newQuota = intval($data['quota']);

$stmt = $pdo->prepare("UPDATE quota_settings SET quota = ? WHERE id = 1");
$stmt->execute([$newQuota]);

echo json_encode(['success' => true, 'quota' => $newQuota]);
?>
