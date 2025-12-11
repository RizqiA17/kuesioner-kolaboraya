<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

// Hanya fasilitator
if (!isset($_SESSION['facilitator_id'])) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

// Pagination (opsional)
$page  = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 20;
$offset = ($page - 1) * $limit;

// Ambil kuota
$quota = $pdo->query("SELECT quota FROM quota_settings WHERE id = 1")->fetchColumn();

// Hitung total peserta LOLOS
$total = $pdo->query("
    SELECT COUNT(*) 
    FROM scores 
    WHERE 
        (manual_override = 'YES' AND manual_status = 'LULUS')
        OR
        (manual_override = 'NO' AND status = 'LULUS')
")->fetchColumn();

// Ambil hanya peserta LOLOS
$stmt = $pdo->prepare("
    SELECT 
        s.id,
        s.user_id,
        u.name,
        u.email,
        u.phone,
        u.organization,
        u.office_address,
        s.core_score,
        s.integrity_status,
        s.status,
        s.manual_override,
        s.manual_status,
        s.ranking
    FROM scores s
    JOIN users u ON u.id = s.user_id
    WHERE 
        (s.manual_override = 'YES' AND s.manual_status = 'LULUS')
        OR
        (s.manual_override = 'NO' AND s.status = 'LULUS')
    ORDER BY s.ranking ASC
    LIMIT :limit OFFSET :offset
");

$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buat format final
$data = [];
foreach ($rows as $r) {

    $finalStatus = $r['manual_override'] === "YES"
        ? $r['manual_status']
        : $r['status'];

    $data[] = [
        'id' => (int)$r['id'],
        'user_id' => (int)$r['user_id'],
        'name' => $r['name'],
        'email' => $r['email'],
        'phone' => $r['phone'],
        'organization' => $r['organization'],
        'office_address' => $r['office_address'],
        'core_score' => (int)$r['core_score'],
        'integrity_status' => $r['integrity_status'],
        'ranking' => (int)$r['ranking'],
        'status' => $finalStatus,
        'manual_override' => $r['manual_override']
    ];
}

// Total halaman
$totalPages = ceil($total / $limit);

// Kirim respons
echo json_encode([
    'success' => true,
    'quota' => (int)$quota,
    'page' => (int)$page,
    'limit' => (int)$limit,
    'total' => (int)$total,
    'totalPages' => (int)$totalPages,
    'data' => $data
]);
