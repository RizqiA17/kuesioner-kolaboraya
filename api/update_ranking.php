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

// Update status otomatis HANYA untuk peserta tanpa override
$stmt = $pdo->prepare("
    UPDATE scores s
    JOIN (
        SELECT id,
            CASE
                WHEN manual_override = 'YES' THEN manual_status
                WHEN integrity_status = 'LULUS' AND ranking <= :q 
                THEN 'LULUS'
                ELSE 'TIDAK LOLOS'
            END AS final_status
        FROM scores
    ) x ON s.id = x.id
    SET s.status = x.final_status
");
$stmt->execute([':q' => $quota]);

// Ambil data terbaru
$updated = $pdo->query("
    SELECT s.*, u.name, u.email, u.phone, u.organization
    FROM scores s 
    JOIN users u ON u.id = s.user_id
    ORDER BY s.ranking ASC
")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(["success" => true, "data" => $updated]);
