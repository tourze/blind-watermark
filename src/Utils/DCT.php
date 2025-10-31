<?php

namespace Tourze\BlindWatermark\Utils;

/**
 * 离散余弦变换(DCT)工具类
 *
 * DCT是图像处理中常用的变换方法，广泛应用于JPEG压缩和数字水印技术。
 * 本实现提供了标准DCT和优化的快速DCT两种实现方式。
 *
 * DCT公式:
 * F(u,v) = (2/sqrt(MN)) * C(u) * C(v) * sum[sum[f(i,j) * cos((2i+1)uπ/2M) * cos((2j+1)vπ/2N)]
 * 其中:
 * - f(i,j)是原始图像数据
 * - F(u,v)是DCT系数
 * - C(u)和C(v)是系数，当u或v为0时为1/sqrt(2)，否则为1
 * - M和N是图像的宽高
 *
 * 参考资料：
 * - https://en.wikipedia.org/wiki/Discrete_cosine_transform
 * - 论文《A DCT-based robust watermarking scheme for digital images》
 * - https://www.sciencedirect.com/science/article/abs/pii/S0165168498000614
 */
class DCT
{
    /**
     * DCT 余弦值缓存表
     * 用于提高计算性能
     *
     * @var array<string, float>
     */
    protected static array $cosineCache = [];

    /**
     * 是否使用快速DCT算法
     */
    protected static bool $useFastImplementation = true;

    /**
     * 设置是否使用快速DCT实现
     *
     * @param bool $useFast 是否使用快速实现
     */
    public static function setUseFastImplementation(bool $useFast): void
    {
        self::$useFastImplementation = $useFast;
    }

    /**
     * 对二维图像数据进行DCT变换
     *
     * @param array<int, array<int, float>> $matrix 输入矩阵
     *
     * @return array<int, array<int, float>> 变换后的DCT系数矩阵
     */
    public static function forward(array $matrix): array
    {
        $m = count($matrix);
        if (0 === $m) {
            return [];
        }

        $n = count($matrix[0]);

        // 使用快速算法
        if (self::$useFastImplementation) {
            return self::fastForward($matrix, $m, $n);
        }

        return self::standardForward($matrix, $m, $n);
    }

    /**
     * 标准DCT正变换实现
     *
     * @param array<int, array<int, float>> $matrix 输入矩阵
     * @param int                           $m      矩阵高度
     * @param int                           $n      矩阵宽度
     *
     * @return array<int, array<int, float>> 变换后的DCT系数矩阵
     */
    private static function standardForward(array $matrix, int $m, int $n): array
    {
        $result = array_fill(0, $m, array_fill(0, $n, 0.0));

        return self::fillStandardForwardResult($result, $matrix, $m, $n);
    }

    /**
     * 计算单个DCT系数
     *
     * @param array<int, array<int, float>> $matrix 输入矩阵
     * @param int                           $m      矩阵高度
     * @param int                           $n      矩阵宽度
     * @param int                           $u      频域坐标u
     * @param int                           $v      频域坐标v
     *
     * @return float DCT系数
     */
    private static function calculateDCTCoefficient(array $matrix, int $m, int $n, int $u, int $v): float
    {
        $sum = DCTCalculator::calculateDCTSum($matrix, $m, $n, $u, $v);
        $alpha_u = DCTCalculator::calculateAlpha($u);
        $alpha_v = DCTCalculator::calculateAlpha($v);

        return DCTCalculator::calculateCoefficientProduct($alpha_u, $alpha_v, $sum, $m, $n);
    }

    /**
     * 快速DCT正变换实现
     *
     * 使用缓存的余弦值和分离变量优化计算性能
     *
     * @param array<int, array<int, float>> $matrix 输入矩阵
     * @param int                           $m      矩阵高度
     * @param int                           $n      矩阵宽度
     *
     * @return array<int, array<int, float>> 变换后的DCT系数矩阵
     */
    public static function fastForward(array $matrix, int $m, int $n): array
    {
        $result = array_fill(0, $m, array_fill(0, $n, 0.0));
        self::ensureCosineCache($m, $n);

        return self::fillFastForwardResult($result, $matrix, $m, $n);
    }

