<?php

require_once 'db.php';

$query = "SELECT * FROM provincies";
$params = [];

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $query .= " WHERE code = :code";
    $params[':code'] = $code;
}

$result = $pdo->prepare($query . " ORDER BY name ASC");
$result->execute($params);

echo json_encode([
    'success' => true,
    'data' => $result->fetchAll(PDO::FETCH_ASSOC)
]);