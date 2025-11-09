<?php
session_start();

if (!isset($_SESSION['facilitator_id'])) {
    http_response_code(401);
    echo json_encode(['authenticated' => false]);
    exit;
}

echo json_encode([
    'authenticated' => true,
    'username' => $_SESSION['facilitator_name']
]);
?>
