<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Tourze\BlindWatermark\BlindWatermark;

// 检查参数
if ($argc < 2) {
    echo "用法: php extract_text.php <带水印图像路径> [密钥]\n";
    exit(1);
}

$watermarkedImagePath = $argv[1];
$key = $argc > 2 ? $argv[2] : '';

// 验证源文件存在
if (!file_exists($watermarkedImagePath)) {
    echo "错误: 带水印图像文件不存在: {$watermarkedImagePath}\n";
    exit(1);
}

// 创建盲水印实例
$watermark = new BlindWatermark();

try {
    // 设置密钥（如果有）
    if (!empty($key)) {
        $watermark->setKey($key);
    }
    
    // 提取水印
    $extractedText = $watermark->extractTextFromImage($watermarkedImagePath);
    
    if (!empty($extractedText)) {
        echo "成功提取水印文本: " . $extractedText . "\n";
    } else {
        echo "未能提取到水印，或水印为空\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
}
