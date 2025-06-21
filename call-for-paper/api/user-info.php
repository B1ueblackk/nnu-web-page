<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
    $logFile = __DIR__ . '/user-info.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog('User info script started - Method: ' . $_SERVER['REQUEST_METHOD']);

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    writeLog('Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => '仅支持 GET 请求'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 启动会话
session_start();
writeLog('Session started. Session ID: ' . session_id());

// 检查是否已登录
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    writeLog('User not logged in');
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => '用户未登录',
        'redirect' => '../login/index.html'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

writeLog('User logged in. User ID: ' . $_SESSION['user_id']);

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

try {
    // 加载环境变量
    $envPath = __DIR__ . '/../.env';
    writeLog('Loading env from: ' . $envPath);
    loadEnv($envPath);
    writeLog('Env loaded successfully');

    // 数据库配置
    $db_config = [
        'host' => getenv('DB_HOST'),
        'username' => getenv('DB_USER'),
        'password' => getenv('DB_PASS'),
        'database' => getenv('DB_NAME')
    ];

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
    $selectFields = ['id', 'email', 'title', 'created_at'];
    $nameField = '';

    if (in_array('surname', $columns) && in_array('given_name', $columns)) {
        // 新表结构
        $selectFields = array_merge($selectFields, [
            'surname', 'given_name', 'unit_name', 'country_region',
            'address', 'phone', 'fax'
        ]);
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
    $sql .= " FROM users WHERE id = ?";

    writeLog('SQL query: ' . $sql);

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('准备语句失败: ' . $conn->error);
    }

    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        writeLog('User not found in database: ' . $_SESSION['user_id']);

        // 清除无效会话
        session_destroy();

        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => '用户信息不存在，请重新登录',
            'redirect' => '../login/index.html'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $user = $result->fetch_assoc();
    writeLog('User info retrieved successfully');

    // 准备返回的用户信息
    $userInfo = [
        'id' => $user['id'],
        'email' => $user['email'],
        'title' => $user['title'],
        'created_at' => $user['created_at']
    ];

    // 设置用户名
    if (isset($user['surname']) && isset($user['given_name'])) {
        $userInfo['name'] = $user['surname'] . $user['given_name'];
        $userInfo['surname'] = $user['surname'];
        $userInfo['given_name'] = $user['given_name'];

        // 添加新字段
        if (isset($user['unit_name'])) $userInfo['unit_name'] = $user['unit_name'];
        if (isset($user['country_region'])) $userInfo['country_region'] = $user['country_region'];
        if (isset($user['address'])) $userInfo['address'] = $user['address'];
        if (isset($user['phone'])) $userInfo['phone'] = $user['phone'];
        if (isset($user['fax'])) $userInfo['fax'] = $user['fax'];
    } else {
        $userInfo['name'] = $user['user_name'];
    }

    // 返回用户信息
    echo json_encode([
        'success' => true,
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
    writeLog('User info script finished');
}
?>