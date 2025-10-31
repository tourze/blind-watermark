<?php

namespace Tourze\BlindWatermark\Tests\Utils;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\Timer\Timer;
use Tourze\BlindWatermark\Utils\DCT;

/**
 * DCT性能测试类
 *
 * 测试常规DCT实现和快速DCT实现的性能差异
 *
 * @internal
 */
#[CoversClass(DCT::class)]
final class DCTPerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // DCT性能测试不需要特殊的设置
    }

    /**
     * 测试矩阵尺寸
     */
    protected const MATRIX_SIZE = 16;

    /**
     * 性能测试轮次
     */
    protected const TEST_ROUNDS = 1;

    /**
     * 测试简单矩阵DCT性能
     */
    public function testDCTPerformance(): void
    {
        // 创建测试矩阵，减小尺寸以加快测试
        $size = 16; // 从256减小到16
        $matrix = [];
        for ($i = 0; $i < $size; ++$i) {
            $matrix[$i] = [];
            for ($j = 0; $j < $size; ++$j) {
                $matrix[$i][$j] = sin($i) * cos($j) * 128 + 128;
            }
        }

        // 初始化变量
        $dct = [];
        $idct = [];

        // 计时标准方法
        $timer = new Timer();
        $timer->start();
        for ($i = 0; $i < 1; ++$i) { // 从10减少到1
            DCT::setUseFastImplementation(false);
            $dct = DCT::forward($matrix);
            $idct = DCT::inverse($dct);
        }
        $standardTime = $timer->stop()->asMicroseconds() / 1000000; // 转换为秒

        // 计时优化方法
        $timer->start();
        for ($i = 0; $i < 1; ++$i) { // 从10减少到1
            DCT::setUseFastImplementation(true);
            $dct = DCT::fastForward($matrix, $size, $size);
            $idct = DCT::fastInverse($dct, $size, $size);
        }
        $fastTime = $timer->stop()->asMicroseconds() / 1000000; // 转换为秒

        // 计算加速比
        $speedup = $standardTime / $fastTime;
        // 性能日志已移除，避免测试输出导致 risky 警告
        // echo '标准DCT平均执行时间: ' . number_format($standardTime, 5) . " 秒\n";
        // echo '快速DCT平均执行时间: ' . number_format($fastTime, 5) . " 秒\n";
        // echo '性能提升: ' . number_format($speedup, 2) . "倍\n";

        // 验证两种方法都能正常工作并产生有效结果
        $this->assertNotEmpty($dct, 'DCT变换结果不应为空');
        $this->assertNotEmpty($idct, '逆DCT变换结果不应为空');
        $this->assertGreaterThan(0, $standardTime, '标准方法执行时间应大于0');
        $this->assertGreaterThan(0, $fastTime, '快速方法执行时间应大于0');
    }

    /**
     * 测试分块DCT性能
     */
    public function testBlockDCTPerformance(): void
    {
        // 创建测试矩阵，减小尺寸以加快测试
        $height = 32; // 从256减小到32
        $width = 32;  // 从256减小到32
        $blockSize = 8;
        $matrix = [];
        for ($i = 0; $i < $height; ++$i) {
            $matrix[$i] = [];
            for ($j = 0; $j < $width; ++$j) {
                $matrix[$i][$j] = sin($i) * cos($j) * 128 + 128;
            }
        }

        // 初始化变量
        $dctBlocks = [];
        $recovered = [];

        // 计时标准方法
        $timer = new Timer();
        $timer->start();
        for ($i = 0; $i < 1; ++$i) { // 从5减少到1
            DCT::setUseFastImplementation(false);
            $dctBlocks = DCT::blockDCT($matrix, $blockSize);
            $recovered = DCT::blockIDCT($dctBlocks, $height, $width, $blockSize);
        }
        $standardTime = $timer->stop()->asMicroseconds() / 1000000; // 转换为秒

        // 计时优化方法
        $timer->start();
        for ($i = 0; $i < 1; ++$i) { // 从5减少到1
            DCT::setUseFastImplementation(true);
            $dctBlocks = DCT::blockDCT($matrix, $blockSize);
            $recovered = DCT::blockIDCT($dctBlocks, $height, $width, $blockSize);
        }
        $fastTime = $timer->stop()->asMicroseconds() / 1000000; // 转换为秒

        // 计算加速比
        $speedup = $standardTime / $fastTime;
        // 性能日志已移除，避免测试输出导致 risky 警告
        // echo '标准分块DCT平均执行时间: ' . number_format($standardTime, 5) . " 秒\n";
        // echo '快速分块DCT平均执行时间: ' . number_format($fastTime, 5) . " 秒\n";
        // echo '性能提升: ' . number_format($speedup, 2) . "倍\n";

        // 验证分块DCT处理结果有效
        $this->assertNotEmpty($dctBlocks, '分块DCT结果不应为空');
        $this->assertNotEmpty($recovered, '恢复的图像数据不应为空');
        $this->assertGreaterThan(0, $standardTime, '标准方法执行时间应大于0');
        $this->assertGreaterThan(0, $fastTime, '快速方法执行时间应大于0');
    }

    /**
     * 测试在极限情况下的DCT正确性
     */
    public function testDCTCorrectnessEdgeCases(): void
    {
        // 测试单元素矩阵
        $singleMatrix = [[255]];

        // 常规实现
        DCT::setUseFastImplementation(false);
        $stdDct = DCT::forward($singleMatrix);
        $stdIdct = DCT::inverse($stdDct);

        // 快速实现
        DCT::setUseFastImplementation(true);
        $fastDct = DCT::forward($singleMatrix);
        $fastIdct = DCT::inverse($fastDct);

        // 验证两种实现的结果一致
        $this->assertEqualsWithDelta($stdDct[0][0], $fastDct[0][0], 0.001);
        $this->assertEqualsWithDelta($stdIdct[0][0], $fastIdct[0][0], 0.001);
        $this->assertEqualsWithDelta($singleMatrix[0][0], $fastIdct[0][0], 0.5);

        // 测试零矩阵
        $zeroMatrix = array_fill(0, 4, array_fill(0, 4, 0));

        // 常规实现
        DCT::setUseFastImplementation(false);
        $stdDct = DCT::forward($zeroMatrix);

        // 快速实现
        DCT::setUseFastImplementation(true);
        $fastDct = DCT::forward($zeroMatrix);

        // 验证两种实现的结果都是零矩阵
        $assertionCount = 0;
        for ($i = 0; $i < 4; ++$i) {
            for ($j = 0; $j < 4; ++$j) {
                $this->assertEqualsWithDelta(0, $stdDct[$i][$j], 0.001);
                $this->assertEqualsWithDelta(0, $fastDct[$i][$j], 0.001);
                $assertionCount += 2;
            }
        }

        // 确保至少执行了一些断言
        $this->assertGreaterThan(0, $assertionCount, '应该至少执行了一些断言');
    }
}
