<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$username = trim($data['username'] ?? '');
$password = trim($data['password'] ?? '');

if ($username === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Field kosong.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM facilitators WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Username tidak ditemukan.']);
    exit;
}

if (password_verify($password, $user['password'])) {
    $_SESSION['facilitator_id'] = $user['id'];
    $_SESSION['facilitator_name'] = $user['username'];
    echo json_encode(['success' => true, 'redirect' => '/dashboard']);
} else {
    echo json_encode(['success' => false, 'message' => 'Password salah.']);
}
