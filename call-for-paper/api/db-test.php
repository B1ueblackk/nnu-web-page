<?php
header('Content-Type: application/json');

// 加载环境变量
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception('.env file not found');
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, '"\'');
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

try {
    loadEnv(__DIR__ . '/../.env');
    
    $db_config = [
        'host' => getenv('DB_HOST'),
        'username' => getenv('DB_USER'),
        'password' => getenv('DB_PASS'),
        'database' => getenv('DB_NAME')
    ];
    
    $conn = new mysqli($db_config['host'], $db_config['username'], $db_config['password'], $db_config['database']);
    
    if ($conn->connect_error) {
        throw new Exception('数据库连接失败: ' . $conn->connect_error);
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => '数据库连接成功',
        'config' => [
            'host' => $db_config['host'],
            'username' => $db_config['username'],
            'database' => $db_config['database']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 