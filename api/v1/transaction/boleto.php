<?php
header('Content-Type: application/json');

$txid = $_GET['txid'] ?? '';
$amount = $_GET['amount'] ?? '1990';

if (!$txid) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing txid']);
    exit;
}

$dueDate = date('Y-m-d', strtotime('+3 days'));
$boletoNumber = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);

echo json_encode([
    'success' => true,
    'txid' => $txid,
    'boleto_number' => $boletoNumber,
    'due_date' => $dueDate,
    'amount' => intval($amount),
    'status' => 'pending'
]);
