<?php
header('Content-Type: application/json');

$uri = $_SERVER['REQUEST_URI'];
$uri = parse_url($uri, PHP_URL_PATH);
$uri = rtrim($uri, '/');

$routes = [
    '/api/v1/transaction/create' => __DIR__ . '/v1/transaction/create.php',
    '/api/v1/transaction/boleto' => __DIR__ . '/v1/transaction/boleto.php',
    '/api/v1/transaction/card'   => __DIR__ . '/v1/transaction/card.php',
];

if (isset($routes[$uri])) {
    require $routes[$uri];
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Route not found']);
}
