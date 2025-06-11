<?php

namespace Tourze\BlindWatermark;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tourze\BlindWatermark\Utils\DCT;
use Tourze\BlindWatermark\Utils\GeometricTransform;

/**
 * 水印提取类
 *
 * 实现从带水印图像中提取水印文本的功能。此类使用DCT（离散余弦变换）域水印提取技术，
 * 从图像的特定频率位置提取水印比特，恢复出原始的水印信息。
 *
 * 提取算法基本步骤:
 * 1. 对带水印图像通道进行分块DCT变换
 * 2. 从DCT块中指定位置读取系数值，判断水印比特（正值为1，负值为0）
 * 3. 从比特流的前16位解析出水印长度信息
 * 4. 根据长度信息截取有效的水印比特
 * 5. 将水印比特转换回原始文本
 *
 * 注意:
 * - 提取参数（分块大小、位置等）必须与嵌入时保持一致
 * - 图像若经过较大修改，可能导致水印提取失败或不完整
 */
class WatermarkExtractor
{
    /**
     * DCT分块大小，默认为8x8
     */
    protected int $blockSize = 8;

    /**
     * 水印嵌入位置参数
     */
    protected array $position = [3, 4];

    /**
     * 是否启用对称性提取（增强抗翻转攻击能力）
     */
    protected bool $useSymmetricExtraction = false;

    /**
     * 对称提取的位置参数（默认为原位置的对称位置）
     */
    protected array $symmetricPositions = [];

    /**
     * 是否启用多点提取（增强鲁棒性）
     */
    protected bool $useMultiPointExtraction = false;

    /**
     * 多点提取的位置参数数组
     */
    protected array $multiPoints = [];

    /**
     * 是否启用几何变换修正
     */
    protected bool $useGeometricCorrection = false;

    /**
     * 参考水印图像数据（用于检测几何变换）
     */
    protected ?array $referenceChannel = null;

    /**
     * 日志记录器
     */
    protected LoggerInterface $logger;

    /**
     * 构造函数
     * 
     * @param LoggerInterface|null $logger 日志记录器
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
        
        // 设置默认的对称位置（基于主位置的对称）
        $this->updateSymmetricPositions();
        
        // 设置默认的多点提取位置
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
        // 更新对称位置
        $this->updateSymmetricPositions();
        return $this;
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
        $this->logger->debug("设置水印提取位置: [{$position[0]},{$position[1]}]");
        // 更新对称位置
        $this->updateSymmetricPositions();
        return $this;
    }

    /**
     * 启用或禁用对称性提取
     *
     * @param bool $enabled 是否启用
     * @return self
     */
    public function setSymmetricExtraction(bool $enabled): self
    {
        $this->useSymmetricExtraction = $enabled;
        $this->logger->debug("对称性提取: " . ($enabled ? '启用' : '禁用'));
        return $this;
    }

    /**
     * 启用或禁用多点提取
     *
     * @param bool $enabled 是否启用
     * @return self
     */
    public function setMultiPointExtraction(bool $enabled): self
    {
        $this->useMultiPointExtraction = $enabled;
        $this->logger->debug("多点提取: " . ($enabled ? '启用' : '禁用'));
        return $this;
    }

    /**
     * 设置多点提取的位置集合
     *
     * @param array $points 位置数组集合，每个元素为 [row, col]
     * @return self
     */
    public function setMultiPoints(array $points): self
    {
        $this->multiPoints = $points;
        $this->logger->debug("设置多点提取位置集合，共 " . count($points) . " 个点");
        return $this;
    }

    /**
     * 启用或禁用几何变换修正
     *
     * @param bool $enabled 是否启用
     * @return self
     */
    public function setGeometricCorrection(bool $enabled): self
    {
        $this->useGeometricCorrection = $enabled;
        $this->logger->debug("几何变换修正: " . ($enabled ? '启用' : '禁用'));
        return $this;
    }

    /**
     * 设置参考水印图像（用于检测几何变换）
     *
     * @param array $referenceChannel 参考图像通道数据
     * @return self
     */
    public function setReferenceChannel(array $referenceChannel): self
    {
        $this->referenceChannel = $referenceChannel;
        $this->logger->debug("设置几何变换参考通道");
        return $this;
    }

