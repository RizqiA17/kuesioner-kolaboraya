<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

// Pastikan hanya fasilitator yang bisa akses
if (!isset($_SESSION['facilitator_id'])) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

// Ambil parameter pagination
$page  = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 20;
$offset = ($page - 1) * $limit;

// Ambil kuota
$quota = $pdo->query("SELECT quota FROM quota_settings WHERE id = 1")->fetchColumn();

// Hitung total peserta
$total = $pdo->query("SELECT COUNT(*) FROM scores")->fetchColumn();

// Ambil data peserta dengan urutan sesuai ranking dan integritas
$stmt = $pdo->prepare("
    SELECT 
        s.id, u.name, u.email, s.core_score, s.integrity_status, s.status, s.ranking
    FROM scores s
    JOIN users u ON u.id = s.user_id
    ORDER BY 
        (s.integrity_status = 'GAGAL') ASC,
        s.ranking ASC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung total halaman
$totalPages = ceil($total / $limit);

// Kirim hasil
echo json_encode([
    'success' => true,
    'quota' => (int)$quota,
    'page' => (int)$page,
    'limit' => (int)$limit,
    'total' => (int)$total,
    'totalPages' => (int)$totalPages,
    'data' => $data
]);
