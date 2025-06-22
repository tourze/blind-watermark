<?php

namespace Tourze\BlindWatermark;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * 图像几何变换处理类
 * 
 * 负责图像的翻转、旋转等几何变换操作
 */
class ImageTransformer
{
    /**
     * 水平翻转
     */
    public const FLIP_HORIZONTAL = 'horizontal';
    
    /**
     * 垂直翻转
     */
    public const FLIP_VERTICAL = 'vertical';
    
    /**
     * 图像处理器
     */
    protected ImageProcessor $processor;
    
    /**
     * 日志记录器
     */
    protected LoggerInterface $logger;
    
    /**
     * 构造函数
     *
     * @param ImageProcessor $processor 图像处理器
     * @param LoggerInterface|null $logger 日志记录器
     */
    public function __construct(ImageProcessor $processor, ?LoggerInterface $logger = null)
    {
        $this->processor = $processor;
        $this->logger = $logger ?? new NullLogger();
    }
    
    /**
     * 翻转图像
     *
     * @param string $direction 翻转方向，可选值：horizontal(水平), vertical(垂直)
     * @return bool 操作是否成功
     * @throws \Exception 参数无效或操作失败时抛出异常
     */
    public function flip(string $direction): bool
    {
        if ($direction !== self::FLIP_HORIZONTAL && $direction !== self::FLIP_VERTICAL) {
            throw new \Exception("无效的翻转方向，可选值: horizontal, vertical");
        }
        
        $this->logger->debug("翻转图像: {$direction}");
        
        // 获取图像尺寸
        $width = $this->processor->getWidth();
        $height = $this->processor->getHeight();
        
        if ($width <= 0 || $height <= 0) {
            throw new \Exception("无效的图像尺寸");
        }
        
        // 分离通道
        $channels = $this->processor->splitChannels();
        
        // 进行翻转操作
        if ($direction === self::FLIP_HORIZONTAL) {
            foreach (['red', 'green', 'blue'] as $channel) {
                for ($y = 0; $y < $height; $y++) {
                    $channels[$channel][$y] = array_reverse($channels[$channel][$y]);
                }
            }
        } else { // FLIP_VERTICAL
            foreach (['red', 'green', 'blue'] as $channel) {
                $flippedChannel = array_reverse($channels[$channel]);
                $channels[$channel] = $flippedChannel;
            }
        }
        
        // 合并通道回图像
        $this->processor->mergeChannels($channels);
        return true;
    }
    
    /**
     * 旋转图像
     *
     * @param float $angle 旋转角度，正值为顺时针，负值为逆时针
     * @return bool 操作是否成功
     * @throws \Exception 参数无效或操作失败时抛出异常
     */
    public function rotate(float $angle): bool
    {
        $this->logger->debug("旋转图像: {$angle}度");
        
        // 获取图像尺寸
        $width = $this->processor->getWidth();
        $height = $this->processor->getHeight();
        
        if ($width <= 0 || $height <= 0) {
            throw new \Exception("无效的图像尺寸");
        }
        
        // 分离通道，进行旋转计算较为复杂，这里只处理常见的90/180/270度旋转
        $channels = $this->processor->splitChannels();
        
        // 归一化角度到0-360度范围
        $normalizedAngle = fmod($angle, 360);
        if ($normalizedAngle < 0) {
            $normalizedAngle += 360;
        }
        
        // 90度的倍数便于实现
        if (abs($normalizedAngle - 90) < 0.001) {
            $this->rotateChannels90($channels);
        } elseif (abs($normalizedAngle - 180) < 0.001) {
            $this->rotateChannels180($channels);
        } elseif (abs($normalizedAngle - 270) < 0.001) {
            $this->rotateChannels270($channels);
        } else {
            throw new \Exception("当前仅支持90度的倍数旋转");
        }
        
        // 合并通道回图像
        $this->processor->mergeChannels($channels);
        return true;
    }
    
    /**
     * 90度旋转通道数据
     *
     * @param array &$channels 通道数据，会被直接修改
     * @return void
     */
    protected function rotateChannels90(array &$channels): void
    {
        $height = count($channels['red']);
        $width = count($channels['red'][0]);
        
        foreach (['red', 'green', 'blue'] as $channel) {
            $rotated = [];
            
            // 初始化新的二维数组
            for ($x = 0; $x < $width; $x++) {
                $rotated[$x] = [];
            }
            
            // 90度顺时针旋转: (x,y) -> (y, width-1-x)
            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    $rotated[$width - 1 - $y][$x] = $channels[$channel][$x][$y];
                }
            }
            
