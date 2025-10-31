<?php

/**
 * 测试图像生成脚本
 *
 * 此脚本用于生成各种类型的测试图像，以便用于盲水印算法的测试
 * 执行方式: php generate-test-images.php
 */

// 定义图像生成目录
$fixturesDir = __DIR__ . '/fixtures';

// 确保目录存在
if (!is_dir($fixturesDir)) {
    mkdir($fixturesDir, 0o777, true);
}

// 生成渐变测试图像
function generateGradientImage(string $path, int $width, int $height): bool
{
    // 确保宽度和高度至少为1
    $width = max(1, $width);
    $height = max(1, $height);

    $image = imagecreatetruecolor($width, $height);

    // 填充渐变背景
    for ($y = 0; $y < $height; ++$y) {
        for ($x = 0; $x < $width; ++$x) {
            $color = imagecolorallocate(
                $image,
                min(255, max(0, (int) ($x / $width * 255))),
                min(255, max(0, (int) ($y / $height * 255))),
                min(255, max(0, (int) (($x + $y) / ($width + $height) * 255)))
            );
            if (false !== $color) {
                imagesetpixel($image, $x, $y, $color);
            }
        }
    }

    // 保存图像
    $result = imagepng($image, $path);
    imagedestroy($image);

    return $result;
}

// 生成带文本的测试图像
function generateTextImage(string $path, int $width, int $height, string $text): bool
{
    // 确保宽度和高度至少为1
    $width = max(1, $width);
    $height = max(1, $height);

    $image = imagecreatetruecolor($width, $height);

    // 填充白色背景
    $white = imagecolorallocate($image, 255, 255, 255);
    if (false !== $white) {
        imagefill($image, 0, 0, $white);
    }

    // 添加文本
    $textColor = imagecolorallocate($image, 0, 0, 0);
    $fontSize = 5;
    $x = 10;
    $y = $height / 2;

    if (false !== $textColor) {
        imagestring($image, $fontSize, $x, (int) $y, $text, $textColor);
    }

    // 保存图像
    $result = imagepng($image, $path);
    imagedestroy($image);

    return $result;
}

// 生成带噪点的测试图像
function generateNoiseImage(string $path, int $width, int $height, float $noiseLevel = 0.1): bool
{
    // 确保宽度和高度至少为1
    $width = max(1, $width);
    $height = max(1, $height);

    $image = imagecreatetruecolor($width, $height);

    // 填充灰色背景
    $gray = imagecolorallocate($image, 200, 200, 200);
    if (false !== $gray) {
        imagefill($image, 0, 0, $gray);
    }

    // 添加噪点
    $totalPixels = $width * $height;
    $noisePixels = (int) ($totalPixels * $noiseLevel);

    for ($i = 0; $i < $noisePixels; ++$i) {
        $x = mt_rand(0, $width - 1);
        $y = mt_rand(0, $height - 1);
        $color = imagecolorallocate(
            $image,
            mt_rand(0, 255),
            mt_rand(0, 255),
            mt_rand(0, 255)
        );
        if (false !== $color) {
            imagesetpixel($image, $x, $y, $color);
        }
    }

    // 保存图像
    $result = imagepng($image, $path);
    imagedestroy($image);

    return $result;
}

// 生成几种不同的测试图像
$images = [
    [
        'name' => 'gradient_256x256.png',
        'function' => 'generateGradientImage',
        'params' => [256, 256],
    ],
    [
        'name' => 'gradient_512x512.png',
        'function' => 'generateGradientImage',
        'params' => [512, 512],
    ],
    [
        'name' => 'text_image.png',
        'function' => 'generateTextImage',
        'params' => [400, 200, 'Test BlindWatermark'],
    ],
    [
        'name' => 'noise_low.png',
        'function' => 'generateNoiseImage',
        'params' => [256, 256, 0.05],
    ],
    [
        'name' => 'noise_high.png',
        'function' => 'generateNoiseImage',
        'params' => [256, 256, 0.2],
    ],
];

// 执行图像生成
$generatedCount = 0;
foreach ($images as $image) {
    $path = $fixturesDir . '/' . $image['name'];
    $function = $image['function'];
    $params = array_merge([$path], $image['params']);

    $result = call_user_func_array($function, $params);
    if (true === $result) {
        ++$generatedCount;
        echo "Generated {$image['name']}\n";
    } else {
        echo "Failed to generate {$image['name']}\n";
    }
}

echo "\nTotal generated images: {$generatedCount}\n";
echo "Images are saved in: {$fixturesDir}\n";
