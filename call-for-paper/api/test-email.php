<?php
// 163邮箱测试脚本
// 文件路径: /var/www/jswcs2025.com/call-for-paper/api/test-163-email.php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// 加载环境变量
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception('.env file not found');
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value, '"\''));
        }
    }
}

try {
    loadEnv(__DIR__ . '/../.env');

    echo "=== 163邮箱连接测试 ===\n\n";

    // 1. 测试网络连接
    echo "1. 测试163邮箱SMTP服务器连接...\n";
    $hosts = [
        'smtp.163.com:25' => ['smtp.163.com', 25],
        'smtp.163.com:465' => ['smtp.163.com', 465],
        'smtp.163.com:994' => ['smtp.163.com', 994]
    ];

    $connected = false;
    $workingConfig = null;

    foreach ($hosts as $label => $config) {
        list($host, $port) = $config;
        echo "  测试 {$label}... ";

        $connection = @fsockopen($host, $port, $errno, $errstr, 5);
        if ($connection) {
            echo "✅ 连接成功\n";
            fclose($connection);
            $connected = true;
            $workingConfig = $config;
            break;
        } else {
            echo "❌ 连接失败 ($errstr)\n";
        }
    }

    if (!$connected) {
        echo "\n❌ 所有163邮箱SMTP端口都无法连接，请检查网络设置\n";
        exit(1);
    }

    // 2. 检查配置
    echo "\n2. 检查163邮箱配置...\n";
    $email163 = getenv('163_EMAIL');
    $password163 = getenv('163_EMAIL_PASSWORD');

    echo "  163邮箱账户: " . ($email163 ?: '❌ 未设置') . "\n";
    echo "  163邮箱密码: " . ($password163 ? '✅ 已设置 (' . strlen($password163) . ' 位)' : '❌ 未设置') . "\n";

    if (!$email163 || !$password163) {
        echo "\n❌ 错误：请先在.env文件中配置163邮箱用户名和授权码\n";
        echo "\n配置示例：\n";
        echo "163_EMAIL=your-email@163.com\n";
        echo "163_EMAIL_PASSWORD=your-authorization-code\n";
        exit(1);
    }

    // 3. 获取测试邮箱
    $testEmail = $argv[1] ?? null;
    if (!$testEmail) {
        echo "\n用法: php test-163-email.php your-test-email@example.com\n";
        exit(1);
    }

    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        echo "\n❌ 错误：邮箱格式不正确\n";
        exit(1);
    }

    echo "\n3. 发送测试邮件到: {$testEmail}\n";
    echo "  使用配置: smtp.163.com:{$workingConfig[1]}\n";
    echo "  正在发送...\n\n";

    $mail = new PHPMailer(true);

    // 163邮箱SMTP配置
    $mail->isSMTP();
    $mail->Host = $workingConfig[0];
    $mail->SMTPAuth = true;
    $mail->Username = $email163;
    $mail->Password = $password163;

    // 根据端口设置加密方式
    if ($workingConfig[1] == 465) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($workingConfig[1] == 994) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        // 端口25，通常不需要加密或使用STARTTLS
        $mail->SMTPSecure = false; // 或者 PHPMailer::ENCRYPTION_STARTTLS
    }

    $mail->Port = $workingConfig[1];
    $mail->CharSet = 'UTF-8';
    $mail->Timeout = 15;

    // 163邮箱特殊设置
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    // 启用调试（显示SMTP交互过程）
    $mail->SMTPDebug = SMTP::DEBUG_CONNECTION;

    // 邮件设置
    $mail->setFrom($email163, '2025年无线通信与射频感知联合峰会');
    $mail->addAddress($testEmail, '测试用户');
    $mail->addReplyTo($email163, '会议技术支持');

    $mail->isHTML(true);
    $mail->Subject = '=?UTF-8?B?' . base64_encode('163邮箱测试 - 2025年无线通信与射频感知联合峰会') . '?=';

    $testTime = date('Y-m-d H:i:s');
    $testResetUrl = 'https://call-for-paper.jswcs2025.cn/reset-password/index.html?token=test' . time();

    $mail->Body = "
    <!DOCTYPE html>
    <html lang='zh-CN'>
    <head>
        <meta charset='UTF-8'>
        <title>163邮箱测试</title>
        <style>
            body { font-family: 'Microsoft YaHei', Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
            .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .info-box { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #667eea; }
            .success { color: #28a745; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎯 163邮箱测试成功！</h1>
                <h2>2025年无线通信与射频感知联合峰会</h2>
            </div>
            <div class='content'>
                <p class='success'>✅ 如果您收到这封邮件，说明163邮箱SMTP配置正确！</p>

                <div class='info-box'>
                    <h3>📊 测试信息</h3>
                    <ul>
                        <li><strong>发送时间：</strong>{$testTime}</li>
                        <li><strong>发送邮箱：</strong>{$email163}</li>
                        <li><strong>接收邮箱：</strong>{$testEmail}</li>
                        <li><strong>SMTP服务器：</strong>{$workingConfig[0]}:{$workingConfig[1]}</li>
                        <li><strong>测试状态：</strong><span class='success'>成功</span></li>
                    </ul>
                </div>

                <p>现在您可以正常使用以下功能：</p>
                <ul>
                    <li>✅ 密码重置邮件发送</li>
                    <li>✅ 注册确认邮件发送</li>
                    <li>✅ 系统通知邮件发送</li>
                </ul>

                <div style='text-align: center;'>
                    <a href='{$testResetUrl}' class='button'>测试重置链接（仅供测试）</a>
                </div>

                <p style='color: #666; font-size: 14px;'><em>这是一封测试邮件，重置链接仅供测试使用，请勿点击。</em></p>

                <div class='info-box'>
                    <h3>🔧 技术信息</h3>
                    <p>此邮件通过163邮箱SMTP服务发送，证明服务器与163邮箱服务器连接正常。</p>
                </div>
            </div>
        </div>
    </body>
    </html>";

    $mail->AltBody = "
163邮箱测试成功！

如果您收到这封邮件，说明163邮箱SMTP配置正确。

测试信息：
- 发送时间：{$testTime}
- 发送邮箱：{$email163}
- 接收邮箱：{$testEmail}
- SMTP服务器：{$workingConfig[0]}:{$workingConfig[1]}
- 测试状态：成功

现在您可以正常使用密码重置、注册确认等邮件功能。

测试链接：{$testResetUrl}
（这是一封测试邮件，请勿点击重置链接）

---
2025年无线通信与射频感知联合峰会
技术支持团队
";

    // 发送邮件
    $result = $mail->send();

    if ($result) {
        echo "\n✅ 163邮箱测试邮件发送成功！\n";
        echo "📧 请检查邮箱: {$testEmail}\n";
        echo "📁 如果主收件箱没有，请检查垃圾邮件文件夹\n";
        echo "🔗 邮件中包含测试重置链接\n";
        echo "⚙️  使用的SMTP配置: {$workingConfig[0]}:{$workingConfig[1]}\n";
    } else {
        echo "\n❌ 测试失败！\n";
        echo "错误信息: " . $mail->ErrorInfo . "\n";
    }

} catch (Exception $e) {
    echo "\n❌ 发送失败: " . $e->getMessage() . "\n";
    echo "\n📋 常见解决方案：\n";
    echo "1. 检查163邮箱用户名和授权码是否正确\n";
    echo "2. 确认已在163邮箱中开启SMTP服务\n";
    echo "3. 检查网络连接是否正常\n";
    echo "4. 尝试使用不同的SMTP端口(25/465/994)\n";
    echo "5. 检查服务器防火墙设置\n";

    echo "\n🔧 如何获取163邮箱授权码：\n";
    echo "1. 登录163邮箱网页版\n";
    echo "2. 点击'设置' -> '邮箱设置'\n";
    echo "3. 选择'客户端授权密码'\n";
    echo "4. 开启'SMTP服务'并设置授权码\n";
}
?>