<?php

namespace Tourze\BlindWatermark;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tourze\BlindWatermark\Utils\DCT;

/**
 * 水印嵌入类
 *
 * 实现向图像中嵌入水印文本的功能。此类使用DCT（离散余弦变换）域水印嵌入技术，
 * 将文本水印比特嵌入到图像特定频率位置，使水印隐蔽且具有一定的抗攻击能力。
 *
 * 嵌入算法基本步骤:
 * 1. 将文本转换为二进制比特流
 * 2. 为比特流添加16位长度头部信息
 * 3. 对图像通道进行分块DCT变换
 * 4. 根据水印比特值，修改DCT块中指定位置的系数值（正值表示1，负值表示0）
 * 5. 对修改后的DCT块进行逆变换，得到嵌入水印的图像
 *
 * 重要参数:
 * - 分块大小：影响水印容量和质量，通常为8x8
 * - 嵌入强度：影响水印的可见度和鲁棒性
 * - 嵌入位置：DCT块中系数的位置，中频位置通常是最佳选择
 */
class WatermarkEmbedder
{
    /**
     * DCT分块大小，默认为8x8
     */
    protected int $blockSize = 8;

    /**
     * 水印强度参数，决定水印的可见度和鲁棒性
     */
    protected float $strength = 36.0;

    /**
     * 水印嵌入位置参数
     */
    protected array $position = [3, 4];
    
    /**
     * 是否启用对称性嵌入（增强抗翻转攻击能力）
     */
    protected bool $useSymmetricEmbedding = false;
    
    /**
     * 对称嵌入的位置参数（默认为原位置的对称位置）
     */
    protected array $symmetricPositions = [];
    
    /**
     * 是否启用多点嵌入（增强鲁棒性）
     */
    protected bool $useMultiPointEmbedding = false;
    
    /**
     * 多点嵌入的位置参数数组
     */
    protected array $multiPoints = [];

    /**
     * 日志记录器
     */
    protected LoggerInterface $logger;

    /**
     * 构造函数
     *
     * @param LoggerInterface|null $logger 可选的日志记录器
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
        
        // 设置默认的对称位置（基于主位置的对称）
        $this->updateSymmetricPositions();
        
        // 设置默认的多点嵌入位置
        $this->multiPoints = [
            [3, 5], // 附近的中频系数
            [4, 3],
            [5, 3]
        ];
    }

    /**
     * 设置日志记录器
     *
     * @param LoggerInterface $logger 日志记录器
     * @return self
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * 设置DCT分块大小
     *
     * @param int $blockSize 分块大小
     * @return self
     */
    public function setBlockSize(int $blockSize): self
    {
        $this->blockSize = $blockSize;
        $this->logger->debug("设置DCT分块大小: {$blockSize}");
        return $this;
    }

    /**
     * 设置水印强度
     *
     * @param float $strength 强度值
     * @return self
     */
    public function setStrength(float $strength): self
    {
        $this->strength = $strength;
        $this->logger->debug("设置水印强度: {$strength}");
        return $this;
    }

    /**
     * 设置水印强度（setStrength的别名，保持向后兼容）
     *
     * @param float $alpha 强度值
     * @return self
     */
    public function setAlpha(float $alpha): self
    {
        return $this->setStrength($alpha);
    }

    /**
     * 设置水印嵌入位置
     *
     * @param array $position 位置数组 [row, col]
     * @return self
     */
    public function setPosition(array $position): self
    {
        $this->position = $position;
        $this->logger->debug("设置水印嵌入位置: [" . implode(',', $position) . "]");
        // 更新对称位置
        $this->updateSymmetricPositions();
        return $this;
    }
    
    /**
     * 启用或禁用对称性嵌入
     *
     * @param bool $enabled 是否启用
     * @return self
     */
    public function setSymmetricEmbedding(bool $enabled): self
    {
        $this->useSymmetricEmbedding = $enabled;
        $this->logger->debug("对称性嵌入: " . ($enabled ? '启用' : '禁用'));
        return $this;
    }
    
