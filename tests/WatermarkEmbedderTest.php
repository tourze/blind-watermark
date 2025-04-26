<?php

namespace Tourze\BlindWatermark\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\BlindWatermark\ImageProcessor;
use Tourze\BlindWatermark\WatermarkEmbedder;

class WatermarkEmbedderTest extends TestCase
{
    /**
     * 测试临时目录
     */
    protected string $tempDir;

    /**
     * 测试图像路径
     */
    protected string $testImagePath;

    /**
     * 测试前的准备工作
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试临时目录
        $this->tempDir = sys_get_temp_dir() . '/embedder_test_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }

        // 使用已生成的测试图像
        $this->testImagePath = __DIR__ . '/fixtures/gradient_256x256.png';
    }

    /**
     * 测试后的清理工作
     */
    protected function tearDown(): void
    {
        // 删除临时目录
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    /**
     * 测试嵌入器的基本属性设置
     */
    public function testSetProperties(): void
    {
        $embedder = new WatermarkEmbedder();

        // 测试链式调用
        $result = $embedder->setBlockSize(16)
            ->setAlpha(30.0)
            ->setPosition([5, 6])
            ->setKey('test_key');

        $this->assertInstanceOf(WatermarkEmbedder::class, $result);
    }

    /**
     * 测试水印嵌入过程
     */
    public function testEmbed(): void
    {
        $embedder = new WatermarkEmbedder();
        $processor = new ImageProcessor();
        $processor->loadFromFile($this->testImagePath);

        // 嵌入简单文本水印
        $text = 'Test WatermarkEmbedder';
        $embeddedImage = $embedder->embed($processor, $text);

        // 结果应该仍然是一个图像处理器实例
        $this->assertInstanceOf(ImageProcessor::class, $embeddedImage);

        // 维度应该保持不变
        $this->assertEquals($processor->getWidth(), $embeddedImage->getWidth());
        $this->assertEquals($processor->getHeight(), $embeddedImage->getHeight());

        // 保存嵌入水印后的图像
        $outputPath = $this->tempDir . '/embedded.png';
        $embeddedImage->saveToFile($outputPath, ImageProcessor::IMAGE_TYPE_PNG);
        $this->assertFileExists($outputPath);

        // 检查文件尺寸，确保图像未损坏
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    /**
     * 测试使用不同水印强度
     */
    public function testEmbedWithDifferentAlpha(): void
    {
        $text = 'Watermark Test';
        $processor = new ImageProcessor();
        $processor->loadFromFile($this->testImagePath);

        // 使用低强度
        $lowAlphaEmbedder = new WatermarkEmbedder();
        $lowAlphaEmbedder->setAlpha(5.0);
        $lowAlphaImage = $lowAlphaEmbedder->embed($processor, $text);
        $lowAlphaPath = $this->tempDir . '/low_alpha.png';
        $lowAlphaImage->saveToFile($lowAlphaPath, ImageProcessor::IMAGE_TYPE_PNG);

        // 使用高强度
        $highAlphaEmbedder = new WatermarkEmbedder();
        $highAlphaEmbedder->setAlpha(50.0);
        $highAlphaImage = $highAlphaEmbedder->embed($processor, $text);
        $highAlphaPath = $this->tempDir . '/high_alpha.png';
        $highAlphaImage->saveToFile($highAlphaPath, ImageProcessor::IMAGE_TYPE_PNG);

        // 两个文件都应该存在
        $this->assertFileExists($lowAlphaPath);
        $this->assertFileExists($highAlphaPath);
    }

    /**
     * 测试使用不同的嵌入位置
     */
    public function testEmbedWithDifferentPositions(): void
    {
        $text = 'Position Test';
        $processor = new ImageProcessor();
        $processor->loadFromFile($this->testImagePath);

        // 测试多个嵌入位置
        $positions = [
            [1, 1],  // 低频位置
            [3, 4],  // 默认中频位置
            [6, 6]   // 高频位置
        ];

        foreach ($positions as $index => $position) {
            $embedder = new WatermarkEmbedder();
            $embedder->setPosition($position);

            $embeddedImage = $embedder->embed($processor, $text);
            $outputPath = $this->tempDir . "/position_{$index}.png";
            $embeddedImage->saveToFile($outputPath, ImageProcessor::IMAGE_TYPE_PNG);

            $this->assertFileExists($outputPath);
        }
    }

    /**
     * 测试使用密钥嵌入
     */
    public function testEmbedWithKey(): void
    {
        $text = 'Secret Watermark';
        $processor = new ImageProcessor();
        $processor->loadFromFile($this->testImagePath);

        // 使用密钥
        $embedder = new WatermarkEmbedder();
        $embedder->setKey('my_secret_key');

        $embeddedImage = $embedder->embed($processor, $text);
        $outputPath = $this->tempDir . '/keyed_watermark.png';
        $embeddedImage->saveToFile($outputPath, ImageProcessor::IMAGE_TYPE_PNG);

        $this->assertFileExists($outputPath);
    }

    /**
     * 测试嵌入长文本
     */
    public function testEmbedLongText(): void
    {
        // 创建较长的测试文本
        $longText = str_repeat('This is a test of embedding longer watermark text. ', 5);

        $processor = new ImageProcessor();
        $processor->loadFromFile($this->testImagePath);

        $embedder = new WatermarkEmbedder();
        $embeddedImage = $embedder->embed($processor, $longText);

        $outputPath = $this->tempDir . '/long_text.png';
        $embeddedImage->saveToFile($outputPath, ImageProcessor::IMAGE_TYPE_PNG);

        $this->assertFileExists($outputPath);
    }

    /**
     * 测试嵌入空文本
     */
    public function testEmbedEmptyText(): void
    {
        $processor = new ImageProcessor();
        $processor->loadFromFile($this->testImagePath);

        $embedder = new WatermarkEmbedder();
        $embeddedImage = $embedder->embed($processor, '');

        $outputPath = $this->tempDir . '/empty_text.png';
        $embeddedImage->saveToFile($outputPath, ImageProcessor::IMAGE_TYPE_PNG);

        $this->assertFileExists($outputPath);

        // 空文本嵌入不应导致异常，应正常处理
        $this->assertEquals($processor->getWidth(), $embeddedImage->getWidth());
        $this->assertEquals($processor->getHeight(), $embeddedImage->getHeight());
    }

    /**
     * 测试使用无效块大小
     */
    public function testWithInvalidBlockSize(): void
    {
        $text = 'Block Size Test';
        $processor = new ImageProcessor();
        $processor->loadFromFile($this->testImagePath);

        // 测试非标准块大小（不是2的幂）
        $embedder = new WatermarkEmbedder();
        $embedder->setBlockSize(7); // 非2的幂数

        // 应该仍能正常工作，无异常
        $embeddedImage = $embedder->embed($processor, $text);
        $outputPath = $this->tempDir . '/invalid_block_size.png';
        $embeddedImage->saveToFile($outputPath, ImageProcessor::IMAGE_TYPE_PNG);

        $this->assertFileExists($outputPath);
    }
}
