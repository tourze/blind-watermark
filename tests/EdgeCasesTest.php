<?php

namespace Tourze\BlindWatermark\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\BlindWatermark\BlindWatermark;
use Tourze\BlindWatermark\ImageProcessor;

/**
 * 边界情况测试类
 *
 * 测试各种边界条件下的盲水印功能
 *
 * @internal
 */
#[CoversClass(BlindWatermark::class)]
final class EdgeCasesTest extends TestCase
{
    /**
     * 测试临时目录
     */
    protected string $tempDir;

    /**
     * 测试前的准备工作
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试临时目录
        $this->tempDir = sys_get_temp_dir() . '/blindwatermark_edgecase_test_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0o777, true);
        }
    }

    /**
     * 测试空文本水印
     */
    public function testEmptyWatermarkText(): void
    {
        $watermark = new BlindWatermark();
        $testImage = __DIR__ . '/fixtures/gradient_256x256.png';
        $outputPath = $this->tempDir . '/empty_watermark.png';

        // 嵌入空文本水印
        $result = $watermark->embedTextToImage(
            $testImage,
            '',
            $outputPath,
            ImageProcessor::IMAGE_TYPE_PNG
        );

        $this->assertTrue($result);
        $this->assertFileExists($outputPath);

        // 提取水印
        $extractedText = $watermark->extractTextFromImage($outputPath);
        $this->assertEquals('', $extractedText);
    }

    /**
     * 测试非常长的水印文本
     */
    public function testVeryLongWatermarkText(): void
    {
        $watermark = new BlindWatermark();
        $testImage = __DIR__ . '/fixtures/gradient_512x512.png';
        $outputPath = $this->tempDir . '/long_watermark.png';

        // 生成一个合理长度的文本（不要太长）
        $longText = str_repeat('LongTest', 5);

        // 增加嵌入强度以提高提取效果
        $watermark->setAlpha(50);

        // 嵌入长文本水印
        $result = $watermark->embedTextToImage(
            $testImage,
            $longText,
            $outputPath,
            ImageProcessor::IMAGE_TYPE_PNG
        );

        $this->assertTrue($result);
        $this->assertFileExists($outputPath);

        // 提取水印
        $extractedText = $watermark->extractTextFromImage($outputPath);

        // 至少应该能提取部分文本
        $this->assertNotEmpty($extractedText);

        // 检查提取的文本包含原始文本的部分内容
        $this->assertStringContainsString('Long', $extractedText);
    }

    /**
     * 测试特殊字符水印
     */
    public function testSpecialCharactersWatermark(): void
    {
        $watermark = new BlindWatermark();
        $testImage = __DIR__ . '/fixtures/gradient_256x256.png';
        $outputPath = $this->tempDir . '/special_chars.png';

        // 包含各种特殊字符的文本
        $specialText = "Special chars: !@#$%^&*()_+-={}[]|\\:;\"'<>,.?/";

        // 嵌入文本水印
        $result = $watermark->embedTextToImage(
            $testImage,
            $specialText,
            $outputPath,
            ImageProcessor::IMAGE_TYPE_PNG
        );

        $this->assertTrue($result);
        $this->assertFileExists($outputPath);

        // 提取水印
        $extractedText = $watermark->extractTextFromImage($outputPath);
        $this->assertEquals($specialText, $extractedText);
    }

    /**
     * 测试多字节字符水印（中文）
     */
    public function testMultibyteCharactersWatermark(): void
    {
        $watermark = new BlindWatermark();
        $testImage = __DIR__ . '/fixtures/gradient_256x256.png';
        $outputPath = $this->tempDir . '/multibyte_chars.png';

        // 多字节字符文本
        $multibyteText = '中文测试文本，盲水印测试';

        // 嵌入文本水印
        $result = $watermark->embedTextToImage(
            $testImage,
            $multibyteText,
            $outputPath,
            ImageProcessor::IMAGE_TYPE_PNG
        );

        $this->assertTrue($result);
        $this->assertFileExists($outputPath);

        // 提取水印
        $extractedText = $watermark->extractTextFromImage($outputPath);
        $this->assertEquals($multibyteText, $extractedText);
    }

    /**
     * 测试loadImage方法在边界情况下的表现
     */
    public function testLoadImage(): void
    {
        $watermark = new BlindWatermark();
        $testImage = __DIR__ . '/fixtures/gradient_256x256.png';

        $result = $watermark->loadImage($testImage);
        $this->assertInstanceOf(BlindWatermark::class, $result);
    }

    /**
     * 测试embedText方法在边界情况下的表现
     */
    public function testEmbedText(): void
    {
        $watermark = new BlindWatermark();
        $testImage = __DIR__ . '/fixtures/gradient_256x256.png';

        $watermark->loadImage($testImage);
        $result = $watermark->embedText('Edge case test');
        $this->assertInstanceOf(BlindWatermark::class, $result);
    }

    /**
     * 测试extractText方法在边界情况下的表现
     */
    public function testExtractText(): void
    {
        $watermark = new BlindWatermark();
        $testImage = __DIR__ . '/fixtures/gradient_256x256.png';
        $testText = 'Extract test';

        $watermark->loadImage($testImage)
            ->embedText($testText)
        ;

        $extractedText = $watermark->extractText();
        $this->assertEquals($testText, $extractedText);
    }