    /**
     * 启用或禁用多点嵌入
     *
     * @param bool $enabled 是否启用
     * @return self
     */
    public function setMultiPointEmbedding(bool $enabled): self
    {
        $this->useMultiPointEmbedding = $enabled;
        $this->logger->debug("多点嵌入: " . ($enabled ? '启用' : '禁用'));
        return $this;
    }
    
    /**
     * 设置多点嵌入的位置集合
     *
     * @param array $points 位置数组集合，每个元素为 [row, col]
     * @return self
     */
    public function setMultiPoints(array $points): self
    {
        $this->multiPoints = $points;
        $this->logger->debug("设置多点嵌入位置，共 " . count($points) . " 个点");
        return $this;
    }
    
    /**
     * 更新对称嵌入位置
     */
    protected function updateSymmetricPositions(): void
    {
        // 基于8x8 DCT块，计算主位置的水平、垂直和对角对称位置
        $this->symmetricPositions = [
            // 水平对称：使水印对水平翻转具有鲁棒性
            [$this->position[0], $this->blockSize - 1 - $this->position[1]],
            // 垂直对称：使水印对垂直翻转具有鲁棒性
            [$this->blockSize - 1 - $this->position[0], $this->position[1]],
            // 对角对称：使水印对180度旋转具有鲁棒性
            [$this->blockSize - 1 - $this->position[0], $this->blockSize - 1 - $this->position[1]]
        ];
    }

    /**
     * 将文本转换为比特数组
     *
     * @param string $text 要转换的文本
     * @return array 转换后的比特数组
     */
    protected function textToBits(string $text): array
    {
        $bits = [];
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $byte = ord($text[$i]);
            for ($j = 7; $j >= 0; $j--) {
                $bits[] = ($byte >> $j) & 1;
            }
        }

