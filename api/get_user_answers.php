<?php
header('Content-Type: application/json');
require 'db.php';

$id = intval($_GET['id'] ?? 0);

// Ambil jawaban user (1 baris)
$stmt = $pdo->prepare("SELECT * FROM responses WHERE user_id = ?");
$stmt->execute([$id]);
$response = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$response) {
    echo json_encode([
        "success" => false,
        "message" => "Data tidak ditemukan"
    ]);
    exit;
}

// Ambil semua pertanyaan (agar bisa dicocokkan dengan q1..q15)
$q = $pdo->query("SELECT id, question_text FROM questions ORDER BY id ASC");
$questions = $q->fetchAll(PDO::FETCH_ASSOC);

// Label Likert
$labels = [
    1 => "Sangat Tidak Setuju",
    2 => "Tidak Setuju",
    3 => "Netral",
    4 => "Setuju",
    5 => "Sangat Setuju"
];

$answers = [];
foreach ($questions as $row) {
    $qid = $row['id'];        // id pertanyaan (1–15)
    $col = "q" . $qid;        // kolom di tabel responses

    if (!isset($response[$col]))
        continue;

    $value = intval($response[$col]);

    $answers[] = [
        "question_id" => $qid,
        "question" => $row['question_text'],
        "value" => $value,
        "answer_text" => $labels[$value] ?? "Tidak Diketahui"
    ];
}

echo json_encode([
    "success" => true,
    "answers" => $answers
]);
