<?php

require_once 'db.php';

session_start();

if (!isset($_SESSION['facilitator_id'])) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

$query = "SELECT * FROM settings";
$params = [];

if(isset($_GET['name'])) {
    $names = $_GET['name'];

    if(!is_array($names)) {
        $names = [$names];
    }

    $placeholders = [];
    foreach ($names as $index => $name) {
        $key = ":name$index";
        $placeholders[] = $key;
        $params[$key] = $name;
    }

    $query .= " WHERE name IN (" . implode(',', $placeholders) . ")";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


echo json_encode([
    'success' => true,
    'data' => $rows
]);