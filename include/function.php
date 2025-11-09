<?php

function calculateScore($answers) {
    // Reverse-scored questions
    $reverse = [7, 10, 11, 13, 14];

    $coreScore = 0;

    for ($i = 1; $i <= 14; $i++) {
        $score = intval($answers["q$i"]);
        if (in_array($i, $reverse)) {
            $score = 6 - $score; // membalik nilai 1↔5, 2↔4, 3 tetap
        }
        $coreScore += $score;
    }

    // Soal ke-15 = Integritas
    $integrityAnswer = intval($answers['q15']);
    $integrityStatus = ($integrityAnswer === 5) ? 'LULUS' : 'GAGAL';

    return [
        'core_score' => $coreScore,
        'integrity_status' => $integrityStatus
    ];
}
?>
