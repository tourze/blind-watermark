# 盲水印使用示例

本目录包含使用盲水印库的示例代码。

## 示例文件

- `embed_text.php`: 将文本水印嵌入到图像中
- `extract_text.php`: 从带水印的图像中提取文本水印

## 使用方法

### 嵌入水印

```bash
php embed_text.php <原始图像路径> <水印文本> <输出图像路径>
```

示例：
```bash
php embed_text.php ../tests/fixtures/gradient_256x256.png "测试水印" ./watermarked.png
```

### 提取水印

```bash
php extract_text.php <带水印图像路径>
```

示例：
```bash
php extract_text.php ./watermarked.png
```

## 注意事项

1. 水印嵌入后的图像在视觉上几乎无法分辨与原始图像的差异
2. 水印强度设置为90，可以在保持图像质量的同时确保水印能够被提取
3. 目前版本仅支持JPEG和PNG格式的图像
4. 水印提取可能会受图像压缩、裁剪等操作的影响
5. 加密解密功能还在完善中，目前建议不使用密钥加密 
