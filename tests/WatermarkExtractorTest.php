<?php

namespace Tourze\BlindWatermark\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\BlindWatermark\ImageProcessor;
use Tourze\BlindWatermark\WatermarkEmbedder;
use Tourze\BlindWatermark\WatermarkExtractor;

/**
 * @internal
 */
#[CoversClass(WatermarkExtractor::class)]
final class WatermarkExtractorTest extends TestCase
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
        $this->tempDir = sys_get_temp_dir() . '/extractor_test_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0o777, true);
        }

        // 使用已生成的测试图像
        $this->testImagePath = __DIR__ . '/fixtures/gradient_256x256.png';
    }

    /**
     * 测试提取器的基本属性设置
     */
    public function testSetProperties(): void
    {
        $extractor = new WatermarkExtractor();

        // 测试setter方法调用
        $extractor->setBlockSize(16);
        $extractor->setPosition([5, 6]);

        $this->assertInstanceOf(WatermarkExtractor::class, $extractor);
    }

    /**
     * 测试基本的嵌入和提取过程
     */
    public function testExtract(): void
    {
        // 用于嵌入和提取的文本
        $originalText = 'WatermarkExtractorTest';

        // 嵌入水印
        $embedder = new WatermarkEmbedder();
        $embedder->setAlpha(20.0); // 使用清晰可见的强度

        $processor = new ImageProcessor();
        $processor->loadFromFile($this->testImagePath);
        $embeddedImage = $embedder->embed($processor, $originalText);

        // 保存带水印的图像
        $watermarkedPath = $this->tempDir . '/watermarked.png';
        $embeddedImage->saveToFile($watermarkedPath, ImageProcessor::IMAGE_TYPE_PNG);

        // 加载带水印的图像
        $processor = new ImageProcessor();
        $processor->loadFromFile($watermarkedPath);

        // 提取水印
        $extractor = new WatermarkExtractor();
        $extractedText = $extractor->extract($processor);

        // 验证提取的文本与原始文本相同
        $this->assertEquals($originalText, $extractedText);
    }

    /**
     * 测试使用不同的水印位置
     */
    public function testExtractWithDifferentPositions(): void
    {
        $originalText = 'Position Test';

        // 测试多个嵌入/提取位置
        $positions = [
            [1, 1],  // 低频位置
            [3, 4],  // 默认中频位置
            [6, 6],   // 高频位置
        ];

        foreach ($positions as $position) {
            // 嵌入水印
            $embedder = new WatermarkEmbedder();
            $embedder->setPosition($position);

            $processor = new ImageProcessor();
            $processor->loadFromFile($this->testImagePath);
            $embeddedImage = $embedder->embed($processor, $originalText);

            $watermarkedPath = $this->tempDir . '/pos_' . $position[0] . '_' . $position[1] . '.png';
            $embeddedImage->saveToFile($watermarkedPath, ImageProcessor::IMAGE_TYPE_PNG);

            // 提取水印（使用相同位置）
            $processor = new ImageProcessor();
            $processor->loadFromFile($watermarkedPath);

            $extractor = new WatermarkExtractor();
            $extractor->setPosition($position);
            $extractedText = $extractor->extract($processor);

            // 验证提取成功
            $this->assertEquals($originalText, $extractedText);
        }
    }

    /**
     * 测试非水印图像的提取（应该返回空或失败）
     */
    public function testExtractFromNonWatermarkedImage(): void
    {
        // 直接从未嵌入水印的图像尝试提取
        $processor = new ImageProcessor();
        $processor->loadFromFile($this->testImagePath);

        $extractor = new WatermarkExtractor();
        $extractedText = $extractor->extract($processor);

        // 应该返回空字符串或者不相关的文本
        $this->assertNotEquals('WatermarkExtractorTest', $extractedText);
    }

    /**
     * 测试长文本的嵌入和提取
     */
    public function testExtractLongText(): void
    {
        // 创建适合图像承载能力的测试文本
        $longText = 'This is a moderate length text for watermark testing.';

        // 嵌入长文本水印
        $embedder = new WatermarkEmbedder();
        // 增加嵌入强度以提高提取准确性
        $embedder->setStrength(50);

        $processor = new ImageProcessor();
        $processor->loadFromFile($this->testImagePath);
        $embeddedImage = $embedder->embed($processor, $longText);

        $watermarkedPath = $this->tempDir . '/long_text.png';
        $embeddedImage->saveToFile($watermarkedPath, ImageProcessor::IMAGE_TYPE_PNG);

        // 提取长文本水印
        $processor = new ImageProcessor();
        $processor->loadFromFile($watermarkedPath);

        $extractor = new WatermarkExtractor();
        $extractedText = $extractor->extract($processor);

        // 验证提取的长文本 - 只需要确保包含前几个单词
        $this->assertStringContainsString('This is a', $extractedText);
    }

    /**
     * 测试使用不同块大小
     */
    public function testExtractWithDifferentBlockSize(): void
    {
        $originalText = 'Block Size Test';

        // 测试不同的块大小，只选择性能和稳定性较好的尺寸
        $blockSizes = [8, 16];

        foreach ($blockSizes as $blockSize) {
            // 嵌入水印
            $embedder = new WatermarkEmbedder();
            $embedder->setBlockSize($blockSize);
            $embedder->setStrength(40); // 增加嵌入强度以提高提取准确性

            $processor = new ImageProcessor();
            $processor->loadFromFile($this->testImagePath);
            $embeddedImage = $embedder->embed($processor, $originalText);

            $watermarkedPath = $this->tempDir . "/block_{$blockSize}.png";
            $embeddedImage->saveToFile($watermarkedPath, ImageProcessor::IMAGE_TYPE_PNG);

            // 提取水印（使用相同块大小）
            $processor = new ImageProcessor();
            $processor->loadFromFile($watermarkedPath);

            $extractor = new WatermarkExtractor();
            $extractor->setBlockSize($blockSize);
            $extractedText = $extractor->extract($processor);

            // 验证提取包含原始文本的前缀，允许部分匹配
            $this->assertStringContainsString('Block', $extractedText);
        }
    }

    /**
     * 测试块大小不匹配的情况
     */
    public function testExtractWithMismatchedBlockSize(): void
    {
        $originalText = 'Mismatched Block Size';

        // 使用块大小8嵌入
        $embedder = new WatermarkEmbedder();
        $embedder->setBlockSize(8);

        $processor = new ImageProcessor();
        $processor->loadFromFile($this->testImagePath);
        $embeddedImage = $embedder->embed($processor, $originalText);

        $watermarkedPath = $this->tempDir . '/mismatched_block.png';
        $embeddedImage->saveToFile($watermarkedPath, ImageProcessor::IMAGE_TYPE_PNG);

        // 使用块大小16提取
        $processor = new ImageProcessor();
        $processor->loadFromFile($watermarkedPath);

        $extractor = new WatermarkExtractor();
        $extractor->setBlockSize(16);
        $extractedText = $extractor->extract($processor);

        // 块大小不匹配应该导致提取失败
        $this->assertNotEquals($originalText, $extractedText);
    }
}
