<?php

namespace Tourze\BlindWatermark\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Tourze\BlindWatermark\Utils\GeometricTransform;

class GeometricTransformTest extends TestCase
{
    public function testDetectHorizontalFlipWithEmptyArray(): void
    {
        $result = GeometricTransform::detectHorizontalFlip([], []);
        $this->assertFalse($result);
    }

    public function testDetectHorizontalFlipWithEmptyWidth(): void
    {
        $original = [[]];
        $transformed = [[]];
        
        $result = GeometricTransform::detectHorizontalFlip($original, $transformed);
        $this->assertFalse($result);
    }

    public function testDetectHorizontalFlip(): void
    {
        // 创建一个更大的图像以确保采样有效
        $size = 20;
        $original = [];
        for ($y = 0; $y < $size; $y++) {
            $original[$y] = [];
            for ($x = 0; $x < $size; $x++) {
                $original[$y][$x] = $y * $size + $x;
            }
        }

        // 创建水平翻转的图像
        $flipped = [];
        for ($y = 0; $y < $size; $y++) {
            $flipped[$y] = [];
            for ($x = 0; $x < $size; $x++) {
                $flipped[$y][$x] = $original[$y][$size - 1 - $x];
            }
        }

        $result = GeometricTransform::detectHorizontalFlip($original, $flipped);
        $this->assertTrue($result);
        
        // 测试非翻转情况
        $result = GeometricTransform::detectHorizontalFlip($original, $original);
        $this->assertFalse($result);
    }

    public function testDetectVerticalFlipWithEmptyArray(): void
    {
        $result = GeometricTransform::detectVerticalFlip([], []);
        $this->assertFalse($result);
    }

    public function testDetectVerticalFlipWithEmptyWidth(): void
    {
        $original = [[]];
        $transformed = [[]];
        
        $result = GeometricTransform::detectVerticalFlip($original, $transformed);
        $this->assertFalse($result);
    }

    public function testDetectVerticalFlip(): void
    {
        // 创建一个更大的图像以确保采样有效
        $size = 20;
        $original = [];
        for ($y = 0; $y < $size; $y++) {
            $original[$y] = [];
            for ($x = 0; $x < $size; $x++) {
                $original[$y][$x] = $y * $size + $x;
            }
        }

        // 创建垂直翻转的图像
        $flipped = [];
        for ($y = 0; $y < $size; $y++) {
            $flipped[$y] = [];
            for ($x = 0; $x < $size; $x++) {
                $flipped[$y][$x] = $original[$size - 1 - $y][$x];
            }
        }

        $result = GeometricTransform::detectVerticalFlip($original, $flipped);
        $this->assertTrue($result);
        
        // 测试非翻转情况
        $result = GeometricTransform::detectVerticalFlip($original, $original);
        $this->assertFalse($result);
    }

    public function testDetectRotationWithEmptyArrays(): void
    {
        $result = GeometricTransform::detectRotation([], []);
        $this->assertEquals(0, $result);
    }

    public function testDetectRotationWithSizeMismatch(): void
    {
        $original = [
            [1, 2, 3],
            [4, 5, 6]
        ];
        
        $transformed = [
            [1, 2, 3, 4],
            [5, 6, 7, 8],
            [9, 10, 11, 12]
        ];
        
        $result = GeometricTransform::detectRotation($original, $transformed);
        $this->assertEquals(0, $result);
    }

    public function testDetectRotation90(): void
    {
        $original = [
            [1, 2],
            [3, 4],
            [5, 6]
        ];
        
        // 90度旋转后
        $rotated = [
            [5, 3, 1],
            [6, 4, 2]
        ];
        
        $result = GeometricTransform::detectRotation($original, $rotated);
        $this->assertEquals(90, $result);
    }

    public function testDetectRotation180(): void
    {
        // 创建一个更大的测试数据以确保采样有效
        $width = 20;
        $height = 15;
        $original = [];
        for ($y = 0; $y < $height; $y++) {
            $original[$y] = [];
            for ($x = 0; $x < $width; $x++) {
                $original[$y][$x] = $y * $width + $x;
            }
        }
        
        // 180度旋转后
        $rotated = [];
        for ($y = 0; $y < $height; $y++) {
            $rotated[$y] = [];
            for ($x = 0; $x < $width; $x++) {
                $rotated[$y][$x] = $original[$height - 1 - $y][$width - 1 - $x];
            }
        }
        
        $result = GeometricTransform::detectRotation($original, $rotated);
        $this->assertEquals(180, $result);
    }

