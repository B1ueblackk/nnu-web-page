<?php
// 邮件测试脚本
// 文件路径: /var/www/jswcs2025.com/call-for-paper/api/test-email.php

require_once __DIR__ . '/email-functions.php';

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

try {
    // 加载环境变量
    loadEnv(__DIR__ . '/../.env');

    echo "=== Gmail邮件发送测试 ===\n\n";

    // 检查配置
    $gmailUser = getenv('GMAIL_USERNAME');
    $gmailPass = getenv('GMAIL_APP_PASSWORD');

    echo "Gmail用户名: " . ($gmailUser ?: '未设置') . "\n";
    echo "Gmail密码: " . ($gmailPass ? '已设置 (' . strlen($gmailPass) . ' 位)' : '未设置') . "\n\n";

    if (!$gmailUser || !$gmailPass) {
        echo "❌ 错误：请先在.env文件中配置Gmail用户名和应用专用密码\n";
        exit(1);
    }

    // 获取测试邮箱
    $testEmail = $argv[1] ?? null;
    if (!$testEmail) {
        echo "用法: php test-email.php your-test-email@example.com\n";
        exit(1);
    }

    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        echo "❌ 错误：邮箱格式不正确\n";
        exit(1);
    }

    echo "开始发送测试邮件到: {$testEmail}\n";
    echo "正在发送...\n\n";

    // 发送测试邮件
    $result = testEmailSending($testEmail);

    if ($result) {
        echo "\n✅ 测试成功！请检查您的邮箱（包括垃圾邮件文件夹）\n";
        echo "📧 如果收到邮件，说明配置正确\n";
    } else {
        echo "\n❌ 测试失败！请检查以下配置：\n";
        echo "1. Gmail用户名和应用专用密码是否正确\n";
        echo "2. 是否启用了Gmail两步验证\n";
        echo "3. 网络连接是否正常\n";
        echo "4. 服务器是否允许SMTP连接\n";
    }

} catch (Exception $e) {
    echo "❌ 错误: " . $e->getMessage() . "\n";
    exit(1);
}
?>