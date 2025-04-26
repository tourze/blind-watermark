<?php

namespace Tourze\BlindWatermark\Tests\Utils;

use PHPUnit\Framework\TestCase;
use SebastianBergmann\Timer\Timer;
use Tourze\BlindWatermark\Utils\DCT;

/**
 * DCT性能测试类
 * 
 * 测试常规DCT实现和快速DCT实现的性能差异
 */
class DCTPerformanceTest extends TestCase
{
    /**
     * 测试矩阵尺寸
     */
    protected const MATRIX_SIZE = 32;
    
    /**
     * 性能测试轮次
     */
    protected const TEST_ROUNDS = 3;
    
    /**
     * 测试简单矩阵DCT性能
     */
    public function testDCTPerformance(): void
    {
        // 略过严格性能测试，性能测试可能受环境影响不稳定
        $this->markTestIncomplete('性能测试可能不稳定，仅作参考');
        
        // 创建测试矩阵
        $size = 256;
        $matrix = [];
        for ($i = 0; $i < $size; $i++) {
            $matrix[$i] = [];
            for ($j = 0; $j < $size; $j++) {
                $matrix[$i][$j] = sin($i) * cos($j) * 128 + 128;
            }
        }

        // 计时标准方法
        $timer = new Timer();
        $timer->start();
        for ($i = 0; $i < 10; $i++) {
            DCT::setUseFastImplementation(false);
            $dct = DCT::forward($matrix);
            $idct = DCT::inverse($dct);
        }
        $standardTime = $timer->stop()->asMicroseconds() / 10;

        // 计时优化方法
        $timer->start();
        for ($i = 0; $i < 10; $i++) {
            DCT::setUseFastImplementation(true);
            $dct = DCT::fastForward($matrix, $size, $size);
            $idct = DCT::fastInverse($dct, $size, $size);
        }
        $fastTime = $timer->stop()->asMicroseconds() / 10;

        // 计算加速比
        $speedup = $standardTime / $fastTime;
        echo "标准DCT平均执行时间: " . number_format($standardTime, 5) . " 秒\n";
        echo "快速DCT平均执行时间: " . number_format($fastTime, 5) . " 秒\n";
        echo "性能提升: " . number_format($speedup, 2) . "倍\n";

        // 只要不抛出异常就算测试通过
        $this->addToAssertionCount(1);
    }
    
    /**
     * 测试分块DCT性能
     */
    public function testBlockDCTPerformance(): void
    {
        // 略过严格性能测试，性能测试可能受环境影响不稳定
        $this->markTestIncomplete('性能测试可能不稳定，仅作参考');
        
        // 创建测试矩阵
        $height = 256;
        $width = 256;
        $blockSize = 8;
        $matrix = [];
        for ($i = 0; $i < $height; $i++) {
            $matrix[$i] = [];
            for ($j = 0; $j < $width; $j++) {
                $matrix[$i][$j] = sin($i) * cos($j) * 128 + 128;
            }
        }

        // 计时标准方法
        $timer = new Timer();
        $timer->start();
        for ($i = 0; $i < 5; $i++) {
            DCT::setUseFastImplementation(false);
            $dctBlocks = DCT::blockDCT($matrix, $blockSize);
            $recovered = DCT::blockIDCT($dctBlocks, $height, $width, $blockSize);
        }
        $standardTime = $timer->stop()->asMicroseconds() / 5;

        // 计时优化方法
        $timer->start();
        for ($i = 0; $i < 5; $i++) {
            DCT::setUseFastImplementation(true);
            $dctBlocks = DCT::blockDCT($matrix, $blockSize);
            $recovered = DCT::blockIDCT($dctBlocks, $height, $width, $blockSize);
        }
        $fastTime = $timer->stop()->asMicroseconds() / 5;

        // 计算加速比
        $speedup = $standardTime / $fastTime;
        echo "标准分块DCT平均执行时间: " . number_format($standardTime, 5) . " 秒\n";
        echo "快速分块DCT平均执行时间: " . number_format($fastTime, 5) . " 秒\n";
        echo "性能提升: " . number_format($speedup, 2) . "倍\n";

        // 只要不抛出异常就算测试通过
        $this->addToAssertionCount(1);
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
        for ($i = 0; $i < 4; $i++) {
            for ($j = 0; $j < 4; $j++) {
                $this->assertEqualsWithDelta(0, $stdDct[$i][$j], 0.001);
                $this->assertEqualsWithDelta(0, $fastDct[$i][$j], 0.001);
            }
        }
    }
    
    /**
     * 创建测试矩阵
     *
     * @param int $size 矩阵尺寸
     * @return array 随机值填充的矩阵
     */
    protected function createTestMatrix(int $size): array
    {
        $matrix = [];
        for ($i = 0; $i < $size; $i++) {
            $matrix[$i] = [];
            for ($j = 0; $j < $size; $j++) {
                $matrix[$i][$j] = mt_rand(0, 255);
            }
        }
        return $matrix;
    }
    
    /**
     * 测量函数执行时间
     *
     * @param callable $function 要测试的函数
     * @return float 平均执行时间（秒）
     */
    protected function benchmarkFunction(callable $function): float
    {
        $times = [];
        
        for ($i = 0; $i < self::TEST_ROUNDS; $i++) {
            $start = microtime(true);
            $function();
            $end = microtime(true);
            $times[] = $end - $start;
        }
        
        // 计算平均执行时间
        return array_sum($times) / count($times);
    }
} 