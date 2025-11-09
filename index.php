<?php
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request = rtrim($request, '/');

switch ($request) {
    case '':
    case '/':
        $file = 'kuesioner.html';
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
