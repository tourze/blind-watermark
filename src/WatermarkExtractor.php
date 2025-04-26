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

        // 反向映射还原原始比特
        $decrypted = array_fill(0, $count, 0);
        foreach ($indices as $i => $pos) {
            $decrypted[$i] = $encryptedBits[$pos];
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

        // 首先提取32位长度信息
        $lengthBits = $this->extractWatermarkFromChannel($channels['blue'], 32);

        // 如果使用了密钥，先解密长度信息
        if (!empty($this->key)) {
            $lengthBits = $this->decryptBits($lengthBits);
        }

        // 计算水印长度
        $watermarkLength = 0;
        foreach ($lengthBits as $bit) {
            $watermarkLength = ($watermarkLength << 1) | $bit;
        }

        // 检查水印长度是否合理
        if ($watermarkLength <= 0 || $watermarkLength > 100000) {
            return ''; // 长度不合理，可能提取失败
        }

        // 提取完整水印(长度信息 + 实际水印)
        $totalBits = $this->extractWatermarkFromChannel($channels['blue'], 32 + $watermarkLength);

        // 解密水印比特
        $decryptedBits = $this->decryptBits($totalBits);

        // 跳过长度信息，只使用实际水印比特
        $watermarkBits = array_slice($decryptedBits, 32, $watermarkLength);

        // 将比特转换为文本
        return $this->bitsToText($watermarkBits);
    }
}
