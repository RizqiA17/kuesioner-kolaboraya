<?php

require_once 'db.php';

session_start();

if (!isset($_SESSION['facilitator_id'])) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['name'], $input['value'])) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
    exit;
}

$query = "UPDATE settings SET value = :value WHERE name = :name";
$params = [
    ':value' => $input['value'],
    ':name'  => $input['name']
];

if ($pdo->prepare($query)->execute($params)) {
    echo json_encode(['success' => true, 'value' => $input['value']]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal mengubah pengaturan.']);
}
