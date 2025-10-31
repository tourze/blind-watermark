<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use Tourze\BlindWatermark\BlindWatermark;

// 检查参数
if ($argc < 4) {
    echo "用法: php embed_text.php <原始图像路径> <水印文本> <输出图像路径>\n";
    exit(1);
}

$srcImagePath = $argv[1];
$watermarkText = $argv[2];
$destImagePath = $argv[3];

// 验证源文件存在
if (!file_exists($srcImagePath)) {
    echo "错误: 原始图像文件不存在: {$srcImagePath}\n";
    exit(1);
}

// 创建盲水印实例
$watermark = new BlindWatermark();

// 根据图片格式确定输出类型
$type = pathinfo($destImagePath, PATHINFO_EXTENSION);
$imageType = 'png' === strtolower($type) ? 'png' : 'jpeg';

try {
    // 设置参数并嵌入水印
    $watermark->setAlpha(90.0); // 设置更高的水印强度，确保水印能被提取

    echo "正在嵌入水印文本: \"{$watermarkText}\"...\n";

    // 嵌入水印并保存
    $success = $watermark->embedTextToImage(
        $srcImagePath,
        $watermarkText,
        $destImagePath,
        $imageType
    );

    if ($success) {
        echo "成功: 水印已嵌入并保存到 {$destImagePath}\n";
    } else {
        echo "错误: 水印嵌入或保存失败\n";
        exit(1);
    }
} catch (Exception $e) {
    echo '错误: ' . $e->getMessage() . "\n";
    exit(1);
}