    /**
     * 使用缓存计算单个DCT系数
     *
     * @param array<int, array<int, float>> $matrix 输入矩阵
     * @param int                           $m      矩阵高度
     * @param int                           $n      矩阵宽度
     * @param int                           $u      频域坐标u
     * @param int                           $v      频域坐标v
     *
     * @return float DCT系数
     */
    private static function calculateFastDCTCoefficient(array $matrix, int $m, int $n, int $u, int $v): float
    {
        $sum = DCTCalculator::calculateFastDCTSum($matrix, $m, $n, $u, $v, self::$cosineCache);
        $alpha_u = DCTCalculator::calculateAlpha($u);
        $alpha_v = DCTCalculator::calculateAlpha($v);

        return DCTCalculator::calculateCoefficientProduct($alpha_u, $alpha_v, $sum, $m, $n);
    }

    /**
     * 对DCT系数矩阵进行逆变换(IDCT)，恢复原始图像数据
     *
     * @param array<int, array<int, float>> $dctMatrix DCT系数矩阵
     *
     * @return array<int, array<int, float>> 恢复的原始图像数据
     */
    public static function inverse(array $dctMatrix): array
    {
        $m = count($dctMatrix);
        if (0 === $m) {
            return [];
        }

        $n = count($dctMatrix[0]);

        // 使用快速算法
        if (self::$useFastImplementation) {
            return self::fastInverse($dctMatrix, $m, $n);
        }

        return self::standardInverse($dctMatrix, $m, $n);
    }

    /**
     * 标准DCT逆变换实现
     *
     * @param array<int, array<int, float>> $dctMatrix DCT系数矩阵
     * @param int                           $m         矩阵高度
     * @param int                           $n         矩阵宽度
     *
     * @return array<int, array<int, float>> 恢复的原始图像数据
     */
    private static function standardInverse(array $dctMatrix, int $m, int $n): array
    {
        $result = array_fill(0, $m, array_fill(0, $n, 0.0));

        return self::fillStandardInverseResult($result, $dctMatrix, $m, $n);
    }

    /**
     * 计算单个IDCT系数
     *
     * @param array<int, array<int, float>> $dctMatrix DCT系数矩阵
     * @param int                           $m         矩阵高度
     * @param int                           $n         矩阵宽度
     * @param int                           $i         空域坐标i
     * @param int                           $j         空域坐标j
     *
     * @return float IDCT系数
     */
    private static function calculateIDCTCoefficient(array $dctMatrix, int $m, int $n, int $i, int $j): float
    {
        $sum = DCTCalculator::calculateIDCTSum($dctMatrix, $m, $n, $i, $j);

        return $sum * 2 / sqrt($m * $n);
    }

    /**
     * 快速DCT逆变换实现
     *
     * 使用缓存的余弦值和分离变量优化计算性能
     *
     * @param array<int, array<int, float>> $dctMatrix DCT系数矩阵
     * @param int                           $m         矩阵高度
     * @param int                           $n         矩阵宽度
     *
     * @return array<int, array<int, float>> 恢复的原始图像数据
     */
    public static function fastInverse(array $dctMatrix, int $m, int $n): array
    {
        $result = array_fill(0, $m, array_fill(0, $n, 0.0));
        self::ensureCosineCache($m, $n);

        return self::fillFastInverseResult($result, $dctMatrix, $m, $n);
    }

    /**
     * 使用缓存计算单个IDCT系数
     *
     * @param array<int, array<int, float>> $dctMatrix DCT系数矩阵
     * @param int                           $m         矩阵高度
     * @param int                           $n         矩阵宽度
     * @param int                           $i         空域坐标i
     * @param int                           $j         空域坐标j
     *
     * @return float IDCT系数
     */
    private static function calculateFastIDCTCoefficient(array $dctMatrix, int $m, int $n, int $i, int $j): float
    {
        $sum = DCTCalculator::calculateFastIDCTSum($dctMatrix, $m, $n, $i, $j, self::$cosineCache);

        return $sum * 2 / sqrt($m * $n);
    }

    /**
     * 确保余弦缓存表已初始化
     *
     * @param int $m 矩阵高度
     * @param int $n 矩阵宽度
     */
    protected static function ensureCosineCache(int $m, int $n): void
    {
        self::ensureRowCosineCache($m);
        self::ensureColumnCosineCache($n);
    }

