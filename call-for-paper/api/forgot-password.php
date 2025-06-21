<?php
// 首先检查并加载PHPMailer
$phpmailerPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($phpmailerPath)) {
    require_once $phpmailerPath;
}

// 在任何其他代码之前声明use语句
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

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
            $value = trim($value, '"\'');
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// 邮件发送函数 - 使用163邮箱端口465
function sendResetEmail($email, $resetUrl, $userName = '') {
    writeLog('Starting email send process with 163 port 465...');

    // 检查是否有PHPMailer类
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        writeLog('PHPMailer class not available');
        return false;
    }

    try {
        // 检查163邮箱配置
        $email163 = getenv('163_EMAIL');
        $password163 = getenv('163_EMAIL_PASSWORD');

        writeLog('163 Email config - Email: ' . ($email163 ?: 'not set') . ', Password: ' . ($password163 ? 'set' : 'not set'));

        if (!$email163 || !$password163) {
            writeLog('163 email credentials not configured');
            return false;
        }

        $mail = new PHPMailer(true);

        // 163邮箱SMTP配置 - 使用端口465和SSL加密
        $mail->isSMTP();
        $mail->Host = 'smtp.163.com';
        $mail->SMTPAuth = true;
        $mail->Username = $email163;
        $mail->Password = $password163;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // 使用SSL加密
        $mail->Port = 465; // 使用465端口
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 30;

        // 163邮箱特殊设置
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // 启用调试（可选）
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;

        // 邮件设置
        $mail->setFrom($email163, '2025年无线通信与射频感知联合峰会');
        $mail->addAddress($email, $userName);
        $mail->addReplyTo($email163, '会议技术支持');

        $mail->isHTML(true);
        $mail->Subject = '=?UTF-8?B?' . base64_encode('密码重置 - 2025年无线通信与射频感知联合峰会') . '?=';

        // 邮件内容
        $mail->Body = "
        <div style='font-family: Microsoft YaHei, Arial, sans-serif; max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1);'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center;'>
                <h1 style='margin: 0 0 10px 0; font-size: 28px; font-weight: bold;'>🔐 密码重置</h1>
                <h2 style='margin: 0; font-size: 16px; font-weight: normal; opacity: 0.9;'>2025年无线通信与射频感知联合峰会</h2>
            </div>

            <div style='padding: 40px 30px;'>
                <div style='font-size: 18px; margin-bottom: 20px; color: #2c3e50;'>
                    亲爱的 <strong style='color: #667eea;'>{$userName}</strong>，您好！
                </div>

                <p style='line-height: 1.6; color: #555;'>我们收到了您的密码重置请求。为了确保您的账户安全，请点击下面的按钮来重置您的密码：</p>

                <div style='text-align: center; margin: 35px 0;'>
                    <a href='{$resetUrl}' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 18px 40px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold; font-size: 16px; transition: transform 0.2s ease; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);'>
                        🔑 立即重置密码
                    </a>
                </div>

                <p style='color: #666; line-height: 1.6;'>如果按钮无法正常使用，请复制以下链接到浏览器地址栏中访问：</p>

                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #667eea;'>
                    <p style='margin: 0; word-break: break-all; font-family: Consolas, Monaco, monospace; font-size: 13px; color: #555; line-height: 1.4;'>{$resetUrl}</p>
                </div>

                <div style='background: #fff3cd; border: 1px solid #ffeaa7; border-left: 4px solid #f39c12; padding: 25px; border-radius: 8px; margin: 30px 0;'>
                    <div style='color: #856404; font-weight: bold; margin-bottom: 15px; font-size: 16px;'>
                        ⚠️ 重要安全提示
                    </div>
                    <ul style='margin: 0; padding-left: 20px; color: #856404; line-height: 1.6;'>
                        <li style='margin-bottom: 8px;'>此重置链接在 <strong>30分钟</strong> 内有效，过期后需重新申请</li>
                        <li style='margin-bottom: 8px;'>此链接只能使用 <strong>一次</strong>，使用后将自动失效</li>
                        <li style='margin-bottom: 8px;'>如果您没有申请重置密码，请 <strong>立即忽略</strong> 此邮件</li>
                        <li style='margin-bottom: 8px;'>为保护您的账户安全，请 <strong>不要</strong> 将此链接分享给任何人</li>
                        <li>建议您重置后设置一个 <strong>强密码</strong>（至少6位，包含字母和数字）</li>
                    </ul>
                </div>

                <div style='background: #e3f2fd; padding: 20px; margin: 25px 0; border-radius: 8px; border-left: 4px solid #2196f3;'>
                    <p style='margin: 0; color: #1976d2; font-weight: bold; margin-bottom: 10px;'>📞 需要技术支持？</p>
                    <p style='margin: 0; color: #555; line-height: 1.6;'>如果您在重置密码过程中遇到任何问题，或者对我们的会议有其他疑问，请随时联系我们的技术支持团队。我们将竭诚为您服务！</p>
                </div>

                <div style='margin-top: 40px; color: #666; line-height: 1.6;'>
                    <p>此致敬礼！</p>
                    <p style='margin: 10px 0;'><strong style='color: #2c3e50;'>2025年无线通信与射频感知联合峰会</strong></p>
                    <p style='margin: 0;'><em>技术支持团队</em></p>
                </div>
            </div>

            <div style='background: #f8f9fa; text-align: center; padding: 25px 20px; color: #666; font-size: 13px; border-top: 1px solid #e9ecef;'>
                <p style='margin: 0 0 8px 0;'><strong>📧 此邮件由系统自动发送，请勿直接回复</strong></p>
                <p style='margin: 0 0 8px 0;'>如需技术支持，请发送邮件至：<strong>{$email163}</strong></p>
                <p style='margin: 0 0 15px 0;'>会议官网：<a href='https://jswcs2025.cn' style='color: #667eea; text-decoration: none;'>https://jswcs2025.cn</a></p>
                <p style='margin: 0; padding-top: 15px; border-top: 1px solid #ddd;'>&copy; 2025 无线通信与射频感知联合峰会 版权所有</p>
            </div>
        </div>";

        $mail->AltBody = "
