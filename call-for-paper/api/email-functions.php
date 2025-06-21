<?php
// 163邮箱发送功能
// 文件路径: /var/www/jswcs2025.com/call-for-paper/api/email-functions.php

require_once __DIR__ . '/../vendor/autoload.php'; // 如果使用Composer安装PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * 发送密码重置邮件 - 163邮箱配置
 * @param string $email 收件人邮箱
 * @param string $resetUrl 重置链接
 * @param string $userName 用户姓名（可选）
 * @return bool 发送是否成功
 */
function sendResetEmail($email, $resetUrl, $userName = '') {
    $mail = new PHPMailer(true);

    try {
        // 163邮箱 SMTP配置
        $mail->isSMTP();
        $mail->Host = '163.com'; // 或者 smtp.163.com
        $mail->SMTPAuth = true;
        $mail->Username = getenv('163_EMAIL') ?: 'your-email@163.com'; // 您的163邮箱地址
        $mail->Password = getenv('163_EMAIL_PASSWORD') ?: 'your-authorization-code'; // 163邮箱授权码
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // 或者不设置加密
        $mail->Port = 25; // 163邮箱常用端口：25 或 994
        $mail->CharSet = 'UTF-8';

        // 163邮箱特殊设置
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // 超时设置
        $mail->Timeout = 10;

        // 启用详细调试（可选，生产环境建议关闭）
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;

        // 发件人
        $mail->setFrom(
            getenv('163_EMAIL') ?: 'your-email@163.com',
            '2025年无线通信与射频感知联合峰会'
        );

        // 收件人
        $mail->addAddress($email, $userName);
        $mail->addReplyTo(
            getenv('163_EMAIL') ?: 'your-email@163.com',
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
                font-family: 'Microsoft YaHei', 'Helvetica Neue', Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                background-color: #f5f7fa;
            }
            .container {
                max-width: 600px;
                margin: 20px auto;
                background: white;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            }
            .header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 40px 20px;
                text-align: center;
            }
            .header h1 {
                margin: 0 0 10px 0;
                font-size: 24px;
                font-weight: bold;
            }
            .header h2 {
                margin: 0;
                font-size: 16px;
                font-weight: normal;
                opacity: 0.9;
            }
            .content {
                padding: 40px 30px;
            }
            .greeting {
                font-size: 18px;
                margin-bottom: 20px;
                color: #2c3e50;
            }
            .button {
                display: inline-block;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 16px 40px;
                text-decoration: none;
                border-radius: 8px;
                margin: 25px 0;
                font-weight: bold;
                font-size: 16px;
                transition: transform 0.2s ease;
            }
            .button:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            }
            .link-box {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                margin: 25px 0;
                border-left: 4px solid #667eea;
            }
            .link-text {
                word-break: break-all;
                font-family: 'Consolas', 'Monaco', monospace;
                font-size: 13px;
                color: #666;
                background: white;
                padding: 10px;
                border-radius: 4px;
            }
            .warning {
                background: #fff8e1;
                border: 1px solid #ffcc02;
                border-left: 4px solid #ff9800;
                padding: 20px;
                margin: 25px 0;
                border-radius: 8px;
            }
            .warning h3 {
                margin: 0 0 15px 0;
                color: #f57c00;
                font-size: 16px;
                display: flex;
                align-items: center;
            }
            .warning ul {
                margin: 10px 0 0 0;
                padding-left: 20px;
            }
            .warning li {
                margin: 8px 0;
                color: #f57c00;
            }
            .footer {
                background: #f8f9fa;
                text-align: center;
                color: #666;
                font-size: 13px;
                padding: 30px 20px;
                border-top: 1px solid #e9ecef;
            }
            .footer p {
                margin: 8px 0;
            }
            .contact-info {
                background: #e3f2fd;
                padding: 20px;
                margin: 25px 0;
                border-radius: 8px;
                border-left: 4px solid #2196f3;
            }
            .icon {
                font-size: 20px;
                margin-right: 8px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎯 2025年无线通信与射频感知联合峰会</h1>
                <h2>📧 密码重置服务</h2>
            </div>

            <div class='content'>
                <div class='greeting'>
                    <span class='icon'>👋</span>亲爱的 <strong style='color: #667eea;'>{$userName}</strong>，您好！
                </div>

                <p>我们收到了您的密码重置请求。为了确保您的账户安全，请点击下面的按钮来重置您的密码：</p>

                <div style='text-align: center; margin: 35px 0;'>
                    <a href='{$resetUrl}' class='button'>
                        <span class='icon'>🔑</span> 立即重置密码
                    </a>
                </div>

                <p>如果按钮无法正常使用，请复制以下链接到浏览器地址栏中访问：</p>

                <div class='link-box'>
                    <strong>重置链接：</strong>
                    <div class='link-text'>{$resetUrl}</div>
                </div>

                <div class='warning'>
                    <h3><span class='icon'>⚠️</span>重要安全提示</h3>
                    <ul>
                        <li>此重置链接在 <strong>{$expiryTime}</strong> 内有效，请尽快使用</li>
                        <li>此链接只能使用 <strong>一次</strong>，使用后将自动失效</li>
                        <li>如果您没有申请重置密码，请 <strong>立即忽略</strong> 此邮件</li>
                        <li>为保护账户安全，请 <strong>不要</strong> 将此链接转发给他人</li>
                        <li>建议设置包含字母、数字的 <strong>强密码</strong>（至少6位）</li>
                    </ul>
                </div>

                <div class='contact-info'>
                    <p><strong><span class='icon'>📞</span>需要技术支持？</strong></p>
                    <p>如果您在重置密码过程中遇到任何问题，或者对我们的会议有其他疑问，请随时联系我们的技术支持团队。我们将竭诚为您服务！</p>
                </div>

                <p style='margin-top: 35px; color: #666;'>
                    此致敬礼！<br>
                    <strong style='color: #2c3e50;'>2025年无线通信与射频感知联合峰会</strong><br>
                    <em>技术支持团队</em>
                </p>
            </div>

            <div class='footer'>
                <p><strong>📧 此邮件由系统自动发送，请勿直接回复</strong></p>
                <p>如需技术支持，请发送邮件至：<strong>" . (getenv('163_EMAIL') ?: 'support@jswcs2025.cn') . "</strong></p>
                <p>会议官网：<a href='https://jswcs2025.cn' style='color: #667eea; text-decoration: none;'>https://jswcs2025.cn</a></p>
                <p style='margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;'>
                    &copy; {$currentYear} 2025年无线通信与射频感知联合峰会 版权所有
                </p>
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
    $supportEmail = getenv('163_EMAIL') ?: 'support@jswcs2025.cn';

    return "
2025年无线通信与射频感知联合峰会
密码重置服务

亲爱的 {$userName}，您好！

我们收到了您的密码重置请求。为了确保您的账户安全，请访问以下链接重置您的密码：

{$resetUrl}

重要安全提示：
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
• 此重置链接在30分钟内有效，请尽快使用
• 此链接只能使用一次，使用后将自动失效
• 如果您没有申请重置密码，请立即忽略此邮件
• 为保护账户安全，请不要将此链接转发给他人
• 建议设置包含字母、数字的强密码（至少6位）
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

需要技术支持？
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
        // 163邮箱SMTP配置
        $mail->isSMTP();
        $mail->Host = 'smtp.163.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('163_EMAIL') ?: 'your-email@163.com';
        $mail->Password = getenv('163_EMAIL_PASSWORD') ?: 'your-authorization-code';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 25;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 10;

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom(
            getenv('163_EMAIL') ?: 'your-email@163.com',
            '2025年无线通信与射频感知联合峰会'
        );
        $mail->addAddress($email, $userName);

        $mail->isHTML(true);
        $mail->Subject = '=?UTF-8?B?' . base64_encode('欢迎注册 - 2025年无线通信与射频感知联合峰会') . '?=';

        $mail->Body = "
        <div style='font-family: Microsoft YaHei, Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0;'>
                <h1 style='margin: 0;'>🎉 欢迎加入我们！</h1>
                <h2 style='margin: 10px 0 0 0; font-weight: normal; opacity: 0.9;'>2025年无线通信与射频感知联合峰会</h2>
            </div>
            <div style='background: white; padding: 30px; border-radius: 0 0 8px 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                <p style='font-size: 16px;'>亲爱的 <strong style='color: #667eea;'>{$userName}</strong>，</p>
                <p>感谢您注册参加2025年无线通信与射频感知联合峰会！您的账户已成功创建。</p>
                <p>您现在可以：</p>
                <ul style='color: #555;'>
                    <li>登录系统管理您的参会信息</li>
                    <li>提交学术论文</li>
                    <li>查看会议日程安排</li>
                    <li>与其他参会者交流</li>
                </ul>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='https://call-for-paper.jswcs2025.cn/login/' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: bold;'>立即登录</a>
                </div>
                <p>如有任何问题，请随时联系我们的技术支持团队。</p>
                <p style='margin-top: 30px;'>此致敬礼！<br><strong>会议组织委员会</strong></p>
            </div>
        </div>";

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
    echo "正在准备163邮箱测试邮件...\n";

    $testResetUrl = 'https://call-for-paper.jswcs2025.cn/reset-password/index.html?token=test' . time();
    $testUserName = '测试用户';

    echo "发送重置密码测试邮件...\n";
    $result = sendResetEmail($testEmail, $testResetUrl, $testUserName);

    if ($result) {
        echo "✅ 163邮箱测试邮件发送成功！\n";
        echo "📧 请检查邮箱: {$testEmail}\n";
        echo "📁 如果主收件箱没有，请检查垃圾邮件文件夹\n";
        return true;
    } else {
        echo "❌ 163邮箱测试邮件发送失败！\n";
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