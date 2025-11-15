<?php
require_once 'db.php';
session_start();
if (!isset($_SESSION['facilitator_id'])) {
  http_response_code(401);
  exit;
}

// Ambil kuota
$q = $pdo->query("SELECT quota FROM quota_settings WHERE id = 1")->fetchColumn();

// Ambil peserta lolos
$stmt = $pdo->prepare("SELECT u.name, u.email, s.core_score, s.integrity_status, s.ranking
                              FROM users u
                              JOIN scores s ON u.id = s.user_id
                              WHERE s.ranking <= ?
                              AND s.integrity_status = 'LULUS'
                              ORDER BY s.ranking ASC");
$stmt->execute([$q]);

echo json_encode([
  'quota' => intval($q),
  'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);
?>