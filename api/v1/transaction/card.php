<?php
header('Content-Type: application/json');

$txid = $_GET['txid'] ?? '';
$installments = $_GET['installments'] ?? '1';

if (!$txid) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing txid']);
    exit;
}

echo json_encode([
    'success' => true,
    'txid' => $txid,
    'installments' => intval($installments),
    'status' => 'pending'
]);