    /**
     * 更新对称提取位置
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
     * 检测图像的几何变换并进行修正
     *
     * @param array $channel 图像通道数据
     * @return array 修正后的通道数据
     */
    protected function correctGeometricTransformations(array $channel): array
    {
        // 如果未设置参考通道或未启用几何修正，则返回原始通道
        if (!$this->useGeometricCorrection || $this->referenceChannel === null) {
            return $channel;
        }
        
        $this->logger->debug("开始检测几何变换...");
        
        try {
            // 检测水平翻转
            $isHorizontalFlipped = GeometricTransform::detectHorizontalFlip($this->referenceChannel, $channel);
            if ($isHorizontalFlipped) {
                $this->logger->info("检测到水平翻转");
            }
            
            // 检测垂直翻转
            $isVerticalFlipped = GeometricTransform::detectVerticalFlip($this->referenceChannel, $channel);
            if ($isVerticalFlipped) {
                $this->logger->info("检测到垂直翻转");
            }
            
            // 检测旋转
            $rotationAngle = GeometricTransform::detectRotation($this->referenceChannel, $channel);
            if ($rotationAngle > 0) {
                $this->logger->info("检测到旋转：{$rotationAngle}度");
            }
            
            // 执行几何变换修正
            if ($isHorizontalFlipped || $isVerticalFlipped || $rotationAngle > 0) {
                $this->logger->info("执行几何变换修正...");
                return GeometricTransform::correctGeometricTransform(
                    $channel,
                    $isHorizontalFlipped,
                    $isVerticalFlipped,
                    $rotationAngle
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error("几何变换检测过程中发生错误: " . $e->getMessage(), ['exception' => $e]);
        }
        
        return $channel;
    }

    /**
     * 从DCT块中提取水印比特
     *
     * @param array $dctBlock DCT系数块
     * @param array $position 提取位置
     * @return int 提取的比特值(0或1)
     */
    protected function extractBitFromBlock(array $dctBlock, array $position): int
    {
        $row = $position[0];
        $col = $position[1];
        $coef = $dctBlock[$row][$col];
        
        // 判断比特值
        return ($coef > 0) ? 1 : 0;
    }
    
    /**
     * 从DCT块的多个位置提取水印比特，采用投票方式决定最终比特值
     *
     * @param array $dctBlock DCT系数块
     * @param array $mainPosition 主提取位置
     * @param array $additionalPositions 附加提取位置
     * @return int 提取的比特值(0或1)
     */
    protected function extractBitWithVoting(array $dctBlock, array $mainPosition, array $additionalPositions): int
    {
        // 首先获取主位置的比特值
        $mainBit = $this->extractBitFromBlock($dctBlock, $mainPosition);
        
        // 如果没有附加位置，直接返回主位置的比特值
        if (empty($additionalPositions)) {
            return $mainBit;
        }
        
        // 计算所有位置的投票结果
        $votes = [$mainBit => 1]; // 主位置的票数初始为1
        
        foreach ($additionalPositions as $position) {
            $bit = $this->extractBitFromBlock($dctBlock, $position);
            if (!isset($votes[$bit])) {
                $votes[$bit] = 0;
            }
            $votes[$bit]++;
        }
        
        // 返回得票最多的比特值
        return (max([$votes[0] ?? 0, $votes[1] ?? 0]) === ($votes[0] ?? 0)) ? 0 : 1;
    }

    /**
     * 从图像通道中提取水印比特
     *
     * @param array $channel 图像通道数据
     * @param int $bitsCount 要提取的比特数量
     * @return array 提取的水印比特数组
     */
    protected function extractWatermarkFromChannel(array $channel, int $bitsCount): array
    {
        try {
            // 如果启用了几何变换修正，先进行修正
            if ($this->useGeometricCorrection && $this->referenceChannel !== null) {
                $channel = $this->correctGeometricTransformations($channel);
            }
            
            // 对图像数据进行分块DCT变换
            $dctBlocks = DCT::blockDCT($channel, $this->blockSize);

            // 计算可提取的块数量
            $blocksY = count($dctBlocks);
            $blocksX = count($dctBlocks[0]);
            $totalBlocks = $blocksX * $blocksY;
            $this->logger->debug("可用DCT块数量: {$totalBlocks}");

            // 确保提取的比特数量不超过可用块数
            $bitsToExtract = min($bitsCount, $totalBlocks);
            $this->logger->debug("计划提取比特数: {$bitsToExtract}");

            // 提取水印比特
            $bits = [];
            $bitIndex = 0;

            for ($by = 0; $by < $blocksY && $bitIndex < $bitsToExtract; $by++) {
                for ($bx = 0; $bx < $blocksX && $bitIndex < $bitsToExtract; $bx++) {
                    // 准备提取位置
                    $extractionPositions = [];
                    
                    // 如果启用了对称性提取，添加对称位置
                    if ($this->useSymmetricExtraction) {
                        $extractionPositions = array_merge($extractionPositions, $this->symmetricPositions);
                    }
                    
                    // 如果启用了多点提取，添加多点位置
                    if ($this->useMultiPointExtraction) {
                        $extractionPositions = array_merge($extractionPositions, $this->multiPoints);
                    }
                    
                    // 如果有多个提取位置，采用投票方式提取比特
                    if (!empty($extractionPositions)) {
                        $bits[] = $this->extractBitWithVoting($dctBlocks[$by][$bx], $this->position, $extractionPositions);
                    } else {
                        // 否则使用单一位置提取
                        $bits[] = $this->extractBitFromBlock($dctBlocks[$by][$bx], $this->position);
                    }
                    
                    $bitIndex++;
                }
            }
            
            $this->logger->debug("实际提取比特数: " . count($bits));
            return $bits;
        } catch (\Throwable $e) {
            $this->logger->error("提取水印比特过程中发生错误: " . $e->getMessage(), ['exception' => $e]);
            return [];
        }
    }

    /**
     * 将比特数组转换为文本
     *
     * @param array $bits 比特数组
     * @return string 转换后的文本
     */
    protected function bitsToText(array $bits): string
    {
        try {
            $text = '';
            $bitsCount = count($bits);
            $this->logger->debug("转换文本，输入比特数: {$bitsCount}");

            // 每8位比特转换为一个字符
            for ($i = 0; $i + 7 < $bitsCount; $i += 8) {
                $byte = 0;
                for ($j = 0; $j < 8; $j++) {
                    $byte = ($byte << 1) | $bits[$i + $j];
                }
                $text .= chr($byte);
            }
            
            $this->logger->debug("转换完成，文本长度: " . strlen($text) . " 字符");
            return $text;
        } catch (\Throwable $e) {
            $this->logger->error("比特转文本过程中发生错误: " . $e->getMessage(), ['exception' => $e]);
            return '';
        }
    }

    /**
     * 从带水印图像中提取文本水印
     *
     * @param ImageProcessor $image 带水印图像处理器
     * @return string 提取的文本水印
     */
    public function extract(ImageProcessor $image): string
    {
        try {
            $this->logger->info("开始提取水印");
            
            // 分离图像通道
            $channels = $image->splitChannels();

            // 先提取足够的比特用于获取水印长度
            $initialBitsToExtract = 256; // 至少包含16位长度信息和一些水印数据
            $this->logger->debug("提取初始比特用于获取水印长度信息");
            $extractedBits = $this->extractWatermarkFromChannel($channels['blue'], $initialBitsToExtract);
            
            // 如果提取的比特不足以包含长度信息（16位），则无法继续处理
            if (count($extractedBits) < 16) {
                $this->logger->error("提取的比特不足16位，无法读取长度信息");
                return '';
            }
            
            // 解析水印长度信息（头16位）
            $lengthBits = array_slice($extractedBits, 0, 16);
            $lengthDebug = implode('', $lengthBits);
            $watermarkLength = bindec($lengthDebug);
            $this->logger->debug("解析的水印长度: {$lengthDebug} ({$watermarkLength} 比特)");

            // 验证长度的合理性
            if ($watermarkLength <= 0 || $watermarkLength > 8192 * 8) {
                $this->logger->error("水印长度不合理: {$watermarkLength}");
                return '';
            }

            // 计算需要的总比特数（长度信息 + 水印数据）
            $totalBitsNeeded = 16 + $watermarkLength;
            if (count($extractedBits) < $totalBitsNeeded) {
                $this->logger->debug("需要提取更多比特: {$totalBitsNeeded}");
                
                // 重新提取足够数量的比特
                $extractedBits = $this->extractWatermarkFromChannel($channels['blue'], $totalBitsNeeded);
                
                if (count($extractedBits) < $totalBitsNeeded) {
                    $this->logger->error("无法提取足够的比特: 需要 " . $totalBitsNeeded . ", 实际 " . count($extractedBits));
                    return '';
                }
            }

            // 提取水印数据部分比特（跳过长度信息）
            $watermarkBits = array_slice($extractedBits, 16, $watermarkLength);
            
            // 将比特转换为文本
            $text = $this->bitsToText($watermarkBits);
            $this->logger->info("水印提取完成，文本长度: " . strlen($text) . " 字符");
            
            return $text;
        } catch (\Throwable $e) {
            $this->logger->error("提取水印过程中发生异常: " . $e->getMessage(), ['exception' => $e]);
            return '';
        }
    }
}
