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
    "ID",
    "Nama",
    "Email",
    "Telepon",
    "Organisasi",
    "Skor",
    "Integritas",
    "Ranking",
    "Status"
], "|");

// Query dengan PDO
$stmt = $pdo->query("
    SELECT u.id, u.name, u.email, u.phone, u.organization, s.core_score, s.integrity_status, s.ranking, s.status
    FROM users u
    LEFT JOIN scores s ON s.user_id = u.id
    ORDER BY 
        (s.integrity_status = 'GAGAL') ASC,
        s.ranking ASC
");

// Output baris CSV
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row, "|");
}

fclose($output);
exit;
