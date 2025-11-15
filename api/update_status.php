<?php
require_once "db.php";

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

$id = intval($data["id"] ?? 0);
$status = $data["status"] ?? "";

$allowed = ["LULUS", "TIDAK LOLOS"];
if (!in_array($status, $allowed)) {
    echo json_encode(["success" => false, "message" => "Status tidak valid."]);
    exit;
}

try {
    // Update berdasarkan id
    $stmt = $pdo->prepare("
        UPDATE scores 
        SET 
            manual_override = 'YES',
            manual_status = :manual_status,
            status = :final_status
        WHERE id = :id
    ");

    // Konsistensi status

    $stmt->execute([
        ':manual_status' => $status,
        ':final_status' => $status,
        ':id' => $id
    ]);

    $stmt = $pdo->prepare("
        SELECT * FROM scores WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $id
    ]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(["success" => true, "data" => $result]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
