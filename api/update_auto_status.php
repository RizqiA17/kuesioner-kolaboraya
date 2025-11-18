<?php
require_once "db.php";

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

$id = intval($data["id"] ?? 0);

try {
    // Update berdasarkan id
    $stmt = $pdo->prepare("
        UPDATE scores 
        SET 
            manual_override = 'NO',
            manual_status = NULL
        WHERE id = :id
    ");

    // Konsistensi status

    $stmt->execute([
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
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
