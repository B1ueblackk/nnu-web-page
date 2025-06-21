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

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 添加日志记录
function writeLog($message) {
    $logFile = __DIR__ . '/change-password.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog('Change password script started - Method: ' . $_SERVER['REQUEST_METHOD']);

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

// 启动会话并检查是否已登录
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    writeLog('User not logged in');
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => '用户未登录，请先登录'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = $_SESSION['user_id'];
writeLog('Change password request for user ID: ' . $userId);

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
$currentPassword = $data['currentPassword'] ?? '';
$newPassword = $data['newPassword'] ?? '';
$confirmNewPassword = $data['confirmNewPassword'] ?? '';

writeLog('Received change password request');

// 验证输入
if (empty($currentPassword) || empty($newPassword) || empty($confirmNewPassword)) {
    writeLog('Validation failed: empty fields');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '请填写所有字段'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($newPassword !== $confirmNewPassword) {
    writeLog('Validation failed: password mismatch');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '两次输入的新密码不一致'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($newPassword) < 6) {
    writeLog('Validation failed: password too short');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '新密码长度至少6个字符'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($currentPassword === $newPassword) {
    writeLog('Validation failed: same password');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '新密码不能与当前密码相同'
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

    // 获取用户当前密码
    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
    if (!$stmt) {
        throw new Exception('准备语句失败: ' . $conn->error);
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        writeLog('User not found: ' . $userId);
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => '用户不存在'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $user = $result->fetch_assoc();
    writeLog('User found, verifying current password');

    // 验证当前密码
    if (!password_verify($currentPassword, $user['password'])) {
        writeLog('Current password verification failed');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => '当前密码错误'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    writeLog('Current password verified successfully');

    // 加密新密码
    $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    writeLog('New password hashed successfully');

    // 更新密码
    $updateStmt = $conn->prepare('UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    if (!$updateStmt) {
        throw new Exception('准备更新语句失败: ' . $conn->error);
    }

    $updateStmt->bind_param('si', $hashedNewPassword, $userId);

    if ($updateStmt->execute()) {
        writeLog('Password updated successfully for user: ' . $userId);

        echo json_encode([
            'success' => true,
            'message' => '密码更改成功'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        throw new Exception('密码更新失败: ' . $updateStmt->error);
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
    if (isset($updateStmt)) {
        $updateStmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    writeLog('Change password script finished');
}
?>