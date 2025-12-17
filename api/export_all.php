<?php
require_once "db.php";
require_once "../vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

session_start();

// Pastikan hanya fasilitator yang boleh download
if (!isset($_SESSION['facilitator_id'])) {
    http_response_code(403);
    exit("Akses ditolak");
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Semua Peserta');

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

// Query data
$stmt = $pdo->query("
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
    LEFT JOIN scores s ON s.user_id = u.id
    ORDER BY 
        (s.integrity_status = 'GAGAL') ASC,
        s.ranking ASC
");

$rowNumber = 2;

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

    // Final status
    $finalStatus = $r['manual_override'] === "YES"
        ? $r['manual_status']
        : $r['status'];

    // Bersihkan newline di alamat
    $cleanAddress = str_replace(["\r", "\n"], ' ', $r['office_address']);

    $sheet->setCellValue('A' . $rowNumber, $r['ranking']);
    $sheet->setCellValue('B' . $rowNumber, $r['name']);
    $sheet->setCellValue('C' . $rowNumber, $r['email']);
    $sheet->setCellValueExplicit('D' . $rowNumber,$r['phone'],DataType::TYPE_STRING);
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
header('Content-Disposition: attachment; filename="semua_peserta.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
