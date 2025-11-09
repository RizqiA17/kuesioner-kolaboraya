<?php
require_once 'api/db.php';

// Ambil status open_question dari database
$stmt = $pdo->query("SELECT is_open FROM open_questions LIMIT 1");
$open = (int)$stmt->fetchColumn();

// Ambil request path
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request = rtrim($request, '/');

switch ($request) {
    case '':
    case '/':
        if ($open === 1) {
            $file = 'kuesioner.html';  // kuesioner dibuka
        } else {
            $file = 'callback.html';   // kuesioner ditutup
        }
        break;

    case '/login':
        $file = 'login.html';
        break;

    case '/dashboard':
        $file = 'dashboard.html';
        break;

    default:
        http_response_code(404);
        $file = '404.html';
        break;
}

if (file_exists($file)) {
    readfile($file);
} else {
    echo "<h1>404 - File hilang di server</h1>";
}
