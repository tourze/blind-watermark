<?php

namespace Tourze\BlindWatermark;

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
     */
    protected array $position = [3, 4];

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->embedder = new WatermarkEmbedder();
        $this->extractor = new WatermarkExtractor();
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
        $this->embedder->setBlockSize($blockSize);
        $this->extractor->setBlockSize($blockSize);
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
        $this->embedder->setStrength($alpha);
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
        $this->embedder->setPosition($position);
        $this->extractor->setPosition($position);
        return $this;
    }

    /**
     * 从文件加载图像
     *
     * @param string $filePath 图像文件路径
     * @return self
     * @throws \Exception 图像加载失败时抛出异常
     */
    public function loadImage(string $filePath): self
    {
        $this->imageProcessor = new ImageProcessor();
        $this->imageProcessor->loadFromFile($filePath);
        return $this;
    }

    /**
     * 将文本水印嵌入到当前加载的图像中
     *
     * @param string $text 要嵌入的文本水印
     * @return self
     * @throws \Exception 图像未加载时抛出异常
     */
    public function embedText(string $text): self
    {
        if ($this->imageProcessor === null) {
            throw new \Exception("请先加载图像");
        }

        $this->imageProcessor = $this->embedder->embed($this->imageProcessor, $text);
        return $this;
    }

    /**
     * 从当前加载的带水印图像中提取文本水印
     *
     * @return string 提取的文本水印
     * @throws \Exception 图像未加载时抛出异常
     */
    public function extractText(): string
    {
        if ($this->imageProcessor === null) {
            throw new \Exception("请先加载图像");
        }

        return $this->extractor->extract($this->imageProcessor);
    }

    /**
     * 将处理后的图像保存到文件
     *
     * @param string $filePath 保存路径
     * @param string $type 图像类型，支持jpeg和png
     * @param int $quality 图像质量(1-100)
     * @return bool 保存是否成功
     * @throws \Exception 图像未加载时抛出异常
     */
    public function saveImage(string $filePath, string $type = ImageProcessor::IMAGE_TYPE_JPEG, int $quality = 90): bool
    {
        if ($this->imageProcessor === null) {
            throw new \Exception("请先加载图像");
        }

        return $this->imageProcessor->saveToFile($filePath, $type, $quality);
    }

    /**
     * 便捷方法：将文本水印嵌入图像并保存
     *
     * @param string $srcImagePath 原始图像路径
     * @param string $text 要嵌入的文本水印
     * @param string $destImagePath 保存路径
     * @param string $type 图像类型，支持jpeg和png
     * @param int $quality 图像质量(1-100)
     * @return bool 操作是否成功
     * @throws \Exception 处理图像时可能抛出异常
     */
    public function embedTextToImage(
        string $srcImagePath,
        string $text,
        string $destImagePath,
        string $type = ImageProcessor::IMAGE_TYPE_JPEG,
        int    $quality = 90
    ): bool
    {
        $this->loadImage($srcImagePath)
            ->embedText($text);

        return $this->saveImage($destImagePath, $type, $quality);
    }

    /**
     * 便捷方法：从带水印图像中提取文本水印
     *
     * @param string $watermarkedImagePath 带水印图像路径
     * @return string 提取的文本水印
     * @throws \Exception 处理图像时可能抛出异常
     */
    public function extractTextFromImage(string $watermarkedImagePath): string
    {
        $this->loadImage($watermarkedImagePath);
        return $this->extractText();
    }
}
