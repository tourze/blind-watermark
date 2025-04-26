<?php

namespace Tourze\BlindWatermark\Utils;

/**
 * 几何变换工具类
 *
 * 提供检测和修正图像几何变换（翻转、旋转等）的功能
 */
class GeometricTransform
{
    /**
     * 检测图像是否被水平翻转
     *
     * @param array $original 原始图像通道数据
     * @param array $transformed 变换后的图像通道数据
     * @return bool 是否水平翻转
     */
    public static function detectHorizontalFlip(array $original, array $transformed): bool
    {
        $height = count($original);
        if ($height === 0) {
            return false;
        }
        
        $width = count($original[0]);
        if ($width === 0) {
            return false;
        }
        
        // 采样图像中心区域的像素进行比较
        $sampleSize = min(10, floor($width / 4));
        $startY = intval($height / 2) - intval($sampleSize / 2);
        $startX = intval($width / 2) - intval($sampleSize / 2);
        
        $similarity = 0;
        $flippedSimilarity = 0;
        
        for ($y = 0; $y < $sampleSize; $y++) {
            for ($x = 0; $x < $sampleSize; $x++) {
                $pixelOriginal = $original[$startY + $y][$startX + $x];
                $pixelTransformed = $transformed[$startY + $y][$startX + $x];
                $pixelFlipped = $transformed[$startY + $y][$width - 1 - ($startX + $x)];
                
                $similarity += abs($pixelOriginal - $pixelTransformed);
                $flippedSimilarity += abs($pixelOriginal - $pixelFlipped);
            }
        }
        
        // 如果翻转的相似度更高，则判断为水平翻转
        return $flippedSimilarity < $similarity;
    }
    
    /**
     * 检测图像是否被垂直翻转
     *
     * @param array $original 原始图像通道数据
     * @param array $transformed 变换后的图像通道数据
     * @return bool 是否垂直翻转
     */
    public static function detectVerticalFlip(array $original, array $transformed): bool
    {
        $height = count($original);
        if ($height === 0) {
            return false;
        }
        
        $width = count($original[0]);
        if ($width === 0) {
            return false;
        }
        
        // 采样图像中心区域的像素进行比较
        $sampleSize = min(10, floor($height / 4));
        $startY = intval($height / 2) - intval($sampleSize / 2);
        $startX = intval($width / 2) - intval($sampleSize / 2);
        
        $similarity = 0;
        $flippedSimilarity = 0;
        
        for ($y = 0; $y < $sampleSize; $y++) {
            for ($x = 0; $x < $sampleSize; $x++) {
                $pixelOriginal = $original[$startY + $y][$startX + $x];
                $pixelTransformed = $transformed[$startY + $y][$startX + $x];
                $pixelFlipped = $transformed[$height - 1 - ($startY + $y)][$startX + $x];
                
                $similarity += abs($pixelOriginal - $pixelTransformed);
                $flippedSimilarity += abs($pixelOriginal - $pixelFlipped);
            }
        }
        
        // 如果翻转的相似度更高，则判断为垂直翻转
        return $flippedSimilarity < $similarity;
    }
    
    /**
     * 检测图像的旋转角度（当前仅支持检测90度的整数倍）
     *
     * @param array $original 原始图像通道数据
     * @param array $transformed 变换后的图像通道数据
     * @return int 旋转角度 (0, 90, 180, 270)
     */
    public static function detectRotation(array $original, array $transformed): int
    {
        $heightOrig = count($original);
        $widthOrig = count($original[0] ?? []);
        $heightTrans = count($transformed);
        $widthTrans = count($transformed[0] ?? []);
        
        // 检查尺寸，判断是否可能是90度或270度旋转
        $possible90or270 = ($heightOrig === $widthTrans && $widthOrig === $heightTrans);
        $possible0or180 = ($heightOrig === $heightTrans && $widthOrig === $widthTrans);
        
        if (!$possible0or180 && !$possible90or270) {
            return 0; // 尺寸不匹配，无法确定
        }
        
        // 计算不同旋转角度的相似度
        $similarities = [
            0 => 0,    // 无旋转
            90 => 0,   // 90度
            180 => 0,  // 180度
            270 => 0   // 270度
        ];
        
        // 采样点数量
        $sampleSize = min(10, floor(min($heightOrig, $widthOrig) / 4));
        $startY = intval($heightOrig / 2) - intval($sampleSize / 2);
        $startX = intval($widthOrig / 2) - intval($sampleSize / 2);
        
        // 计算每种旋转角度的相似度
        for ($y = 0; $y < $sampleSize; $y++) {
            for ($x = 0; $x < $sampleSize; $x++) {
                $pixelOriginal = $original[$startY + $y][$startX + $x];
                
                // 0度旋转（无旋转）
                if ($possible0or180) {
                    $pixel0 = $transformed[$startY + $y][$startX + $x];
                    $similarities[0] += abs($pixelOriginal - $pixel0);
                }
                
                // 90度旋转
                if ($possible90or270) {
                    $pixel90 = $transformed[$startX + $x][$heightOrig - 1 - ($startY + $y)];
                    $similarities[90] += abs($pixelOriginal - $pixel90);
                }
                
                // 180度旋转
                if ($possible0or180) {
                    $pixel180 = $transformed[$heightOrig - 1 - ($startY + $y)][$widthOrig - 1 - ($startX + $x)];
                    $similarities[180] += abs($pixelOriginal - $pixel180);
                }
                
                // 270度旋转
                if ($possible90or270) {
                    $pixel270 = $transformed[$widthOrig - 1 - ($startX + $x)][$startY + $y];
                    $similarities[270] += abs($pixelOriginal - $pixel270);
                }
            }
        }
        
        // 找出相似度最高（差异最小）的角度
        $minDiff = PHP_INT_MAX;
        $bestAngle = 0;
        
        foreach ($similarities as $angle => $diff) {
            // 只考虑实际计算了相似度的角度
            if (($angle == 0 || $angle == 180) && !$possible0or180) {
                continue;
            }
            if (($angle == 90 || $angle == 270) && !$possible90or270) {
                continue;
            }
            
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $bestAngle = $angle;
            }
        }
        
