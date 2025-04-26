<?php

namespace Tourze\BlindWatermark;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tourze\BlindWatermark\Utils\DCT;

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
     * 日志记录器
     */
    protected LoggerInterface $logger;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->logger = new NullLogger();
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
        return $this;
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
        // 对图像数据进行分块DCT变换
        $dctBlocks = DCT::blockDCT($channel, $this->blockSize);

        // 计算可提取的块数量
        $blocksY = count($dctBlocks);
        $blocksX = count($dctBlocks[0]);
        $totalBlocks = $blocksX * $blocksY;

        // 确保提取的比特数量不超过可用块数
        $bitsToExtract = min($bitsCount, $totalBlocks);

        // 提取水印比特
        $bits = [];
        $bitIndex = 0;

        for ($by = 0; $by < $blocksY && $bitIndex < $bitsToExtract; $by++) {
            for ($bx = 0; $bx < $blocksX && $bitIndex < $bitsToExtract; $bx++) {
                // 获取指定位置的DCT系数
                $row = $this->position[0];
                $col = $this->position[1];
                $coef = $dctBlocks[$by][$bx][$row][$col];

                // 判断比特值
                $bits[] = ($coef > 0) ? 1 : 0;
                $bitIndex++;
            }
        }

        return $bits;
    }

    /**
     * 将比特数组转换为文本
     *
     * @param array $bits 比特数组
     * @return string 转换后的文本
     */
    protected function bitsToText(array $bits): string
    {
        $text = '';
        $bitsCount = count($bits);

        // 每8位比特转换为一个字符
        for ($i = 0; $i + 7 < $bitsCount; $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | $bits[$i + $j];
            }
            $text .= chr($byte);
        }

        return $text;
    }

    /**
     * 从带水印图像中提取文本水印
     *
     * @param ImageProcessor $image 带水印图像处理器
     * @return string 提取的文本水印
     */
    public function extract(ImageProcessor $image): string
    {
        // 分离图像通道
        $channels = $image->splitChannels();

        // 先提取足够的比特用于获取水印长度
        $initialBitsToExtract = 256; // 至少包含16位长度信息和一些水印数据
        $extractedBits = $this->extractWatermarkFromChannel($channels['blue'], $initialBitsToExtract);

        // 获取初始提取的比特数
        $this->logger->debug("初始提取比特数: " . count($extractedBits));

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
                $this->logger->error("无法提取足够的比特: 需要 " . (16 + $watermarkLength) . ", 实际 " . count($extractedBits));
                return '';
            }
        }

        // 提取水印数据部分比特（跳过长度信息）
        $watermarkBits = array_slice($extractedBits, 16, $watermarkLength);

        // 将比特转换为文本
        $text = $this->bitsToText($watermarkBits);
        $this->logger->debug("提取的文本: {$text}");

        return $text;
    }
}
