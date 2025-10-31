<?php

namespace Tourze\BlindWatermark;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tourze\BlindWatermark\Exception\BlindWatermarkException;

/**
 * 图像处理类
 *
 * 负责图像的加载、保存和GD相关操作
 */
class ImageProcessor
{
    /**
     * JPEG图像类型
     */
    public const IMAGE_TYPE_JPEG = 'jpeg';

    /**
     * PNG图像类型
     */
    public const IMAGE_TYPE_PNG = 'png';

    /**
     * 原始图像GD资源
     */
    protected ?\GdImage $image = null;

    /**
     * 图像宽度
     */
    protected int $width = 0;

    /**
     * 图像高度
     */
    protected int $height = 0;

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
    }

    /**
     * 从文件加载图像
     *
     * @param string $filePath 图像文件路径
     *
     * @return bool 加载是否成功
     *
     * @throws BlindWatermarkException 文件不存在或加载失败时抛出异常
     */
    public function loadFromFile(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            throw new BlindWatermarkException('图像文件不存在: ' . $filePath);
        }

        $imageInfo = getimagesize($filePath);
        if (false === $imageInfo) {
            throw new BlindWatermarkException('无法获取图像信息: ' . $filePath);
        }

        $this->width = $imageInfo[0];
        $this->height = $imageInfo[1];
        $mimeType = $imageInfo['mime'];

        $this->logger->debug("加载图像: {$filePath}, 尺寸: {$this->width}x{$this->height}, 类型: {$mimeType}");

        switch ($mimeType) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($filePath);
                break;
            case 'image/png':
                $image = imagecreatefrompng($filePath);
                break;
            default:
                throw new BlindWatermarkException('不支持的图像类型: ' . $mimeType);
        }

        if (false === $image) {
            throw new BlindWatermarkException('图像加载失败: ' . $filePath);
        }

        $this->image = $image;

        // 确保图像使用真彩色模式
        if (false === imageistruecolor($this->image)) {
            $this->logger->debug('将索引图像转换为真彩色模式');

            if ($this->width < 1 || $this->height < 1) {
                throw new BlindWatermarkException('图像尺寸无效');
            }

            $trueColorImage = imagecreatetruecolor($this->width, $this->height);
            if (false === $trueColorImage) {
                throw new BlindWatermarkException('创建真彩色图像失败');
            }

            imagecopy($trueColorImage, $this->image, 0, 0, 0, 0, $this->width, $this->height);
            imagedestroy($this->image);
            $this->image = $trueColorImage;
        }

        return true;
    }

    /**
     * 创建一个新的空白图像
     *
     * @param int $width  图像宽度
     * @param int $height 图像高度
     *
     * @return bool 创建是否成功
     *
     * @throws BlindWatermarkException
     */
    public function createImage(int $width, int $height): bool
    {
        if ($width < 1 || $height < 1) {
            throw new BlindWatermarkException('图像尺寸必须为正整数');
        }

        $this->width = $width;
        $this->height = $height;

        // 创建真彩色图像
        $image = imagecreatetruecolor($width, $height);
        if (false === $image) {
            $this->logger->error('创建图像失败');

            return false;
        }

        $this->image = $image;

        // 填充为白色背景
        $white = imagecolorallocate($this->image, 255, 255, 255);
        if (false !== $white) {
            imagefill($this->image, 0, 0, $white);
        }

        $this->logger->debug("创建新图像: 尺寸: {$width}x{$height}");

        return true;
    }

    /**
     * 获取图像宽度
     *
     * @return int 图像宽度
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * 获取图像高度
     *
     * @return int 图像高度
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * 将图像保存到文件
     *
     * @param string $filePath 保存路径
     * @param string $type     图像类型，支持jpeg和png
     * @param int    $quality  图像质量(1-100)
     *
     * @return bool 保存是否成功
     *
     * @throws BlindWatermarkException 图像未加载或保存失败时抛出异常
     */
    public function saveToFile(string $filePath, string $type = self::IMAGE_TYPE_JPEG, int $quality = 90): bool
    {
        if (null === $this->image) {
            throw new BlindWatermarkException('没有图像可以保存');
        }

        $this->logger->debug("保存图像: {$filePath}, 类型: {$type}, 质量: {$quality}");

        $result = false;
        switch ($type) {
            case self::IMAGE_TYPE_JPEG:
                $result = imagejpeg($this->image, $filePath, $quality);
                break;
            case self::IMAGE_TYPE_PNG:
                // PNG质量范围为0-9，需要转换
                $pngQuality = (int) min(9, max(0, floor($quality / 10)));
                $result = imagepng($this->image, $filePath, $pngQuality);
                break;
            default:
                throw new BlindWatermarkException('不支持的图像类型: ' . $type);
        }

        if (false === $result) {
            $this->logger->error("图像保存失败: {$filePath}");

            return false;
        }

        return true;
    }

    /**
     * 将图像分割为RGB三个通道
     *
     * @return array<string, array<int, array<int, int>>> 包含三个通道的二维数组，键名为'red', 'green', 'blue'
     *
     * @throws BlindWatermarkException 图像未加载时抛出异常
     */
    public function splitChannels(): array
    {
        if (null === $this->image) {
            throw new BlindWatermarkException('没有图像可以分割');
        }

        $this->logger->debug("分割图像通道: {$this->width}x{$this->height}");

        $channels = [
            'red' => [],
            'green' => [],
            'blue' => [],
        ];

        for ($y = 0; $y < $this->height; ++$y) {
            $channels['red'][$y] = [];
            $channels['green'][$y] = [];
            $channels['blue'][$y] = [];

            for ($x = 0; $x < $this->width; ++$x) {
                $rgb = imagecolorat($this->image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $channels['red'][$y][$x] = $r;
                $channels['green'][$y][$x] = $g;
                $channels['blue'][$y][$x] = $b;
            }
        }

        return $channels;
    }

    /**
     * 获取图像GD资源
     *
     * @return \GdImage|null 图像GD资源
     */
    public function getImage(): ?\GdImage
    {
        return $this->image;
    }

    /**
     * 将RGB三个通道合并为一个图像
     *
     * @param array<string, array<int, array<int, int>>> $channels 包含三个通道的二维数组，键名为'red', 'green', 'blue'
     *
     * @return self 图像处理器实例，支持链式调用
     *
     * @throws BlindWatermarkException 图像未加载或通道数据无效时抛出异常
     */
    public function mergeChannels(array $channels): self
    {
        $this->validateImageLoaded();
        $this->validateChannels($channels);

        $this->logger->debug("合并图像通道: {$this->width}x{$this->height}");

        for ($y = 0; $y < $this->height; ++$y) {
            $this->mergeChannelRow($channels, $y);
        }

        return $this;
    }

    /**
     * 验证图像是否已加载
     *
     * @throws BlindWatermarkException
     */
    private function validateImageLoaded(): void
    {
        if (null === $this->image) {
            throw new BlindWatermarkException('没有图像可以合并通道');
        }
    }

    /**
     * 验证通道数据的有效性
     *
     * @param array<string, array<int, array<int, int>>> $channels
     *
     * @throws BlindWatermarkException
     */
    private function validateChannels(array $channels): void
    {
        if (!isset($channels['red']) || !isset($channels['green']) || !isset($channels['blue'])) {
            throw new BlindWatermarkException('通道数据无效，必须包含red、green和blue三个通道');
        }

        $height = count($channels['red']);
        if (0 === $height || $height !== $this->height) {
            throw new BlindWatermarkException('通道数据高度与图像不匹配');
        }

        $width = count($channels['red'][0]);
        if (0 === $width || $width !== $this->width) {
            throw new BlindWatermarkException('通道数据宽度与图像不匹配');
        }
    }

    /**
     * 合并一行通道数据
     *
     * @param array<string, array<int, array<int, int>>> $channels
     */
    private function mergeChannelRow(array $channels, int $y): void
    {
        if (null === $this->image) {
            return;
        }

        for ($x = 0; $x < $this->width; ++$x) {
            $r = max(0, min(255, (int) $channels['red'][$y][$x]));
            $g = max(0, min(255, (int) $channels['green'][$y][$x]));
            $b = max(0, min(255, (int) $channels['blue'][$y][$x]));

            $color = imagecolorallocate($this->image, $r, $g, $b);
            if (false !== $color) {
                imagesetpixel($this->image, $x, $y, $color);
            }
        }
    }

    /**
     * 获取亮度通道（灰度图像）
     *
     * 将RGB图像转换为灰度图像，返回亮度通道的二维数组
     * 使用亮度转换公式：Y = 0.299R + 0.587G + 0.114B
     *
     * @return array<int, array<int, float>> 亮度通道数据的二维数组
     *
     * @throws BlindWatermarkException
     */
    public function getLuminanceChannel(): array
    {
        if (null === $this->image) {
            throw new BlindWatermarkException('没有图像可以获取亮度通道');
        }

        $luminance = [];

        for ($y = 0; $y < $this->height; ++$y) {
            $luminance[$y] = [];

            for ($x = 0; $x < $this->width; ++$x) {
                $rgb = imagecolorat($this->image, $x, $y);

                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // 计算亮度 (Y = 0.299R + 0.587G + 0.114B)
                $luminance[$y][$x] = 0.299 * $r + 0.587 * $g + 0.114 * $b;
            }
        }

        return $luminance;
    }

    /**
     * 析构函数，自动释放图像资源
     */
    public function __destruct()
    {
        if (null !== $this->image) {
            imagedestroy($this->image);
        }
    }
}