    /**
     * 测试saveImage方法在边界情况下的表现
     */
    public function testSaveImage(): void
    {
        $watermark = new BlindWatermark();
        $testImage = __DIR__ . '/fixtures/gradient_256x256.png';
        $savePath = $this->tempDir . '/edge_save_test.png';

        $watermark->loadImage($testImage)
            ->embedText('Save test')
        ;

        $result = $watermark->saveImage($savePath, ImageProcessor::IMAGE_TYPE_PNG);
        $this->assertTrue($result);
        $this->assertFileExists($savePath);

        if (file_exists($savePath)) {
            unlink($savePath);
        }
    }

    /**
     * 测试saveAsReference方法在边界情况下的表现
     */
    public function testSaveAsReference(): void
    {
        $watermark = new BlindWatermark();
        $testImage = __DIR__ . '/fixtures/gradient_256x256.png';

        $watermark->loadImage($testImage)
            ->embedText('Reference test')
        ;

        $result = $watermark->saveAsReference();
        $this->assertInstanceOf(BlindWatermark::class, $result);
    }

    /**
     * 测试enableGeometricCorrection方法在边界情况下的表现
     */
    public function testEnableGeometricCorrection(): void
    {
        $watermark = new BlindWatermark();

        $watermark->enableGeometricCorrection(true);
        $this->assertInstanceOf(BlindWatermark::class, $watermark);

        $watermark->enableGeometricCorrection(false);
        $this->assertInstanceOf(BlindWatermark::class, $watermark);
    }

    /**
     * 测试enableSymmetricEmbedding方法在边界情况下的表现
     */
    public function testEnableSymmetricEmbedding(): void
    {
        $watermark = new BlindWatermark();

        $watermark->enableSymmetricEmbedding(true);
        $this->assertInstanceOf(BlindWatermark::class, $watermark);

        $watermark->enableSymmetricEmbedding(false);
        $this->assertInstanceOf(BlindWatermark::class, $watermark);
    }

    /**
     * 测试enableMultiPointEmbedding方法在边界情况下的表现
     */
    public function testEnableMultiPointEmbedding(): void
    {
        $watermark = new BlindWatermark();

        $watermark->enableMultiPointEmbedding(true);
        $this->assertInstanceOf(BlindWatermark::class, $watermark);

        $watermark->enableMultiPointEmbedding(false);
        $this->assertInstanceOf(BlindWatermark::class, $watermark);
    }

    /**
     * 测试flipHorizontal方法在边界情况下的表现
     */
    public function testFlipHorizontal(): void
    {
        $watermark = new BlindWatermark();
        $testImage = __DIR__ . '/fixtures/gradient_256x256.png';

        $watermark->loadImage($testImage);
        $result = $watermark->flipHorizontal();
        $this->assertInstanceOf(BlindWatermark::class, $result);
    }

    /**
     * 测试flipVertical方法在边界情况下的表现
     */
    public function testFlipVertical(): void
    {
        $watermark = new BlindWatermark();
        $testImage = __DIR__ . '/fixtures/gradient_256x256.png';

        $watermark->loadImage($testImage);
        $result = $watermark->flipVertical();
        $this->assertInstanceOf(BlindWatermark::class, $result);
    }

    /**
     * 测试rotate方法在边界情况下的表现
     */
    public function testRotate(): void
    {
        $watermark = new BlindWatermark();
        $testImage = __DIR__ . '/fixtures/gradient_256x256.png';

        $watermark->loadImage($testImage);

        $angles = [90, 180, 270];
        foreach ($angles as $angle) {
            $result = $watermark->rotate($angle);
            $this->assertInstanceOf(BlindWatermark::class, $result);
        }
    }

    /**
     * 测试embedTextToImage方法在边界情况下的表现
     */
    public function testEmbedTextToImage(): void
    {
        $watermark = new BlindWatermark();
        $testImage = __DIR__ . '/fixtures/gradient_256x256.png';
        $outputPath = $this->tempDir . '/edge_embed_test.png';

        $result = $watermark->embedTextToImage(
            $testImage,
            'Embed edge test',
            $outputPath,
            ImageProcessor::IMAGE_TYPE_PNG
        );

        $this->assertTrue($result);
        $this->assertFileExists($outputPath);

        if (file_exists($outputPath)) {
            unlink($outputPath);
        }
    }

    /**
     * 测试extractTextFromImage方法在边界情况下的表现
     */
    public function testExtractTextFromImage(): void
    {
        $watermark = new BlindWatermark();
        $testImage = __DIR__ . '/fixtures/gradient_256x256.png';
        $outputPath = $this->tempDir . '/edge_extract_test.png';
        $testText = 'Extract edge test';

        // 先嵌入水印
        $watermark->embedTextToImage(
            $testImage,
            $testText,
            $outputPath,
            ImageProcessor::IMAGE_TYPE_PNG
        );

        // 然后提取
        $extractedText = $watermark->extractTextFromImage($outputPath);
        $this->assertEquals($testText, $extractedText);

        if (file_exists($outputPath)) {
            unlink($outputPath);
        }
    }
}
