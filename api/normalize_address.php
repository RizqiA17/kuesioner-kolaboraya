<?php
require_once 'db.php';
require_once '../helper/normalize_address.php';
require_once '../helper/tokenize_address.php';

session_start();
if (!isset($_SESSION['facilitator_id'])) {
    http_response_code(401);
    exit;
}

// ===================================
// PRELOAD MASTER DATA
// ===================================

$provincies = $pdo->query(
    "SELECT code, name FROM provincies"
)->fetchAll(PDO::FETCH_ASSOC);

$regencies = $pdo->query(
    "SELECT code, name, province_code FROM regencies"
)->fetchAll(PDO::FETCH_ASSOC);

$districts = $pdo->query(
    "SELECT code, name, regency_code FROM districts"
)->fetchAll(PDO::FETCH_ASSOC);

// ===================================
// QUERY USERS
// ===================================

$query = "SELECT id, office_address FROM users";
$params = [];

if (isset($_GET['user_id'])) {
    $query .= " WHERE id = :id";
    $params['id'] = $_GET['user_id'];
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);

// ===================================
// COLLECT BATCH DATA
// ===================================

$batchData = [];

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $address = $r['office_address'];

    $location = detectLocationOptimized(
        $address,
        $provincies,
        $regencies,
        $districts
    );

    $batchData[] = [
        'user_id' => $r['id'],
        'province_code' => $location['province']['code'] ?? null,
        'regency_code' => $location['regency']['code'] ?? null,
        'district_code' => $location['district']['code'] ?? null
    ];

    echo "<strong>Alamat:</strong> " . htmlspecialchars($address) . "<br>";
    echo "<strong>Hasil Deteksi:</strong> "
        . json_encode($location, JSON_UNESCAPED_UNICODE)
        . "<br><br>";
}

// ===================================
// BATCH INSERT + ON DUPLICATE KEY
// ===================================

if (!empty($batchData)) {
    $placeholders = [];
    $values = [];

    foreach ($batchData as $row) {
        $placeholders[] = "(?, ?, ?, ?)";
        $values[] = $row['user_id'];
        $values[] = $row['province_code'];
        $values[] = $row['regency_code'];
        $values[] = $row['district_code'];
    }

    $sql = "
        INSERT INTO address
        (user_id, province_code, regency_code, district_code)
        VALUES " . implode(', ', $placeholders) . "
        ON DUPLICATE KEY UPDATE
            province_code = VALUES(province_code),
            regency_code  = VALUES(regency_code),
            district_code = VALUES(district_code)
    ";

    $stmtInsert = $pdo->prepare($sql);
    $stmtInsert->execute($values);
}