    /**
     * 确保行方向的余弦缓存已初始化
     */
    private static function ensureRowCosineCache(int $m): void
    {
        for ($i = 0; $i < $m; ++$i) {
            self::ensureRowCosineCacheForIndex($i, $m);
        }
    }

    /**
     * 确保列方向的余弦缓存已初始化
     */
    private static function ensureColumnCosineCache(int $n): void
    {
        for ($j = 0; $j < $n; ++$j) {
            self::ensureColumnCosineCacheForIndex($j, $n);
        }
    }

    /**
     * 为指定行索引确保余弦缓存已初始化
     *
     * @param int $i 行索引
     * @param int $m 矩阵高度
     */
    private static function ensureRowCosineCacheForIndex(int $i, int $m): void
    {
        for ($u = 0; $u < $m; ++$u) {
            self::cacheRowCosineValue($i, $u, $m);
        }
    }

    /**
     * 为指定列索引确保余弦缓存已初始化
     *
     * @param int $j 列索引
     * @param int $n 矩阵宽度
     */
    private static function ensureColumnCosineCacheForIndex(int $j, int $n): void
    {
        for ($v = 0; $v < $n; ++$v) {
            self::cacheColumnCosineValue($j, $v, $n);
        }
    }

    /**
     * 缓存行方向的余弦值
     *
     * @param int $i 行索引
     * @param int $u 频率索引
     * @param int $m 矩阵高度
     */
    private static function cacheRowCosineValue(int $i, int $u, int $m): void
    {
        $key = "{$i}:{$u}:{$m}";
        if (!isset(self::$cosineCache[$key])) {
            self::$cosineCache[$key] = self::calculateRowCosine($i, $u, $m);
        }
    }

    /**
     * 缓存列方向的余弦值
     *
     * @param int $j 列索引
     * @param int $v 频率索引
     * @param int $n 矩阵宽度
     */
    private static function cacheColumnCosineValue(int $j, int $v, int $n): void
    {
        $key = "{$j}:{$v}:{$n}";
        if (!isset(self::$cosineCache[$key])) {
            self::$cosineCache[$key] = self::calculateColumnCosine($j, $v, $n);
        }
    }

    /**
     * 计算行方向的余弦值
     *
     * @param int $i 行索引
     * @param int $u 频率索引
     * @param int $m 矩阵高度
     *
     * @return float 余弦值
     */
    private static function calculateRowCosine(int $i, int $u, int $m): float
    {
        return cos((2 * $i + 1) * $u * M_PI / (2 * $m));
    }

    /**
     * 计算列方向的余弦值
     *
     * @param int $j 列索引
     * @param int $v 频率索引
     * @param int $n 矩阵宽度
     *
     * @return float 余弦值
     */
    private static function calculateColumnCosine(int $j, int $v, int $n): float
    {
        return cos((2 * $j + 1) * $v * M_PI / (2 * $n));
    }

    /**
     * 填充标准DCT正变换结果矩阵
     *
     * @param array<int, array<int, float>> $result  结果矩阵
     * @param array<int, array<int, float>> $matrix  输入矩阵
     * @param int                           $m       矩阵高度
     * @param int                           $n       矩阵宽度
     *
     * @return array<int, array<int, float>> 填充后的结果矩阵
     */
    private static function fillStandardForwardResult(array $result, array $matrix, int $m, int $n): array
    {
        for ($u = 0; $u < $m; ++$u) {
            $result = self::fillStandardForwardRow($result, $matrix, $m, $n, $u);
        }

        return $result;
    }

    /**
     * 填充标准DCT正变换结果矩阵的一行
     *
     * @param array<int, array<int, float>> $result  结果矩阵
     * @param array<int, array<int, float>> $matrix  输入矩阵
     * @param int                           $m       矩阵高度
     * @param int                           $n       矩阵宽度
     * @param int                           $u       频率域u坐标
     *
     * @return array<int, array<int, float>> 填充后的结果矩阵
     */
    private static function fillStandardForwardRow(array $result, array $matrix, int $m, int $n, int $u): array
    {
        for ($v = 0; $v < $n; ++$v) {
            $result[$u][$v] = self::calculateDCTCoefficient($matrix, $m, $n, $u, $v);
        }

        return $result;
    }

