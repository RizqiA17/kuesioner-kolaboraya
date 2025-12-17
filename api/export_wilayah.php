<?php

ob_start();

require_once 'db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/*
|--------------------------------------------------
| TOKEN
|--------------------------------------------------
*/
if (empty($_GET['token'])) {
    http_response_code(401);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id
    FROM tokens
    WHERE token = :token
        AND expires_at > NOW()
    LIMIT 1
");
$stmt->execute([':token' => $_GET['token']]);
$tokenRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tokenRow) {
    http_response_code(401);
    exit;
}

$pdo->prepare("DELETE FROM tokens WHERE id = :id")
    ->execute([':id' => $tokenRow['id']]);

/*
|--------------------------------------------------
| QUERY UTAMA (TIDAK DIUBAH)
|--------------------------------------------------
*/
$query = "
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
    JOIN address a ON u.id = a.user_id
";

$params = [];
$wilayahLabel = 'Semua_Wilayah';

/*
|--------------------------------------------------
| WILAYAH (TIDAK DIUBAH)
|--------------------------------------------------
*/
if (!isset($_GET['provinsi'])) {
    echo json_encode(['status' => false, 'message' => 'Tidak ada wilayah yang dipilih']);
    ob_end_flush();
    exit;
}

if ($_GET['provinsi'] === 'none') {
    $query .= " WHERE a.province_code IS NULL ";
    $wilayahLabel = 'Tanpa_Provinsi';
} else {
    $query .= " WHERE a.province_code = :provinsi ";
    $params[':provinsi'] = $_GET['provinsi'];

    $pStmt = $pdo->prepare("SELECT name FROM provincies WHERE code = :code LIMIT 1");
    $pStmt->execute([':code' => $_GET['provinsi']]);
    $provName = $pStmt->fetchColumn();

    if ($provName) {
        $wilayahLabel = preg_replace('/\s+/', '_', strtoupper($provName));
    }
}

if (!empty($_GET['kabupaten'])) {
    $query .= " AND a.regency_code = :kabupaten ";
    $params[':kabupaten'] = $_GET['kabupaten'];

    $rStmt = $pdo->prepare("SELECT name FROM regencies WHERE code = :code LIMIT 1");
    $rStmt->execute([':code' => $_GET['kabupaten']]);
    $regName = $rStmt->fetchColumn();

    if ($regName) {
        $wilayahLabel .= '_' . preg_replace('/\s+/', '_', strtoupper($regName));
    }
}

$query .= " ORDER BY s.ranking ASC";

/*
|--------------------------------------------------
| BATCH CONFIG (TIDAK DIUBAH)
|--------------------------------------------------
*/
$limitPerXlsx = isset($_GET['limit_per_xlsx']) && (int) $_GET['limit_per_xlsx'] > 0
    ? (int) $_GET['limit_per_xlsx']
    : null;

/*
|--------------------------------------------------
| HELPER: BUILD XLSX DARI PDOStatement
|--------------------------------------------------
*/
function buildXlsx(PDOStatement $stmt, string $filename)
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

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
    foreach ($headers as $h) {
        $sheet->setCellValue($col . '1', $h);
        $col++;
    }

    $rowNum = 2;
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $finalStatus = $r['manual_override'] === 'YES'
            ? $r['manual_status']
            : $r['status'];

        $sheet->setCellValue('A' . $rowNum, $r['ranking']);
        $sheet->setCellValue('B' . $rowNum, $r['name']);
        $sheet->setCellValue('C' . $rowNum, $r['email']);

        // PHONE AS TEXT (AMAN +62)
        $sheet->setCellValueExplicit(
            'D' . $rowNum,
            $r['phone'],
            DataType::TYPE_STRING
        );

        $sheet->setCellValue('E' . $rowNum, $r['organization']);
        $sheet->setCellValue('F' . $rowNum, str_replace(["\r", "\n"], ' ', $r['office_address']));
        $sheet->setCellValue('G' . $rowNum, $r['core_score']);
        $sheet->setCellValue('H' . $rowNum, $r['integrity_status']);
        $sheet->setCellValue('I' . $rowNum, $finalStatus);

        $rowNum++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
}

/*
|--------------------------------------------------
| SINGLE XLSX
|--------------------------------------------------
*/
if ($limitPerXlsx === null) {

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    buildXlsx($stmt, 'peserta_' . $wilayahLabel);

    ob_end_flush();
    exit;
}

/*
|--------------------------------------------------
| ZIP XLSX (BATCH)
|--------------------------------------------------
*/
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM ({$query}) t");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();

$tmpZipPath = tempnam(sys_get_temp_dir(), 'zip_');
unlink($tmpZipPath);

$zip = new ZipArchive();
$zip->open($tmpZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

for ($offset = 0, $batch = 1; $offset < $totalRows; $offset += $limitPerXlsx, $batch++) {

    $stmt = $pdo->prepare($query . " LIMIT :limit OFFSET :offset");

    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limitPerXlsx, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

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
    foreach ($headers as $h) {
        $sheet->setCellValue($col . '1', $h);
        $col++;
    }

    $rowNum = 2;
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $finalStatus = $r['manual_override'] === 'YES'
            ? $r['manual_status']
            : $r['status'];

        $sheet->setCellValue('A' . $rowNum, $r['ranking']);
        $sheet->setCellValue('B' . $rowNum, $r['name']);
        $sheet->setCellValue('C' . $rowNum, $r['email']);
        $sheet->setCellValueExplicit('D' . $rowNum, $r['phone'], DataType::TYPE_STRING);
        $sheet->setCellValue('E' . $rowNum, $r['organization']);
        $sheet->setCellValue('F' . $rowNum, str_replace(["\r", "\n"], ' ', $r['office_address']));
        $sheet->setCellValue('G' . $rowNum, $r['core_score']);
        $sheet->setCellValue('H' . $rowNum, $r['integrity_status']);
        $sheet->setCellValue('I' . $rowNum, $finalStatus);

        $rowNum++;
    }

    $tmpXlsx = tempnam(sys_get_temp_dir(), 'xlsx_');
    $writer = new Xlsx($spreadsheet);
    $writer->save($tmpXlsx);

    $zip->addFile($tmpXlsx, 'peserta_' . $wilayahLabel . '_' . $batch . '.xlsx');
}

$zip->close();

/*
|--------------------------------------------------
| STREAM ZIP
|--------------------------------------------------
*/
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="peserta_' . $wilayahLabel . '.zip"');
header('Content-Length: ' . filesize($tmpZipPath));

readfile($tmpZipPath);
unlink($tmpZipPath);

ob_end_flush();
exit;
