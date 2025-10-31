<?php

namespace Tourze\BlindWatermark\Exception;

/**
 * 盲水印操作异常类
 *
 * 用于处理盲水印相关操作中的错误情况
 */
class BlindWatermarkException extends \Exception
{
    /**
     * 图像处理错误
     */
    public const ERROR_IMAGE_PROCESSING = 100;

    /**
     * 水印嵌入错误
     */
    public const ERROR_WATERMARK_EMBEDDING = 200;

    /**
     * 水印提取错误
     */
    public const ERROR_WATERMARK_EXTRACTION = 300;

    /**
     * 创建特定类型的异常
     *
     * @param string          $message  错误消息
     * @param int             $code     错误代码
     * @param \Throwable|null $previous 前一个异常
     */
    public static function create(string $message, int $code = 0, ?\Throwable $previous = null): self
    {
        return new self($message, $code, $previous);
    }
}