    /**
     * 填充快速DCT正变换结果矩阵
     *
     * @param array<int, array<int, float>> $result  结果矩阵
     * @param array<int, array<int, float>> $matrix  输入矩阵
     * @param int                           $m       矩阵高度
     * @param int                           $n       矩阵宽度
     *
     * @return array<int, array<int, float>> 填充后的结果矩阵
     */
    private static function fillFastForwardResult(array $result, array $matrix, int $m, int $n): array
    {
        for ($u = 0; $u < $m; ++$u) {
            $result = self::fillFastForwardRow($result, $matrix, $m, $n, $u);
        }

        return $result;
    }

    /**
     * 填充快速DCT正变换结果矩阵的一行
     *
     * @param array<int, array<int, float>> $result  结果矩阵
     * @param array<int, array<int, float>> $matrix  输入矩阵
     * @param int                           $m       矩阵高度
     * @param int                           $n       矩阵宽度
     * @param int                           $u       频率域u坐标
     *
     * @return array<int, array<int, float>> 填充后的结果矩阵
     */
    private static function fillFastForwardRow(array $result, array $matrix, int $m, int $n, int $u): array
    {
        for ($v = 0; $v < $n; ++$v) {
            $result[$u][$v] = self::calculateFastDCTCoefficient($matrix, $m, $n, $u, $v);
        }

        return $result;
    }

    /**
     * 填充标准DCT逆变换结果矩阵
     *
     * @param array<int, array<int, float>> $result    结果矩阵
     * @param array<int, array<int, float>> $dctMatrix DCT系数矩阵
     * @param int                           $m         矩阵高度
     * @param int                           $n         矩阵宽度
     *
     * @return array<int, array<int, float>> 填充后的结果矩阵
     */
    private static function fillStandardInverseResult(array $result, array $dctMatrix, int $m, int $n): array
    {
        for ($i = 0; $i < $m; ++$i) {
            $result = self::fillStandardInverseRow($result, $dctMatrix, $m, $n, $i);
        }

        return $result;
    }

    /**
     * 填充标准DCT逆变换结果矩阵的一行
     *
     * @param array<int, array<int, float>> $result    结果矩阵
     * @param array<int, array<int, float>> $dctMatrix DCT系数矩阵
     * @param int                           $m         矩阵高度
     * @param int                           $n         矩阵宽度
     * @param int                           $i         空域i坐标
     *
     * @return array<int, array<int, float>> 填充后的结果矩阵
     */
    private static function fillStandardInverseRow(array $result, array $dctMatrix, int $m, int $n, int $i): array
    {
        for ($j = 0; $j < $n; ++$j) {
            $result[$i][$j] = self::calculateIDCTCoefficient($dctMatrix, $m, $n, $i, $j);
        }

        return $result;
    }

    /**
     * 填充快速DCT逆变换结果矩阵
     *
     * @param array<int, array<int, float>> $result    结果矩阵
     * @param array<int, array<int, float>> $dctMatrix DCT系数矩阵
     * @param int                           $m         矩阵高度
     * @param int                           $n         矩阵宽度
     *
     * @return array<int, array<int, float>> 填充后的结果矩阵
     */
    private static function fillFastInverseResult(array $result, array $dctMatrix, int $m, int $n): array
    {
        for ($i = 0; $i < $m; ++$i) {
            $result = self::fillFastInverseRow($result, $dctMatrix, $m, $n, $i);
        }

        return $result;
    }

    /**
     * 填充快速DCT逆变换结果矩阵的一行
     *
     * @param array<int, array<int, float>> $result    结果矩阵
     * @param array<int, array<int, float>> $dctMatrix DCT系数矩阵
     * @param int                           $m         矩阵高度
     * @param int                           $n         矩阵宽度
     * @param int                           $i         空域i坐标
     *
     * @return array<int, array<int, float>> 填充后的结果矩阵
     */
    private static function fillFastInverseRow(array $result, array $dctMatrix, int $m, int $n, int $i): array
    {
        for ($j = 0; $j < $n; ++$j) {
            $result[$i][$j] = self::calculateFastIDCTCoefficient($dctMatrix, $m, $n, $i, $j);
        }

        return $result;
    }

