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
 * 3. 可选：使用密钥对提取的比特流进行解密
 * 4. 从比特流的前16位解析出水印长度信息
 * 5. 根据长度信息截取有效的水印比特
 * 6. 将水印比特转换回原始文本
 * 
 * 注意:
 * - 提取参数（分块大小、位置等）必须与嵌入时保持一致
 * - 如果使用了密钥加密，提取时必须提供相同的密钥
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
     * 加密密钥，用于还原水印信息
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

        // 生成随机种子
        $seed = $this->generateSeedFromKey($this->key);
        
        // 生成映射表
        $mappingTable = $this->generateMappingTable(count($encryptedBits), $seed);
        
        // 应用反向映射还原原始比特
        $decrypted = $this->applyReverseMappingToData($encryptedBits, $mappingTable);
        
        // 调试：验证长度编码
        $this->debugDecryptedLength($decrypted);

        return $decrypted;
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
        // 使用相同的密钥生成相同的伪随机序列
        srand($seed);

        // 生成与加密时相同的随机置乱索引
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
     * 应用反向映射还原原始数据
     *
     * @param array $encryptedData 加密的数据
     * @param array $mappingTable 映射表
     * @return array 解密后的数据
     */
    protected function applyReverseMappingToData(array $encryptedData, array $mappingTable): array
    {
        $count = count($encryptedData);
        $decrypted = array_fill(0, $count, 0);
        
        for ($i = 0; $i < $count; $i++) {
            $decrypted[$i] = $encryptedData[$mappingTable[$i]];
        }
        
        return $decrypted;
    }
    
    /**
     * 调试解密后的长度编码
     *
     * @param array $decrypted 解密后的数据
     */
    protected function debugDecryptedLength(array $decrypted): void
    {
        // 验证长度编码被正确解密
        if (count($decrypted) >= 16) {
            $lengthDebug = '';
            for ($i = 0; $i < 16; $i++) {
                $lengthDebug .= $decrypted[$i];
            }
            $this->logger->debug("解密后的长度编码: {$lengthDebug}");
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
        
        // 获取初始提取的比特数
        $this->logger->debug("初始提取比特数: " . count($encryptedBits));
        
        // 解密提取的比特
        $decryptedBits = !empty($this->key) ? $this->decryptBits($encryptedBits) : $encryptedBits;
        
        // 如果提取的比特不足以包含长度信息（16位），则无法继续处理
        if (count($decryptedBits) < 16) {
            $this->logger->error("提取的比特不足16位，无法读取长度信息");
            return '';
        }
        
        // 解析水印长度信息（头16位）
        $lengthBits = array_slice($decryptedBits, 0, 16);
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
        if (count($decryptedBits) < $totalBitsNeeded) {
            $this->logger->debug("需要提取更多比特: {$totalBitsNeeded}");
            
            // 重新提取足够数量的比特
            $encryptedBits = $this->extractWatermarkFromChannel($channels['blue'], $totalBitsNeeded);
            $decryptedBits = $this->decryptBits($encryptedBits);
            
            if (count($decryptedBits) < $totalBitsNeeded) {
                $this->logger->error("无法提取足够的比特: 需要 " . (16 + $watermarkLength) . ", 实际 " . count($decryptedBits));
                return '';
            }
        }

        // 提取水印数据部分比特（跳过长度信息）
        $watermarkBits = array_slice($decryptedBits, 16, $watermarkLength);
        
        // 将比特转换为文本
        $text = $this->bitsToText($watermarkBits);
        $this->logger->debug("提取的文本: {$text}");
        
        return $text;
    }
}
