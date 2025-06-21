<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

// 获取POST数据
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'JSON 解析错误'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$captcha = $data['captcha'] ?? '';

if (empty($captcha)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '请输入验证码'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 验证验证码
if (!isset($_SESSION['captcha']) || strtoupper($captcha) !== $_SESSION['captcha']) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '验证码错误'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 验证码正确
echo json_encode([
    'success' => true,
    'message' => '验证码正确'
], JSON_UNESCAPED_UNICODE);
?>