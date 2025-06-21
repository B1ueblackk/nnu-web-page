<?php
header('Content-Type: application/json');

// 允许跨域请求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 添加文件日志
function writeLog($message) {
    $logFile = __DIR__ . '/register.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog('Register script started - Method: ' . $_SERVER['REQUEST_METHOD']);

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    writeLog('Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => '仅支持 POST 请求',
        'method_received' => $_SERVER['REQUEST_METHOD'],
        'debug' => [
            'note' => '这是注册 API，需要通过 POST 方法调用',
            'example' => 'curl -X POST -H "Content-Type: application/json" -d \'{"name":"test","email":"test@example.com","title":"test","password":"123456"}\' ' . $_SERVER['REQUEST_URI']
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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
    writeLog('Loading env from: ' . $envPath);
    loadEnv($envPath);
    writeLog('Env loaded successfully');
} catch (Exception $e) {
    writeLog('Env error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '配置错误：' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 数据库配置
$db_config = [
    'host' => getenv('DB_HOST'),
    'username' => getenv('DB_USER'),
    'password' => getenv('DB_PASS'),
    'database' => getenv('DB_NAME')
];

writeLog('DB Config - host: ' . $db_config['host'] . ', user: ' . $db_config['username'] . ', db: ' . $db_config['database']);

// 获取POST数据
$input = file_get_contents('php://input');
writeLog('Raw input length: ' . strlen($input));
writeLog('Raw input content: ' . $input);

// 检查输入是否为空
if (empty($input)) {
    writeLog('Empty input received');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '请求体为空，请发送 JSON 数据',
        'expected_format' => [
            'name' => 'string',
            'email' => 'string',
            'title' => 'string',
            'password' => 'string'
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    writeLog('JSON error: ' . json_last_error_msg());
    writeLog('Invalid JSON content: ' . $input);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'JSON 解析错误：' . json_last_error_msg(),
        'received_content' => $input
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = $data['name'] ?? '';
$email = $data['email'] ?? '';
$title = $data['title'] ?? '';
$password = $data['password'] ?? '';
$captcha = $data['captcha'] ?? '';

writeLog('Received data - name: ' . $name . ', email: ' . $email . ', title: ' . $title);

// 验证验证码
session_start();
writeLog('Session captcha: ' . ($_SESSION['captcha'] ?? 'not set') . ', User input: ' . $captcha);

if (empty($captcha)) {
    writeLog('Validation failed: empty captcha');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '请输入验证码'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['captcha']) || strtoupper($captcha) !== $_SESSION['captcha']) {
    writeLog('Validation failed: incorrect captcha');
    // 清除验证码，防止重复使用
    unset($_SESSION['captcha']);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '验证码错误，请重新输入'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 验证码正确，清除会话中的验证码
unset($_SESSION['captcha']);
writeLog('Captcha verification passed');

// 验证输入
if (empty($name) || empty($email) || empty($title) || empty($password)) {
    writeLog('Validation failed: empty fields');
    $missing_fields = [];
    if (empty($name)) $missing_fields[] = 'name';
    if (empty($email)) $missing_fields[] = 'email';
    if (empty($title)) $missing_fields[] = 'title';
    if (empty($password)) $missing_fields[] = 'password';

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '所有字段都必须填写',
        'missing_fields' => $missing_fields
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 验证邮箱格式
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    writeLog('Validation failed: invalid email');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '邮箱格式不正确'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 验证密码长度
if (strlen($password) < 6) {
    writeLog('Validation failed: password too short');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '密码长度至少6位'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    writeLog('Connecting to database...');
    // 连接数据库
    $conn = new mysqli($db_config['host'], $db_config['username'], $db_config['password'], $db_config['database']);

    if ($conn->connect_error) {
        throw new Exception('数据库连接失败: ' . $conn->connect_error);
    }
    writeLog('Database connected successfully');

    // 设置字符集
    $conn->set_charset('utf8mb4');

    // 检查邮箱是否已存在
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
    if (!$stmt) {
        throw new Exception('准备语句失败: ' . $conn->error);
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        writeLog('Email already exists: ' . $email);
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => '该邮箱已被注册'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 加密密码
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    writeLog('Password hashed successfully');

    // 插入新用户
    $stmt = $conn->prepare('INSERT INTO users (name, email, title, password) VALUES (?, ?, ?, ?)');
    if (!$stmt) {
        throw new Exception('准备插入语句失败: ' . $conn->error);
    }

    $stmt->bind_param('ssss', $name, $email, $title, $hashedPassword);

    if ($stmt->execute()) {
        writeLog('User created successfully: ' . $email);
        echo json_encode([
            'success' => true,
            'message' => '注册成功',
            'user' => [
                'name' => $name,
                'email' => $email,
                'title' => $title
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('用户创建失败: ' . $stmt->error);
    }

} catch (Exception $e) {
    writeLog('Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '服务器错误：' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    writeLog('Script finished');
}
?>