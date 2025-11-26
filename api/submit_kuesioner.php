<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

// === Ambil data input ===
$data = json_decode(file_get_contents('php://input'), true);
$name = trim($data['name']);
$email = trim($data['email']);
$phone = trim($data['phone']);
$organization = trim($data['organization']);
$office_address = trim($data['office_address']);

$answers = $data['answers'] ?? [];

// === Simpan User ===
$stmt = $pdo->prepare("INSERT INTO users (name, email, phone, organization, office_address) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), phone = VALUES(phone), organization = VALUES(organization), office_address = VALUES(office_address)");
$stmt->execute([$name, $email, $phone, $organization, $office_address]);
$userId = $pdo->lastInsertId();
if (!$userId) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $userId = $stmt->fetchColumn();
}

// === Simpan Jawaban ===
$qKeys = range(1, 15);
$values = array_map(fn($k) => $answers["q$k"] ?? null, $qKeys);
$placeholders = implode(',', array_fill(0, 15, '?'));

$stmt = $pdo->prepare("
    INSERT INTO responses (user_id, q1,q2,q3,q4,q5,q6,q7,q8,q9,q10,q11,q12,q13,q14,q15)
    VALUES (?, $placeholders)
    ON DUPLICATE KEY UPDATE
    q1=VALUES(q1), q2=VALUES(q2), q3=VALUES(q3), q4=VALUES(q4),
    q5=VALUES(q5), q6=VALUES(q6), q7=VALUES(q7), q8=VALUES(q8),
    q9=VALUES(q9), q10=VALUES(q10), q11=VALUES(q11), q12=VALUES(q12),
    q13=VALUES(q13), q14=VALUES(q14), q15=VALUES(q15)
");
$stmt->execute(array_merge([$userId], $values));

// === Hitung Skor Kompetensi (tanpa Q15) ===
$qRows = $pdo->query("SELECT id, reversed FROM questions ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$total = 0;
foreach ($qRows as $row) {
    $qid = "q{$row['id']}";
    $value = $answers[$qid] ?? 0;

    // Lewati pertanyaan integritas (Q15)
    if ($row['id'] == 15)
        continue;

    $score = $row['reversed'] ? (6 - $value) : $value;
    $total += $score;
}

// === Tentukan Status Integritas ===
$integrity_value = $answers['q15'] ?? 0;
$integrity_status = ($integrity_value == 5) ? 'LULUS' : 'GAGAL';

// === Simpan Skor ===
$stmt = $pdo->prepare("
    INSERT INTO scores (user_id, core_score, integrity_status)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE core_score = VALUES(core_score), integrity_status = VALUES(integrity_status)
");
$stmt->execute([$userId, $total, $integrity_status]);

// === Ambil semua data untuk ranking ===
// Urutkan: Integritas LULUS lebih dulu, lalu berdasarkan skor menurun
$results = $pdo->query("
    SELECT id, user_id, core_score, integrity_status
    FROM scores
    ORDER BY 
        (integrity_status = 'GAGAL') ASC,  -- LULUS dulu, GAGAL terakhir
        core_score DESC
")->fetchAll(PDO::FETCH_ASSOC);

// === Update ranking ===
$rank = 1;
foreach ($results as $r) {
    $stmt = $pdo->prepare("UPDATE scores SET ranking = ? WHERE id = ?");
    $stmt->execute([$rank++, $r['id']]);
}

// === Ambil kuota ===
$quota = $pdo->query("SELECT quota FROM quota_settings WHERE id = 1")->fetchColumn();

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

// === Pastikan jumlah LULUS sesuai kuota ===
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

// === Selesai ===
echo json_encode(['success' => true]);
