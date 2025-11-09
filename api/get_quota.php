<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['facilitator_id'])) {
    http_response_code(401);
    exit;
}

$stmt = $pdo->query("SELECT quota FROM quota_settings WHERE id = 1");
$quota = $stmt->fetchColumn();

echo json_encode(['quota' => intval($quota)]);
?>
