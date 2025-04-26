<?php

namespace Tourze\BlindWatermark\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\BlindWatermark\BlindWatermark;
use Tourze\BlindWatermark\ImageProcessor;
use Tourze\BlindWatermark\Utils\DCT;

class BlindWatermarkTest extends TestCase
{
    /**
     * 测试临时目录
     */
    protected string $tempDir;

    /**
     * 测试输入图像路径
     */
    protected string $testImagePath;

    /**
     * 测试水印文本
     */
    protected string $watermarkText = 'Hello BlindWatermark!';

    /**
     * 测试输出图像路径
     */
    protected string $outputImagePath;

    /**
     * 测试前的准备工作
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试临时目录
        $this->tempDir = sys_get_temp_dir() . '/blindwatermark_test_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }

        // 使用生成的测试图像
        $this->testImagePath = __DIR__ . '/fixtures/gradient_256x256.png';

        // 设置输出图像路径
        $this->outputImagePath = $this->tempDir . '/watermarked_image.png';
    }

    /**
     * 测试后的清理工作
     */
    protected function tearDown(): void
    {
        // 删除临时文件
        if (file_exists($this->outputImagePath)) {
            unlink($this->outputImagePath);
        }

        // 删除临时目录
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    /**
     * 测试DCT正变换和逆变换
     */
    public function testDCTTransform(): void
    {
        // 创建测试矩阵
        $matrix = [
            [120, 90, 80, 70],
            [100, 110, 120, 130],
            [80, 85, 90, 95],
            [70, 75, 80, 85]
        ];

        // 进行DCT正变换
        $dctMatrix = DCT::forward($matrix);

        // 进行DCT逆变换
        $recoveredMatrix = DCT::inverse($dctMatrix);

        // 验证恢复的矩阵与原始矩阵近似相等（考虑浮点误差）
        for ($i = 0; $i < count($matrix); $i++) {
            for ($j = 0; $j < count($matrix[0]); $j++) {
                $this->assertEqualsWithDelta($matrix[$i][$j], $recoveredMatrix[$i][$j], 0.5);
            }
        }
    }

    /**
     * 测试图像处理类的基本功能
     */
    public function testImageProcessor(): void
    {
        $processor = new ImageProcessor();
        $processor->loadFromFile($this->testImagePath);

        // 测试图像尺寸
        $this->assertGreaterThan(0, $processor->getWidth());
        $this->assertGreaterThan(0, $processor->getHeight());

        // 测试通道分离和合并
        $channels = $processor->splitChannels();
        $this->assertArrayHasKey('red', $channels);
        $this->assertArrayHasKey('green', $channels);
        $this->assertArrayHasKey('blue', $channels);

        $result = $processor->mergeChannels($channels);
        $this->assertInstanceOf(ImageProcessor::class, $result);

        // 测试保存图像
        $savePath = $this->tempDir . '/saved_image.png';
        $this->assertTrue($processor->saveToFile($savePath, ImageProcessor::IMAGE_TYPE_PNG));
        $this->assertFileExists($savePath);

        // 清理
        if (file_exists($savePath)) {
            unlink($savePath);
        }
    }

    /**
     * 测试水印嵌入和提取完整流程
     */
    public function testWatermarkEmbedAndExtract(): void
    {
        $watermark = new BlindWatermark();

        // 嵌入水印
        $result = $watermark->embedTextToImage(
            $this->testImagePath,
            $this->watermarkText,
            $this->outputImagePath,
            ImageProcessor::IMAGE_TYPE_PNG
        );

        $this->assertTrue($result);
        $this->assertFileExists($this->outputImagePath);

        // 使用新实例提取水印
        $extractWatermark = new BlindWatermark();
        $extractedText = $extractWatermark->extractTextFromImage($this->outputImagePath);

        // 验证提取的水印文本
        $this->assertEquals($this->watermarkText, $extractedText);
    }

    /**
     * 测试使用不同图像的水印嵌入和提取
     */
    public function testWatermarkWithDifferentImages(): void
    {
        $watermark = new BlindWatermark();

        $images = [
            __DIR__ . '/fixtures/gradient_256x256.png',
            __DIR__ . '/fixtures/gradient_512x512.png',
            __DIR__ . '/fixtures/text_image.png',
        ];

        foreach ($images as $index => $imagePath) {
            $outputPath = $this->tempDir . "/watermarked_{$index}.png";

            // 嵌入水印
            $result = $watermark->embedTextToImage(
                $imagePath,
                $this->watermarkText,
                $outputPath,
                ImageProcessor::IMAGE_TYPE_PNG
            );

            $this->assertTrue($result);
            $this->assertFileExists($outputPath);

            // 提取水印
            $extractedText = $watermark->extractTextFromImage($outputPath);
            $this->assertEquals($this->watermarkText, $extractedText);

            // 清理
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }
        }
    }
}
