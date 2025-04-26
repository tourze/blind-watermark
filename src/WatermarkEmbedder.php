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
 * 3. 可选：使用密钥对比特流进行加密置乱
 * 4. 对图像通道进行分块DCT变换
 * 5. 根据水印比特值，修改DCT块中指定位置的系数值（正值表示1，负值表示0）
 * 6. 对修改后的DCT块进行逆变换，得到嵌入水印的图像
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
     * 加密密钥，用于保护水印信息
     */
    protected string $key = '';
    
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

        // 生成随机种子
        $seed = $this->generateSeedFromKey($this->key);
        
        // 生成映射表
        $mappingTable = $this->generateMappingTable(count($bits), $seed);
        
        // 按照映射表重排比特
        $encrypted = $this->applyMappingToData($bits, $mappingTable);
        
        // 调试：验证长度编码
        $this->debugEncryptedLength($encrypted);

        return $encrypted;
    }
    
    /**
     * 从密钥生成随机种子
     *
     * @param string $key 密钥
     * @return int 随机种子
     */
    protected function generateSeedFromKey(string $key): int
    {
        return crc32($key);
    }
    
    /**
     * 生成随机映射表
     *
     * @param int $dataLength 数据长度
     * @param int $seed 随机种子
     * @return array 索引映射表
     */
    protected function generateMappingTable(int $dataLength, int $seed): array
    {
        // 使用密钥生成随机种子
        srand($seed);

        // 生成随机置乱索引
        $indices = range(0, $dataLength - 1);
        shuffle($indices);

        // 创建映射表
        $mappingTable = [];
        for ($i = 0; $i < $dataLength; $i++) {
            $mappingTable[$i] = $indices[$i];
        }
        
        // 恢复随机数生成器状态
        srand();
        
        return $mappingTable;
    }
    
    /**
     * 应用映射表重排数据
     *
     * @param array $data 原始数据
     * @param array $mappingTable 映射表
     * @return array 重排后的数据
     */
    protected function applyMappingToData(array $data, array $mappingTable): array
    {
        $encrypted = [];
        for ($i = 0; $i < count($data); $i++) {
            $encrypted[$mappingTable[$i]] = $data[$i];
        }
        
        return $encrypted;
    }
    
    /**
     * 调试加密后的长度编码
     *
     * @param array $encrypted 加密后的数据
     */
    protected function debugEncryptedLength(array $encrypted): void
    {
        // 验证长度编码被正确加密
        if (count($encrypted) >= 16) {
            $lengthDebug = '';
            for ($i = 0; $i < 16; $i++) {
                $lengthDebug .= $encrypted[$i];
            }
            $this->logger->debug("加密后的长度编码: {$lengthDebug}");
        }
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

        // 记录嵌入进度
        $this->logger->debug("已嵌入 {$bitIndex} 比特的水印数据");

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
