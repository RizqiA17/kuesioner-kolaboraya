<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

// Pastikan hanya fasilitator yang bisa akses
if (!isset($_SESSION['facilitator_id'])) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

// === Ambil kuota terbaru ===
$quota = $pdo->query("SELECT quota FROM quota_settings WHERE id = 1")->fetchColumn();

// === Ambil semua peserta ===
// Urutkan: LULUS integritas dulu, lalu berdasarkan skor menurun
$results = $pdo->query("
    SELECT id, user_id, core_score, integrity_status
    FROM scores
    ORDER BY 
        (integrity_status = 'GAGAL') ASC,  -- GAGAL di bawah
        core_score DESC
")->fetchAll(PDO::FETCH_ASSOC);

// === Update ranking ===
$rank = 1;
foreach ($results as $r) {
    $stmt = $pdo->prepare("UPDATE scores SET ranking = ? WHERE id = ?");
    $stmt->execute([$rank++, $r['id']]);
}

// === Tentukan status akhir (LULUS / TIDAK LOLOS) ===
$stmt = $pdo->prepare("
    UPDATE scores s
    JOIN (
        SELECT id,
                CASE
                    WHEN integrity_status = 'LULUS' AND ranking <= :q THEN 'LULUS'
                    ELSE 'TIDAK LOLOS'
                END AS new_status
        FROM scores
    ) sub ON s.id = sub.id
    SET s.status = sub.new_status
");
$stmt->execute(['q' => $quota]);

// === Pastikan jumlah LULUS tidak melebihi kuota ===
$passed = $pdo->query("SELECT COUNT(*) FROM scores WHERE status='LULUS'")->fetchColumn();
if ($passed > $quota) {
    $excess = $passed - $quota;
    $stmt = $pdo->prepare("
        UPDATE scores
        SET status='TIDAK LOLOS'
        WHERE status='LULUS'
        ORDER BY ranking DESC
        LIMIT $excess
    ");
    $stmt->execute();
}

// === Ambil data terbaru untuk dikirim ke admin dashboard ===
$updated = $pdo->query("
    SELECT s.id, u.name, u.email, s.core_score, s.integrity_status, s.status, s.ranking
    FROM scores s
    JOIN users u ON u.id = s.user_id
    ORDER BY s.ranking ASC
")->fetchAll(PDO::FETCH_ASSOC);

// === Output ===
echo json_encode(['success' => true, 'data' => $updated]);
