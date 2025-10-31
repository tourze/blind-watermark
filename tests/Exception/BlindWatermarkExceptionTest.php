<?php

namespace Tourze\BlindWatermark\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\BlindWatermark\Exception\BlindWatermarkException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(BlindWatermarkException::class)]
final class BlindWatermarkExceptionTest extends AbstractExceptionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Exception 测试不需要特殊的设置
    }

    public function testExceptionConstants(): void
    {
        $this->assertEquals(100, BlindWatermarkException::ERROR_IMAGE_PROCESSING);
        $this->assertEquals(200, BlindWatermarkException::ERROR_WATERMARK_EMBEDDING);
        $this->assertEquals(300, BlindWatermarkException::ERROR_WATERMARK_EXTRACTION);
    }

    public function testCreateException(): void
    {
        $message = 'Test exception message';
        $code = 100;
        $previous = new \Exception('Previous exception');

        $exception = BlindWatermarkException::create($message, $code, $previous);

        $this->assertInstanceOf(BlindWatermarkException::class, $exception);
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testCreateExceptionWithoutPrevious(): void
    {
        $message = 'Test exception message';
        $code = 200;

        $exception = BlindWatermarkException::create($message, $code);

        $this->assertInstanceOf(BlindWatermarkException::class, $exception);
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testCreateExceptionWithDefaultCode(): void
    {
        $message = 'Test exception message';

        $exception = BlindWatermarkException::create($message);

        $this->assertInstanceOf(BlindWatermarkException::class, $exception);
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionInheritance(): void
    {
        $exception = new BlindWatermarkException('Test message');
        $this->assertInstanceOf(\Exception::class, $exception);
    }
}
