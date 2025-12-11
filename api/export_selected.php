<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['facilitator_id'])) {
    http_response_code(401);
    exit;
}

// Ambil minimum_score dari settings
$settingStmt = $pdo->prepare("SELECT value FROM settings WHERE name = 'minimum_score' LIMIT 1");
$settingStmt->execute();
$minimumScore = (int) $settingStmt->fetchColumn();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="peserta_lulus.csv"');

$output = fopen('php://output', 'w');

// Header CSV
fputcsv($output, [
    'Ranking',
    'Nama',
    'Email',
    'Phone',
    'Organization',
    'Office Address',
    'Core Score',
    'Integrity Status',
    'Final Status'
], '|');

// Query peserta: LULUS atau TIDAK LOLOS tapi core_score >= minimumScore
$stmt = $pdo->prepare("
    SELECT 
        s.ranking,
        u.name,
        u.email,
        u.phone,
        u.organization,
        u.office_address,
        s.core_score,
        s.integrity_status,
        s.status,
        s.manual_override,
        s.manual_status
    FROM users u
    JOIN scores s ON u.id = s.user_id
    WHERE 
        s.status = 'LULUS'
        OR (s.status = 'TIDAK LOLOS' AND s.core_score >= :minScore)
    ORDER BY s.ranking ASC
");

$stmt->execute([':minScore' => $minimumScore]);

// Proses data seperti di API (menghitung finalStatus)
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

    // Final status
    $finalStatus = $r['manual_override'] === "YES"
        ? $r['manual_status']
        : $r['status'];

    // Hapus newline di alamat sebelum masuk CSV
    $cleanAddress = str_replace(["\r", "\n"], ' ', $r['office_address']);

    fputcsv($output, [
        $r['ranking'],
        $r['name'],
        $r['email'],
        $r['phone'],
        $r['organization'],
        $cleanAddress,
        $r['core_score'],
        $r['integrity_status'],
        $finalStatus
    ], '|');
}


fclose($output);
?>