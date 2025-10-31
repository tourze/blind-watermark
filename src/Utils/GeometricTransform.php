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
     * @param array<int, array<int, int>> $original    原始图像通道数据
     * @param array<int, array<int, int>> $transformed 变换后的图像通道数据
     *
     * @return bool 是否水平翻转
     */
    public static function detectHorizontalFlip(array $original, array $transformed): bool
    {
        $height = count($original);
        if (0 === $height) {
            return false;
        }

        $width = count($original[0]);
        if (0 === $width) {
            return false;
        }

        // 采样图像中心区域的像素进行比较
        $sampleSize = min(10, floor($width / 4));
        $startY = intval($height / 2) - intval($sampleSize / 2);
        $startX = intval($width / 2) - intval($sampleSize / 2);

        $similarity = 0;
        $flippedSimilarity = 0;

        for ($y = 0; $y < $sampleSize; ++$y) {
            for ($x = 0; $x < $sampleSize; ++$x) {
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
     * @param array<int, array<int, int>> $original    原始图像通道数据
     * @param array<int, array<int, int>> $transformed 变换后的图像通道数据
     *
     * @return bool 是否垂直翻转
     */
    public static function detectVerticalFlip(array $original, array $transformed): bool
    {
        $height = count($original);
        if (0 === $height) {
            return false;
        }

        $width = count($original[0]);
        if (0 === $width) {
            return false;
        }

        // 采样图像中心区域的像素进行比较
        $sampleSize = min(10, floor($height / 4));
        $startY = intval($height / 2) - intval($sampleSize / 2);
        $startX = intval($width / 2) - intval($sampleSize / 2);

        $similarity = 0;
        $flippedSimilarity = 0;

        for ($y = 0; $y < $sampleSize; ++$y) {
            for ($x = 0; $x < $sampleSize; ++$x) {
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
     * @param array<int, array<int, int>> $original    原始图像通道数据
     * @param array<int, array<int, int>> $transformed 变换后的图像通道数据
     *
     * @return int 旋转角度 (0, 90, 180, 270)
     */
    public static function detectRotation(array $original, array $transformed): int
    {
        $originalDimensions = self::getDimensions($original);
        $transformedDimensions = self::getDimensions($transformed);

        $rotationConstraints = self::analyzeRotationConstraints($originalDimensions, $transformedDimensions);

        if (!$rotationConstraints['possible0or180'] && !$rotationConstraints['possible90or270']) {
            return 0; // 尺寸不匹配，无法确定
        }

        $sampleParams = self::calculateSampleParameters($originalDimensions);
        $similarities = self::calculateRotationSimilarities($original, $transformed, $sampleParams, $rotationConstraints);

        return self::findBestRotationAngle($similarities, $rotationConstraints);
    }

    /**
     * 获取图像尺寸
     *
     * @param array<int, array<int, int>> $image 图像通道数据
     *
     * @return array{height: int, width: int} 图像尺寸
     */
    private static function getDimensions(array $image): array
    {
        return [
            'height' => count($image),
            'width' => count($image[0] ?? []),
        ];
    }

    /**
     * 分析旋转约束条件
     *
     * @param array{height: int, width: int} $originalDim    原始图像尺寸
     * @param array{height: int, width: int} $transformedDim 变换后图像尺寸
     *
     * @return array{possible90or270: bool, possible0or180: bool} 约束条件
     */
    private static function analyzeRotationConstraints(array $originalDim, array $transformedDim): array
    {
        return [
            'possible90or270' => ($originalDim['height'] === $transformedDim['width'] && $originalDim['width'] === $transformedDim['height']),
            'possible0or180' => ($originalDim['height'] === $transformedDim['height'] && $originalDim['width'] === $transformedDim['width']),
        ];
    }

    /**
     * 计算采样参数
     *
     * @param array{height: int, width: int} $originalDim 原始图像尺寸
     *
     * @return array{sampleSize: int, startY: int, startX: int} 采样参数
     */
    private static function calculateSampleParameters(array $originalDim): array
    {
        $sampleSize = (int) min(10, floor(min($originalDim['height'], $originalDim['width']) / 4));

        return [
            'sampleSize' => $sampleSize,
            'startY' => intval($originalDim['height'] / 2) - intval($sampleSize / 2),
            'startX' => intval($originalDim['width'] / 2) - intval($sampleSize / 2),
        ];
    }

    /**
     * 计算各个旋转角度的相似度
     *
     * @param array<int, array<int, int>>                        $original     原始图像通道数据
     * @param array<int, array<int, int>>                        $transformed  变换后的图像通道数据
     * @param array{sampleSize: int, startY: int, startX: int}   $sampleParams 采样参数
     * @param array{possible90or270: bool, possible0or180: bool} $constraints  约束条件
     *
     * @return array<int, int> 各角度的相似度值
     */
    private static function calculateRotationSimilarities(array $original, array $transformed, array $sampleParams, array $constraints): array
    {
        $similarities = [0 => 0, 90 => 0, 180 => 0, 270 => 0];

        for ($y = 0; $y < $sampleParams['sampleSize']; ++$y) {
            for ($x = 0; $x < $sampleParams['sampleSize']; ++$x) {
                $pixelOriginal = $original[$sampleParams['startY'] + $y][$sampleParams['startX'] + $x];

                $similarities0And180 = self::calculateSimilarityFor0And180($pixelOriginal, $transformed, $sampleParams, $y, $x, $constraints);
                $similarities90And270 = self::calculateSimilarityFor90And270($pixelOriginal, $transformed, $sampleParams, $y, $x, $constraints, $original);

                $similarities[0] += $similarities0And180[0];
                $similarities[180] += $similarities0And180[180];
                $similarities[90] += $similarities90And270[90];
                $similarities[270] += $similarities90And270[270];
            }
        }

        return $similarities;
    }

    /**
     * 计算0度和180度旋转的相似度
     *
     * @param int                                                $pixelOriginal 原始像素值
     * @param array<int, array<int, int>>                        $transformed   变换后的图像通道数据
     * @param array{sampleSize: int, startY: int, startX: int}   $sampleParams  采样参数
     * @param int                                                $y             Y坐标偏移
     * @param int                                                $x             X坐标偏移
     * @param array{possible90or270: bool, possible0or180: bool} $constraints   约束条件
     *
     * @return array{0: int, 180: int} 0度和180度的相似度差异值
     */
    private static function calculateSimilarityFor0And180(int $pixelOriginal, array $transformed, array $sampleParams, int $y, int $x, array $constraints): array
    {
        if (!$constraints['possible0or180']) {
            return [0 => 0, 180 => 0];
        }

        // 0度旋转（无旋转）
        $pixel0 = $transformed[$sampleParams['startY'] + $y][$sampleParams['startX'] + $x];
        $diff0 = abs($pixelOriginal - $pixel0);

        // 180度旋转
        $heightOrig = count($transformed);
        $widthOrig = count($transformed[0]);
        $pixel180 = $transformed[$heightOrig - 1 - ($sampleParams['startY'] + $y)][$widthOrig - 1 - ($sampleParams['startX'] + $x)];
        $diff180 = abs($pixelOriginal - $pixel180);

        return [0 => $diff0, 180 => $diff180];
    }

    /**
     * 计算90度和270度旋转的相似度
     *
     * @param int                                                $pixelOriginal 原始像素值
     * @param array<int, array<int, int>>                        $transformed   变换后的图像通道数据
     * @param array{sampleSize: int, startY: int, startX: int}   $sampleParams  采样参数
     * @param int                                                $y             Y坐标偏移
     * @param int                                                $x             X坐标偏移
     * @param array{possible90or270: bool, possible0or180: bool} $constraints   约束条件
     * @param array<int, array<int, int>>                        $original      原始图像通道数据
     *
     * @return array{90: int, 270: int} 90度和270度的相似度差异值
     */
    private static function calculateSimilarityFor90And270(int $pixelOriginal, array $transformed, array $sampleParams, int $y, int $x, array $constraints, array $original): array
    {
        if (!$constraints['possible90or270']) {
            return [90 => 0, 270 => 0];
        }

        $heightOrig = count($original);
        $widthOrig = count($original[0]);

        // 90度旋转
        $pixel90 = $transformed[$sampleParams['startX'] + $x][$heightOrig - 1 - ($sampleParams['startY'] + $y)];
        $diff90 = abs($pixelOriginal - $pixel90);

        // 270度旋转
        $pixel270 = $transformed[$widthOrig - 1 - ($sampleParams['startX'] + $x)][$sampleParams['startY'] + $y];
        $diff270 = abs($pixelOriginal - $pixel270);

        return [90 => $diff90, 270 => $diff270];
    }

    /**
     * 找出最佳旋转角度
     *
     * @param array<int, int>                                    $similarities 各角度的相似度值
     * @param array{possible90or270: bool, possible0or180: bool} $constraints  约束条件
     *
     * @return int 最佳旋转角度
     */
    private static function findBestRotationAngle(array $similarities, array $constraints): int
    {
        $minDiff = PHP_INT_MAX;
        $bestAngle = 0;

        foreach ($similarities as $angle => $diff) {
            // 只考虑实际计算了相似度的角度
            if ((0 === $angle || 180 === $angle) && !$constraints['possible0or180']) {
                continue;
            }
            if ((90 === $angle || 270 === $angle) && !$constraints['possible90or270']) {
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
     * @param array<int, array<int, int>> $channel 图像通道数据
     *
     * @return array<int, array<int, int>> 翻转后的通道数据
     */
    public static function flipHorizontal(array $channel): array
    {
        $height = count($channel);
        $flipped = [];

        for ($y = 0; $y < $height; ++$y) {
            $width = count($channel[$y]);
            $flipped[$y] = [];

            for ($x = 0; $x < $width; ++$x) {
                $flipped[$y][$x] = $channel[$y][$width - 1 - $x];
            }
        }

        return $flipped;
    }

    /**
     * 垂直翻转图像通道
     *
     * @param array<int, array<int, int>> $channel 图像通道数据
     *
     * @return array<int, array<int, int>> 翻转后的通道数据
     */
    public static function flipVertical(array $channel): array
    {
        $height = count($channel);
        $flipped = [];

        for ($y = 0; $y < $height; ++$y) {
            $flipped[$y] = $channel[$height - 1 - $y];
        }

        return $flipped;
    }

    /**
     * 旋转图像通道（90度的倍数）
     *
     * @param array<int, array<int, int>> $channel 图像通道数据
     * @param int                         $angle   旋转角度 (90, 180, 270)
     *
     * @return array<int, array<int, int>> 旋转后的通道数据
     */
    public static function rotate(array $channel, int $angle): array
    {
        $dimensions = self::validateChannelDimensions($channel);
        if (null === $dimensions) {
            return [];
        }

        return match ($angle) {
            90 => self::rotate90($channel, $dimensions),
            180 => self::rotate180($channel, $dimensions),
            270 => self::rotate270($channel, $dimensions),
            default => $channel, // 不支持的角度，返回原始通道
        };
    }

    /**
     * 验证通道尺寸
     *
     * @param array<int, array<int, int>> $channel 图像通道数据
     *
     * @return array{height: int, width: int}|null 尺寸信息或null
     */
    private static function validateChannelDimensions(array $channel): ?array
    {
        $height = count($channel);
        if (0 === $height) {
            return null;
        }

        $width = count($channel[0]);
        if (0 === $width) {
            return null;
        }

        return ['height' => $height, 'width' => $width];
    }

    /**
     * 90度旋转
     *
     * @param array<int, array<int, int>>    $channel    图像通道数据
     * @param array{height: int, width: int} $dimensions 尺寸信息
     *
     * @return array<int, array<int, int>> 旋转后的通道数据
     */
    private static function rotate90(array $channel, array $dimensions): array
    {
        $rotated = [];

        for ($y = 0; $y < $dimensions['width']; ++$y) {
            $rotated[$y] = [];
            for ($x = 0; $x < $dimensions['height']; ++$x) {
                $rotated[$y][$x] = $channel[$dimensions['height'] - 1 - $x][$y];
            }
        }

        return $rotated;
    }

    /**
     * 180度旋转
     *
     * @param array<int, array<int, int>>    $channel    图像通道数据
     * @param array{height: int, width: int} $dimensions 尺寸信息
     *
     * @return array<int, array<int, int>> 旋转后的通道数据
     */
    private static function rotate180(array $channel, array $dimensions): array
    {
        $rotated = [];

        for ($y = 0; $y < $dimensions['height']; ++$y) {
            $rotated[$y] = [];
            for ($x = 0; $x < $dimensions['width']; ++$x) {
                $rotated[$y][$x] = $channel[$dimensions['height'] - 1 - $y][$dimensions['width'] - 1 - $x];
            }
        }

        return $rotated;
    }

    /**
     * 270度旋转
     *
     * @param array<int, array<int, int>>    $channel    图像通道数据
     * @param array{height: int, width: int} $dimensions 尺寸信息
     *
     * @return array<int, array<int, int>> 旋转后的通道数据
     */
    private static function rotate270(array $channel, array $dimensions): array
    {
        $rotated = [];

        for ($y = 0; $y < $dimensions['width']; ++$y) {
            $rotated[$y] = [];
            for ($x = 0; $x < $dimensions['height']; ++$x) {
                $rotated[$y][$x] = $channel[$x][$dimensions['width'] - 1 - $y];
            }
        }

        return $rotated;
    }

    /**
     * 根据检测到的几何变换修正图像通道
     *
     * @param array<int, array<int, int>> $channel             图像通道数据
     * @param bool                        $isHorizontalFlipped 是否水平翻转
     * @param bool                        $isVerticalFlipped   是否垂直翻转
     * @param int                         $rotationAngle       旋转角度
     *
     * @return array<int, array<int, int>> 修正后的通道数据
     */
    public static function correctGeometricTransform(
        array $channel,
        bool $isHorizontalFlipped,
        bool $isVerticalFlipped,
        int $rotationAngle,
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
