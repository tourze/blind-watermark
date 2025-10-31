# 盲水印

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/blind-watermark.svg?style=flat-square)]
(https://packagist.org/packages/tourze/blind-watermark)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/blind-watermark.svg?style=flat-square)]
(https://packagist.org/packages/tourze/blind-watermark)  
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/blind-watermark.svg?style=flat-square)]
(https://packagist.org/packages/tourze/blind-watermark)
[![License](https://img.shields.io/packagist/l/tourze/blind-watermark.svg?style=flat-square)]
(https://packagist.org/packages/tourze/blind-watermark)  
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/monorepo/ci.yml?style=flat-square)]
(https://github.com/tourze/monorepo/actions)
[![Code Coverage](https://img.shields.io/codecov/c/github/tourze/monorepo?style=flat-square)]
(https://codecov.io/gh/tourze/monorepo)

基于 DCT（离散余弦变换）的 PHP 盲水印实现，支持在图像中嵌入不可见的文本水印。

## 目录

- [系统要求](#系统要求)
- [功能特性](#功能特性)
- [安装](#安装)
- [快速开始](#快速开始)
- [高级用法](#高级用法)
- [命令行使用](#命令行使用)
- [配置参数](#配置参数)
- [抗攻击特性](#抗攻击特性)
- [异常处理](#异常处理)
- [技术细节](#技术细节)
- [限制](#限制)
- [性能考虑](#性能考虑)
- [安全性](#安全性)
- [贡献](#贡献)
- [更新日志](#更新日志)
- [许可证](#许可证)

## 系统要求

- PHP 8.1 或更高版本
- GD 扩展

## 功能特性

- **不可见水印**：嵌入人眼无法察觉的文本水印
- **盲提取**：无需原始图像即可提取水印
- **DCT 技术**：使用频域嵌入技术实现稳定的水印放置
- **抗攻击特性**：
  - 对称嵌入抗翻转
  - 多点嵌入增强鲁棒性
  - 几何变换修正
- **图像格式支持**：支持 JPEG 和 PNG 格式
- **简单 API**：易于使用的水印嵌入和提取接口

## 安装

通过 Composer 安装：

```bash
composer require tourze/blind-watermark
```

## 快速开始

### 基本用法

#### 嵌入水印

```php
use Tourze\BlindWatermark\BlindWatermark;

$watermark = new BlindWatermark();
$watermark->embedTextToImage(
    'input.jpg',           // 源图像路径
    '版权所有 2024',        // 要嵌入的文本
    'watermarked.jpg'      // 输出图像路径
);
```

#### 提取水印

```php
use Tourze\BlindWatermark\BlindWatermark;

$watermark = new BlindWatermark();
$text = $watermark->extractTextFromImage('watermarked.jpg');
echo "提取的文本: " . $text;
```

## 高级用法

### 高级配置

```php
use Tourze\BlindWatermark\BlindWatermark;

$watermark = new BlindWatermark();

// 配置水印参数
$watermark->setAlpha(90.0)              // 设置水印强度（默认：36.0）
    ->setBlockSize(8)                   // 设置 DCT 分块大小（默认：8）
    ->setPosition([3, 4])               // 设置在 DCT 系数中的嵌入位置
    ->enableSymmetricEmbedding()        // 启用抗翻转
    ->enableMultiPointEmbedding()       // 启用增强鲁棒性
    ->enableGeometricCorrection();      // 启用几何变换修正

// 嵌入水印
$watermark->embedTextToImage(
    'source.jpg',
    '秘密消息',
    'output.jpg',
    'jpeg',     // 输出格式（可选，默认：jpeg）
    90          // 输出质量（可选，默认：90）
);
```

### 分步 API

```php
use Tourze\BlindWatermark\BlindWatermark;

$watermark = new BlindWatermark();

// 加载图像
$watermark->loadImage('source.jpg');

// 配置并嵌入
$watermark->setAlpha(50.0)
    ->embedText('我的水印');

// 保存结果
$watermark->saveImage('output.jpg', 'jpeg', 95);

// 从保存的图像中提取
$watermark->loadImage('output.jpg');
$extractedText = $watermark->extractText();
```

## 命令行使用

包含命令行脚本，方便进行水印操作：

### 嵌入水印
```bash
php examples/embed_text.php <源图像> <水印文本> <输出图像>
```

### 提取水印
```bash
php examples/extract_text.php <带水印的图像>
```

## 配置参数

| 参数 | 描述 | 默认值 | 范围 |
|------|------|--------|------|
| `alpha` | 水印强度系数 | 36.0 | 0.1 - 100.0 |
| `blockSize` | DCT 分块大小 | 8 | 4, 8, 16 |
| `position` | DCT 矩阵中的嵌入位置 | [3, 4] | [0-7, 0-7] |

## 抗攻击特性

### 对称嵌入
通过对称嵌入水印信息来防止水平和垂直翻转攻击。

```php
$watermark->enableSymmetricEmbedding();
```

### 多点嵌入
通过在多个位置嵌入相同的水印比特来增强鲁棒性。

```php
$watermark->enableMultiPointEmbedding();
```

### 几何修正
在提取前自动检测并修正几何变换。

```php
$watermark->enableGeometricCorrection();
```

## 异常处理

所有错误都以 `BlindWatermarkException` 形式抛出：

```php
use Tourze\BlindWatermark\BlindWatermark;
use Tourze\BlindWatermark\Exception\BlindWatermarkException;

try {
    $watermark = new BlindWatermark();
    $watermark->embedTextToImage('input.jpg', 'watermark', 'output.jpg');
} catch (BlindWatermarkException $e) {
    echo "错误: " . $e->getMessage();
}
```

## 技术细节

该库使用 DCT（离散余弦变换）实现盲水印：

1. **嵌入过程**：
    - 将图像分解为 RGB 通道
    - 对蓝色通道应用 8x8 分块 DCT 变换
    - 在中频 DCT 系数中嵌入水印比特
    - 使用逆 DCT 重建图像

2. **提取过程**：
    - 对带水印的图像执行相同的 DCT 变换
    - 从指定的 DCT 系数中提取嵌入的比特
    - 从二进制数据重建原始文本

## 限制

- 仅支持文本水印（不支持图像水印）
- 水印容量取决于图像大小
- 旋转支持仅限于 90 度的倍数
- 需要 GD 扩展（不支持 ImageMagick）

## 性能考虑

- 较大的 `blockSize` 值会减少处理时间但降低水印容量
- 较高的 `alpha` 值会增加水印鲁棒性但可能影响图像质量
- 启用抗攻击特性会增加处理时间

## 安全性

### 安全考虑

- **水印检测**：虽然水印对人眼不可见，但可能被专业分析工具检测到
- **文本限制**：避免嵌入敏感信息，因为提取算法可能被逆向工程
- **文件完整性**：在水印提取前始终验证图像完整性
- **参数保护**：保持水印参数（alpha、位置）的机密性以增强安全性

### 最佳实践

1. 为每次水印会话使用强且唯一的参数
2. 对敏感水印内容实施额外加密
3. 定期测试水印对常见图像处理操作的鲁棒性
4. 监控潜在的水印移除尝试

## 贡献

我们欢迎贡献！请遵循以下指南：

### 报告问题

- 使用 GitHub issue 跟踪器报告错误
- 提供清晰的重现步骤
- 包含 PHP 版本和系统信息
- 如果适用，请附上示例图像

### 提交 Pull Request

1. Fork 仓库
2. 创建功能分支 (`git checkout -b feature/amazing-feature`)
3. 进行更改
4. 运行测试：`./vendor/bin/phpunit packages/blind-watermark/tests`
5. 运行静态分析：`php -d memory_limit=2G ./vendor/bin/phpstan analyse packages/blind-watermark`
6. 提交更改 (`git commit -m 'Add amazing feature'`)
7. 推送到分支 (`git push origin feature/amazing-feature`)
8. 打开 Pull Request

### 代码风格

- 遵循 PSR-12 编码标准
- 使用 PHP 8.1+ 特性和类型声明
- 为公共方法添加 PHPDoc 注释
- 使用有意义的变量和方法名称

### 测试要求

- 为新功能编写测试
- 确保所有测试通过
- 保持或提高代码覆盖率
- 测试边界情况和错误条件

## 更新日志

### [未发布]
- 初始版本发布
- 基本水印嵌入和提取功能
- 基于 DCT 的实现
- 抗攻击特性（翻转、旋转抗性）
- 命令行界面
- 全面的测试套件

## 许可证

本软件包是基于 MIT 许可证的开源软件。