    public function testDetectRotation270(): void
    {
        // 创建一个更大的测试数据以确保采样有效
        $width = 15;
        $height = 20;
        $original = [];
        for ($y = 0; $y < $height; $y++) {
            $original[$y] = [];
            for ($x = 0; $x < $width; $x++) {
                $original[$y][$x] = $y * $width + $x;
            }
        }
        
        // 270度旋转后: 尺寸变为 width x height
        $rotated = [];
        for ($y = 0; $y < $width; $y++) {
            $rotated[$y] = [];
            for ($x = 0; $x < $height; $x++) {
                $rotated[$y][$x] = $original[$x][$width - 1 - $y];
            }
        }
        
        $result = GeometricTransform::detectRotation($original, $rotated);
        $this->assertEquals(270, $result);
    }

    public function testFlipHorizontal(): void
    {
        $channel = [
            [1, 2, 3],
            [4, 5, 6]
        ];
        
        $expected = [
            [3, 2, 1],
            [6, 5, 4]
        ];
        
        $result = GeometricTransform::flipHorizontal($channel);
        $this->assertEquals($expected, $result);
    }

    public function testFlipVertical(): void
    {
        $channel = [
            [1, 2, 3],
            [4, 5, 6]
        ];
        
        $expected = [
            [4, 5, 6],
            [1, 2, 3]
        ];
        
        $result = GeometricTransform::flipVertical($channel);
        $this->assertEquals($expected, $result);
    }

    public function testRotate90(): void
    {
        $channel = [
            [1, 2],
            [3, 4],
            [5, 6]
        ];
        
        $expected = [
            [5, 3, 1],
            [6, 4, 2]
        ];
        
        $result = GeometricTransform::rotate($channel, 90);
        $this->assertEquals($expected, $result);
    }

    public function testRotate180(): void
    {
        $channel = [
            [1, 2, 3],
            [4, 5, 6]
        ];
        
        $expected = [
            [6, 5, 4],
            [3, 2, 1]
        ];
        
        $result = GeometricTransform::rotate($channel, 180);
        $this->assertEquals($expected, $result);
    }

    public function testRotate270(): void
    {
        $channel = [
            [1, 2],
            [3, 4],
            [5, 6]
        ];
        
        $expected = [
            [2, 4, 6],
            [1, 3, 5]
        ];
        
        $result = GeometricTransform::rotate($channel, 270);
        $this->assertEquals($expected, $result);
    }

    public function testRotateWithInvalidAngle(): void
    {
        $channel = [
            [1, 2],
            [3, 4]
        ];
        
        $result = GeometricTransform::rotate($channel, 45);
        $this->assertEquals($channel, $result);
    }

    public function testRotateWithEmptyChannel(): void
    {
        $result = GeometricTransform::rotate([], 90);
        $this->assertEquals([], $result);
        
        $result = GeometricTransform::rotate([[]], 90);
        $this->assertEquals([], $result);
    }

    public function testCorrectGeometricTransform(): void
    {
        $channel = [
            [1, 2, 3],
            [4, 5, 6]
        ];
        
        // 测试仅旋转修正
        $result = GeometricTransform::correctGeometricTransform($channel, false, false, 90);
        // 90度旋转的逆向是270度
        $expected = GeometricTransform::rotate($channel, 270);
        $this->assertEquals($expected, $result);
        
        // 测试仅水平翻转修正
        $result = GeometricTransform::correctGeometricTransform($channel, true, false, 0);
        $expected = GeometricTransform::flipHorizontal($channel);
        $this->assertEquals($expected, $result);
        
        // 测试仅垂直翻转修正
        $result = GeometricTransform::correctGeometricTransform($channel, false, true, 0);
        $expected = GeometricTransform::flipVertical($channel);
        $this->assertEquals($expected, $result);
        
        // 测试组合修正
        $result = GeometricTransform::correctGeometricTransform($channel, true, true, 180);
        // 先逆向旋转180度，再水平翻转，再垂直翻转
        $temp = GeometricTransform::rotate($channel, 180);
        $temp = GeometricTransform::flipHorizontal($temp);
        $expected = GeometricTransform::flipVertical($temp);
        $this->assertEquals($expected, $result);
    }
}