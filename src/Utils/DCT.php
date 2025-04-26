<?php

namespace Tourze\BlindWatermark\Utils;

/**
 * 离散余弦变换(DCT)工具类
 * 
 * DCT是图像处理中常用的变换方法，广泛应用于JPEG压缩和数字水印技术。
 * 本实现提供了标准DCT和优化的快速DCT两种实现方式。
 * 
 * DCT公式:
 * F(u,v) = (2/sqrt(MN)) * C(u) * C(v) * sum[sum[f(i,j) * cos((2i+1)uπ/2M) * cos((2j+1)vπ/2N)]]
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
     * @param array $matrix 输入矩阵
     * @return array 变换后的DCT系数矩阵
     */
    public static function forward(array $matrix): array
    {
        $m = count($matrix);
        if ($m === 0) {
            return [];
        }
        
        $n = count($matrix[0]);
        
        // 使用快速算法
        if (self::$useFastImplementation) {
            return self::fastForward($matrix, $m, $n);
        }
        
        // 使用原始算法
        $result = array_fill(0, $m, array_fill(0, $n, 0.0));
        
        // 二维DCT变换实现
        for ($u = 0; $u < $m; $u++) {
            for ($v = 0; $v < $n; $v++) {
                $sum = 0.0;
                
                // 计算α(u)和α(v)系数
                $alpha_u = ($u === 0) ? 1 / sqrt(2) : 1;
                $alpha_v = ($v === 0) ? 1 / sqrt(2) : 1;
                
                // 二维DCT变换公式计算
                for ($i = 0; $i < $m; $i++) {
                    for ($j = 0; $j < $n; $j++) {
                        $sum += $matrix[$i][$j] * 
                                cos((2 * $i + 1) * $u * M_PI / (2 * $m)) * 
                                cos((2 * $j + 1) * $v * M_PI / (2 * $n));
                    }
                }
                
                // 结果乘以系数
                $result[$u][$v] = $alpha_u * $alpha_v * $sum * 2 / sqrt($m * $n);
            }
        }
        
        return $result;
    }
    
    /**
     * 快速DCT正变换实现
     * 
     * 使用缓存的余弦值和分离变量优化计算性能
     *
     * @param array $matrix 输入矩阵
     * @param int $m 矩阵高度
     * @param int $n 矩阵宽度
     * @return array 变换后的DCT系数矩阵
     */
    public static function fastForward(array $matrix, int $m, int $n): array
    {
        $result = array_fill(0, $m, array_fill(0, $n, 0.0));
        
        // 预计算系数和余弦值
        self::ensureCosineCache($m, $n);
        
        // 二维DCT变换实现
        for ($u = 0; $u < $m; $u++) {
            for ($v = 0; $v < $n; $v++) {
                $sum = 0.0;
                
                // 计算α(u)和α(v)系数
                $alpha_u = ($u === 0) ? 1 / sqrt(2) : 1;
                $alpha_v = ($v === 0) ? 1 / sqrt(2) : 1;
                
                // 使用缓存的余弦值计算
                for ($i = 0; $i < $m; $i++) {
                    $cosU = self::$cosineCache["$i:$u:$m"];
                    for ($j = 0; $j < $n; $j++) {
                        $cosV = self::$cosineCache["$j:$v:$n"];
                        $sum += $matrix[$i][$j] * $cosU * $cosV;
                    }
                }
                
                // 结果乘以系数
                $result[$u][$v] = $alpha_u * $alpha_v * $sum * 2 / sqrt($m * $n);
            }
        }
        
        return $result;
    }
    
    /**
     * 对DCT系数矩阵进行逆变换(IDCT)，恢复原始图像数据
     *
     * @param array $dctMatrix DCT系数矩阵
     * @return array 恢复的原始图像数据
     */
    public static function inverse(array $dctMatrix): array
    {
        $m = count($dctMatrix);
        if ($m === 0) {
            return [];
        }
        
        $n = count($dctMatrix[0]);
        
        // 使用快速算法
        if (self::$useFastImplementation) {
            return self::fastInverse($dctMatrix, $m, $n);
        }
        
        // 使用原始算法
        $result = array_fill(0, $m, array_fill(0, $n, 0.0));
        
        // 二维IDCT变换实现
        for ($i = 0; $i < $m; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $sum = 0.0;
                
                for ($u = 0; $u < $m; $u++) {
                    for ($v = 0; $v < $n; $v++) {
                        // 计算α(u)和α(v)系数
                        $alpha_u = ($u === 0) ? 1 / sqrt(2) : 1;
                        $alpha_v = ($v === 0) ? 1 / sqrt(2) : 1;
                        
                        // 二维IDCT变换公式计算
                        $sum += $alpha_u * $alpha_v * $dctMatrix[$u][$v] * 
                                cos((2 * $i + 1) * $u * M_PI / (2 * $m)) * 
                                cos((2 * $j + 1) * $v * M_PI / (2 * $n));
                    }
                }
                
                // 结果乘以系数
                $result[$i][$j] = $sum * 2 / sqrt($m * $n);
            }
        }
        
        return $result;
    }
    
    /**
     * 快速DCT逆变换实现
     * 
     * 使用缓存的余弦值和分离变量优化计算性能
     *
     * @param array $dctMatrix DCT系数矩阵
     * @param int $m 矩阵高度
     * @param int $n 矩阵宽度
     * @return array 恢复的原始图像数据
     */
    public static function fastInverse(array $dctMatrix, int $m, int $n): array
    {
        $result = array_fill(0, $m, array_fill(0, $n, 0.0));
        
        // 预计算系数和余弦值
        self::ensureCosineCache($m, $n);
        
        // 二维IDCT变换实现
        for ($i = 0; $i < $m; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $sum = 0.0;
                
                for ($u = 0; $u < $m; $u++) {
                    $cosU = self::$cosineCache["$i:$u:$m"];
                    $alpha_u = ($u === 0) ? 1 / sqrt(2) : 1;
                    
                    for ($v = 0; $v < $n; $v++) {
                        $cosV = self::$cosineCache["$j:$v:$n"];
                        $alpha_v = ($v === 0) ? 1 / sqrt(2) : 1;
                        
                        $sum += $alpha_u * $alpha_v * $dctMatrix[$u][$v] * $cosU * $cosV;
                    }
                }
                
                // 结果乘以系数
                $result[$i][$j] = $sum * 2 / sqrt($m * $n);
            }
        }
        
        return $result;
    }
    
    /**
     * 确保余弦缓存表已初始化
     *
     * @param int $m 矩阵高度
     * @param int $n 矩阵宽度
     */
    protected static function ensureCosineCache(int $m, int $n): void
    {
        // 计算行方向的余弦值
        for ($i = 0; $i < $m; $i++) {
            for ($u = 0; $u < $m; $u++) {
                $key = "$i:$u:$m";
                if (!isset(self::$cosineCache[$key])) {
                    self::$cosineCache[$key] = cos((2 * $i + 1) * $u * M_PI / (2 * $m));
                }
            }
        }
        
        // 计算列方向的余弦值
        for ($j = 0; $j < $n; $j++) {
            for ($v = 0; $v < $n; $v++) {
                $key = "$j:$v:$n";
                if (!isset(self::$cosineCache[$key])) {
                    self::$cosineCache[$key] = cos((2 * $j + 1) * $v * M_PI / (2 * $n));
                }
            }
        }
    }
    
    /**
     * 对图像进行分块DCT变换
     *
     * @param array $imageData 图像数据
     * @param int $blockSize 分块大小，默认为8x8
     * @return array 分块DCT变换结果
     */
    public static function blockDCT(array $imageData, int $blockSize = 8): array
    {
        $height = count($imageData);
        if ($height === 0) {
            return [];
        }
        
        $width = count($imageData[0]);
        $result = [];
        
        // 计算需要的块数
        $blocksY = ceil($height / $blockSize);
        $blocksX = ceil($width / $blockSize);
        
        for ($by = 0; $by < $blocksY; $by++) {
            $result[$by] = [];
            for ($bx = 0; $bx < $blocksX; $bx++) {
                // 提取当前块
                $block = [];
                for ($i = 0; $i < $blockSize; $i++) {
                    $y = $by * $blockSize + $i;
                    if ($y >= $height) {
                        // 填充0
                        $block[$i] = array_fill(0, $blockSize, 0);
                        continue;
                    }
                    
                    $block[$i] = [];
                    for ($j = 0; $j < $blockSize; $j++) {
                        $x = $bx * $blockSize + $j;
                        $block[$i][$j] = ($x < $width) ? $imageData[$y][$x] : 0;
                    }
                }
                
                // 对当前块进行DCT变换
                $result[$by][$bx] = self::forward($block);
            }
        }
        
        return $result;
    }
    
    /**
     * 对分块DCT系数进行逆变换，恢复图像
     *
     * @param array $dctBlocks 分块DCT系数
     * @param int $height 原始图像高度
     * @param int $width 原始图像宽度
     * @param int $blockSize 分块大小
     * @return array 恢复的图像数据
     */
    public static function blockIDCT(array $dctBlocks, int $height, int $width, int $blockSize = 8): array
    {
        $result = array_fill(0, $height, array_fill(0, $width, 0));
        
        $blocksY = count($dctBlocks);
        if ($blocksY === 0) {
            return $result;
        }
        
        $blocksX = count($dctBlocks[0]);
        
        for ($by = 0; $by < $blocksY; $by++) {
            for ($bx = 0; $bx < $blocksX; $bx++) {
                // 对当前块进行IDCT变换
                $block = self::inverse($dctBlocks[$by][$bx]);
                
                // 将恢复的块放回原始位置
                for ($i = 0; $i < $blockSize; $i++) {
                    $y = $by * $blockSize + $i;
                    if ($y >= $height) {
                        continue;
                    }
                    
                    for ($j = 0; $j < $blockSize; $j++) {
                        $x = $bx * $blockSize + $j;
                        if ($x >= $width) {
                            continue;
                        }
                        
                        $result[$y][$x] = $block[$i][$j];
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * 对分块DCT系数进行逆变换，恢复图像
     * 
     * 与blockIDCT功能相同，提供兼容性支持
     *
     * @param array $dctBlocks 分块DCT系数
     * @param int $blockSize 分块大小
     * @return array 恢复的图像数据
     */
    public static function inverseBlockDCT(array $dctBlocks, int $blockSize = 8): array
    {
        $blocksY = count($dctBlocks);
        if ($blocksY === 0) {
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
