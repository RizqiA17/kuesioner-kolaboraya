<?php
require_once "db.php";
require_once "../helper/email_helper.php";

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);
$user_id = intval($data["user_id"] ?? 0);

$stmt = $pdo->prepare("
    SELECT u.email, u.name, s.status 
    FROM scores s
    JOIN users u ON u.id = s.user_id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['status'] !== 'LULUS') {
    echo json_encode(["success" => false, "message" => "User tidak ditemukan atau tidak berstatus LULUS"]);
    exit;
}

$template = file_get_contents(__DIR__ . '/../email_templates/lulus.html');
$htmlBody = str_replace('{{name}}', $user['name'], $template);

$sent = sendHtmlMail($user['email'], $user['name'], "Selamat! Anda Lulus Seleksi", $htmlBody);

echo json_encode([
    "success" => $sent,
    "message" => $sent ? "Email terkirim" : "Gagal mengirim email"
]);
