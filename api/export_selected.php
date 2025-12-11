<?php
require_once 'db.php';
session_start();
if (!isset($_SESSION['facilitator_id'])) {
    http_response_code(401);
    exit;
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="peserta_lulus.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Nama', 'Email', 'Skor', 'Integritas', 'Ranking', 'Status'], '|');

$stmt = $pdo->query("SELECT u.name, u.email, s.core_score, s.integrity_status, s.ranking, s.status
                        FROM users u
                        JOIN scores s ON u.id = s.user_id
                        WHERE s.status = 'LULUS'
                        ORDER BY s.ranking ASC");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row, "|");
}
fclose($output);
?>
