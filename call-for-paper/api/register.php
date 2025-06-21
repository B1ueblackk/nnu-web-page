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

// 添加日志记录
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
        'message' => '仅支持 POST 请求'
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

writeLog('DB Config loaded');

// 获取POST数据
$input = file_get_contents('php://input');
writeLog('Raw input length: ' . strlen($input));

if (empty($input)) {
    writeLog('Empty input received');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '请求体为空'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    writeLog('JSON error: ' . json_last_error_msg());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'JSON 解析错误：' . json_last_error_msg()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取表单数据
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';
$confirmPassword = $data['confirmPassword'] ?? '';
$title = $data['title'] ?? '先生';
$surname = $data['surname'] ?? '';
$givenName = $data['givenName'] ?? '';
$unitName = $data['unitName'] ?? '';
$countryRegion = $data['countryRegion'] ?? '中国';
$address = $data['address'] ?? '';
$phone = $data['phone'] ?? '';
$fax = $data['fax'] ?? '';
$captcha = $data['captcha'] ?? '';

writeLog('Received data - email: ' . $email . ', surname: ' . $surname . ', givenName: ' . $givenName);

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

// 验证必填字段
$required_fields = [
    'email' => $email,
    'password' => $password,
    'confirmPassword' => $confirmPassword,
    'surname' => $surname,
    'givenName' => $givenName,
    'unitName' => $unitName,
    'phone' => $phone
];

$missing_fields = [];
foreach ($required_fields as $field => $value) {
    if (empty($value)) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    writeLog('Validation failed: missing required fields - ' . implode(', ', $missing_fields));
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '请填写所有必填字段',
        'missing_fields' => $missing_fields
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 验证密码
if ($password !== $confirmPassword) {
    writeLog('Validation failed: password mismatch');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '两次输入的密码不一致'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($password) < 4) {
    writeLog('Validation failed: password too short');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '密码长度至少4个字符'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 验证邮箱格式
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    writeLog('Validation failed: invalid email format');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '邮箱格式不正确'
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

    // 检查表结构是否包含新字段
    $columnsResult = $conn->query("SHOW COLUMNS FROM users");
    $columns = [];
    while ($row = $columnsResult->fetch_assoc()) {
        $columns[] = $row['Field'];
    }

    writeLog('Available columns: ' . implode(', ', $columns));

    // 根据表结构动态构建插入语句
    if (in_array('surname', $columns) && in_array('given_name', $columns)) {
        // 新表结构 - 插入到新字段
        $sql = 'INSERT INTO users (email, password, title, surname, given_name, unit_name, country_region, address, phone, fax) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('准备插入语句失败: ' . $conn->error);
        }
        $stmt->bind_param('ssssssssss', $email, $hashedPassword, $title, $surname, $givenName, $unitName, $countryRegion, $address, $phone, $fax);
        writeLog('Using new table structure with separate surname/given_name fields');

        // 同时更新 name 字段以保持兼容性
        if (in_array('name', $columns)) {
            // 先插入基本信息
            if ($stmt->execute()) {
                $userId = $conn->insert_id;
                // 然后更新 name 字段
                $fullName = $surname . $givenName;
                $updateStmt = $conn->prepare('UPDATE users SET name = ? WHERE id = ?');
                $updateStmt->bind_param('si', $fullName, $userId);
                $updateStmt->execute();
                $updateStmt->close();
            }
        } else {
            $stmt->execute();
            $userId = $conn->insert_id;
        }
    } else {
        // 旧表结构 - 合并姓名到现有字段
        $fullName = $surname . $givenName;
        if (in_array('name', $columns)) {
            $nameField = 'name';
        } elseif (in_array('username', $columns)) {
            $nameField = 'username';
        } else {
            throw new Exception('未找到姓名字段（name 或 username）');
        }

        $sql = "INSERT INTO users (email, password, title, {$nameField}) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('准备插入语句失败: ' . $conn->error);
        }
        $stmt->bind_param('ssss', $email, $hashedPassword, $title, $fullName);
        writeLog('Using old table structure with combined name field: ' . $nameField);

        $stmt->execute();
        $userId = $conn->insert_id;
    }

    if ($userId > 0) {
        writeLog('User created successfully: ' . $email . ' (ID: ' . $userId . ')');

        echo json_encode([
            'success' => true,
            'message' => '注册成功',
            'user' => [
                'id' => $userId,
                'email' => $email,
                'name' => $surname . $givenName,
                'title' => $title,
                'unit_name' => $unitName,
                'phone' => $phone
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('用户创建失败：获取用户ID失败');
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
    writeLog('Register script finished');
}
?>