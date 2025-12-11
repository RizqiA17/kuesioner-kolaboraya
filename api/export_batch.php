<?php
require_once 'db.php';
session_start();

// Hanya fasilitator
if (!isset($_SESSION['facilitator_id'])) {
    http_response_code(401);
    echo "Unauthorized";
    exit;
}

// Ambil nomor batch dari URL
$batchNumber = isset($_GET['batch']) ? intval($_GET['batch']) : 1;
if ($batchNumber < 1) $batchNumber = 1;

// Ambil limit dari settings
$settingStmt = $pdo->prepare("SELECT value FROM settings WHERE name = 'class_size' LIMIT 1");
$settingStmt->execute();
$classSize = (int) $settingStmt->fetchColumn();

// Hitung offset
$offset = ($batchNumber - 1) * $classSize;

// Ambil data (lulus atau gagal tapi nilai di atas minimum)
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


// Set headers CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="kelas_' . $batchNumber . '.csv"');

$output = fopen('php://output', 'w');

// Header CSV
fputcsv($output, [
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


// Output data
foreach ($rows as $r) {

    // Tentukan final status
    $finalStatus = ($r['manual_override'] === "YES")
        ? $r['manual_status']
        : $r['status'];

    // Bersihkan newline pada alamat agar tidak pecah baris
    $cleanAddress = preg_replace('/\s+/', ' ', trim($r['office_address']));

    // Tulis ke CSV
    fputcsv($output, [
        $r['ranking'],
        $r['name'],
        $r['email'],
        $r['phone'],
        $r['organization'],
        $cleanAddress,
        $r['core_score'],
        $r['integrity_status'],
        $finalStatus
    ], '|');
}

fclose($output);
exit;

?>
