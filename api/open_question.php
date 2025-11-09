<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['facilitator_id'])) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query("SELECT is_open FROM open_questions LIMIT 1");
        $status = $stmt->fetchColumn();
        echo json_encode(['success' => true, 'is_open' => (int) $status]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $open = isset($data['open']) ? (int) $data['open'] : null;
        if ($open === null || ($open !== 0 && $open !== 1)) {
            echo json_encode(['success' => false, 'message' => 'Nilai open harus 0 atau 1.']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE open_questions SET is_open = ?");
        $stmt->execute([$open]);
        echo json_encode(['success' => true, 'message' => 'Status kuesioner berhasil diperbarui.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
