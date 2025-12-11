<?php
require_once __DIR__ . "/db.php";
session_start();

// Pastikan hanya fasilitator yang boleh download
if (!isset($_SESSION['facilitator_id'])) {
    http_response_code(403);
    exit("Akses ditolak");
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=semua_peserta.csv');

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

// Query dengan PDO
$stmt = $pdo->query("
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
    LEFT JOIN scores s ON s.user_id = u.id
    ORDER BY 
        (s.integrity_status = 'GAGAL') ASC,
        s.ranking ASC
");

// Output baris CSV
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
exit;
