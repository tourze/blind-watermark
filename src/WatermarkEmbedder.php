<?php

namespace Tourze\BlindWatermark;

use Tourze\BlindWatermark\Utils\DCT;

/**
 * 水印嵌入类
 *
 * 实现向图像中嵌入水印文本的功能
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
     * 加密密钥，用于保护水印信息
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
     * @param float $strength 强度值
     * @return self
     */
    public function setStrength(float $strength): self
    {
        $this->strength = $strength;
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

        // 使用密钥生成随机种子
        $seed = crc32($this->key);
        srand($seed);

        // 生成随机置乱索引
        $count = count($bits);
        $indices = range(0, $count - 1);
        shuffle($indices);

        // 创建映射表
        $mappingTable = [];
        for ($i = 0; $i < $count; $i++) {
            $mappingTable[$i] = $indices[$i];
        }

        // 按照映射表重排比特
        $encrypted = [];
        for ($i = 0; $i < $count; $i++) {
            $encrypted[$mappingTable[$i]] = $bits[$i];
        }

        // 验证长度编码被正确加密
        if ($count >= 16) {
            $lengthDebug = '';
            for ($i = 0; $i < 16; $i++) {
                $lengthDebug .= $encrypted[$i];
            }
            error_log("加密后的长度编码: " . $lengthDebug);
        }

        // 恢复随机数生成器状态
        srand();

        return $encrypted;
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
        // 对图像数据进行分块DCT变换
        $dctBlocks = DCT::blockDCT($channel, $this->blockSize);

        // 计算可用于嵌入的块数量
        $blocksY = count($dctBlocks);
        $blocksX = count($dctBlocks[0]);
        $totalBlocks = $blocksX * $blocksY;

        // 确保水印比特数量不超过可用块数
        $bitsToEmbed = min(count($bits), $totalBlocks);

        // 嵌入水印比特
        $bitIndex = 0;

        for ($by = 0; $by < $blocksY && $bitIndex < $bitsToEmbed; $by++) {
            for ($bx = 0; $bx < $blocksX && $bitIndex < $bitsToEmbed; $bx++) {
                // 设置指定位置的DCT系数
                $row = $this->position[0];
                $col = $this->position[1];

                // 强制改变DCT系数
                if ($bits[$bitIndex] == 1) {
                    $dctBlocks[$by][$bx][$row][$col] = $this->strength;
                } else {
                    $dctBlocks[$by][$bx][$row][$col] = -$this->strength;
                }

                $bitIndex++;
            }
        }

        // 输出调试信息
        error_log("已嵌入 {$bitIndex} 比特的水印数据");

        // 进行逆DCT变换，得到嵌入水印后的图像通道
        $height = count($channel);
        $width = count($channel[0]);
        return DCT::blockIDCT($dctBlocks, $height, $width, $this->blockSize);
    }

    /**
     * 向图像中嵌入文本水印
     *
     * @param ImageProcessor $image 图像处理器
     * @param string $text 要嵌入的文本
     * @return ImageProcessor 嵌入水印后的图像处理器
     */
    public function embed(ImageProcessor $image, string $text): ImageProcessor
    {
        // 将文本转换为比特数组
        $textBits = $this->textToBits($text);
        $bitLength = count($textBits);
        
        error_log("文本长度: " . strlen($text) . " 字符, " . $bitLength . " 比特");
        
        // 使用16位表示长度（最多支持8192字符）
        $lengthBits = [];
        for ($i = 15; $i >= 0; $i--) {
            $lengthBits[] = ($bitLength >> $i) & 1;
        }
        
        // 调试: 输出长度比特
        $lengthDebug = '';
        foreach ($lengthBits as $bit) {
            $lengthDebug .= $bit;
        }
        error_log("长度编码: " . $lengthDebug . " (" . bindec($lengthDebug) . ")");
        
        // 将长度信息和文本比特合并
        $allBits = array_merge($lengthBits, $textBits);
        
        // 加密比特
        $encryptedBits = !empty($this->key) ? $this->encryptBits($allBits) : $allBits;
        
        // 分离图像通道
        $channels = $image->splitChannels();
        
        // 仅在蓝色通道嵌入水印（对人眼最不敏感）
        $channels['blue'] = $this->embedWatermarkInChannel($channels['blue'], $encryptedBits);
        
        // 合并通道，返回嵌入水印后的图像
        return $image->mergeChannels($channels);
    }
}
