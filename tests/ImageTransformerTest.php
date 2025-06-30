<?php

namespace Tourze\BlindWatermark\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tourze\BlindWatermark\ImageProcessor;
use Tourze\BlindWatermark\ImageTransformer;

class ImageTransformerTest extends TestCase
{
    private ImageProcessor $processor;
    private ImageTransformer $transformer;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->processor = $this->createMock(ImageProcessor::class);
        $this->transformer = new ImageTransformer($this->processor, $this->logger);
    }

    public function testFlipInvalidDirection(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('无效的翻转方向，可选值: horizontal, vertical');
        
        $this->transformer->flip('diagonal');
    }

    public function testFlipWithInvalidImageSize(): void
    {
        $this->processor->expects($this->once())
            ->method('getWidth')
            ->willReturn(0);
        
        $this->processor->expects($this->once())
            ->method('getHeight')
            ->willReturn(0);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('无效的图像尺寸');
        
        $this->transformer->flip(ImageTransformer::FLIP_HORIZONTAL);
    }

    public function testFlipHorizontal(): void
    {
        $width = 3;
        $height = 2;
        
        $this->processor->expects($this->once())
            ->method('getWidth')
            ->willReturn($width);
        
        $this->processor->expects($this->once())
            ->method('getHeight')
            ->willReturn($height);

        $channels = [
            'red' => [
                [1, 2, 3],
                [4, 5, 6]
            ],
            'green' => [
                [10, 20, 30],
                [40, 50, 60]
            ],
            'blue' => [
                [100, 200, 250],
                [150, 175, 225]
            ]
        ];

        $expectedChannels = [
            'red' => [
                [3, 2, 1],
                [6, 5, 4]
            ],
            'green' => [
                [30, 20, 10],
                [60, 50, 40]
            ],
            'blue' => [
                [250, 200, 100],
                [225, 175, 150]
            ]
        ];

        $this->processor->expects($this->once())
            ->method('splitChannels')
            ->willReturn($channels);

        $this->processor->expects($this->once())
            ->method('mergeChannels')
            ->with($expectedChannels);

        $result = $this->transformer->flip(ImageTransformer::FLIP_HORIZONTAL);
        $this->assertTrue($result);
    }

    public function testFlipVertical(): void
    {
        $width = 3;
        $height = 2;
        
        $this->processor->expects($this->once())
            ->method('getWidth')
            ->willReturn($width);
        
        $this->processor->expects($this->once())
            ->method('getHeight')
            ->willReturn($height);

        $channels = [
            'red' => [
                [1, 2, 3],
                [4, 5, 6]
            ],
            'green' => [
                [10, 20, 30],
                [40, 50, 60]
            ],
            'blue' => [
                [100, 200, 250],
                [150, 175, 225]
            ]
        ];

        $expectedChannels = [
            'red' => [
                [4, 5, 6],
                [1, 2, 3]
            ],
            'green' => [
                [40, 50, 60],
                [10, 20, 30]
            ],
            'blue' => [
                [150, 175, 225],
                [100, 200, 250]
            ]
        ];

        $this->processor->expects($this->once())
            ->method('splitChannels')
            ->willReturn($channels);

        $this->processor->expects($this->once())
            ->method('mergeChannels')
            ->with($expectedChannels);

        $result = $this->transformer->flip(ImageTransformer::FLIP_VERTICAL);
        $this->assertTrue($result);
    }

    public function testRotateWithInvalidImageSize(): void
    {
        $this->processor->expects($this->once())
            ->method('getWidth')
            ->willReturn(0);
        
        $this->processor->expects($this->once())
            ->method('getHeight')
            ->willReturn(0);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('无效的图像尺寸');
        
        $this->transformer->rotate(90);
    }

    public function testRotateUnsupportedAngle(): void
    {
        $this->processor->expects($this->once())
            ->method('getWidth')
            ->willReturn(100);
        
        $this->processor->expects($this->once())
            ->method('getHeight')
            ->willReturn(100);

        $this->processor->expects($this->once())
            ->method('splitChannels')
            ->willReturn([
                'red' => [[1]],
                'green' => [[1]],
                'blue' => [[1]]
            ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('当前仅支持90度的倍数旋转');
        
        $this->transformer->rotate(45);
    }

    public function testRotate90Degrees(): void
    {
        $this->processor->expects($this->once())
            ->method('getWidth')
            ->willReturn(2);
        
        $this->processor->expects($this->once())
            ->method('getHeight')
            ->willReturn(3);

        $channels = [
            'red' => [
                [1, 2],
                [3, 4],
                [5, 6]
            ],
            'green' => [[1, 2], [3, 4], [5, 6]],
            'blue' => [[1, 2], [3, 4], [5, 6]]
        ];

        $this->processor->expects($this->once())
            ->method('splitChannels')
            ->willReturn($channels);

        $this->processor->expects($this->once())
            ->method('mergeChannels')
            ->with($this->callback(function ($mergedChannels) {
                // 验证90度旋转后的结果
                // 原始 3x2 -> 旋转后 2x3
                return count($mergedChannels['red']) === 2 &&
                       count($mergedChannels['red'][0]) === 3;
            }));

        $result = $this->transformer->rotate(90);
        $this->assertTrue($result);
    }

    public function testRotate180Degrees(): void
    {
        $this->processor->expects($this->once())
            ->method('getWidth')
            ->willReturn(2);
        
        $this->processor->expects($this->once())
            ->method('getHeight')
            ->willReturn(2);

        $channels = [
            'red' => [
                [1, 2],
                [3, 4]
            ],
            'green' => [[1, 2], [3, 4]],
            'blue' => [[1, 2], [3, 4]]
        ];

        $expectedChannels = [
            'red' => [
                [4, 3],
                [2, 1]
            ],
            'green' => [[4, 3], [2, 1]],
            'blue' => [[4, 3], [2, 1]]
        ];

        $this->processor->expects($this->once())
            ->method('splitChannels')
            ->willReturn($channels);

        $this->processor->expects($this->once())
            ->method('mergeChannels')
            ->with($expectedChannels);

        $result = $this->transformer->rotate(180);
        $this->assertTrue($result);
    }

    public function testRotate270Degrees(): void
    {
        $this->processor->expects($this->once())
            ->method('getWidth')
            ->willReturn(2);
        
        $this->processor->expects($this->once())
            ->method('getHeight')
            ->willReturn(3);

        $channels = [
            'red' => [
                [1, 2],
                [3, 4],
                [5, 6]
            ],
            'green' => [[1, 2], [3, 4], [5, 6]],
            'blue' => [[1, 2], [3, 4], [5, 6]]
        ];

        $this->processor->expects($this->once())
            ->method('splitChannels')
            ->willReturn($channels);

        $this->processor->expects($this->once())
            ->method('mergeChannels')
            ->with($this->callback(function ($mergedChannels) {
                // 验证270度旋转后的结果
                // 原始 3x2 -> 旋转后 2x3
                return count($mergedChannels['red']) === 2 &&
                       count($mergedChannels['red'][0]) === 3;
            }));

        $result = $this->transformer->rotate(270);
        $this->assertTrue($result);
    }

    public function testDetectRotationAngleWithInvalidReference(): void
    {
        // 测试一个绝对不存在的文件路径
        $invalidPath = '/tmp/test_invalid_image_' . uniqid() . '.jpg';
        
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('无法加载参考图像'));

        $result = $this->transformer->detectRotationAngle($invalidPath);
        $this->assertNull($result);
    }

    public function testDetectFlipDirectionWithInvalidReference(): void
    {
        // 测试一个绝对不存在的文件路径
        $invalidPath = '/tmp/test_invalid_image_' . uniqid() . '.jpg';
        
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('无法加载参考图像'));

        $result = $this->transformer->detectFlipDirection($invalidPath);
        $this->assertNull($result);
    }

    public function testCalculateSimilarityWithDifferentSizes(): void
    {
        $processor1 = $this->createMock(ImageProcessor::class);
        $processor2 = $this->createMock(ImageProcessor::class);

        $processor1->method('getWidth')->willReturn(100);
        $processor1->method('getHeight')->willReturn(100);
        $processor2->method('getWidth')->willReturn(200);
        $processor2->method('getHeight')->willReturn(200);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('图像尺寸不一致，无法计算准确的相似度');

        $reflection = new \ReflectionClass($this->transformer);
        $method = $reflection->getMethod('calculateSimilarity');
        $method->setAccessible(true);

        $result = $method->invoke($this->transformer, $processor1, $processor2);
        $this->assertEquals(0, $result);
    }
}