<?php

require_once 'db.php';

session_start();

if (!isset($_SESSION['facilitator_id'])) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

try {
    $stmt = $pdo->query("SELECT * FROM settings WHERE name = 'class_size'");
    $class_size = $stmt->fetch();

    $stmt = $pdo->query("SELECT * FROM quota_settings WHERE id = 1 LIMIT 1");
    $quota = $stmt->fetch();

    $stmt = $pdo->query("SELECT count(*) as total FROM users");
    $count = $stmt->fetch();

    $batch = ceil(($quota['quota'] > $count['total'] ? $count['total'] : $quota['quota']) / $class_size['value']);
    $moreBatch = ceil($count['total'] / $class_size['value']) - $batch;

    echo json_encode(['success' => true, ['batch' => $batch, 'moreBatch' => $moreBatch]]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil data.']);
}