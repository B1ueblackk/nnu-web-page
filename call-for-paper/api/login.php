<?php
header('Content-Type: application/json');

// 允许跨域请求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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
            
            // 移除引号
            $value = trim($value, '"\'');
            
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// 加载环境变量
try {
    loadEnv(__DIR__ . '/../.env');
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => '配置错误：' . $e->getMessage()]);
    exit;
}

// 数据库配置
$db_config = [
    'host' => getenv('DB_HOST'),
    'username' => getenv('DB_USER'),
    'password' => getenv('DB_PASS'),
    'database' => getenv('DB_NAME')
];

// 获取POST数据
$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

// 验证输入
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['message' => '邮箱和密码不能为空']);
    exit;
}

try {
    // 连接数据库
    $conn = new mysqli($db_config['host'], $db_config['username'], $db_config['password'], $db_config['database']);
    
    if ($conn->connect_error) {
        throw new Exception('数据库连接失败');
    }
    
    // 准备SQL语句
    $stmt = $conn->prepare('SELECT id, email, password, name FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['message' => '用户不存在']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    // 验证密码
    if (!password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['message' => '密码错误']);
        exit;
    }
    
    // 生成会话ID
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['name'];
    
    // 返回成功响应
    echo json_encode([
        'message' => '登录成功',
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => '服务器错误：' . $e->getMessage()]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
} 