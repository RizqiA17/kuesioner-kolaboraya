<?php

require_once 'db.php';
require_once '../helper/normalize_address.php';
require_once '../helper/tokenize_address.php';

session_start();

if (!isset($_SESSION['facilitator_id'])) {
    http_response_code(401);
    exit;
}

$query = "SELECT id, office_address FROM users";
$params = [];
$addresses = [];

if (isset($_POST['user_id'])) {
    $query .= " WHERE id = :id";
    $params['id'] = $_POST['user_id'];
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $addresses[$r['id']] = $r['office_address'];
    $nomalize = normalizeAddress($r['office_address']);
    $tokenize = tokenizeAddress($nomalize);

    echo $r['office_address'] . '<br>';
    echo $nomalize . '<br>';
    echo json_encode($tokenize) . '<br>';
    echo '<br>';
}