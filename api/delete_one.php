<?php
require_once __DIR__ . '/db.php';
header("Content-Type: application/json");
session_start();

// Pastikan hanya fasilitator yang bisa menghapus
if (!isset($_SESSION['facilitator_id'])) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$id = intval($data["id"] ?? 0);

if ($id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid ID"]);
    exit;
}

try {
    // Hapus data di tabel users
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);

    // Hapus data skor di tabel scores (kalau ada)
    $pdo->prepare("DELETE FROM scores WHERE user_id = ?")->execute([$id]);

    // Hapus data responses kalau ada
    $pdo->prepare("DELETE FROM responses WHERE user_id = ?")->execute([$id]);

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Gagal menghapus",
        "error" => $e->getMessage()
    ]);
}
exit;
