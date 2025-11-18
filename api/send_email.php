<?php
require_once "db.php";
require_once "../helper/email_helper.php";

header("Content-Type: application/json");

// Ambil semua peserta yang LULUS
$stmt = $pdo->query("
    SELECT u.email, u.name 
    FROM scores s 
    JOIN users u ON u.id = s.user_id
    WHERE (
        s.status = 'LULUS' AND s.manual_override = 'NO'
    ) OR (
        s.manual_override = 'YES' AND s.manual_status = 'LULUS'
    )
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$users) {
    echo json_encode([
        "success" => false,
        "message" => "Tidak ada peserta yang LULUS."
    ]);
    exit;
}

// Load template email
$template = file_get_contents(__DIR__ . '/../email_templates/lulus.html');

// Subject email
$subject = "Selamat! Anda Lulus Seleksi";

// Kirim email menggunakan bulk
$sent = sendBulkMails($users, $subject, $template);

// Balikkan response
echo json_encode([
    "success" => true,
    "message" => "Email diproses: {$sent} dari " . count($users) . " peserta."
]);