        $this->logger->debug("文本转换为比特数组，长度: " . count($bits) . " 比特");
        return $bits;
    }

    /**
     * 向DCT块中嵌入一个水印比特
     *
     * @param array $dctBlock DCT系数块
     * @param int $bit 要嵌入的比特值(0或1)
     * @param array $position 嵌入位置 [row, col]
     * @return array 嵌入水印后的DCT块
     */
    protected function embedBitInBlock(array $dctBlock, int $bit, array $position): array
    {
        $row = $position[0];
        $col = $position[1];
        
        // 根据比特值设置DCT系数
        if ($bit == 1) {
            $dctBlock[$row][$col] = $this->strength;
        } else {
            $dctBlock[$row][$col] = -$this->strength;
        }
        
        return $dctBlock;
    }

    /**
     * 向图像通道嵌入水印比特
     *
     * @param array $channel 图像通道数据
     * @param array $bits 水印比特数组
     * @return array 嵌入水印后的通道数据
     */
    protected function embedWatermarkInChannel(array $channel, array $bits): array
    {
        try {
            // 对图像数据进行分块DCT变换
            $dctBlocks = DCT::blockDCT($channel, $this->blockSize);

            // 计算可用于嵌入的块数量
            $blocksY = count($dctBlocks);
            $blocksX = count($dctBlocks[0]);
            $totalBlocks = $blocksX * $blocksY;

            // 确保水印比特数量不超过可用块数
            $bitsToEmbed = min(count($bits), $totalBlocks);
            $this->logger->info("可用块数: {$totalBlocks}，需嵌入比特数: {$bitsToEmbed}");

            if ($bitsToEmbed < count($bits)) {
                $this->logger->warning("可用DCT块数量不足，水印将被截断");
            }

            // 嵌入水印比特
            $bitIndex = 0;

            for ($by = 0; $by < $blocksY && $bitIndex < $bitsToEmbed; $by++) {
                for ($bx = 0; $bx < $blocksX && $bitIndex < $bitsToEmbed; $bx++) {
                    // 设置指定位置的DCT系数
                    $row = $this->position[0];
                    $col = $this->position[1];
                    $bit = $bits[$bitIndex];

                    // 在主位置嵌入水印比特
                    if ($bit == 1) {
                        $dctBlocks[$by][$bx][$row][$col] = $this->strength;
                    } else {
                        $dctBlocks[$by][$bx][$row][$col] = -$this->strength;
                    }
                    
                    // 对称性嵌入：在对称位置同时嵌入相同比特，提高抗翻转攻击能力
                    if ($this->useSymmetricEmbedding) {
                        foreach ($this->symmetricPositions as $symPos) {
                            $symRow = $symPos[0];
                            $symCol = $symPos[1];
                            
                            if ($bit == 1) {
                                $dctBlocks[$by][$bx][$symRow][$symCol] = $this->strength;
                            } else {
                                $dctBlocks[$by][$bx][$symRow][$symCol] = -$this->strength;
                            }
                        }
                    }
                    
                    // 多点嵌入：在多个位置嵌入相同比特，提高鲁棒性
                    if ($this->useMultiPointEmbedding) {
                        foreach ($this->multiPoints as $point) {
                            $mpRow = $point[0];
                            $mpCol = $point[1];
                            
                            // 确保位置在有效范围内
                            if ($mpRow < $this->blockSize && $mpCol < $this->blockSize) {
                                if ($bit == 1) {
                                    $dctBlocks[$by][$bx][$mpRow][$mpCol] = $this->strength * 0.8; // 稍微降低强度
                                } else {
                                    $dctBlocks[$by][$bx][$mpRow][$mpCol] = -$this->strength * 0.8;
                                }
                            }
                        }
                    }

                    $bitIndex++;
                }
            }

            // 记录嵌入进度
            $this->logger->debug("已嵌入 {$bitIndex} 比特的水印数据");

            // 进行逆DCT变换，得到嵌入水印后的图像通道
            $height = count($channel);
            $width = count($channel[0]);
            return DCT::blockIDCT($dctBlocks, $height, $width, $this->blockSize);
        } catch (\Throwable $e) {
            $this->logger->error("嵌入水印时发生错误: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }

    /**
     * 向图像中嵌入文本水印
     *
     * @param ImageProcessor $image 图像处理器
     * @param string $text 要嵌入的文本
     * @return ImageProcessor 嵌入水印后的图像处理器
     * @throws \Exception 嵌入过程中可能发生的异常
     */
    public function embed(ImageProcessor $image, string $text): ImageProcessor
    {
        try {
            // 将文本转换为比特数组
            $textBits = $this->textToBits($text);
            $bitLength = count($textBits);
            
            $this->logger->info("开始嵌入水印");
            $this->logger->debug("文本长度: " . strlen($text) . " 字符, " . $bitLength . " 比特");
            
            // 使用16位表示长度（最多支持8192字符）
            $lengthBits = [];
            for ($i = 15; $i >= 0; $i--) {
                $lengthBits[] = ($bitLength >> $i) & 1;
            }
            
            // 记录长度编码信息
            $lengthDebug = '';
            foreach ($lengthBits as $bit) {
                $lengthDebug .= $bit;
            }
            $this->logger->debug("长度编码: " . $lengthDebug . " (" . bindec($lengthDebug) . ")");
            
            // 将长度信息和文本比特合并
            $allBits = array_merge($lengthBits, $textBits);

            // 分离图像通道
            $channels = $image->splitChannels();
            
            // 仅在蓝色通道嵌入水印（对人眼最不敏感）
            $channels['blue'] = $this->embedWatermarkInChannel($channels['blue'], $allBits);
            
            // 合并通道，返回嵌入水印后的图像
            $image->mergeChannels($channels);
            $this->logger->info("水印嵌入完成");
            
            return $image;
        } catch (\Throwable $e) {
            $this->logger->error("嵌入水印过程中发生错误: " . $e->getMessage(), ['exception' => $e]);
            throw $e;
        }
    }
}
