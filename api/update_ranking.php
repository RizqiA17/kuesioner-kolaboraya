<?php
require_once "db.php";
session_start();
header('Content-Type: application/json');

// hanya fasilitator
if (!isset($_SESSION['facilitator_id'])) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

// Ambil kuota
$quota = $pdo->query("SELECT quota FROM quota_settings WHERE id = 1")->fetchColumn();

// Ambil minimal score dari table settings
$minimum_score = $pdo->prepare("SELECT value FROM settings WHERE name = 'minimum_score' LIMIT 1");
$minimum_score->execute();
$minScore = (float) $minimum_score->fetchColumn();

// Ambil semua peserta
$results = $pdo->query("
    SELECT s.id, s.user_id, s.integrity_status, s.manual_override, s.manual_status, s.core_score
    FROM scores s
    ORDER BY 
        (s.integrity_status = 'GAGAL') ASC,
        s.core_score DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Update ranking
$rank = 1;
foreach ($results as $r) {
    $stmt = $pdo->prepare("UPDATE scores SET ranking = :r WHERE id = :id");
    $stmt->execute([':r' => $rank++, ':id' => $r['id']]);
}

// Hitung jumlah peserta manual LULUS
$manual_count = $pdo->query("
    SELECT COUNT(*) FROM scores 
    WHERE manual_override = 'YES' AND manual_status = 'LULUS'
")->fetchColumn();

$effective_quota = max(0, $quota - $manual_count);

// 1) Reset semua peserta non-manual ke TIDAK LOLOS (tanpa mengubah manual)
$pdo->exec("
    UPDATE scores
    SET status = 'TIDAK LOLOS'
    WHERE manual_override = 'NO'
");

// 2) Tetapkan LULUS untuk top N non-manual berdasarkan core_score,
//    hanya jika integrity LULUS dan core_score >= minimum_score
$stmt = $pdo->prepare("
    UPDATE scores s
    JOIN (
        SELECT id
        FROM scores
        WHERE manual_override = 'NO'
            AND integrity_status = 'LULUS'
            AND core_score >= :min_score
        ORDER BY core_score DESC, created_at ASC
        LIMIT :q
    ) sel ON s.id = sel.id
    SET s.status = 'LULUS'
");

$stmt->bindValue(':min_score', $minScore);
$stmt->bindValue(':q', (int) $effective_quota, PDO::PARAM_INT);
$stmt->execute();

// Ambil data terbaru
$updated = $pdo->query("
    SELECT s.*, u.name, u.email, u.phone, u.organization
    FROM scores s 
    JOIN users u ON u.id = s.user_id
    ORDER BY s.ranking ASC
")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(["success" => true, "data" => $updated]);
