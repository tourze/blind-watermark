<?php

namespace Tourze\BlindWatermark\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\BlindWatermark\ImageProcessor;

class ImageProcessorTest extends TestCase
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
        $this->tempDir = sys_get_temp_dir() . '/image_processor_test_' . uniqid();
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
     * 测试从文件加载图像
     */
    public function testLoadFromFile(): void
    {
        $processor = new ImageProcessor();
        $processor->loadFromFile($this->testImagePath);

        $this->assertGreaterThan(0, $processor->getWidth());
        $this->assertGreaterThan(0, $processor->getHeight());
        $this->assertNotNull($processor->getImage());
    }

    /**
     * 测试加载不存在的文件时抛出异常
     */
    public function testLoadFromNonExistingFile(): void
    {
        $this->expectException(\Exception::class);

        $processor = new ImageProcessor();
        $processor->loadFromFile($this->tempDir . '/non_existing_file.jpg');
    }

    /**
     * 测试创建新图像
     */
    public function testCreateImage(): void
    {
        $processor = new ImageProcessor();
        $processor->createImage(100, 200);

        $this->assertEquals(100, $processor->getWidth());
        $this->assertEquals(200, $processor->getHeight());
        $this->assertNotNull($processor->getImage());
    }

    /**
     * 测试保存图像到文件
     */
    public function testSaveToFile(): void
    {
        $processor = new ImageProcessor();
        $processor->loadFromFile($this->testImagePath);

        // 测试保存为PNG
        $pngPath = $this->tempDir . '/test_save.png';
        $result = $processor->saveToFile($pngPath, ImageProcessor::IMAGE_TYPE_PNG);
        $this->assertTrue($result);
        $this->assertFileExists($pngPath);

        // 测试不同质量参数
        $pngHighQualityPath = $this->tempDir . '/test_high_quality.png';
        $result = $processor->saveToFile($pngHighQualityPath, ImageProcessor::IMAGE_TYPE_PNG, 0);
        $this->assertTrue($result);

        $pngLowQualityPath = $this->tempDir . '/test_low_quality.png';
        $result = $processor->saveToFile($pngLowQualityPath, ImageProcessor::IMAGE_TYPE_PNG, 9);
        $this->assertTrue($result);
    }

    /**
     * 测试通道分离和合并
     */
    public function testSplitAndMergeChannels(): void
    {
        $processor = new ImageProcessor();
        $processor->loadFromFile($this->testImagePath);

        // 分离通道
        $channels = $processor->splitChannels();

        $this->assertArrayHasKey('red', $channels);
        $this->assertArrayHasKey('green', $channels);
        $this->assertArrayHasKey('blue', $channels);

        // 检查通道尺寸
        $this->assertEquals($processor->getHeight(), count($channels['red']));
        $this->assertEquals($processor->getWidth(), count($channels['red'][0]));

        // 合并通道
        $result = $processor->mergeChannels($channels);
        $this->assertInstanceOf(ImageProcessor::class, $result);

        // 保存合并后的图像
        $mergedPath = $this->tempDir . '/merged_channels.png';
        $processor->saveToFile($mergedPath, ImageProcessor::IMAGE_TYPE_PNG);
        $this->assertFileExists($mergedPath);
    }

    /**
     * 测试合并无效通道数据
     */
    public function testMergeInvalidChannels(): void
    {
        $processor = new ImageProcessor();
        $processor->createImage(100, 100);

        // 测试缺少通道
        $invalidChannels = [
            'red' => array_fill(0, 100, array_fill(0, 100, 128)),
            // 缺少green和blue通道
        ];

        // 期望当缺少通道时抛出异常
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("通道数据无效，必须包含red、green和blue三个通道");
        
        $processor->mergeChannels($invalidChannels);
    }

    /**
     * 测试通道归一化（值超出0-255范围）
     */
    public function testChannelNormalization(): void
    {
        $processor = new ImageProcessor();
        $processor->createImage(10, 10);

        // 创建一些值超出0-255范围的通道数据
        $channels = [
            'red' => array_fill(0, 10, array_fill(0, 10, 300)), // 超出上限
            'green' => array_fill(0, 10, array_fill(0, 10, -50)), // 低于下限
            'blue' => array_fill(0, 10, array_fill(0, 10, 150)), // 正常值
        ];

        // 合并通道（内部应进行归一化）
        $processor->mergeChannels($channels);

        // 再次分离通道，检查值是否被归一化
        $normalizedChannels = $processor->splitChannels();

        // 红色通道应该被限制为255
        $this->assertEquals(255, $normalizedChannels['red'][0][0]);

        // 绿色通道应该被限制为0
        $this->assertEquals(0, $normalizedChannels['green'][0][0]);

        // 蓝色通道应该保持不变
        $this->assertEquals(150, $normalizedChannels['blue'][0][0]);
    }

    /**
     * 测试在没有加载图像时尝试获取通道
     */
    public function testSplitChannelsWithoutImage(): void
    {
        $processor = new ImageProcessor();
        // 不加载任何图像

        // 期望在没有图像时抛出异常
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("没有图像可以分割");
        
        $processor->splitChannels();
    }
}
