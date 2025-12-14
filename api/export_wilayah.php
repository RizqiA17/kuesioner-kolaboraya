<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['facilitator_id'])) {
    http_response_code(401);
    exit;
}

/*
|--------------------------------------------------
| Query utama
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
| Wilayah
|--------------------------------------------------
*/
if (!isset($_GET['provinsi'])) {
    echo json_encode(['status' => false, 'message' => 'Tidak ada wilayah yang dipilih']);
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
| Batch config
|--------------------------------------------------
*/
$limitPerCsv = isset($_GET['limit_per_csv']) && (int) $_GET['limit_per_csv'] > 0
    ? (int) $_GET['limit_per_csv']
    : null;

/*
|--------------------------------------------------
| SINGLE CSV
|--------------------------------------------------
*/
if ($limitPerCsv === null) {

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="peserta_' . $wilayahLabel . '.csv"');

    $out = fopen('php://output', 'w');

    fputcsv($out, [
        'Ranking',
        'Nama',
        'Email',
        'Phone',
        'Organization',
        'Office Address',
        'Core Score',
        'Integrity Status',
        'Final Status'
    ], '|');

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $finalStatus = $r['manual_override'] === 'YES'
            ? $r['manual_status']
            : $r['status'];

        fputcsv($out, [
            $r['ranking'],
            $r['name'],
            $r['email'],
            $r['phone'],
            $r['organization'],
            str_replace(["\r", "\n"], ' ', $r['office_address']),
            $r['core_score'],
            $r['integrity_status'],
            $finalStatus
        ], '|');
    }

    fclose($out);
    exit;
}

/*
|--------------------------------------------------
| ZIP export (temp file OS)
|--------------------------------------------------
*/
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM ({$query}) t");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();

$tmpZipPath = tempnam(sys_get_temp_dir(), 'zip_');

$zip = new ZipArchive();
$zip->open($tmpZipPath, ZipArchive::CREATE);

for ($offset = 0, $batch = 1; $offset < $totalRows; $offset += $limitPerCsv, $batch++) {

    $csvStream = fopen('php://temp/maxmemory:5242880', 'w+');

    fputcsv($csvStream, [
        'Ranking',
        'Nama',
        'Email',
        'Phone',
        'Organization',
        'Office Address',
        'Core Score',
        'Integrity Status',
        'Final Status'
    ], '|');

    $stmt = $pdo->prepare($query . " LIMIT :limit OFFSET :offset");

    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }

    $stmt->bindValue(':limit', $limitPerCsv, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $finalStatus = $r['manual_override'] === 'YES'
            ? $r['manual_status']
            : $r['status'];

        fputcsv($csvStream, [
            $r['ranking'],
            $r['name'],
            $r['email'],
            $r['phone'],
            $r['organization'],
            str_replace(["\r", "\n"], ' ', $r['office_address']),
            $r['core_score'],
            $r['integrity_status'],
            $finalStatus
        ], '|');
    }

    rewind($csvStream);

    $zip->addFromString(
        'peserta_' . $wilayahLabel . '_' . $batch . '.csv',
        stream_get_contents($csvStream)
    );

    fclose($csvStream);
}

$zip->close();

/*
|--------------------------------------------------
| Stream ZIP ke client
|--------------------------------------------------
*/
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="peserta_' . $wilayahLabel . '.zip"');
header('Content-Length: ' . filesize($tmpZipPath));

readfile($tmpZipPath);
unlink($tmpZipPath);
exit;
