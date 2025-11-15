<?php
require_once "db.php";
require_once "../helper/email_helper.php";

header("Content-Type: application/json");

$stmt = $pdo->query("
    SELECT u.email, u.name 
    FROM scores s 
    JOIN users u ON u.id = s.user_id
    WHERE s.status = 'LULUS'
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$users) {
    echo json_encode(["success" => false, "message" => "Tidak ada peserta yang LULUS."]);
    exit;
}

$template = file_get_contents(__DIR__ . '/../email_templates/lulus.html');

$total = count($users);
$sent  = 0;

foreach ($users as $u) {

    $htmlBody = str_replace('{{name}}', $u['name'], $template);

    if (sendHtmlMail($u['email'], $u['name'], "Selamat! Anda Lulus Seleksi", $htmlBody)) {
        $sent++;
    }

    usleep(150000);
}

echo json_encode([
    "success" => true,
    "message" => "Email diproses: {$sent} dari {$total} peserta."
]);
