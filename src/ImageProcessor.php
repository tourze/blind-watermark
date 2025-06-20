<?php

namespace Tourze\BlindWatermark;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
     * 
     * @var resource|\GdImage|null
     */
    protected $image = null;

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
     * @return bool 加载是否成功
     * @throws \Exception 文件不存在或加载失败时抛出异常
     */
    public function loadFromFile(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            throw new \Exception("图像文件不存在: " . $filePath);
        }

        $imageInfo = getimagesize($filePath);
        if ($imageInfo === false) {
            throw new \Exception("无法获取图像信息: " . $filePath);
        }

        $this->width = $imageInfo[0];
        $this->height = $imageInfo[1];
        $mimeType = $imageInfo['mime'];

        $this->logger->debug("加载图像: {$filePath}, 尺寸: {$this->width}x{$this->height}, 类型: {$mimeType}");

        switch ($mimeType) {
            case 'image/jpeg':
                $this->image = imagecreatefromjpeg($filePath);
                break;
            case 'image/png':
                $this->image = imagecreatefrompng($filePath);
                break;
            default:
                throw new \Exception("不支持的图像类型: " . $mimeType);
        }

        if ($this->image === false) {
            throw new \Exception("图像加载失败: " . $filePath);
        }

        // 确保图像使用真彩色模式
        if (imageistruecolor($this->image) === false) {
            $this->logger->debug("将索引图像转换为真彩色模式");
            
            $trueColorImage = imagecreatetruecolor($this->width, $this->height);
            imagecopy($trueColorImage, $this->image, 0, 0, 0, 0, $this->width, $this->height);
            imagedestroy($this->image);
            $this->image = $trueColorImage;
        }

        return true;
    }
    
    /**
     * 创建一个新的空白图像
     * 
     * @param int $width 图像宽度
     * @param int $height 图像高度
     * @return bool 创建是否成功
     */
    public function createImage(int $width, int $height): bool
    {
        $this->width = $width;
        $this->height = $height;
        
        // 创建真彩色图像
        $this->image = imagecreatetruecolor($width, $height);
        if ($this->image === false) {
            $this->logger->error("创建图像失败");
            return false;
        }
        
        // 填充为白色背景
        $white = imagecolorallocate($this->image, 255, 255, 255);
        imagefill($this->image, 0, 0, $white);
        
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
     * @param string $type 图像类型，支持jpeg和png
     * @param int $quality 图像质量(1-100)
     * @return bool 保存是否成功
     * @throws \Exception 图像未加载或保存失败时抛出异常
     */
    public function saveToFile(string $filePath, string $type = self::IMAGE_TYPE_JPEG, int $quality = 90): bool
    {
        if ($this->image === null) {
            throw new \Exception("没有图像可以保存");
        }

        $this->logger->debug("保存图像: {$filePath}, 类型: {$type}, 质量: {$quality}");

        $result = false;
        switch ($type) {
            case self::IMAGE_TYPE_JPEG:
                $result = imagejpeg($this->image, $filePath, $quality);
                break;
            case self::IMAGE_TYPE_PNG:
                // PNG质量范围为0-9，需要转换
                $pngQuality = min(9, max(0, floor($quality / 10)));
                $result = imagepng($this->image, $filePath, $pngQuality);
                break;
            default:
                throw new \Exception("不支持的图像类型: " . $type);
        }

        if ($result === false) {
            $this->logger->error("图像保存失败: {$filePath}");
            return false;
        }

        return true;
    }

    /**
     * 将图像分割为RGB三个通道
     * 
     * @return array<string, array<int, array<int, int>>> 包含三个通道的二维数组，键名为'red', 'green', 'blue'
     * @throws \Exception 图像未加载时抛出异常
     */
    public function splitChannels(): array
    {
        if ($this->image === null) {
            throw new \Exception("没有图像可以分割");
        }

        $this->logger->debug("分割图像通道: {$this->width}x{$this->height}");

        $channels = [
            'red' => [],
            'green' => [],
            'blue' => [],
        ];

        for ($y = 0; $y < $this->height; $y++) {
            $channels['red'][$y] = [];
            $channels['green'][$y] = [];
            $channels['blue'][$y] = [];

            for ($x = 0; $x < $this->width; $x++) {
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
     * @return resource|\GdImage|null 图像GD资源
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * 将RGB三个通道合并为一个图像
     * 
     * @param array<string, array<int, array<int, int>>> $channels 包含三个通道的二维数组，键名为'red', 'green', 'blue'
     * @return self 图像处理器实例，支持链式调用
     * @throws \Exception 图像未加载或通道数据无效时抛出异常
     */
    public function mergeChannels(array $channels): self
    {
        if ($this->image === null) {
            throw new \Exception("没有图像可以合并通道");
        }

        if (!isset($channels['red']) || !isset($channels['green']) || !isset($channels['blue'])) {
            throw new \Exception("通道数据无效，必须包含red、green和blue三个通道");
        }

        $height = count($channels['red']);
        if ($height === 0 || $height !== $this->height) {
            throw new \Exception("通道数据高度与图像不匹配");
        }

        $width = count($channels['red'][0]);
        if ($width === 0 || $width !== $this->width) {
            throw new \Exception("通道数据宽度与图像不匹配");
        }

        $this->logger->debug("合并图像通道: {$width}x{$height}");

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $r = max(0, min(255, (int)$channels['red'][$y][$x]));
                $g = max(0, min(255, (int)$channels['green'][$y][$x]));
                $b = max(0, min(255, (int)$channels['blue'][$y][$x]));

                $color = imagecolorallocate($this->image, $r, $g, $b);
                imagesetpixel($this->image, $x, $y, $color);
            }
        }

        return $this;
    }

    /**
     * 获取亮度通道（灰度图像）
     *
     * 将RGB图像转换为灰度图像，返回亮度通道的二维数组
     * 使用亮度转换公式：Y = 0.299R + 0.587G + 0.114B
     *
     * @return array<int, array<int, float>> 亮度通道数据的二维数组
     */
    public function getLuminanceChannel(): array
    {
        $luminance = [];

        for ($y = 0; $y < $this->height; $y++) {
            $luminance[$y] = [];

            for ($x = 0; $x < $this->width; $x++) {
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
        if ($this->image !== null) {
            imagedestroy($this->image);
        }
    }
}
