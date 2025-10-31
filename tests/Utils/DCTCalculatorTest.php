<?php

namespace Tourze\BlindWatermark\Tests\Utils;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\BlindWatermark\Utils\DCTCalculator;

/**
 * DCT计算辅助类测试
 *
 * @internal
 */
#[CoversClass(DCTCalculator::class)]
final class DCTCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // DCTCalculator 测试不需要特殊的设置
    }

    /**
     * 测试计算α系数
     */
    public function testCalculateAlpha(): void
    {
        // 测试u=0的情况
        $alpha0 = DCTCalculator::calculateAlpha(0);
        $this->assertEqualsWithDelta(1 / sqrt(2), $alpha0, 0.0001, 'α(0)应该等于1/√2');

        // 测试u>0的情况
        $alpha1 = DCTCalculator::calculateAlpha(1);
        $this->assertEquals(1, $alpha1, 'α(1)应该等于1');

        $alpha5 = DCTCalculator::calculateAlpha(5);
        $this->assertEquals(1, $alpha5, 'α(5)应该等于1');
    }

    /**
     * 测试计算系数积
     */
    public function testCalculateCoefficientProduct(): void
    {
        $alpha_u = 1 / sqrt(2);
        $alpha_v = 1;
        $sum = 10.0;
        $m = 4;
        $n = 4;

        $result = DCTCalculator::calculateCoefficientProduct($alpha_u, $alpha_v, $sum, $m, $n);
        $expected = $alpha_u * $alpha_v * $sum * 2 / sqrt($m * $n);

        $this->assertEqualsWithDelta($expected, $result, 0.0001, '系数积计算错误');
    }

    /**
     * 测试DCT求和计算
     */
    public function testCalculateDCTSum(): void
    {
        // 创建简单的2x2矩阵
        $matrix = [
            [1, 2],
            [3, 4],
        ];

        $m = 2;
        $n = 2;
        $u = 0;
        $v = 0;

        $sum = DCTCalculator::calculateDCTSum($matrix, $m, $n, $u, $v);

        // 当u=0, v=0时，所有cos值都是1，所以sum应该是矩阵所有元素的和
        $expected = 1 + 2 + 3 + 4; // = 10
        $this->assertEquals($expected, $sum, 'DCT求和计算错误');
    }

    /**
     * 测试快速DCT求和计算
     */
    public function testCalculateFastDCTSum(): void
    {
        // 创建简单的2x2矩阵
        $matrix = [
            [1, 2],
            [3, 4],
        ];

        $m = 2;
        $n = 2;
        $u = 0;
        $v = 0;

        // 创建余弦缓存
        $cosineCache = [];
        for ($i = 0; $i < $m; ++$i) {
            for ($u_val = 0; $u_val < $m; ++$u_val) {
                $key = "{$i}:{$u_val}:{$m}";
                $cosineCache[$key] = cos((2 * $i + 1) * $u_val * M_PI / (2 * $m));
            }
        }
        for ($j = 0; $j < $n; ++$j) {
            for ($v_val = 0; $v_val < $n; ++$v_val) {
                $key = "{$j}:{$v_val}:{$n}";
                $cosineCache[$key] = cos((2 * $j + 1) * $v_val * M_PI / (2 * $n));
            }
        }

        $sum = DCTCalculator::calculateFastDCTSum($matrix, $m, $n, $u, $v, $cosineCache);

        // 验证快速计算和标准计算的结果一致
        $standardSum = DCTCalculator::calculateDCTSum($matrix, $m, $n, $u, $v);
        $this->assertEqualsWithDelta($standardSum, $sum, 0.0001, '快速DCT求和与标准求和结果不一致');
    }

    /**
     * 测试IDCT求和计算
     */
    public function testCalculateIDCTSum(): void
    {
        // 创建简单的2x2 DCT系数矩阵
        $dctMatrix = [
            [5.0, 1.0],
            [2.0, 0.5],
        ];

        $m = 2;
        $n = 2;
        $i = 0;
        $j = 0;

        $sum = DCTCalculator::calculateIDCTSum($dctMatrix, $m, $n, $i, $j);

        // 验证IDCT求和计算完成且结果合理
        $this->assertGreaterThan(0, $sum, 'IDCT求和结果应该大于0');
    }

    /**
     * 测试快速IDCT求和计算
     */
    public function testCalculateFastIDCTSum(): void
    {
        // 创建简单的2x2 DCT系数矩阵
        $dctMatrix = [
            [5.0, 1.0],
            [2.0, 0.5],
        ];

        $m = 2;
        $n = 2;
        $i = 0;
        $j = 0;

        // 创建余弦缓存
        $cosineCache = [];
        for ($i_val = 0; $i_val < $m; ++$i_val) {
            for ($u = 0; $u < $m; ++$u) {
                $key = "{$i_val}:{$u}:{$m}";
                $cosineCache[$key] = cos((2 * $i_val + 1) * $u * M_PI / (2 * $m));
            }
        }
        for ($j_val = 0; $j_val < $n; ++$j_val) {
            for ($v = 0; $v < $n; ++$v) {
                $key = "{$j_val}:{$v}:{$n}";
                $cosineCache[$key] = cos((2 * $j_val + 1) * $v * M_PI / (2 * $n));
            }
        }

        $sum = DCTCalculator::calculateFastIDCTSum($dctMatrix, $m, $n, $i, $j, $cosineCache);

        // 验证快速计算和标准计算的结果一致
        $standardSum = DCTCalculator::calculateIDCTSum($dctMatrix, $m, $n, $i, $j);
        $this->assertEqualsWithDelta($standardSum, $sum, 0.0001, '快速IDCT求和与标准求和结果不一致');
    }
}
