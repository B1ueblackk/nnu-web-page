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
    $logFile = __DIR__ . '/login.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog('Login script started - Method: ' . $_SERVER['REQUEST_METHOD']);

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

$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

writeLog('Login attempt for email: ' . $email);

// 验证输入
if (empty($email) || empty($password)) {
    writeLog('Validation failed: empty fields');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '邮箱和密码不能为空'
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

    // 检查表结构，动态适配字段名
    $columnsResult = $conn->query("SHOW COLUMNS FROM users");
    $columns = [];
    while ($row = $columnsResult->fetch_assoc()) {
        $columns[] = $row['Field'];
    }

    writeLog('Available columns: ' . implode(', ', $columns));

    // 根据表结构选择合适的字段
    $selectFields = ['id', 'email', 'password', 'title'];
    $nameField = '';

    if (in_array('surname', $columns) && in_array('given_name', $columns)) {
        // 新表结构
        $selectFields[] = 'surname';
        $selectFields[] = 'given_name';
        $selectFields[] = 'unit_name';
        $selectFields[] = 'country_region';
        $selectFields[] = 'phone';
        $nameField = 'CONCAT(surname, given_name) as user_name';
    } elseif (in_array('name', $columns)) {
        $selectFields[] = 'name as user_name';
        $nameField = 'name as user_name';
    } elseif (in_array('username', $columns)) {
        $selectFields[] = 'username as user_name';
        $nameField = 'username as user_name';
    } else {
        throw new Exception('未找到姓名字段（surname/given_name、name 或 username）');
    }

    // 构建 SQL 查询
    $sql = "SELECT " . implode(', ', $selectFields);
    if ($nameField && !in_array($nameField, $selectFields)) {
        $sql .= ", " . $nameField;
    }
    $sql .= " FROM users WHERE email = ?";

    writeLog('SQL query: ' . $sql);

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('准备语句失败: ' . $conn->error);
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        writeLog('User not found: ' . $email);
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => '用户不存在'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $user = $result->fetch_assoc();
    writeLog('User found: ' . $email);

    // 验证密码
    if (!password_verify($password, $user['password'])) {
        writeLog('Password verification failed for: ' . $email);
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => '密码错误'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    writeLog('Password verified successfully for: ' . $email);

    // 生成会话ID
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];

    // 设置用户名
    if (isset($user['surname']) && isset($user['given_name'])) {
        $userName = $user['surname'] . $user['given_name'];
        $_SESSION['user_name'] = $userName;
        $_SESSION['user_surname'] = $user['surname'];
        $_SESSION['user_given_name'] = $user['given_name'];
    } else {
        $userName = $user['user_name'];
        $_SESSION['user_name'] = $userName;
    }

    $_SESSION['user_title'] = $user['title'];

    writeLog('Session created for user: ' . $email);

    // 准备返回的用户信息
    $userInfo = [
        'id' => $user['id'],
        'email' => $user['email'],
        'name' => $userName,
        'title' => $user['title']
    ];

    // 如果有新字段，添加到返回信息中
    if (isset($user['unit_name'])) {
        $userInfo['unit_name'] = $user['unit_name'];
    }
    if (isset($user['country_region'])) {
        $userInfo['country_region'] = $user['country_region'];
    }
    if (isset($user['phone'])) {
        $userInfo['phone'] = $user['phone'];
    }

    // 返回成功响应
    echo json_encode([
        'success' => true,
        'message' => '登录成功',
        'user' => $userInfo
    ], JSON_UNESCAPED_UNICODE);

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
    writeLog('Login script finished');
}
?>