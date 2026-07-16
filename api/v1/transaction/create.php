<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$config = require __DIR__ . '/../../config.php';

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if (empty($authHeader)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authorization header required']);
    exit;
}

if (preg_match('/^Basic\s+(.+)$/i', $authHeader, $matches)) {
    $decoded = base64_decode($matches[1], true);
    if ($decoded === false || !str_contains($decoded, ':')) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid Basic Auth format']);
        exit;
    }
    [$secretKey, $companyId] = explode(':', $decoded, 2);

    if ($secretKey !== $config['secret_key'] || $companyId !== $config['company_id']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
        exit;
    }
} elseif (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
    $token = $matches[1];
    $validToken = hash_hmac('sha256', $config['secret_key'], $config['company_id']);
    if (!hash_equals($validToken, $token)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid Bearer token']);
        exit;
    }
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unsupported authorization method. Use Basic or Bearer.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request body']);
    exit;
}

$required = ['payment_method', 'amount', 'customer', 'items'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
        exit;
    }
}

$customerRequired = ['name', 'email', 'document'];
foreach ($customerRequired as $field) {
    if (empty($input['customer'][$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Missing required customer field: $field"]);
        exit;
    }
}

$paymentMethod = $input['payment_method'];
if (!in_array($paymentMethod, ['pix', 'credit_card', 'boleto'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid payment_method. Use: pix, credit_card, boleto']);
    exit;
}

$amount = intval($input['amount']);
if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Amount must be greater than 0']);
    exit;
}

$orderId = $input['order_id'] ?? 'ORD-' . bin2hex(random_bytes(8));
$expiresAt = date('c', time() + 3600);
$txId = 'tx_' . bin2hex(random_bytes(6));

$response = [
    'success' => true,
    'txid' => $txId,
    'status' => 'pending',
    'amount' => $amount,
    'payment_method' => $paymentMethod,
    'order_id' => $orderId,
    'customer' => [
        'name' => $input['customer']['name'],
        'email' => $input['customer']['email'],
        'document' => $input['customer']['document']
    ]
];

if ($paymentMethod === 'pix') {
    $pixCode = '00020126360014BR.GOV.BCB.PIX0136' . $txId . '5204000053039865404' . number_format($amount / 100, 2, '.', '') . '5802BR5913SIMPLIC ONL62070503***6304';
    $qrData = base64_encode($pixCode);

    $response['qr_code'] = $pixCode;
    $response['qr_code_image'] = 'data:image/png;base64,' . $qrData;
    $response['expires_at'] = $expiresAt;
    $response['pix_copy_paste'] = $pixCode;

} elseif ($paymentMethod === 'boleto') {
    $dueDate = date('Y-m-d', strtotime('+3 days'));
    $boletoNumber = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);

    $response['boleto_url'] = '/api/v1/transaction/boleto.php?txid=' . $txId;
    $response['boleto_number'] = $boletoNumber;
    $response['due_date'] = $dueDate;
    $response['amount'] = $amount;

} elseif ($paymentMethod === 'credit_card') {
    $installments = intval($input['installments'] ?? 1);
    if ($installments < 1 || $installments > 12) $installments = 1;

    $response['installments'] = $installments;
    $response['card_url'] = '/api/v1/transaction/card.php?txid=' . $txId . '&installments=' . $installments;
    $response['amount'] = $amount;
}

echo json_encode($response);
