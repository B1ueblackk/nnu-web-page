<?php
session_start();

// 生成随机验证码
function generateCaptcha($length = 4) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $captcha = '';
    for ($i = 0; $i < $length; $i++) {
        $captcha .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $captcha;
}

// 生成验证码
$captcha_code = generateCaptcha(4);
$_SESSION['captcha'] = $captcha_code;

// 创建图片
$width = 120;
$height = 40;
$image = imagecreate($width, $height);

// 设置颜色
$bg_color = imagecolorallocate($image, 255, 255, 255); // 白色背景
$text_color = imagecolorallocate($image, 0, 0, 0); // 黑色文字
$line_color = imagecolorallocate($image, 128, 128, 128); // 灰色干扰线
$noise_color = imagecolorallocate($image, 200, 200, 200); // 浅灰色噪点

// 填充背景
imagefill($image, 0, 0, $bg_color);

// 添加干扰线
for ($i = 0; $i < 6; $i++) {
    imageline($image, rand(0, $width), rand(0, $height),
              rand(0, $width), rand(0, $height), $line_color);
}

// 添加噪点
for ($i = 0; $i < 100; $i++) {
    imagesetpixel($image, rand(0, $width), rand(0, $height), $noise_color);
}

// 添加验证码文字
$font_size = 5;
$x = ($width - strlen($captcha_code) * imagefontwidth($font_size)) / 2;
$y = ($height - imagefontheight($font_size)) / 2;

// 逐个字符绘制，添加随机偏移
for ($i = 0; $i < strlen($captcha_code); $i++) {
    $char_x = $x + $i * imagefontwidth($font_size) + rand(-2, 2);
    $char_y = $y + rand(-3, 3);
    imagechar($image, $font_size, $char_x, $char_y, $captcha_code[$i], $text_color);
}

// 设置响应头
header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1990 00:00:00 GMT');

// 输出图片
imagepng($image);
imagedestroy($image);
?>