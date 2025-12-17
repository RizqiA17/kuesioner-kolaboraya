<?php
require_once 'db.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

session_start();

if (!isset($_SESSION['facilitator_id'])) {
    http_response_code(401);
    exit;
}

// Ambil minimum_score dari settings
$settingStmt = $pdo->prepare("
    SELECT value 
    FROM settings 
    WHERE name = 'minimum_score' 
    LIMIT 1
");
$settingStmt->execute();
$minimumScore = (int) $settingStmt->fetchColumn();

// Query peserta lulus
$stmt = $pdo->prepare("
    SELECT 
        s.ranking,
        u.name,
        u.email,
        u.phone,
        u.organization,
        u.office_address,
        s.core_score,
        s.integrity_status,
        s.status,
        s.manual_override,
        s.manual_status
    FROM users u
    JOIN scores s ON u.id = s.user_id
    WHERE 
        s.status = 'LULUS'
        OR (s.status = 'TIDAK LOLOS' AND s.core_score >= :minScore)
    ORDER BY s.ranking ASC
");

$stmt->execute([
    ':minScore' => $minimumScore
]);

// Buat spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Peserta Lulus');

// Header kolom
$headers = [
    'Ranking',
    'Nama',
    'Email',
    'No HP',
    'Organisasi',
    'Alamat',
    'Skor',
    'Integritas',
    'Status'
];

$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);
    $col++;
}

// Isi data
$rowNumber = 2;

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

    $finalStatus = ($r['manual_override'] === "YES")
        ? $r['manual_status']
        : $r['status'];

    $cleanAddress = str_replace(["\r", "\n"], ' ', $r['office_address']);

    $sheet->setCellValue('A' . $rowNumber, $r['ranking']);
    $sheet->setCellValue('B' . $rowNumber, $r['name']);
    $sheet->setCellValue('C' . $rowNumber, $r['email']);

    $sheet->setCellValueExplicit(
        'D' . $rowNumber,
        $r['phone'],
        DataType::TYPE_STRING
    );

    $sheet->setCellValue('E' . $rowNumber, $r['organization']);
    $sheet->setCellValue('F' . $rowNumber, $cleanAddress);
    $sheet->setCellValue('G' . $rowNumber, $r['core_score']);
    $sheet->setCellValue('H' . $rowNumber, $r['integrity_status']);
    $sheet->setCellValue('I' . $rowNumber, $finalStatus);

    $rowNumber++;
}

// Auto width kolom
foreach (range('A', 'I') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Header download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="peserta_lulus.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
