<?php

namespace Tourze\BlindWatermark\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Tourze\BlindWatermark\Utils\DCT;

class DCTTest extends TestCase
{
    /**
     * 测试DCT正变换和逆变换恢复原始矩阵
     */
    public function testForwardAndInverse(): void
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
     * 测试对空矩阵进行DCT变换
     */
    public function testEmptyMatrix(): void
    {
        $emptyMatrix = [];

        $result = DCT::forward($emptyMatrix);
        $this->assertEmpty($result);

        $result = DCT::inverse($emptyMatrix);
        $this->assertEmpty($result);
    }

    /**
     * 测试分块DCT变换和逆变换
     */
    public function testBlockDCTAndIDCT(): void
    {
        // 创建测试图像数据 (8x8)
        $imageData = [];
        for ($i = 0; $i < 8; $i++) {
            $imageData[$i] = [];
            for ($j = 0; $j < 8; $j++) {
                $imageData[$i][$j] = ($i * 8 + $j) % 256; // 0-255之间的值
            }
        }

        // 分块DCT变换 (块大小为4)
        $blockSize = 4;
        $dctBlocks = DCT::blockDCT($imageData, $blockSize);

        // 逆变换恢复图像
        $recoveredImage = DCT::blockIDCT($dctBlocks, 8, 8, $blockSize);

        // 验证恢复的图像与原始图像近似相等
        for ($i = 0; $i < 8; $i++) {
            for ($j = 0; $j < 8; $j++) {
                $this->assertEqualsWithDelta($imageData[$i][$j], $recoveredImage[$i][$j], 1.0);
            }
        }
    }

    /**
     * 测试非方形矩阵的分块DCT变换
     */
    public function testNonSquareBlockDCT(): void
    {
        // 创建测试图像数据 (10x12)
        $imageData = [];
        for ($i = 0; $i < 10; $i++) {
            $imageData[$i] = [];
            for ($j = 0; $j < 12; $j++) {
                $imageData[$i][$j] = ($i * 12 + $j) % 256;
            }
        }

        // 分块DCT变换
        $blockSize = 8;
        $dctBlocks = DCT::blockDCT($imageData, $blockSize);

        // 逆变换恢复图像
        $recoveredImage = DCT::blockIDCT($dctBlocks, 10, 12, $blockSize);

        // 验证恢复的图像与原始图像近似相等
        for ($i = 0; $i < 10; $i++) {
            for ($j = 0; $j < 12; $j++) {
                $this->assertEqualsWithDelta($imageData[$i][$j], $recoveredImage[$i][$j], 1.0);
            }
        }
    }

    /**
     * 测试空图像数据的分块DCT
     */
    public function testEmptyImageBlockDCT(): void
    {
        $emptyImage = [];

        $result = DCT::blockDCT($emptyImage);
        $this->assertEmpty($result);

        $result = DCT::blockIDCT([], 0, 0);
        $this->assertEmpty($result);
    }

    /**
     * 测试DCT系数的正确性（与期望值比较）
     */
    public function testDCTCoefficients(): void
    {
        // 简单矩阵示例
        $matrix = [
            [1, 1],
            [1, 1]
        ];

        $dctMatrix = DCT::forward($matrix);

        // 第一个系数应该是矩阵平均值的倍数
        $this->assertEqualsWithDelta(2.0, $dctMatrix[0][0], 0.1);

        // 其他系数应该接近0（由于所有值相同）
        $this->assertEqualsWithDelta(0.0, $dctMatrix[0][1], 0.1);
        $this->assertEqualsWithDelta(0.0, $dctMatrix[1][0], 0.1);
        $this->assertEqualsWithDelta(0.0, $dctMatrix[1][1], 0.1);
    }
}
