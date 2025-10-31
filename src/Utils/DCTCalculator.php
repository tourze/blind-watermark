<?php

namespace Tourze\BlindWatermark\Utils;

/**
 * DCT计算辅助类
 *
 * 封装DCT计算的具体算法，减少主DCT类的复杂度
 */
class DCTCalculator
{
    /**
     * 计算α系数
     */
    public static function calculateAlpha(int $value): float
    {
        return (0 === $value) ? 1 / sqrt(2) : 1;
    }

    /**
     * 计算系数积
     */
    public static function calculateCoefficientProduct(float $alpha_u, float $alpha_v, float $sum, int $m, int $n): float
    {
        return $alpha_u * $alpha_v * $sum * 2 / sqrt($m * $n);
    }

    /**
     * 计算DCT求和部分
     *
     * @param array<int, array<int, float>> $matrix 输入矩阵（二维浮点数数组）
     * @param int                           $m      矩阵行数
     * @param int                           $n      矩阵列数
     * @param int                           $u      DCT频率域的u坐标
     * @param int                           $v      DCT频率域的v坐标
     *
     * @return float DCT求和结果
     */
    public static function calculateDCTSum(array $matrix, int $m, int $n, int $u, int $v): float
    {
        $sum = 0.0;

        for ($i = 0; $i < $m; ++$i) {
            for ($j = 0; $j < $n; ++$j) {
                $sum += $matrix[$i][$j] *
                    cos((2 * $i + 1) * $u * M_PI / (2 * $m)) *
                    cos((2 * $j + 1) * $v * M_PI / (2 * $n));
            }
        }

        return $sum;
    }

    /**
     * 使用缓存计算DCT求和部分
     *
     * @param array<int, array<int, float>> $matrix      输入矩阵（二维浮点数数组）
     * @param int                           $m           矩阵行数
     * @param int                           $n           矩阵列数
     * @param int                           $u           DCT频率域的u坐标
     * @param int                           $v           DCT频率域的v坐标
     * @param array<string, float>          $cosineCache 余弦值缓存（字符串键到浮点数值的映射）
     *
     * @return float DCT求和结果
     */
    public static function calculateFastDCTSum(array $matrix, int $m, int $n, int $u, int $v, array $cosineCache): float
    {
        $sum = 0.0;

        for ($i = 0; $i < $m; ++$i) {
            $cosU = $cosineCache["{$i}:{$u}:{$m}"];
            for ($j = 0; $j < $n; ++$j) {
                $cosV = $cosineCache["{$j}:{$v}:{$n}"];
                $sum += $matrix[$i][$j] * $cosU * $cosV;
            }
        }

        return $sum;
    }

    /**
     * 计算IDCT求和部分
     *
     * @param array<int, array<int, float>> $dctMatrix DCT系数矩阵（二维浮点数数组）
     * @param int                           $m         矩阵行数
     * @param int                           $n         矩阵列数
     * @param int                           $i         空间域的i坐标
     * @param int                           $j         空间域的j坐标
     *
     * @return float IDCT求和结果
     */
    public static function calculateIDCTSum(array $dctMatrix, int $m, int $n, int $i, int $j): float
    {
        $sum = 0.0;

        for ($u = 0; $u < $m; ++$u) {
            for ($v = 0; $v < $n; ++$v) {
                $alpha_u = self::calculateAlpha($u);
                $alpha_v = self::calculateAlpha($v);

                $sum += $alpha_u * $alpha_v * $dctMatrix[$u][$v] *
                    cos((2 * $i + 1) * $u * M_PI / (2 * $m)) *
                    cos((2 * $j + 1) * $v * M_PI / (2 * $n));
            }
        }

        return $sum;
    }

    /**
     * 使用缓存计算IDCT求和部分
     *
     * @param array<int, array<int, float>> $dctMatrix   DCT系数矩阵（二维浮点数数组）
     * @param int                           $m           矩阵行数
     * @param int                           $n           矩阵列数
     * @param int                           $i           空间域的i坐标
     * @param int                           $j           空间域的j坐标
     * @param array<string, float>          $cosineCache 余弦值缓存（字符串键到浮点数值的映射）
     *
     * @return float IDCT求和结果
     */
    public static function calculateFastIDCTSum(array $dctMatrix, int $m, int $n, int $i, int $j, array $cosineCache): float
    {
        $sum = 0.0;

        for ($u = 0; $u < $m; ++$u) {
            $cosU = $cosineCache["{$i}:{$u}:{$m}"];
            $alpha_u = self::calculateAlpha($u);

            for ($v = 0; $v < $n; ++$v) {
                $cosV = $cosineCache["{$j}:{$v}:{$n}"];
                $alpha_v = self::calculateAlpha($v);

                $sum += $alpha_u * $alpha_v * $dctMatrix[$u][$v] * $cosU * $cosV;
            }
        }

        return $sum;
    }
}
