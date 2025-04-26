<?php

namespace Tourze\BlindWatermark;

use Tourze\BlindWatermark\Utils\DCT;

/**
 * 水印嵌入类
 *
 * 实现将文本水印嵌入到图像中的功能
 */
class WatermarkEmbedder
{
    /**
     * DCT分块大小，默认为8x8
     */
    protected int $blockSize = 8;

    /**
     * 水印嵌入强度，数值越大水印越明显，但图像质量下降
     */
    protected float $alpha = 20.0;

    /**
     * 水印位置选择参数，默认选择中低频段
     */
    protected array $position = [3, 4];

    /**
     * 加密密钥，用于置乱水印信息
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
     * 设置水印强度
     *
     * @param float $alpha 水印强度系数
     * @return self
     */
    public function setAlpha(float $alpha): self
    {
        $this->alpha = $alpha;
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
     * 设置加密密钥
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
     * 将文本转换为比特数组
     *
     * @param string $text 要嵌入的文本
     * @return array 比特数组
     */
    protected function textToBits(string $text): array
    {
        $bits = [];

        // 转换字符串为二进制，并获取比特
        for ($i = 0; $i < strlen($text); $i++) {
            $char = ord($text[$i]);

            // 每个字符转为8位二进制
            for ($j = 7; $j >= 0; $j--) {
                $bits[] = ($char >> $j) & 1;
            }
        }

        return $bits;
    }

    /**
     * 使用密钥对水印比特进行加密
     *
     * @param array $bits 原始比特数组
     * @return array 加密后的比特数组
     */
    protected function encryptBits(array $bits): array
    {
        if (empty($this->key)) {
            return $bits;
        }

        // 使用密钥生成伪随机序列
        $seed = crc32($this->key);
        srand($seed);

        // 简单的随机置乱算法
        $count = count($bits);
        $indices = range(0, $count - 1);
        shuffle($indices);

        $encrypted = array_fill(0, $count, 0);
        foreach ($bits as $i => $bit) {
            $encrypted[$indices[$i]] = $bit;
        }

        // 恢复随机数生成器状态
        srand();

        return $encrypted;
    }

    /**
     * 将水印嵌入到图像通道中
     *
     * @param array $channel 图像通道数据
     * @param array $watermarkBits 水印比特数组
     * @return array 嵌入水印后的通道数据
     */
    protected function embedWatermarkInChannel(array $channel, array $watermarkBits): array
    {
        // 对图像数据进行分块DCT变换
        $dctBlocks = DCT::blockDCT($channel, $this->blockSize);

        $height = count($channel);
        $width = count($channel[0]);

        // 计算可嵌入的块数量
        $blocksY = count($dctBlocks);
        $blocksX = count($dctBlocks[0]);
        $totalBlocks = $blocksX * $blocksY;

        // 确保水印比特数量不超过可嵌入的块数量
        $bitsCount = count($watermarkBits);
        if ($bitsCount > $totalBlocks) {
            $watermarkBits = array_slice($watermarkBits, 0, $totalBlocks);
            $bitsCount = $totalBlocks;
        }

        // 嵌入水印比特
        $bitIndex = 0;
        for ($by = 0; $by < $blocksY && $bitIndex < $bitsCount; $by++) {
            for ($bx = 0; $bx < $blocksX && $bitIndex < $bitsCount; $bx++) {
                $bit = $watermarkBits[$bitIndex++];

                // 获取指定位置的DCT系数
                $row = $this->position[0];
                $col = $this->position[1];

                // 根据比特值修改DCT系数
                if ($bit == 1) {
                    $dctBlocks[$by][$bx][$row][$col] += $this->alpha;
                } else {
                    $dctBlocks[$by][$bx][$row][$col] -= $this->alpha;
                }
            }
        }

        // 进行逆DCT变换，恢复图像数据
        return DCT::blockIDCT($dctBlocks, $height, $width, $this->blockSize);
    }

    /**
     * 将文本水印嵌入到图像中
     *
     * @param ImageProcessor $image 原始图像处理器
     * @param string $text 要嵌入的文本水印
     * @return ImageProcessor 嵌入水印后的图像处理器
     */
    public function embed(ImageProcessor $image, string $text): ImageProcessor
    {
        // 分离图像通道
        $channels = $image->splitChannels();

        // 将文本转换为比特数组
        $bits = $this->textToBits($text);

        // 记录水印长度，便于提取时使用
        $watermarkLength = count($bits);
        $lengthBits = [];
        for ($i = 31; $i >= 0; $i--) {
            $lengthBits[] = ($watermarkLength >> $i) & 1;
        }

        // 合并长度信息和水印比特
        $allBits = array_merge($lengthBits, $bits);

        // 使用密钥加密比特
        $encryptedBits = $this->encryptBits($allBits);

        // 只在蓝色通道嵌入水印，保持其他通道不变
        // 可根据实际需求修改为多通道嵌入
        $channels['blue'] = $this->embedWatermarkInChannel($channels['blue'], $encryptedBits);

        // 合并通道
        $image->mergeChannels($channels);

        return $image;
    }
}
