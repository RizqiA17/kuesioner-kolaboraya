<?php
require_once 'db.php';
require_once "./../vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

session_start();

// Hanya fasilitator
if (!isset($_SESSION['facilitator_id'])) {
    http_response_code(401);
    exit("Unauthorized");
}

// Ambil nomor batch
$batchNumber = isset($_GET['batch']) ? intval($_GET['batch']) : 1;
if ($batchNumber < 1) {
    $batchNumber = 1;
}

// Ambil class_size dari settings
$settingStmt = $pdo->prepare("
    SELECT value 
    FROM settings 
    WHERE name = 'class_size' 
    LIMIT 1
");
$settingStmt->execute();
$classSize = (int) $settingStmt->fetchColumn();

// Hitung offset
$offset = ($batchNumber - 1) * $classSize;

// Query data
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
    ORDER BY s.ranking ASC
    LIMIT :limit OFFSET :offset
");

$stmt->bindValue(':limit', $classSize, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buat spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Kelas ' . $batchNumber);

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

foreach ($rows as $r) {

    $finalStatus = ($r['manual_override'] === "YES")
        ? $r['manual_status']
        : $r['status'];

    $cleanAddress = preg_replace('/\s+/', ' ', trim($r['office_address']));

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
header(
    'Content-Disposition: attachment; filename="kelas_' . $batchNumber . '.xlsx"'
);
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