            $channels[$channel] = $rotated;
        }
    }
    
    /**
     * 180度旋转通道数据
     *
     * @param array &$channels 通道数据，会被直接修改
     * @return void
     */
    protected function rotateChannels180(array &$channels): void
    {
        foreach (['red', 'green', 'blue'] as $channel) {
            // 水平翻转每一行
            foreach ($channels[$channel] as &$row) {
                $row = array_reverse($row);
            }
            
            // 垂直翻转所有行
            $channels[$channel] = array_reverse($channels[$channel]);
        }
    }
    
    /**
     * 270度旋转通道数据
     *
     * @param array &$channels 通道数据，会被直接修改
     * @return void
     */
    protected function rotateChannels270(array &$channels): void
    {
        $height = count($channels['red']);
        $width = count($channels['red'][0]);
        
        foreach (['red', 'green', 'blue'] as $channel) {
            $rotated = [];
            
            // 初始化新的二维数组
            for ($x = 0; $x < $width; $x++) {
                $rotated[$x] = [];
            }
            
            // 270度顺时针旋转: (x,y) -> (width-1-y, x)
            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    $rotated[$y][$width - 1 - $x] = $channels[$channel][$x][$y];
                }
            }
            
            $channels[$channel] = $rotated;
        }
    }
    
    /**
     * 检测图像的旋转角度
     *
     * 通过比较与参考图像的相似度，估计旋转角度
     *
     * @param string $referencePath 参考图像路径
     * @return float|null 估计的旋转角度，如果无法检测则返回null
     */
    public function detectRotationAngle(string $referencePath): ?float
    {
        // 创建临时图像处理器加载参考图像
        $referenceProcessor = new ImageProcessor($this->logger);
        if (!$referenceProcessor->loadFromFile($referencePath)) {
            $this->logger->error("无法加载参考图像: {$referencePath}");
            return null;
        }
        
        $bestAngle = null;
        $bestScore = -1;
        
        // 测试不同角度(0, 90, 180, 270)的相似度
        foreach ([0, 90, 180, 270] as $angle) {
            // 创建当前图像的副本
            $tempProcessor = clone $this->processor;
            
            // 旋转图像以测试相似度
            if ($angle !== 0) {
                $tempTransformer = new self($tempProcessor, $this->logger);
                $tempTransformer->rotate($angle);
            }
            
            // 计算与参考图像的相似度
            $score = $this->calculateSimilarity($tempProcessor, $referenceProcessor);
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestAngle = $angle;
            }
        }
        
        $this->logger->debug("检测到旋转角度: {$bestAngle}度，相似度分数: {$bestScore}");
        return $bestAngle;
    }
    
    /**
     * 检测图像的翻转方向
     *
     * 通过与参考图像比较，检测是否存在翻转
     *
     * @param string $referencePath 参考图像路径
     * @return string|null 检测到的翻转方向，如果没有翻转返回null
     */
    public function detectFlipDirection(string $referencePath): ?string
    {
        // 创建临时图像处理器加载参考图像
        $referenceProcessor = new ImageProcessor($this->logger);
        if (!$referenceProcessor->loadFromFile($referencePath)) {
            $this->logger->error("无法加载参考图像: {$referencePath}");
            return null;
        }
        
        // 计算原始相似度
        $originalScore = $this->calculateSimilarity($this->processor, $referenceProcessor);
        
        // 测试水平翻转
        $horizontalProcessor = clone $this->processor;
        $horizontalTransformer = new self($horizontalProcessor, $this->logger);
        $horizontalTransformer->flip(self::FLIP_HORIZONTAL);
        $horizontalScore = $this->calculateSimilarity($horizontalProcessor, $referenceProcessor);
        
        // 测试垂直翻转
        $verticalProcessor = clone $this->processor;
        $verticalTransformer = new self($verticalProcessor, $this->logger);
        $verticalTransformer->flip(self::FLIP_VERTICAL);
        $verticalScore = $this->calculateSimilarity($verticalProcessor, $referenceProcessor);
        
        // 找出最高分数对应的方向
        $bestScore = max($originalScore, $horizontalScore, $verticalScore);
        
        if ($bestScore === $originalScore) {
            $this->logger->debug("未检测到翻转");
            return null;
        } elseif ($bestScore === $horizontalScore) {
            $this->logger->debug("检测到水平翻转");
            return self::FLIP_HORIZONTAL;
        } else {
            $this->logger->debug("检测到垂直翻转");
            return self::FLIP_VERTICAL;
        }
    }
    
    /**
     * 计算两个图像的相似度
     *
     * @param ImageProcessor $image1 第一个图像
     * @param ImageProcessor $image2 第二个图像
     * @return float 相似度分数，范围0-1，越高表示越相似
     */
    protected function calculateSimilarity(ImageProcessor $image1, ImageProcessor $image2): float
    {
        // 获取图像尺寸
        $width1 = $image1->getWidth();
        $height1 = $image1->getHeight();
        $width2 = $image2->getWidth();
        $height2 = $image2->getHeight();
        
        // 尺寸不一致时，无法直接比较
        if ($width1 !== $width2 || $height1 !== $height2) {
            $this->logger->warning("图像尺寸不一致，无法计算准确的相似度");
            return 0;
        }
        
        // 分离通道
        $channels1 = $image1->splitChannels();
        $channels2 = $image2->splitChannels();
        
        // 计算每个通道的相似度并求平均
        $channelSimilarities = [];
        
        foreach (['red', 'green', 'blue'] as $channel) {
            $sumSquaredDiff = 0;
            $pixelCount = 0;
            
            for ($y = 0; $y < $height1; $y++) {
                for ($x = 0; $x < $width1; $x++) {
                    $diff = $channels1[$channel][$y][$x] - $channels2[$channel][$y][$x];
                    $sumSquaredDiff += $diff * $diff;
                    $pixelCount++;
                }
            }
            
            // 使用均方根误差(RMSE)的倒数作为相似度指标
            $rmse = ($pixelCount > 0) ? sqrt($sumSquaredDiff / $pixelCount) : 0;
            // 归一化到0-1范围，255是像素值的最大差异
            $channelSimilarities[$channel] = 1 - ($rmse / 255);
        }
        
        // 计算三个通道的平均相似度
        return array_sum($channelSimilarities) / count($channelSimilarities);
    }
} 