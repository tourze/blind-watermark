<?php

namespace Tourze\BlindWatermark;

use Tourze\BlindWatermark\Utils\DCT;

/**
 * 水印提取类
 *
 * 实现从带水印图像中提取水印文本的功能
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
     * 加密密钥，用于还原水印信息
     */
    protected string $key = '';

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
     * 设置解密密钥
     *
     * @param string $key 密钥
     * @return self
     */
    public function setKey(string $key): self
    {
        $this->key = $key;
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
     * 使用密钥对水印比特进行解密
     *
     * @param array $encryptedBits 加密的比特数组
     * @return array 解密后的比特数组
     */
    protected function decryptBits(array $encryptedBits): array
    {
        if (empty($this->key)) {
            return $encryptedBits;
        }

        // 使用相同的密钥生成相同的伪随机序列
        $seed = crc32($this->key);
        srand($seed);

        // 生成与加密时相同的随机置乱索引
        $count = count($encryptedBits);
        $indices = range(0, $count - 1);
        shuffle($indices);

        // 创建映射表
        $mappingTable = [];
        for ($i = 0; $i < $count; $i++) {
            $mappingTable[$i] = $indices[$i];
        }

        // 反向映射还原原始比特
        $decrypted = array_fill(0, $count, 0);
        for ($i = 0; $i < $count; $i++) {
            $decrypted[$i] = $encryptedBits[$mappingTable[$i]];
        }

        // 验证长度编码被正确解密
        if ($count >= 16) {
            $lengthDebug = '';
            for ($i = 0; $i < 16; $i++) {
                $lengthDebug .= $decrypted[$i];
            }
            error_log("解密后的长度编码: " . $lengthDebug);
        }

        // 恢复随机数生成器状态
        srand();

        return $decrypted;
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
        $encryptedBits = $this->extractWatermarkFromChannel($channels['blue'], $initialBitsToExtract);
        
        error_log("初始提取比特数: " . count($encryptedBits));
        
        // 解密提取的比特
        $decryptedBits = !empty($this->key) ? $this->decryptBits($encryptedBits) : $encryptedBits;
        
        // 从解密后的比特中提取长度信息（前16位）
        if (count($decryptedBits) < 16) {
            error_log("提取的比特不足16位，无法读取长度信息");
            return '';
        }
        
        $lengthBits = array_slice($decryptedBits, 0, 16);
        
        // 调试: 输出长度比特
        $lengthDebug = '';
        foreach ($lengthBits as $bit) {
            $lengthDebug .= $bit;
        }
        
        // 计算水印长度
        $watermarkLength = bindec($lengthDebug);
        error_log("解析的水印长度: " . $lengthDebug . " (" . $watermarkLength . " 比特)");

        // 检查水印长度是否合理
        if ($watermarkLength <= 0 || $watermarkLength > 8192) {
            error_log("水印长度不合理: {$watermarkLength}");
            return '';
        }

        // 如果需要更多比特，继续提取
        $totalBitsNeeded = 16 + $watermarkLength;
        if ($totalBitsNeeded > count($decryptedBits)) {
            error_log("需要提取更多比特: {$totalBitsNeeded}");
            $encryptedBits = $this->extractWatermarkFromChannel($channels['blue'], $totalBitsNeeded);
            $decryptedBits = !empty($this->key) ? $this->decryptBits($encryptedBits) : $encryptedBits;
        }
        
        // 再次检查是否有足够的比特
        if (count($decryptedBits) < 16 + $watermarkLength) {
            error_log("无法提取足够的比特: 需要 " . (16 + $watermarkLength) . ", 实际 " . count($decryptedBits));
            return '';
        }

        // 提取水印比特（跳过长度信息）
        $watermarkBits = array_slice($decryptedBits, 16, $watermarkLength);
        
        // 将比特转换为文本
        $text = $this->bitsToText($watermarkBits);
        error_log("提取的文本: " . $text);
        
        return $text;
    }
}
