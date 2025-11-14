<?php
require_once __DIR__ . "/db.php";
header("Content-Type: application/json");
session_start();

// Cegah akses tanpa login fasilitator
if (!isset($_SESSION['facilitator_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "Akses ditolak."
    ]);
    exit;
}

try {
    // Matikan foreign key sementara
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // TRUNCATE semua tabel
    $pdo->exec("TRUNCATE TABLE responses");
    $pdo->exec("TRUNCATE TABLE scores");
    $pdo->exec("TRUNCATE TABLE users");

    // Aktifkan kembali foreign key
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Gagal menghapus semua data",
        "error" => $e->getMessage()
    ]);
}

exit;
