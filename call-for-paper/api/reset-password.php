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
    $logFile = __DIR__ . '/reset-password.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog('Reset password script started - Method: ' . $_SERVER['REQUEST_METHOD']);

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
$token = $data['token'] ?? '';
$newPassword = $data['newPassword'] ?? '';
$confirmNewPassword = $data['confirmNewPassword'] ?? '';

writeLog('Reset password request with token: ' . substr($token, 0, 8) . '...');

// 验证输入
if (empty($token) || empty($newPassword) || empty($confirmNewPassword)) {
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

if (strlen($token) !== 64) {
    writeLog('Validation failed: invalid token format');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '无效的重置链接'
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

    // 开始事务
    $conn->begin_transaction();

    try {
        // 查询并验证token
        $stmt = $conn->prepare('SELECT email, expires_at, used FROM password_reset_tokens WHERE token = ?');
        if (!$stmt) {
            throw new Exception('准备语句失败: ' . $conn->error);
        }

        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            writeLog('Token not found');
            throw new Exception('无效的重置链接，请重新申请重置密码');
        }

        $tokenData = $result->fetch_assoc();
        $email = $tokenData['email'];
        writeLog('Token found for email: ' . $email);

        // 检查token是否已使用
        if ($tokenData['used']) {
            writeLog('Token already used');
            throw new Exception('重置链接已使用，请重新申请重置密码');
        }

        // 检查token是否已过期
        $now = new DateTime();
        $expiresAt = new DateTime($tokenData['expires_at']);

        if ($now > $expiresAt) {
            writeLog('Token expired');
            throw new Exception('重置链接已过期，请重新申请重置密码');
        }

        writeLog('Token validation successful');

        // 加密新密码
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        writeLog('New password hashed successfully');

        // 更新用户密码
        $updateUserStmt = $conn->prepare('UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE email = ?');
        if (!$updateUserStmt) {
            throw new Exception('准备更新用户语句失败: ' . $conn->error);
        }

        $updateUserStmt->bind_param('ss', $hashedPassword, $email);

        if (!$updateUserStmt->execute()) {
            throw new Exception('更新用户密码失败: ' . $updateUserStmt->error);
        }

        if ($updateUserStmt->affected_rows === 0) {
            throw new Exception('用户不存在或密码未更新');
        }

        writeLog('User password updated successfully');

        // 标记token为已使用
        $updateTokenStmt = $conn->prepare('UPDATE password_reset_tokens SET used = 1 WHERE token = ?');
        if (!$updateTokenStmt) {
            throw new Exception('准备更新token语句失败: ' . $conn->error);
        }

        $updateTokenStmt->bind_param('s', $token);

        if (!$updateTokenStmt->execute()) {
            throw new Exception('更新token状态失败: ' . $updateTokenStmt->error);
        }

        writeLog('Token marked as used');

        // 删除该用户的其他重置token
        $deleteOtherTokensStmt = $conn->prepare('DELETE FROM password_reset_tokens WHERE email = ? AND token != ?');
        if ($deleteOtherTokensStmt) {
            $deleteOtherTokensStmt->bind_param('ss', $email, $token);
            $deleteOtherTokensStmt->execute();
            $deleteOtherTokensStmt->close();
            writeLog('Other tokens for user deleted');
        }

        // 提交事务
        $conn->commit();
        writeLog('Transaction committed successfully');

        echo json_encode([
            'success' => true,
            'message' => '密码重置成功，请使用新密码登录'
        ], JSON_UNESCAPED_UNICODE);

    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        writeLog('Transaction rolled back due to error: ' . $e->getMessage());
        throw $e;
    }

} catch (Exception $e) {
    writeLog('Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($updateUserStmt)) {
        $updateUserStmt->close();
    }
    if (isset($updateTokenStmt)) {
        $updateTokenStmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    writeLog('Reset password script finished');
}
?>