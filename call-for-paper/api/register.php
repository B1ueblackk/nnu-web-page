<?php
header('Content-Type: application/json');

// 允许跨域请求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// 加载环境变量
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception('.env file not found at: ' . $path);
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
    $envPath = __DIR__ . '/../.env';
    loadEnv($envPath);
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

// 调试信息
error_log('DB Config: ' . print_r($db_config, true));

// 获取POST数据
$data = json_decode(file_get_contents('php://input'), true);
$name = $data['name'] ?? '';
$email = $data['email'] ?? '';
$title = $data['title'] ?? '';
$password = $data['password'] ?? '';

// 验证输入
if (empty($name) || empty($email) || empty($title) || empty($password)) {
    http_response_code(400);
    echo json_encode(['message' => '所有字段都必须填写']);
    exit;
}

// 验证邮箱格式
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['message' => '邮箱格式不正确']);
    exit;
}

// 验证密码长度
if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['message' => '密码长度至少6位']);
    exit;
}

try {
    // 连接数据库
    $conn = new mysqli($db_config['host'], $db_config['username'], $db_config['password'], $db_config['database']);
    
    if ($conn->connect_error) {
        throw new Exception('数据库连接失败: ' . $conn->connect_error);
    }
    
    // 检查邮箱是否已存在
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        http_response_code(400);
        echo json_encode(['message' => '该邮箱已被注册']);
        exit;
    }
    
    // 加密密码
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // 插入新用户
    $stmt = $conn->prepare('INSERT INTO users (name, email, title, password) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $name, $email, $title, $hashedPassword);
    
    if ($stmt->execute()) {
        echo json_encode([
            'message' => '注册成功',
            'user' => [
                'name' => $name,
                'email' => $email,
                'title' => $title
            ]
        ]);
    } else {
        throw new Exception('用户创建失败');
    }
    
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