密码重置 - 2025年无线通信与射频感知联合峰会

亲爱的 {$userName}，您好！

我们收到了您的密码重置请求。请访问以下链接重置您的密码：

{$resetUrl}

重要安全提示：
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
• 此重置链接在30分钟内有效，过期后需重新申请
• 此链接只能使用一次，使用后将自动失效
• 如果您没有申请重置密码，请立即忽略此邮件
• 为保护您的账户安全，请不要将此链接分享给任何人
• 建议您重置后设置一个强密码（至少6位，包含字母和数字）
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

需要技术支持？
如果您在重置密码过程中遇到任何问题，请联系我们：
邮箱：{$email163}
官网：https://jswcs2025.cn

此致敬礼！
2025年无线通信与射频感知联合峰会
技术支持团队

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
此邮件由系统自动发送，请勿直接回复
© 2025 无线通信与射频感知联合峰会 版权所有
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
";

        writeLog('Attempting to send email via 163 port 465...');
        $result = $mail->send();
        writeLog('Email send result: ' . ($result ? 'success' : 'failed'));

        if (!$result) {
            writeLog('Email send error: ' . $mail->ErrorInfo);
        }

        return $result;

    } catch (PHPMailerException $e) {
        writeLog('PHPMailer exception: ' . $e->getMessage());
        return false;
    } catch (Exception $e) {
        writeLog('General exception in sendResetEmail: ' . $e->getMessage());
        return false;
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
    $stmt = $conn->prepare('SELECT id, name, surname, given_name FROM users WHERE email = ?');
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

    $user = $result->fetch_assoc();
    writeLog('Email found: ' . $email);

    // 获取用户姓名
    $userName = $user['name'] ?: (($user['surname'] ?: '') . ($user['given_name'] ?: ''));
    $userName = trim($userName) ?: $email;

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

        // 生成重置URL
        $resetUrl = 'https://call-for-paper.jswcs2025.cn/reset-password/index.html?token=' . $token;
        writeLog('Reset URL generated: ' . $resetUrl);
        writeLog('Sending email to: ' . $email . ' with userName: ' . $userName);

        // 发送重置邮件
        $emailSent = sendResetEmail($email, $resetUrl, $userName);

        // 返回响应
        if ($emailSent) {
            writeLog('Reset email sent successfully via 163 port 465 to: ' . $email);
            echo json_encode([
                'success' => true,
                'message' => '重置密码链接已发送到您的邮箱，请在30分钟内点击链接重置密码'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            writeLog('Failed to send email, but token was saved');
            // 即使邮件发送失败，也告诉用户成功，避免泄露信息
            echo json_encode([
                'success' => true,
                'message' => '如果该邮箱已注册，重置链接将发送到您的邮箱。请检查邮箱（包括垃圾邮件文件夹）'
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