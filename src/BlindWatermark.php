<?php

namespace Tourze\BlindWatermark;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tourze\BlindWatermark\Exception\BlindWatermarkException;

/**
 * 盲水印主类
 *
 * 提供统一的API接口，封装内部实现细节
 */
class BlindWatermark
{
    /**
     * 水印嵌入器
     */
    protected WatermarkEmbedder $embedder;

    /**
     * 水印提取器
     */
    protected WatermarkExtractor $extractor;

    /**
     * 图像处理器
     */
    protected ?ImageProcessor $imageProcessor = null;

    /**
     * DCT分块大小
     */
    protected int $blockSize = 8;

    /**
     * 水印嵌入强度
     */
    protected float $alpha = 36.0;

    /**
     * 水印嵌入位置
     *
     * @var array<int>
     */
    protected array $position = [3, 4];

    /**
     * 是否启用几何变换修正功能
     */
    protected bool $geometricCorrectionEnabled = false;

    /**
     * 是否启用对称性嵌入和提取
     */
    protected bool $symmetricEmbeddingEnabled = false;

    /**
     * 是否启用多点嵌入和提取
     */
    protected bool $multiPointEmbeddingEnabled = false;

    /**
     * 参考图像处理器（用于几何变换检测）
     */
    protected ?ImageProcessor $referenceImage = null;

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
        $this->embedder = new WatermarkEmbedder($this->logger);
        $this->extractor = new WatermarkExtractor($this->logger);
    }

    /**
     * 设置DCT分块大小
     *
     * @param int $blockSize 分块大小
     */
    public function setBlockSize(int $blockSize): void
    {
        $this->blockSize = $blockSize;
        $this->embedder->setBlockSize($blockSize);
        $this->extractor->setBlockSize($blockSize);
    }

    /**
     * 设置水印强度
     *
     * @param float $alpha 水印强度系数
     */
    public function setAlpha(float $alpha): void
    {
        $this->alpha = $alpha;
        $this->embedder->setStrength($alpha);
    }

    /**
     * 设置水印嵌入位置
     *
     * @param array<int, int> $position 位置数组 [row, col]
     */
    public function setPosition(array $position): void
    {
        $this->position = $position;
        $this->embedder->setPosition($position);
        $this->extractor->setPosition($position);
    }

    /**
     * 启用对称性嵌入和提取功能（增强抗翻转攻击能力）
     *
     * 此功能可以使水印对图像的翻转和旋转操作具有一定的鲁棒性
     *
     * @param bool $enabled 是否启用
     */
    public function enableSymmetricEmbedding(bool $enabled = true): void
    {
        $this->symmetricEmbeddingEnabled = $enabled;
        $this->embedder->setSymmetricEmbedding($enabled);
        $this->extractor->setSymmetricExtraction($enabled);
    }

    /**
     * 启用多点嵌入和提取功能（增强水印鲁棒性）
     *
     * 此功能可以通过在多个位置同时嵌入相同的水印比特，提高水印对图像处理操作的鲁棒性
     *
     * @param bool $enabled 是否启用
     */
    public function enableMultiPointEmbedding(bool $enabled = true): void
    {
        $this->multiPointEmbeddingEnabled = $enabled;
        $this->embedder->setMultiPointEmbedding($enabled);
        $this->extractor->setMultiPointExtraction($enabled);
    }

    /**
     * 启用几何变换修正功能
     *
     * 此功能可以检测和修正图像的几何变换（如翻转、旋转等），提高水印提取的成功率
     *
     * @param bool $enabled 是否启用
     */
    public function enableGeometricCorrection(bool $enabled = true): void
    {
        $this->geometricCorrectionEnabled = $enabled;
        $this->extractor->setGeometricCorrection($enabled);
    }

    /**
     * 保存嵌入水印后的图像作为参考图像
     *
     * 参考图像用于几何变换的检测和修正，通过比较原始水印图像和变换后的图像，
     * 可以检测出图像经历了哪些几何变换
     */
    public function saveAsReference(): self
    {
        if (null !== $this->imageProcessor) {
            $this->referenceImage = clone $this->imageProcessor;
            // 将参考图像的蓝色通道传递给提取器
            $channels = $this->referenceImage->splitChannels();
            $this->extractor->setReferenceChannel($channels['blue']);
        }

        return $this;
    }

    /**
     * 从文件加载图像
     *
     * @param string $filePath 图像文件路径
     *
     * @throws BlindWatermarkException 图像加载失败时抛出异常
     */
    public function loadImage(string $filePath): self
    {
        $this->imageProcessor = new ImageProcessor($this->logger);
        $this->imageProcessor->loadFromFile($filePath);

        return $this;
    }

    /**
     * 将文本水印嵌入到当前加载的图像中
     *
     * @param string $text 要嵌入的文本水印
     *
     * @throws BlindWatermarkException 图像未加载时抛出异常
     */
    public function embedText(string $text): self
    {
        if (null === $this->imageProcessor) {
            throw new BlindWatermarkException('请先加载图像');
        }

        $this->imageProcessor = $this->embedder->embed($this->imageProcessor, $text);

        return $this;
    }

    /**
     * 从当前加载的带水印图像中提取文本水印
     *
     * @return string 提取的文本水印
     *
     * @throws BlindWatermarkException 图像未加载时抛出异常
     */
    public function extractText(): string
    {
        if (null === $this->imageProcessor) {
            throw new BlindWatermarkException('请先加载图像');
        }

        return $this->extractor->extract($this->imageProcessor);
    }

    /**
     * 将处理后的图像保存到文件
     *
     * @param string $filePath 保存路径
     * @param string $type     图像类型，支持jpeg和png
     * @param int    $quality  图像质量(1-100)
     *
     * @return bool 保存是否成功
     *
     * @throws BlindWatermarkException 图像未加载时抛出异常
     */
    public function saveImage(string $filePath, string $type = ImageProcessor::IMAGE_TYPE_JPEG, int $quality = 90): bool
    {
        if (null === $this->imageProcessor) {
            throw new BlindWatermarkException('请先加载图像');
        }

        return $this->imageProcessor->saveToFile($filePath, $type, $quality);
    }

    /**
     * 便捷方法：将文本水印嵌入图像并保存
     *
     * @param string $srcImagePath  原始图像路径
     * @param string $text          要嵌入的文本水印
     * @param string $destImagePath 保存路径
     * @param string $type          图像类型，支持jpeg和png
     * @param int    $quality       图像质量(1-100)
     *
     * @return bool 操作是否成功
     *
     * @throws BlindWatermarkException 处理图像时可能抛出异常
     */
    public function embedTextToImage(
        string $srcImagePath,
        string $text,
        string $destImagePath,
        string $type = ImageProcessor::IMAGE_TYPE_JPEG,
        int $quality = 90,
    ): bool {
        $this->loadImage($srcImagePath)
            ->embedText($text)
        ;

        // 如果启用了几何修正，保存为参考图像
        if ($this->geometricCorrectionEnabled) {
            $this->saveAsReference();
        }

        return $this->saveImage($destImagePath, $type, $quality);
    }

    /**
     * 便捷方法：从带水印图像中提取文本水印
     *
     * @param string $watermarkedImagePath 带水印图像路径
     *
     * @return string 提取的文本水印
     *
     * @throws BlindWatermarkException 处理图像时可能抛出异常
     */
    public function extractTextFromImage(string $watermarkedImagePath): string
    {
        $this->loadImage($watermarkedImagePath);

        return $this->extractText();
    }

    /**
     * 对图像进行水平翻转（用于测试水印的抗翻转能力）
     *
     * @throws BlindWatermarkException 图像未加载时抛出异常
     */
    public function flipHorizontal(): self
    {
        if (null === $this->imageProcessor) {
            throw new BlindWatermarkException('请先加载图像');
        }

        $channels = $this->imageProcessor->splitChannels();

        // 对所有通道进行水平翻转
        foreach ($channels as $channel => $data) {
            $channels[$channel] = $this->flipImageChannel($data, true, false);
        }

        // 合并通道
        $this->imageProcessor->mergeChannels($channels);

        return $this;
    }

    /**
     * 对图像进行垂直翻转（用于测试水印的抗翻转能力）
     *
     * @throws BlindWatermarkException 图像未加载时抛出异常
     */
    public function flipVertical(): self
    {
        if (null === $this->imageProcessor) {
            throw new BlindWatermarkException('请先加载图像');
        }

        $channels = $this->imageProcessor->splitChannels();

        // 对所有通道进行垂直翻转
        foreach ($channels as $channel => $data) {
            $channels[$channel] = $this->flipImageChannel($data, false, true);
        }

        // 合并通道
        $this->imageProcessor->mergeChannels($channels);

        return $this;
    }

    /**
     * 对图像进行旋转（用于测试水印的抗旋转能力，当前仅支持90度的倍数）
     *
     * @param int $angle 旋转角度 (90, 180, 270)
     *
     * @throws BlindWatermarkException 图像未加载时抛出异常
     */
    public function rotate(int $angle): self
    {
        if (null === $this->imageProcessor) {
            throw new BlindWatermarkException('请先加载图像');
        }

        if (0 !== $angle % 90) {
            throw new BlindWatermarkException('当前仅支持90度的倍数进行旋转');
        }

        $angle %= 360;
        if (0 === $angle) {
            return $this; // 无需旋转
        }

        $channels = $this->imageProcessor->splitChannels();
        $width = $this->imageProcessor->getWidth();
        $height = $this->imageProcessor->getHeight();

        // 对所有通道进行旋转
        foreach ($channels as $channel => $data) {
            $channels[$channel] = $this->rotateImageChannel($data, $angle);
        }

        // 如果是90度或270度旋转，需要创建新的图像（因为宽高会交换）
        if (90 === $angle || 270 === $angle) {
            $newImage = new ImageProcessor();
            $newImage->createImage($height, $width);
            $newImage->mergeChannels($channels);
            $this->imageProcessor = $newImage;
        } else {
            // 180度旋转不改变尺寸
            $this->imageProcessor->mergeChannels($channels);
        }

        return $this;
    }

    /**
     * 翻转图像通道
     *
     * @param array<int, array<int, int>> $channel    图像通道数据
     * @param bool                        $horizontal 是否水平翻转
     * @param bool                        $vertical   是否垂直翻转
     *
     * @return array<int, array<int, int>> 翻转后的通道数据
     */
    protected function flipImageChannel(array $channel, bool $horizontal, bool $vertical): array
    {
        $height = count($channel);
        if (0 === $height) {
            return [];
        }

        $width = count($channel[0]);
        if (0 === $width) {
            return [];
        }

        return $this->performChannelFlip($channel, $horizontal, $vertical, $width, $height);
    }

    /**
     * 执行通道翻转操作
     *
     * @param array<int, array<int, int>> $channel
     *
     * @return array<int, array<int, int>>
     */
    private function performChannelFlip(array $channel, bool $horizontal, bool $vertical, int $width, int $height): array
    {
        $flipped = [];

        for ($y = 0; $y < $height; ++$y) {
            $srcY = $vertical ? ($height - 1 - $y) : $y;
            $flipped[$y] = $this->flipChannelRow($channel[$srcY], $horizontal, $width);
        }

        return $flipped;
    }

    /**
     * 翻转通道的一行数据
     *
     * @param array<int, int> $row
     *
     * @return array<int, int>
     */
    private function flipChannelRow(array $row, bool $horizontal, int $width): array
    {
        if (!$horizontal) {
            return $row;
        }

        $flippedRow = [];
        for ($x = 0; $x < $width; ++$x) {
            $srcX = $width - 1 - $x;
            $flippedRow[$x] = $row[$srcX];
        }

        return $flippedRow;
    }

    /**
     * 旋转图像通道
     *
     * @param array<int, array<int, int>> $channel 图像通道数据
     * @param int                         $angle   旋转角度 (90, 180, 270)
     *
     * @return array<int, array<int, int>> 旋转后的通道数据
     */
    protected function rotateImageChannel(array $channel, int $angle): array
    {
        $height = count($channel);
        if (0 === $height) {
            return [];
        }

        $width = count($channel[0]);
        if (0 === $width) {
            return [];
        }

        return $this->performChannelRotation($channel, $angle, $width, $height);
    }

    /**
     * 执行通道旋转操作
     *
     * @param array<int, array<int, int>> $channel
     *
     * @return array<int, array<int, int>>
     */
    private function performChannelRotation(array $channel, int $angle, int $width, int $height): array
    {
        switch ($angle) {
            case 90:
                return $this->rotate90($channel, $width, $height);
            case 180:
                return $this->rotate180($channel, $width, $height);
            case 270:
                return $this->rotate270($channel, $width, $height);
            default:
                return $channel; // 不支持的角度，返回原始通道
        }
    }

    /**
     * 90度旋转
     *
     * @param array<int, array<int, int>> $channel
     *
     * @return array<int, array<int, int>>
     */
    private function rotate90(array $channel, int $width, int $height): array
    {
        $rotated = [];
        for ($y = 0; $y < $width; ++$y) {
            $rotated[$y] = [];
            for ($x = 0; $x < $height; ++$x) {
                $rotated[$y][$x] = $channel[$height - 1 - $x][$y];
            }
        }

        return $rotated;
    }

    /**
     * 180度旋转
     *
     * @param array<int, array<int, int>> $channel
     *
     * @return array<int, array<int, int>>
     */
    private function rotate180(array $channel, int $width, int $height): array
    {
        $rotated = [];
        for ($y = 0; $y < $height; ++$y) {
            $rotated[$y] = [];
            for ($x = 0; $x < $width; ++$x) {
                $rotated[$y][$x] = $channel[$height - 1 - $y][$width - 1 - $x];
            }
        }

        return $rotated;
    }

    /**
     * 270度旋转
     *
     * @param array<int, array<int, int>> $channel
     *
     * @return array<int, array<int, int>>
     */
    private function rotate270(array $channel, int $width, int $height): array
    {
        $rotated = [];
        for ($y = 0; $y < $width; ++$y) {
            $rotated[$y] = [];
            for ($x = 0; $x < $height; ++$x) {
                $rotated[$y][$x] = $channel[$x][$width - 1 - $y];
            }
        }

        return $rotated;
    }
}