        return $bestAngle;
    }
    
    /**
     * 水平翻转图像通道
     *
     * @param array $channel 图像通道数据
     * @return array 翻转后的通道数据
     */
    public static function flipHorizontal(array $channel): array
    {
        $height = count($channel);
        $flipped = [];
        
        for ($y = 0; $y < $height; $y++) {
            $width = count($channel[$y]);
            $flipped[$y] = [];
            
            for ($x = 0; $x < $width; $x++) {
                $flipped[$y][$x] = $channel[$y][$width - 1 - $x];
            }
        }
        
        return $flipped;
    }
    
    /**
     * 垂直翻转图像通道
     *
     * @param array $channel 图像通道数据
     * @return array 翻转后的通道数据
     */
    public static function flipVertical(array $channel): array
    {
        $height = count($channel);
        $flipped = [];
        
        for ($y = 0; $y < $height; $y++) {
            $flipped[$y] = $channel[$height - 1 - $y];
        }
        
        return $flipped;
    }
    
    /**
     * 旋转图像通道（90度的倍数）
     *
     * @param array $channel 图像通道数据
     * @param int $angle 旋转角度 (90, 180, 270)
     * @return array 旋转后的通道数据
     */
    public static function rotate(array $channel, int $angle): array
    {
        $height = count($channel);
        if ($height === 0) {
            return [];
        }
        
        $width = count($channel[0]);
        if ($width === 0) {
            return [];
        }
        
        $rotated = [];
        
        switch ($angle) {
            case 90:
                // 90度旋转
                for ($y = 0; $y < $width; $y++) {
                    $rotated[$y] = [];
                    for ($x = 0; $x < $height; $x++) {
                        $rotated[$y][$x] = $channel[$height - 1 - $x][$y];
                    }
                }
                break;
                
            case 180:
                // 180度旋转
                for ($y = 0; $y < $height; $y++) {
                    $rotated[$y] = [];
                    for ($x = 0; $x < $width; $x++) {
                        $rotated[$y][$x] = $channel[$height - 1 - $y][$width - 1 - $x];
                    }
                }
                break;
                
            case 270:
                // 270度旋转
                for ($y = 0; $y < $width; $y++) {
                    $rotated[$y] = [];
                    for ($x = 0; $x < $height; $x++) {
                        $rotated[$y][$x] = $channel[$x][$width - 1 - $y];
                    }
                }
                break;
                
            default:
                // 不支持的角度，返回原始通道
                return $channel;
        }
        
        return $rotated;
    }
    
    /**
     * 根据检测到的几何变换修正图像通道
     *
     * @param array $channel 图像通道数据
     * @param bool $isHorizontalFlipped 是否水平翻转
     * @param bool $isVerticalFlipped 是否垂直翻转
     * @param int $rotationAngle 旋转角度
     * @return array 修正后的通道数据
     */
    public static function correctGeometricTransform(
        array $channel,
        bool $isHorizontalFlipped,
        bool $isVerticalFlipped,
        int $rotationAngle
    ): array {
        $corrected = $channel;
        
        // 先修正旋转
        if ($rotationAngle > 0) {
            // 逆向旋转
            $inverseAngle = (360 - $rotationAngle) % 360;
            if ($inverseAngle > 0) {
                $corrected = self::rotate($corrected, $inverseAngle);
            }
        }
        
        // 修正水平翻转
        if ($isHorizontalFlipped) {
            $corrected = self::flipHorizontal($corrected);
        }
        
        // 修正垂直翻转
        if ($isVerticalFlipped) {
            $corrected = self::flipVertical($corrected);
        }
        
        return $corrected;
    }
} 