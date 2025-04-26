# 盲水印 / Blind Watermark

PHP实现的图像盲水印库，支持在图像中嵌入不可见的文本水印，并在不需要原图的情况下提取水印。

## 功能特点

- 基于DCT（离散余弦变换）的频域水印嵌入技术
- 支持文本水印嵌入和提取
- 水印不可见，对图像质量影响小
- 支持常见图像格式（JPEG、PNG）
- 提供简单易用的API

## 安装

通过Composer安装：

```bash
composer require tourze/blind-watermark
```

## 使用方法

### 嵌入水印

```php
use Tourze\BlindWatermark\BlindWatermark;

// 创建盲水印实例
$watermark = new BlindWatermark();

// 可选：设置参数
$watermark->setAlpha(25.0);       // 设置水印强度

// 嵌入水印并保存
$watermark->embedTextToImage(
    'input.jpg',     // 输入图像路径
    '水印文本内容',   // 要嵌入的文本
    'output.jpg',    // 输出图像路径
    'jpeg',          // 输出图像类型（可选，默认为jpeg）
    90               // 图像质量（可选，默认为90）
);
```

### 提取水印

```php
use Tourze\BlindWatermark\BlindWatermark;

// 创建盲水印实例
$watermark = new BlindWatermark();

// 提取水印
$text = $watermark->extractTextFromImage('watermarked.jpg');
echo "提取的水印文本: " . $text;
```

### 高级配置

```php
use Tourze\BlindWatermark\BlindWatermark;

$watermark = new BlindWatermark();

// 设置DCT分块大小（默认为8）
$watermark->setBlockSize(8);

// 设置嵌入位置（DCT系数位置，默认为[3,4]）
$watermark->setPosition([3, 4]);

// 设置水印强度（数值越大水印越强，但可能影响图像质量）
$watermark->setAlpha(20.0);
```

## 命令行使用

包含两个命令行示例脚本：

### 嵌入水印

```bash
php examples/embed_text.php <原始图像路径> <水印文本> <输出图像路径>
```

### 提取水印

```bash
php examples/extract_text.php <带水印图像路径>
```

## 技术原理

该库使用DCT（离散余弦变换）频域嵌入技术实现盲水印：

1. 将原始图像分解为RGB通道
2. 对蓝色通道进行8x8分块DCT变换
3. 在DCT系数的中低频段嵌入水印信息
4. 进行IDCT逆变换重建图像

提取水印时无需原始图像，直接从水印图像中提取嵌入的信息。

## 注意事项

- 图像经过压缩、裁剪等操作后，可能会影响水印的提取效果
- 水印强度参数(alpha)影响水印的鲁棒性和图像质量，需要根据实际情况调整

## 依赖

- PHP 8.1+
- GD扩展

## 许可证

MIT许可证
