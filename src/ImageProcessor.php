<?php

namespace Tourze\BlindWatermark;

/**
 * 图像处理基础类
 *
 * 提供图像读取、保存及处理的基础功能
 */
class ImageProcessor
{
    /**
     * 原始图像资源
     */
    protected $image;

    /**
     * 图像宽度
     */
    protected int $width;

    /**
     * 图像高度
     */
    protected int $height;

    /**
     * 图像类型常量
     */
    public const IMAGE_TYPE_JPEG = 'jpeg';
    public const IMAGE_TYPE_PNG = 'png';

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->width = 0;
        $this->height = 0;
    }

    /**
     * 从文件中加载图像
     *
     * @param string $filePath 图像文件路径
     * @return self
     * @throws \Exception 图像加载失败时抛出异常
     */
    public function loadFromFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new \Exception("图像文件不存在: {$filePath}");
        }

        // 获取图像信息
        $imageInfo = getimagesize($filePath);
        if ($imageInfo === false) {
            throw new \Exception("无法读取图像信息: {$filePath}");
        }

        // 根据图像类型创建资源
        $type = $imageInfo[2];
        switch ($type) {
            case IMAGETYPE_JPEG:
                $this->image = \imagecreatefromjpeg($filePath);
                break;
            case IMAGETYPE_PNG:
                $this->image = \imagecreatefrompng($filePath);
                break;
            default:
                throw new \Exception("不支持的图像类型");
        }

        if ($this->image === false) {
            throw new \Exception("图像加载失败");
        }

        // 保存图像尺寸
        $this->width = $imageInfo[0];
        $this->height = $imageInfo[1];

        // 确保PNG图像支持Alpha通道
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($this->image, false);
            imagesavealpha($this->image, true);
        }

        return $this;
    }

    /**
     * 创建新的空白图像
     *
     * @param int $width 图像宽度
     * @param int $height 图像高度
     * @return self
     */
    public function createImage(int $width, int $height): self
    {
        $this->width = $width;
        $this->height = $height;
        $this->image = imagecreatetruecolor($width, $height);

        // 支持Alpha通道
        imagealphablending($this->image, false);
        imagesavealpha($this->image, true);

        return $this;
    }

    /**
     * 保存图像到文件
     *
     * @param string $filePath 保存路径
     * @param string $type 图像类型，支持jpeg和png
     * @param int $quality 图像质量(1-100)，仅对JPEG有效
     * @return bool 保存是否成功
     */
    public function saveToFile(string $filePath, string $type = self::IMAGE_TYPE_JPEG, int $quality = 90): bool
    {
        if ($this->image === null) {
            return false;
        }

        $result = false;
        switch ($type) {
            case self::IMAGE_TYPE_JPEG:
                $result = imagejpeg($this->image, $filePath, $quality);
                break;
            case self::IMAGE_TYPE_PNG:
                // PNG质量范围为0-9，需要转换
                $pngQuality = min(9, intval(9 * (100 - $quality) / 100));
                $result = imagepng($this->image, $filePath, $pngQuality);
                break;
        }

        return $result;
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
     * 获取图像资源
     *
     * @return resource|null 图像资源
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * 分离图像通道为二维数组
     *
     * @return array 包含RGB三个通道的数组，每个通道是二维数组
     */
    public function splitChannels(): array
    {
        $channels = [
            'red' => [],
            'green' => [],
            'blue' => []
        ];

        for ($y = 0; $y < $this->height; $y++) {
            $channels['red'][$y] = [];
            $channels['green'][$y] = [];
            $channels['blue'][$y] = [];

            for ($x = 0; $x < $this->width; $x++) {
                $rgb = imagecolorat($this->image, $x, $y);

                $channels['red'][$y][$x] = ($rgb >> 16) & 0xFF;
                $channels['green'][$y][$x] = ($rgb >> 8) & 0xFF;
                $channels['blue'][$y][$x] = $rgb & 0xFF;
            }
        }

        return $channels;
    }

    /**
     * 合并图像通道
     *
     * @param array $channels 包含RGB三个通道的数组
     * @return self 用于链式调用
     */
    public function mergeChannels(array $channels): self
    {
        if (empty($channels['red']) || empty($channels['green']) || empty($channels['blue'])) {
            // 通道数据不完整时返回self，而不抛出异常
            return $this;
        }

        // 确保值在0-255范围内
        $normalizeChannel = function (array $channel) {
            $result = [];
            foreach ($channel as $y => $row) {
                $result[$y] = [];
                foreach ($row as $x => $value) {
                    $result[$y][$x] = max(0, min(255, (int)round($value)));
                }
            }
            return $result;
        };

        $channels['red'] = $normalizeChannel($channels['red']);
        $channels['green'] = $normalizeChannel($channels['green']);
        $channels['blue'] = $normalizeChannel($channels['blue']);

        // 合并通道到图像
        for ($y = 0; $y < $this->height; $y++) {
            for ($x = 0; $x < $this->width; $x++) {
                $r = $channels['red'][$y][$x];
                $g = $channels['green'][$y][$x];
                $b = $channels['blue'][$y][$x];

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
     * @return array 亮度通道数据的二维数组
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
     * 释放图像资源
     */
    public function __destruct()
    {
        if ($this->image !== null && is_resource($this->image)) {
            imagedestroy($this->image);
        }
    }
}
