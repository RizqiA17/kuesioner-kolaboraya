<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

// Pastikan hanya fasilitator yang bisa akses
if (!isset($_SESSION['facilitator_id'])) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

// Ambil data input
$data = json_decode(file_get_contents('php://input'), true);
$startDate = $data['startDate'] ?? null;
$endDate = $data['endDate'] ?? null;

// Validasi input
if (!$startDate || !$endDate) {
    echo json_encode(['success' => false, 'message' => 'Tanggal mulai dan tanggal akhir wajib diisi.']);
    exit;
}

try {
    // Bangun query dengan filter tanggal
    $query = "INSERT INTO open_questions_schedules (start_date, end_date) VALUES (:start_date, :end_date)";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':start_date', $startDate);
    $stmt->bindValue(':end_date', $endDate);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Jadwal berhasil ditambahkan.']);
} catch (PDOException $e) {
    // Tangani error database
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan pada database: ' . $e->getMessage()
    ]);
}
