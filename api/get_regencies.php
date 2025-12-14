<?php

require_once 'db.php';

$query = "SELECT * FROM regencies";
$params = [];

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $query .= " WHERE code = :code";
    $params[':code'] = $code;
}

if(isset($_GET['prov'])) {
    $prov = $_GET['prov'];
    $query .= " WHERE province_code = :prov";
    $params[':prov'] = $prov;
}

$result = $pdo->prepare($query . " ORDER BY name ASC");
$result->execute($params);

echo json_encode([
    'success' => true,
    'data' => $result->fetchAll(PDO::FETCH_ASSOC)
]);