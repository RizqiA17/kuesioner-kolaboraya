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
fputcsv($output, ['Nama', 'Email', 'Skor', 'Integritas', 'Ranking', 'Status'], '|');

// Query peserta: LULUS atau GAGAL tapi core_score >= minimumScore
$stmt = $pdo->prepare("
    SELECT 
        u.name, 
        u.email, 
        s.core_score, 
        s.integrity_status, 
        s.ranking, 
        s.status
    FROM users u
    JOIN scores s ON u.id = s.user_id
    WHERE s.status = 'LULUS'
        OR (s.status = 'TIDAK LOLOS' AND s.core_score >= :minScore)
    ORDER BY s.ranking ASC
");

$stmt->execute([':minScore' => $minimumScore]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row, "|");
}

fclose($output);
?>
