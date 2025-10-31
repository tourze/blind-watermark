# Blind Watermark

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/blind-watermark.svg?style=flat-square)]
(https://packagist.org/packages/tourze/blind-watermark)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/blind-watermark.svg?style=flat-square)]
(https://packagist.org/packages/tourze/blind-watermark)  
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/blind-watermark.svg?style=flat-square)]
(https://packagist.org/packages/tourze/blind-watermark)
[![License](https://img.shields.io/packagist/l/tourze/blind-watermark.svg?style=flat-square)]
(https://packagist.org/packages/tourze/blind-watermark)  
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/monorepo/ci.yml?style=flat-square)]
(https://github.com/tourze/monorepo/actions)
[![Code Coverage](https://img.shields.io/codecov/c/github/tourze/monorepo?style=flat-square)]
(https://codecov.io/gh/tourze/monorepo)

A PHP implementation of blind watermarking technology that allows embedding 
invisible text watermarks into images using DCT (Discrete Cosine Transform).

## Table of Contents

- [Requirements](#requirements)
- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Advanced Usage](#advanced-usage)
- [Command Line Usage](#command-line-usage)
- [Configuration Parameters](#configuration-parameters)
- [Anti-Attack Features](#anti-attack-features)
- [Exception Handling](#exception-handling)
- [Technical Details](#technical-details)
- [Limitations](#limitations)
- [Performance Considerations](#performance-considerations)
- [Security](#security)
- [Contributing](#contributing)
- [Changelog](#changelog)
- [License](#license)

## Requirements

- PHP 8.1 or higher
- GD extension

## Features

- **Invisible Watermarks**: Embeds text watermarks that are imperceptible to human eyes
- **Blind Extraction**: Extract watermarks without requiring the original image
- **DCT-based Technology**: Uses frequency domain embedding for robust watermark placement
- **Anti-attack Features**:
  - Symmetric embedding for flip resistance
  - Multi-point embedding for enhanced robustness
  - Geometric transformation correction
- **Image Format Support**: Supports JPEG and PNG formats
- **Simple API**: Easy-to-use interface for watermark embedding and extraction

## Installation

Install via Composer:

```bash
composer require tourze/blind-watermark
```

## Quick Start

### Basic Usage

#### Embed Watermark

```php
use Tourze\BlindWatermark\BlindWatermark;

$watermark = new BlindWatermark();
$watermark->embedTextToImage(
    'input.jpg',           // Source image path
    'Copyright 2024',      // Text to embed
    'watermarked.jpg'      // Output image path
);
```

#### Extract Watermark

```php
use Tourze\BlindWatermark\BlindWatermark;

$watermark = new BlindWatermark();
$text = $watermark->extractTextFromImage('watermarked.jpg');
echo "Extracted text: " . $text;
```

## Advanced Usage

### Advanced Configuration

```php
use Tourze\BlindWatermark\BlindWatermark;

$watermark = new BlindWatermark();

// Configure watermark parameters
$watermark->setAlpha(90.0)              // Set watermark strength (default: 36.0)
    ->setBlockSize(8)                   // Set DCT block size (default: 8)
    ->setPosition([3, 4])               // Set embedding position in DCT coefficients
    ->enableSymmetricEmbedding()        // Enable flip resistance
    ->enableMultiPointEmbedding()       // Enable enhanced robustness
    ->enableGeometricCorrection();      // Enable geometric transformation correction

// Embed watermark
$watermark->embedTextToImage(
    'source.jpg',
    'Secret Message',
    'output.jpg',
    'jpeg',     // Output format (optional, default: jpeg)
    90          // Output quality (optional, default: 90)
);
```

### Step-by-Step API

```php
use Tourze\BlindWatermark\BlindWatermark;

$watermark = new BlindWatermark();

// Load image
$watermark->loadImage('source.jpg');

// Configure and embed
$watermark->setAlpha(50.0)
    ->embedText('My Watermark');

// Save result
$watermark->saveImage('output.jpg', 'jpeg', 95);

// Extract from saved image
$watermark->loadImage('output.jpg');
$extractedText = $watermark->extractText();
```

## Command Line Usage

The package includes command-line scripts for easy watermark operations:

### Embed watermark
```bash
php examples/embed_text.php <source_image> <watermark_text> <output_image>
```

### Extract watermark
```bash
php examples/extract_text.php <watermarked_image>
```

## Configuration Parameters

| Parameter | Description | Default | Range |
|-----------|-------------|---------|-------|
| `alpha` | Watermark strength coefficient | 36.0 | 0.1 - 100.0 |
| `blockSize` | DCT block size | 8 | 4, 8, 16 |
| `position` | Embedding position in DCT matrix | [3, 4] | [0-7, 0-7] |

## Anti-Attack Features

### Symmetric Embedding
Protects against horizontal and vertical flipping by embedding watermark information symmetrically.

```php
$watermark->enableSymmetricEmbedding();
```

### Multi-Point Embedding
Enhances robustness by embedding the same watermark bit at multiple positions.

```php
$watermark->enableMultiPointEmbedding();
```

### Geometric Correction
Automatically detects and corrects geometric transformations before extraction.

```php
$watermark->enableGeometricCorrection();
```

## Exception Handling

All errors are thrown as `BlindWatermarkException`:

```php
use Tourze\BlindWatermark\BlindWatermark;
use Tourze\BlindWatermark\Exception\BlindWatermarkException;

try {
    $watermark = new BlindWatermark();
    $watermark->embedTextToImage('input.jpg', 'watermark', 'output.jpg');
} catch (BlindWatermarkException $e) {
    echo "Error: " . $e->getMessage();
}
```

## Technical Details

The library implements blind watermarking using DCT (Discrete Cosine Transform):

1. **Embedding Process**:
    - Decomposes the image into RGB channels
    - Applies 8x8 block DCT transformation to the blue channel
    - Embeds watermark bits in mid-frequency DCT coefficients
    - Reconstructs the image using inverse DCT

2. **Extraction Process**:
    - Performs the same DCT transformation on the watermarked image
    - Extracts embedded bits from the designated DCT coefficients
    - Reconstructs the original text from the binary data

## Limitations

- Text watermarks only (image watermarks not supported)
- Watermark capacity depends on image size
- Rotation support limited to 90-degree increments
- Requires GD extension (ImageMagick not supported)

## Performance Considerations

- Larger `blockSize` values reduce processing time but decrease watermark capacity
- Higher `alpha` values increase watermark robustness but may affect image quality
- Enabling anti-attack features increases processing time

## Security

### Security Considerations

- **Watermark Detection**: While watermarks are invisible to human eyes, they can 
  potentially be detected by specialized analysis tools
- **Text Limitations**: Avoid embedding sensitive information as the extraction 
  algorithm may be reverse-engineered
- **File Integrity**: Always verify image integrity before watermark extraction
- **Parameter Protection**: Keep watermark parameters (alpha, position) confidential 
  for enhanced security

### Best Practices

1. Use strong, unique parameters for each watermarking session
2. Implement additional encryption for sensitive watermark content
3. Regularly test watermark robustness against common image processing operations
4. Monitor for potential watermark removal attempts

## Contributing

We welcome contributions! Please follow these guidelines:

### Reporting Issues

- Use the GitHub issue tracker to report bugs
- Provide clear reproduction steps
- Include PHP version and system information
- Attach sample images if applicable

### Submitting Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run tests: `./vendor/bin/phpunit packages/blind-watermark/tests`
5. Run static analysis: `php -d memory_limit=2G ./vendor/bin/phpstan analyse packages/blind-watermark`
6. Commit your changes (`git commit -m 'Add amazing feature'`)
7. Push to the branch (`git push origin feature/amazing-feature`)
8. Open a Pull Request

### Code Style

- Follow PSR-12 coding standards
- Use PHP 8.1+ features and type declarations
- Add PHPDoc comments for public methods
- Write meaningful variable and method names

### Testing Requirements

- Write tests for new features
- Ensure all tests pass
- Maintain or improve code coverage
- Test edge cases and error conditions

## Changelog

### [Unreleased]
- Initial release
- Basic watermark embedding and extraction
- DCT-based implementation
- Anti-attack features (flip, rotation resistance)
- Command line interface
- Comprehensive test suite

## License

This package is open-sourced software licensed under the MIT license.
