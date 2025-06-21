<?php
// Gmail邮件发送功能
// 文件路径: /var/www/jswcs2025.com/call-for-paper/api/email-functions.php

require_once __DIR__ . '/../vendor/autoload.php'; // 如果使用Composer安装PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * 发送密码重置邮件 - Gmail配置
 * @param string $email 收件人邮箱
 * @param string $resetUrl 重置链接
 * @param string $userName 用户姓名（可选）
 * @return bool 发送是否成功
 */
function sendResetEmail($email, $resetUrl, $userName = '') {
    $mail = new PHPMailer(true);

    try {
        // Gmail SMTP配置
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('GMAIL_USERNAME') ?: 'your-email@gmail.com'; // 您的Gmail地址
        $mail->Password = getenv('GMAIL_APP_PASSWORD') ?: 'your-app-password'; // Gmail应用专用密码
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        // 启用详细调试（可选，生产环境建议关闭）
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;

        // 发件人
        $mail->setFrom(
            getenv('GMAIL_USERNAME') ?: 'your-email@gmail.com',
            '2025年无线通信与射频感知联合峰会'
        );

        // 收件人
        $mail->addAddress($email, $userName);
        $mail->addReplyTo(
            getenv('GMAIL_USERNAME') ?: 'your-email@gmail.com',
            '会议技术支持'
        );

        // 邮件内容
        $mail->isHTML(true);
        $mail->Subject = '=?UTF-8?B?' . base64_encode('密码重置 - 2025年无线通信与射频感知联合峰会') . '?=';

        // 生成邮件HTML内容
        $emailContent = generateResetEmailContent($resetUrl, $userName, $email);
        $mail->Body = $emailContent;

        // 纯文本版本（备用）
        $mail->AltBody = generateResetEmailText($resetUrl, $userName);

        // 发送邮件
        $result = $mail->send();

        if ($result) {
            writeLog('Reset email sent successfully to: ' . $email);
            return true;
        } else {
            writeLog('Failed to send reset email to: ' . $email . ' - Error: ' . $mail->ErrorInfo);
            return false;
        }

    } catch (Exception $e) {
        writeLog('Email sending error: ' . $e->getMessage());
        writeLog('PHPMailer ErrorInfo: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * 生成密码重置邮件HTML内容
 */
function generateResetEmailContent($resetUrl, $userName, $email) {
    $userName = $userName ?: $email;
    $expiryTime = '30分钟';
    $currentYear = date('Y');

    return "
    <!DOCTYPE html>
    <html lang='zh-CN'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>密码重置</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                background-color: #f5f5f5;
            }
            .container {
                max-width: 600px;
                margin: 20px auto;
                background: white;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
            }
            .header {
                background: linear-gradient(135deg, #4a90e2, #357abd);
                color: white;
                padding: 30px 20px;
                text-align: center;
            }
            .header h1 {
                margin: 0 0 10px 0;
                font-size: 24px;
            }
            .header h2 {
                margin: 0;
                font-size: 18px;
                font-weight: normal;
                opacity: 0.9;
            }
            .content {
                padding: 40px 30px;
            }
            .button {
                display: inline-block;
                background: #4a90e2;
                color: white;
                padding: 15px 40px;
                text-decoration: none;
                border-radius: 8px;
                margin: 25px 0;
                font-weight: bold;
                transition: background-color 0.3s ease;
            }
            .button:hover {
                background: #357abd;
            }
            .link-box {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 6px;
                margin: 20px 0;
                border-left: 4px solid #4a90e2;
            }
            .link-text {
                word-break: break-all;
                font-family: 'Courier New', monospace;
                font-size: 13px;
                color: #666;
            }
            .warning {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-left: 4px solid #ffc107;
                padding: 20px;
                margin: 25px 0;
                border-radius: 6px;
            }
            .warning h3 {
                margin: 0 0 10px 0;
                color: #856404;
                font-size: 16px;
            }
            .warning ul {
                margin: 10px 0 0 0;
                padding-left: 20px;
            }
            .warning li {
                margin: 5px 0;
                color: #856404;
            }
            .footer {
                background: #f8f9fa;
                text-align: center;
                color: #666;
                font-size: 13px;
                padding: 25px 20px;
                border-top: 1px solid #e9ecef;
            }
            .footer p {
                margin: 8px 0;
            }
            .contact-info {
                background: #e3f2fd;
                padding: 20px;
                margin: 25px 0;
                border-radius: 6px;
                border-left: 4px solid #2196f3;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>2025年无线通信与射频感知联合峰会</h1>
                <h2>密码重置请求</h2>
            </div>

            <div class='content'>
                <p style='font-size: 16px; margin-bottom: 20px;'>
                    亲爱的 <strong style='color: #4a90e2;'>{$userName}</strong>，您好！
                </p>

                <p>我们收到了您的密码重置请求。如果这是您本人的操作，请点击下面的按钮来重置您的密码：</p>

                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$resetUrl}' class='button'>🔑 立即重置密码</a>
                </div>

                <p>如果按钮无法点击，请复制以下链接到浏览器地址栏访问：</p>

                <div class='link-box'>
                    <div class='link-text'>{$resetUrl}</div>
                </div>

                <div class='warning'>
                    <h3>⚠️ 重要安全提示</h3>
                    <ul>
                        <li>此链接在 <strong>{$expiryTime}</strong> 内有效，过期后需重新申请</li>
                        <li>此链接只能使用 <strong>一次</strong>，使用后将自动失效</li>
                        <li>如果您没有申请重置密码，请 <strong>忽略此邮件</strong></li>
                        <li>为保护您的账户安全，请 <strong>不要</strong> 将此链接分享给任何人</li>
                        <li>建议您在重置后设置一个 <strong>强密码</strong>（至少6位，包含字母和数字）</li>
                    </ul>
                </div>

                <div class='contact-info'>
                    <p><strong>📞 需要帮助？</strong></p>
                    <p>如果您在重置密码过程中遇到任何问题，或者有其他疑问，请随时联系我们的技术支持团队。</p>
                </div>

                <p style='margin-top: 30px;'>
                    此致敬礼！<br>
                    <strong>2025年无线通信与射频感知联合峰会</strong><br>
                    <em>技术支持团队</em>
                </p>
            </div>

            <div class='footer'>
                <p><strong>📧 此邮件由系统自动发送，请勿直接回复</strong></p>
                <p>如需技术支持，请发送邮件至：<strong>" . (getenv('GMAIL_USERNAME') ?: 'support@jswcs2025.cn') . "</strong></p>
                <p>会议官网：<a href='https://jswcs2025.cn' style='color: #4a90e2;'>https://jswcs2025.cn</a></p>
                <p>&copy; {$currentYear} 2025年无线通信与射频感知联合峰会 版权所有</p>
            </div>
        </div>
    </body>
    </html>";
}

/**
 * 生成密码重置邮件纯文本内容
 */
function generateResetEmailText($resetUrl, $userName) {
    $currentYear = date('Y');
    $supportEmail = getenv('GMAIL_USERNAME') ?: 'support@jswcs2025.cn';

    return "
2025年无线通信与射频感知联合峰会
密码重置请求

亲爱的 {$userName}，您好！

我们收到了您的密码重置请求。如果这是您本人的操作，请访问以下链接重置您的密码：

{$resetUrl}

重要安全提示：
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
• 此链接在30分钟内有效，过期后需重新申请
• 此链接只能使用一次，使用后将自动失效
• 如果您没有申请重置密码，请忽略此邮件
• 为保护您的账户安全，请不要将此链接分享给任何人
• 建议您在重置后设置一个强密码（至少6位，包含字母和数字）
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

需要帮助？
如果您在重置密码过程中遇到任何问题，请联系我们：
邮箱：{$supportEmail}
官网：https://jswcs2025.cn

此致敬礼！
2025年无线通信与射频感知联合峰会
技术支持团队

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
此邮件由系统自动发送，请勿直接回复。
© {$currentYear} 2025年无线通信与射频感知联合峰会 版权所有
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
";
}

/**
 * 发送注册欢迎邮件
 */
function sendWelcomeEmail($email, $userName) {
    $mail = new PHPMailer(true);

    try {
        // Gmail SMTP配置
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('GMAIL_USERNAME') ?: 'your-email@gmail.com';
        $mail->Password = getenv('GMAIL_APP_PASSWORD') ?: 'your-app-password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            getenv('GMAIL_USERNAME') ?: 'your-email@gmail.com',
            '2025年无线通信与射频感知联合峰会'
        );
        $mail->addAddress($email, $userName);

        $mail->isHTML(true);
        $mail->Subject = '=?UTF-8?B?' . base64_encode('欢迎注册 - 2025年无线通信与射频感知联合峰会') . '?=';

        $mail->Body = "
        <h2>欢迎加入2025年无线通信与射频感知联合峰会！</h2>
        <p>亲爱的 {$userName}，</p>
        <p>感谢您注册参加我们的会议。您的账户已成功创建。</p>
        <p>您现在可以登录系统提交论文和管理您的参会信息。</p>
        <p>如有任何问题，请联系我们的技术支持。</p>
        <br>
        <p>此致，<br>会议组织委员会</p>
        ";

        return $mail->send();

    } catch (Exception $e) {
        writeLog('Welcome email sending error: ' . $e->getMessage());
        return false;
    }
}

/**
 * 测试邮件发送功能
 */
function testEmailSending($testEmail) {
    echo "正在准备测试邮件...\n";

    $testResetUrl = 'https://call-for-paper.jswcs2025.cn/reset-password/index.html?token=test' . time();
    $testUserName = '测试用户';

    echo "发送重置密码测试邮件...\n";
    $result = sendResetEmail($testEmail, $testResetUrl, $testUserName);

    if ($result) {
        echo "✅ 测试邮件发送成功！\n";
        echo "📧 请检查邮箱: {$testEmail}\n";
        echo "📁 如果主收件箱没有，请检查垃圾邮件文件夹\n";
        return true;
    } else {
        echo "❌ 测试邮件发送失败！\n";
        echo "请检查日志文件了解详细错误信息\n";
        return false;
    }
}

/**
 * 简化的日志函数（如果主项目中没有定义）
 */
if (!function_exists('writeLog')) {
    function writeLog($message) {
        $logFile = __DIR__ . '/email.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
        echo "[LOG] $message\n"; // 同时输出到控制台
    }
}
?>