    /**
     * 对图像进行分块DCT变换
     *
     * @param array<int, array<int, float>> $imageData 图像数据
     * @param int                           $blockSize 分块大小，默认为8x8
     *
     * @return array<int, array<int, array<int, array<int, float>>>> 分块DCT变换结果
     */
    public static function blockDCT(array $imageData, int $blockSize = 8): array
    {
        $height = count($imageData);
        if (0 === $height) {
            return [];
        }

        $width = count($imageData[0]);
        $result = [];

        // 计算需要的块数
        $blocksY = ceil($height / $blockSize);
        $blocksX = ceil($width / $blockSize);

        for ($by = 0; $by < $blocksY; ++$by) {
            $result[$by] = [];
            for ($bx = 0; $bx < $blocksX; ++$bx) {
                $block = self::extractBlock($imageData, $by, $bx, $blockSize, $height, $width);
                $result[$by][$bx] = self::forward($block);
            }
        }

        return $result;
    }

    /**
     * 提取指定位置的图像块
     *
     * @param array<int, array<int, float>> $imageData 图像数据
     * @param int                           $by        块的Y坐标
     * @param int                           $bx        块的X坐标
     * @param int                           $blockSize 分块大小
     * @param int                           $height    图像高度
     * @param int                           $width     图像宽度
     *
     * @return array<int, array<int, float>> 提取的图像块
     */
    private static function extractBlock(array $imageData, int $by, int $bx, int $blockSize, int $height, int $width): array
    {
        $block = [];

        for ($i = 0; $i < $blockSize; ++$i) {
            $y = $by * $blockSize + $i;
            if ($y >= $height) {
                // 填充0
                $block[$i] = array_fill(0, $blockSize, 0);
                continue;
            }

            $block[$i] = self::extractBlockRow($imageData[$y], $bx, $blockSize, $width);
        }

        return $block;
    }

    /**
     * 提取块的一行数据
     *
     * @param array<int, float> $imageRow  图像行数据
     * @param int               $bx        块的X坐标
     * @param int               $blockSize 分块大小
     * @param int               $width     图像宽度
     *
     * @return array<int, float> 提取的块行数据
     */
    private static function extractBlockRow(array $imageRow, int $bx, int $blockSize, int $width): array
    {
        $blockRow = [];

        for ($j = 0; $j < $blockSize; ++$j) {
            $x = $bx * $blockSize + $j;
            $blockRow[$j] = ($x < $width) ? $imageRow[$x] : 0;
        }

        return $blockRow;
    }

    /**
     * 对分块DCT系数进行逆变换，恢复图像
     *
     * @param array<int, array<int, array<int, array<int, float>>>> $dctBlocks 分块DCT系数
     * @param int                                                   $height    原始图像高度
     * @param int                                                   $width     原始图像宽度
     * @param int                                                   $blockSize 分块大小
     *
     * @return array<int, array<int, float>> 恢复的图像数据
     */
    public static function blockIDCT(array $dctBlocks, int $height, int $width, int $blockSize = 8): array
    {
        $result = self::initializeResultMatrix($height, $width);

        $blocksY = count($dctBlocks);
        if (0 === $blocksY) {
            return $result;
        }

        $blocksX = count($dctBlocks[0]);

        return self::processAllBlocks($dctBlocks, $result, $blocksY, $blocksX, $height, $width, $blockSize);
    }

    /**
     * 初始化结果矩阵
     *
     * @param int $height 矩阵高度
     * @param int $width  矩阵宽度
     *
     * @return array<int, array<int, float>> 初始化的结果矩阵
     */
    private static function initializeResultMatrix(int $height, int $width): array
    {
        return array_fill(0, $height, array_fill(0, $width, 0));
    }

