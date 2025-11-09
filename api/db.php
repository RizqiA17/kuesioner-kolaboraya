<?php
$host = '127.0.0.1';     // atau 127.0.0.1
$db   = 'nemolab_kuesioner'; // sesuai nama database di SQL
$user = 'root';          // ganti jika berbeda di server
$pass = '';              // password MySQL-mu
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal terhubung ke database.',
        'error' => $e->getMessage()
    ]);
    exit;
}
