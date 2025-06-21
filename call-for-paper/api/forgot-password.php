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
    $logFile = __DIR__ . '/forgot-password.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog('Forgot password script started - Method: ' . $_SERVER['REQUEST_METHOD']);

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
$email = $data['email'] ?? '';
$captcha = $data['captcha'] ?? '';

writeLog('Forgot password request for email: ' . $email);

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

// 验证邮箱格式
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    writeLog('Validation failed: invalid email format');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '请输入有效的邮箱地址'
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

    // 检查邮箱是否存在
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
    if (!$stmt) {
        throw new Exception('准备语句失败: ' . $conn->error);
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        writeLog('Email not found: ' . $email);
        // 为了安全，不告诉用户邮箱不存在
        echo json_encode([
            'success' => true,
            'message' => '如果该邮箱已注册，重置链接已发送到您的邮箱'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    writeLog('Email found: ' . $email);

    // 生成重置token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 1800); // 30分钟后过期

    writeLog('Generated reset token: ' . substr($token, 0, 8) . '...');

    // 检查是否已存在重置token表
    $tableExists = $conn->query("SHOW TABLES LIKE 'password_reset_tokens'")->num_rows > 0;

    if (!$tableExists) {
        // 创建密码重置token表
        $createTableSql = "
        CREATE TABLE password_reset_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            used TINYINT(1) DEFAULT 0 COMMENT '是否已使用',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_token (token),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='密码重置token表'
        ";

        if (!$conn->query($createTableSql)) {
            throw new Exception('创建密码重置表失败: ' . $conn->error);
        }
        writeLog('Password reset tokens table created');
    }

    // 删除该邮箱的旧token
    $deleteStmt = $conn->prepare('DELETE FROM password_reset_tokens WHERE email = ? OR expires_at < NOW()');
    if ($deleteStmt) {
        $deleteStmt->bind_param('s', $email);
        $deleteStmt->execute();
        $deleteStmt->close();
        writeLog('Old tokens cleaned up');
    }

    // 插入新的重置token
    $insertStmt = $conn->prepare('INSERT INTO password_reset_tokens (email, token, expires_at) VALUES (?, ?, ?)');
    if (!$insertStmt) {
        throw new Exception('准备插入语句失败: ' . $conn->error);
    }

    $insertStmt->bind_param('sss', $email, $token, $expiresAt);

if ($insertStmt->execute()) {
    writeLog('Reset token saved to database');

    // 获取用户姓名（可选）
    $userStmt = $conn->prepare('SELECT name, surname, given_name FROM users WHERE email = ?');
    $userName = '';
    if ($userStmt) {
        $userStmt->bind_param('s', $email);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        if ($userRow = $userResult->fetch_assoc()) {
            // 优先使用完整姓名，其次是分开的姓名
            $userName = $userRow['name'] ?: (($userRow['surname'] ?: '') . ($userRow['given_name'] ?: ''));
            $userName = trim($userName) ?: $email; // 如果都没有，使用邮箱
        }
        $userStmt->close();
    }

    if (empty($userName)) {
        $userName = $email; // 最后保底使用邮箱作为称呼
    }

    // 生成重置URL
    $resetUrl = 'https://call-for-paper.jswcs2025.cn/reset-password/index.html?token=' . $token;
    writeLog('Reset URL generated: ' . $resetUrl);
    writeLog('Sending email to: ' . $email . ' with userName: ' . $userName);

    // 发送重置邮件
    try {
        $emailSent = sendResetEmail($email, $resetUrl, $userName);

        if ($emailSent) {
            writeLog('Reset email sent successfully via 163 to: ' . $email);
            echo json_encode([
                'success' => true,
                'message' => '重置密码链接已通过163邮箱发送到您的邮箱，请在30分钟内点击链接重置密码'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            writeLog('Failed to send email via 163, but token was saved');
            // 即使邮件发送失败，也不要告诉用户具体错误，避免泄露信息
            echo json_encode([
                'success' => true,
                'message' => '如果该邮箱已注册，重置链接将发送到您的邮箱。请检查邮箱（包括垃圾邮件文件夹）'
            ], JSON_UNESCAPED_UNICODE);
        }
    } catch (Exception $emailError) {
        writeLog('Email sending exception: ' . $emailError->getMessage());
        // 同样不泄露错误信息
        echo json_encode([
            'success' => true,
            'message' => '如果该邮箱已注册，重置链接将发送到您的邮箱'
        ], JSON_UNESCAPED_UNICODE);
    }

} else {
    throw new Exception('保存重置token失败: ' . $insertStmt->error);
}

} catch (Exception $e) {
    writeLog('Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '服务器错误，请稍后重试'
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($insertStmt)) {
        $insertStmt->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
    writeLog('Forgot password script finished');
}
?>