    /**
     * 处理所有图像块
     *
     * @param array<int, array<int, array<int, array<int, float>>>> $dctBlocks 分块DCT系数
     * @param array<int, array<int, float>>                          $result    结果矩阵
     * @param int                                                   $blocksY   Y方向的块数
     * @param int                                                   $blocksX   X方向的块数
     * @param int                                                   $height    原始图像高度
     * @param int                                                   $width     原始图像宽度
     * @param int                                                   $blockSize 分块大小
     *
     * @return array<int, array<int, float>> 处理后的结果矩阵
     */
    private static function processAllBlocks(
        array $dctBlocks,
        array $result,
        int $blocksY,
        int $blocksX,
        int $height,
        int $width,
        int $blockSize,
    ): array {
        for ($by = 0; $by < $blocksY; ++$by) {
            $result = self::processBlockRow($dctBlocks, $result, $by, $blocksX, $height, $width, $blockSize);
        }

        return $result;
    }

    /**
     * 处理单行图像块
     *
     * @param array<int, array<int, array<int, array<int, float>>>> $dctBlocks 分块DCT系数
     * @param array<int, array<int, float>>                          $result    结果矩阵
     * @param int                                                   $by        Y方向的块索引
     * @param int                                                   $blocksX   X方向的块数
     * @param int                                                   $height    原始图像高度
     * @param int                                                   $width     原始图像宽度
     * @param int                                                   $blockSize 分块大小
     *
     * @return array<int, array<int, float>> 处理后的结果矩阵
     */
    private static function processBlockRow(
        array $dctBlocks,
        array $result,
        int $by,
        int $blocksX,
        int $height,
        int $width,
        int $blockSize,
    ): array {
        for ($bx = 0; $bx < $blocksX; ++$bx) {
            $block = self::inverse($dctBlocks[$by][$bx]);
            $result = self::placeBlockInResult($result, $block, $by, $bx, $height, $width, $blockSize);
        }

        return $result;
    }

    /**
     * 将图像块放置到结果矩阵中的正确位置
     *
     * @param array<int, array<int, float>>       $result    结果矩阵
     * @param array<int, array<int, float>>      $block     要放置的图像块
     * @param int                                $by        Y方向的块索引
     * @param int                                $bx        X方向的块索引
     * @param int                                $height    原始图像高度
     * @param int                                $width     原始图像宽度
     * @param int                                $blockSize 分块大小
     *
     * @return array<int, array<int, float>> 更新后的结果矩阵
     */
    private static function placeBlockInResult(
        array $result,
        array $block,
        int $by,
        int $bx,
        int $height,
        int $width,
        int $blockSize,
    ): array {
        for ($i = 0; $i < $blockSize; ++$i) {
            $y = $by * $blockSize + $i;
            if ($y >= $height) {
                continue;
            }

            $result = self::placeBlockRowInResult($result, $block[$i], $y, $bx, $width, $blockSize);
        }

        return $result;
    }

    /**
     * 将图像块的一行放置到结果矩阵中
     *
     * @param array<int, array<int, float>> $result    结果矩阵
     * @param array<int, float>             $blockRow  图像块的一行
     * @param int                           $y         结果矩阵的Y坐标
     * @param int                           $bx        X方向的块索引
     * @param int                           $width     原始图像宽度
     * @param int                           $blockSize 分块大小
     *
     * @return array<int, array<int, float>> 更新后的结果矩阵
     */
    private static function placeBlockRowInResult(
        array $result,
        array $blockRow,
        int $y,
        int $bx,
        int $width,
        int $blockSize,
    ): array {
        for ($j = 0; $j < $blockSize; ++$j) {
            $x = $bx * $blockSize + $j;
            if ($x < $width) {
                $result[$y][$x] = $blockRow[$j];
            }
        }

        return $result;
    }

    /**
     * 对分块DCT系数进行逆变换，恢复图像
     *
     * 与blockIDCT功能相同，提供兼容性支持
     *
     * @param array<int, array<int, array<int, array<int, float>>>> $dctBlocks 分块DCT系数
     * @param int                                                   $blockSize 分块大小
     *
     * @return array<int, array<int, float>> 恢复的图像数据
     */
    public static function inverseBlockDCT(array $dctBlocks, int $blockSize = 8): array
    {
        $blocksY = count($dctBlocks);
        if (0 === $blocksY) {
            return [];
        }

        $blocksX = count($dctBlocks[0]);

        // 计算原始图像尺寸
        $height = $blocksY * $blockSize;
        $width = $blocksX * $blockSize;

        // 调用blockIDCT实现功能
        return self::blockIDCT($dctBlocks, $height, $width, $blockSize);
    }
}
