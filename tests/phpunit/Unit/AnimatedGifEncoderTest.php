<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Tests\Unit;

use DrevOps\BehatScreenshotExtension\AnimatedGifEncoder;
use DrevOps\BehatScreenshotExtension\Tests\Traits\GifParserTrait;
use DrevOps\BehatScreenshotExtension\Tests\Traits\ReflectionTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\TestCase;

/**
 * Test AnimatedGifEncoder.
 */
#[CoversClass(AnimatedGifEncoder::class)]
#[RequiresFunction('imagecreatetruecolor')]
#[RequiresFunction('imagegif')]
class AnimatedGifEncoderTest extends TestCase {

  use GifParserTrait;
  use ReflectionTrait;

  public function testEncodeProducesValidAnimatedGif(): void {
    $frames = [
      $this->createPngFrame(120, 90, [255, 0, 0]),
      $this->createPngFrame(120, 90, [0, 255, 0]),
      $this->createPngFrame(120, 90, [0, 0, 255]),
    ];

    $gif = (new AnimatedGifEncoder())->encode($frames, 500);

    $this->assertStringStartsWith('GIF89a', $gif);
    // Looping is requested via the Netscape Application Extension.
    $this->assertStringContainsString('NETSCAPE2.0', $gif);
    $this->assertCount(3, $this->parseFrames($gif));
    $this->assertSame([120, 90], $this->readCanvasSize($gif));
    $this->assertSame([120, 90], $this->readFirstFrameSize($gif));
  }

  public function testEncodeSizesCanvasToLargestFrame(): void {
    $frames = [
      $this->createPngFrame(100, 100, [10, 20, 30]),
      $this->createPngFrame(64, 48, [200, 100, 50]),
      $this->createPngFrame(150, 120, [0, 0, 0]),
    ];

    $gif = (new AnimatedGifEncoder())->encode($frames, 200);

    $this->assertSame([150, 120], $this->readCanvasSize($gif));
  }

  public function testEncodeWritesFramesAtCapturedSize(): void {
    $frames = [
      $this->createPngFrame(100, 100, [10, 20, 30]),
      $this->createPngFrame(64, 48, [200, 100, 50]),
      $this->createPngFrame(150, 120, [0, 0, 0]),
    ];

    $gif = (new AnimatedGifEncoder())->encode($frames, 200);

    $this->assertSame([
      ['left' => 0, 'top' => 0, 'width' => 100, 'height' => 100],
      ['left' => 0, 'top' => 0, 'width' => 64, 'height' => 48],
      ['left' => 0, 'top' => 0, 'width' => 150, 'height' => 120],
    ], $this->readFrameGeometry($gif));
  }

  public function testEncodeDoesNotStretchSmallerFrames(): void {
    $frames = [
      $this->createPngFrame(40, 30, [255, 0, 0]),
      $this->createPngFrame(80, 60, [0, 0, 255]),
    ];

    $gif = (new AnimatedGifEncoder())->encode($frames, 100);

    $this->assertSame([80, 60], $this->readCanvasSize($gif));
    $this->assertSame([40, 30], $this->readFirstFrameSize($gif));
    $this->assertColorNear([255, 0, 0], $this->readPixelColor($gif, 5, 5));
  }

  public function testEncodeExposesWhiteAroundSmallerFrames(): void {
    $frames = [
      $this->createPngFrame(40, 30, [255, 0, 0]),
      $this->createPngFrame(80, 60, [0, 0, 255]),
    ];

    $gif = (new AnimatedGifEncoder())->encode($frames, 100);

    // The global colour table holds white at index 0 and the Logical Screen
    // Descriptor points the background colour at it.
    $this->assertSame(0xF0, ord($gif[10]));
    $this->assertSame(0, ord($gif[11]));
    $this->assertSame(0, ord($gif[12]));
    $this->assertSame("\xFF\xFF\xFF\x00\x00\x00", substr($gif, 13, 6));
  }

  public function testEncodeKeepsFramesOnScreenWhenTheyDoNotShrink(): void {
    $frames = [
      $this->createPngFrame(80, 60, [255, 0, 0]),
      $this->createPngFrame(80, 60, [0, 255, 0]),
      $this->createPngFrame(80, 60, [0, 0, 255]),
    ];

    $gif = (new AnimatedGifEncoder())->encode($frames, 100);

    // Same-sized opaque frames always cover the previous one, so none needs
    // clearing.
    $this->assertSame([1, 1, 1], array_column($this->parseFrames($gif), 'disposal'));
  }

  #[DataProvider('dataProviderEncodeSetsDisposalPerFrame')]
  public function testEncodeSetsDisposalPerFrame(array $frame_specs, array $expected_disposals): void {
    $frames = [];

    foreach ($frame_specs as [$width, $height, $is_transparent]) {
      $frames[] = $is_transparent ? $this->createTransparentFrame($width, $height) : $this->createPngFrame($width, $height, [10, 20, 30]);
    }

    $gif = (new AnimatedGifEncoder())->encode($frames, 100);

    $this->assertSame($expected_disposals, array_column($this->parseFrames($gif), 'disposal'));
  }

  public static function dataProviderEncodeSetsDisposalPerFrame(): array {
    return [
      'single frame' => [[[80, 60, FALSE]], [1]],
      'growing frames' => [[[40, 30, FALSE], [80, 60, FALSE]], [1, 2]],
      'shrinking frames' => [[[80, 60, FALSE], [40, 30, FALSE]], [2, 1]],
      'narrower next frame' => [[[80, 60, FALSE], [40, 60, FALSE]], [2, 1]],
      'shorter next frame' => [[[80, 60, FALSE], [80, 30, FALSE]], [2, 1]],
      'uniform then shorter' => [[[80, 60, FALSE], [80, 60, FALSE], [80, 30, FALSE]], [1, 2, 1]],
      'single transparent frame' => [[[80, 60, TRUE]], [2]],
      'transparent next frame' => [[[80, 60, FALSE], [80, 60, TRUE]], [2, 1]],
      'opaque next frame' => [[[80, 60, TRUE], [80, 60, FALSE]], [1, 2]],
      'transparent frames' => [[[80, 60, TRUE], [80, 60, TRUE]], [2, 2]],
      'growing transparent frames' => [[[40, 30, TRUE], [80, 60, TRUE]], [2, 2]],
    ];
  }

  public function testEncodeDoesNotInflatePixelsWhenOneFrameIsMuchTaller(): void {
    $frames = [];

    for ($step = 0; $step < 120; $step++) {
      $frames[] = $step === 60
        ? $this->createPngFrame(160, 2400, [0, 0, 200])
        : $this->createPngFrame(160, 120, [200, 0, 0]);
    }

    $gif = (new AnimatedGifEncoder())->encode($frames, 500);

    // The logical screen still covers the tall frame. The other 119 frames
    // are encoded at their own size, not padded to it: 2,668,800 pixels
    // instead of 46,080,000 for a shared canvas.
    $this->assertSame([160, 2400], $this->readCanvasSize($gif));
    $this->assertSame(2668800, $this->countEncodedPixels($gif));
  }

  public function testEncodeDoesNotPayForPaddingPixels(): void {
    $tall = $this->createGradientPngFrame(400, 400);
    $short = $this->createGradientPngFrame(20, 20);

    $mixed = (new AnimatedGifEncoder())->encode([$tall, $short, $short, $short, $short], 100);
    $uniform = (new AnimatedGifEncoder())->encode([$tall, $tall, $tall, $tall, $tall], 100);

    // The four small frames are encoded at 20x20 rather than padded to
    // 400x400, so the mixed animation costs about one large frame.
    $this->assertLessThan(intdiv(strlen($uniform), 3), strlen($mixed));
  }

  #[DataProvider('dataProviderEncodeKeepsFrameTransparency')]
  public function testEncodeKeepsFrameTransparency(string $format, int $max_height, array $expected_size): void {
    $transparent = $this->createTransparentFrame(40, 30, $format);
    $opaque = $this->createPngFrame(40, 30, [10, 20, 30]);

    $gif = (new AnimatedGifEncoder(0, $max_height))->encode([$transparent, $opaque, $transparent], 100);

    $this->assertSame($expected_size, $this->readCanvasSize($gif));

    // GD decodes only the first frame, so pixels are checked on that frame
    // and the other frames through their Graphic Control Extensions.
    $this->assertTrue($this->isPixelTransparent($gif, 0, 0));
    $this->assertFalse($this->isPixelTransparent($gif, 39, 0));
    $this->assertColorNear([200, 30, 30], $this->readPixelColor($gif, 39, 0));

    $transparent_index = $this->parseFrames($gif)[0]['transparent_index'];
    $this->assertSame([$transparent_index, self::GIF_NO_TRANSPARENT_INDEX, $transparent_index], array_column($this->parseFrames($gif), 'transparent_index'));
  }

  public static function dataProviderEncodeKeepsFrameTransparency(): array {
    // GD's PNG writer moves the transparent colour to palette index 0, and a
    // GIF keeps it at index 1.
    return [
      'palette PNG frame' => ['png', 0, [40, 30]],
      'palette GIF frame' => ['gif', 0, [40, 30]],
      'cropped palette PNG frame' => ['png', 20, [40, 20]],
      'cropped palette GIF frame' => ['gif', 20, [40, 20]],
    ];
  }

  #[DataProvider('dataProviderReadExtensionBlocksFindsDescriptorAndTransparency')]
  public function testReadExtensionBlocksFindsDescriptorAndTransparency(string $gif, int $expected_descriptor_offset, ?int $expected_transparent_index): void {
    $result = self::callProtectedMethod(new AnimatedGifEncoder(), 'readExtensionBlocks', [$gif]);

    $this->assertSame(['descriptor_offset' => $expected_descriptor_offset, 'transparent_index' => $expected_transparent_index], $result);
  }

  public static function dataProviderReadExtensionBlocksFindsDescriptorAndTransparency(): array {
    // The header with its two-entry global colour table takes 19 bytes, a
    // Graphic Control Extension 8 bytes and the comment extension 7 bytes.
    $header = 'GIF89a' . pack('vv', 1, 1) . "\x80\x00\x00\x00\x00\x00\xFF\xFF\xFF";
    $image = "\x2C" . pack('vvvv', 0, 0, 1, 1) . "\x00\x02\x02\x44\x01\x00\x3B";
    $transparent_control = "\x21\xF9\x04\x01\x00\x00\x05\x00";
    $opaque_control = "\x21\xF9\x04\x00\x00\x00\x05\x00";
    $comment = "\x21\xFE\x03abc\x00";

    return [
      'no extension blocks' => [$header . $image, 19, NULL],
      'transparent graphic control' => [$header . $transparent_control . $image, 27, 5],
      'opaque graphic control' => [$header . $opaque_control . $image, 27, NULL],
      'comment before graphic control' => [$header . $comment . $transparent_control . $image, 34, 5],
      'graphic control before comment' => [$header . $transparent_control . $comment . $image, 34, 5],
    ];
  }

  public function testEncodeMatchesFixture(): void {
    $dir = __DIR__ . '/../fixtures/animation';

    $frames = [
      (string) file_get_contents($dir . '/frame_001.png'),
      (string) file_get_contents($dir . '/frame_002.png'),
      (string) file_get_contents($dir . '/frame_003.png'),
    ];

    $produced = (new AnimatedGifEncoder())->encode($frames, 300);
    $expected = (string) file_get_contents($dir . '/expected.gif');

    // The per-frame colour tables and LZW byte stream are produced by GD and
    // are not guaranteed to be identical across libgd versions. The GIFs are
    // compared on the structure the encoder is responsible for rather than
    // byte for byte.
    $this->assertSame($this->readGifSignature($expected), $this->readGifSignature($produced));
    $this->assertSame([80, 60], $this->readFirstFrameSize($produced));
  }

  public function testEncodeSkipsUndecodableFrames(): void {
    $frames = [
      $this->createPngFrame(30, 20, [0, 128, 0]),
      'not-an-image',
    ];

    $gif = (new AnimatedGifEncoder())->encode($frames, 100);

    $this->assertStringStartsWith('GIF89a', $gif);
    $this->assertCount(1, $this->parseFrames($gif));
    $this->assertSame([30, 20], $this->readCanvasSize($gif));
  }

  public function testEncodeThrowsWhenNoFramesProvided(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('At least one frame is required');

    (new AnimatedGifEncoder())->encode([], 500);
  }

  public function testEncodeThrowsWhenNoFramesDecodable(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('None of the provided frames could be decoded');

    (new AnimatedGifEncoder())->encode(['not-an-image'], 500);
  }

  public function testEncodeDiscardsFramesFromPreviousCall(): void {
    $encoder = new AnimatedGifEncoder();

    $encoder->encode([$this->createPngFrame(40, 30, [255, 0, 0])], 100);
    $gif = $encoder->encode([$this->createPngFrame(80, 60, [0, 0, 255])], 100);

    $this->assertCount(1, $this->parseFrames($gif));
    $this->assertSame([80, 60], $this->readCanvasSize($gif));
  }

  #[DataProvider('dataProviderEncodeConvertsDelayToCentiseconds')]
  public function testEncodeConvertsDelayToCentiseconds(int $milliseconds, int $expected_centiseconds): void {
    $frames = [
      $this->createPngFrame(20, 20, [1, 2, 3]),
      $this->createPngFrame(20, 20, [4, 5, 6]),
    ];

    $gif = (new AnimatedGifEncoder())->encode($frames, $milliseconds);

    $this->assertSame([$expected_centiseconds, $expected_centiseconds], array_column($this->parseFrames($gif), 'delay'));
  }

  public static function dataProviderEncodeConvertsDelayToCentiseconds(): array {
    return [
      'half second' => [500, 50],
      'one second' => [1000, 100],
      'zero delay' => [0, 0],
      'rounds to nearest' => [44, 4],
      // The delay shares the unsigned 16-bit field width of the geometry.
      'largest representable delay' => [655350, 65535],
      'beyond the representable delay' => [900000, 65535],
    ];
  }

  public function testAddFrameReportsWhetherTheFrameWasAdded(): void {
    $encoder = new AnimatedGifEncoder();

    $this->assertTrue($encoder->addFrame($this->createPngFrame(20, 20, [1, 2, 3])));
    $this->assertFalse($encoder->addFrame('not-an-image'));
    $this->assertCount(1, $encoder);
  }

  public function testResetDiscardsAddedFrames(): void {
    $encoder = new AnimatedGifEncoder();
    $encoder->addFrame($this->createPngFrame(20, 20, [1, 2, 3]));
    $encoder->reset();

    $this->assertCount(0, $encoder);
  }

  public function testRenderThrowsWhenNoFramesAdded(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('None of the provided frames could be decoded');

    (new AnimatedGifEncoder())->render(500);
  }

  public function testRenderAssemblesIncrementallyAddedFrames(): void {
    $encoder = new AnimatedGifEncoder();
    $encoder->addFrame($this->createPngFrame(40, 30, [255, 0, 0]));
    $encoder->addFrame($this->createPngFrame(80, 60, [0, 0, 255]));

    $gif = $encoder->render(100);

    $this->assertSame([80, 60], $this->readCanvasSize($gif));
    $this->assertSame([
      ['left' => 0, 'top' => 0, 'width' => 40, 'height' => 30],
      ['left' => 0, 'top' => 0, 'width' => 80, 'height' => 60],
    ], $this->readFrameGeometry($gif));
  }

  #[DataProvider('dataProviderConstrainCapsFramesToTheConfiguredMaximums')]
  public function testConstrainCapsFramesToTheConfiguredMaximums(int $max_width, int $max_height, int $width, int $height, array $expected_size): void {
    $gif = (new AnimatedGifEncoder($max_width, $max_height))->encode([$this->createPngFrame($width, $height, [10, 20, 30])], 100);

    $this->assertSame($expected_size, $this->readCanvasSize($gif));
  }

  public static function dataProviderConstrainCapsFramesToTheConfiguredMaximums(): array {
    return [
      'no caps' => [0, 0, 400, 200, [400, 200]],
      'width cap leaves height alone' => [100, 0, 400, 200, [100, 200]],
      'height cap leaves width alone' => [0, 100, 400, 200, [400, 100]],
      'both caps apply' => [200, 50, 400, 200, [200, 50]],
      'frame already within caps' => [800, 800, 400, 200, [400, 200]],
      'cap equal to frame size' => [400, 200, 400, 200, [400, 200]],
      'extreme height cap' => [0, 20, 400, 4000, [400, 20]],
      'single pixel height cap' => [0, 1, 400, 4000, [400, 1]],
    ];
  }

  public function testConstrainClampsFramesToTheGifDimensionLimit(): void {
    // GIF records frame dimensions as unsigned 16-bit values, so a taller
    // frame has to be cropped to stay representable.
    $gif = (new AnimatedGifEncoder())->encode([$this->createPngFrame(4, 70000, [10, 20, 30])], 100);

    $this->assertSame([4, 65535], $this->readCanvasSize($gif));
    $this->assertSame([['left' => 0, 'top' => 0, 'width' => 4, 'height' => 65535]], $this->readFrameGeometry($gif));
  }

  #[DataProvider('dataProviderConstrainKeepsTheTopLeftOfAnOversizedFrame')]
  public function testConstrainKeepsTheTopLeftOfAnOversizedFrame(bool $is_truecolor): void {
    $image = $is_truecolor ? imagecreatetruecolor(80, 200) : imagecreate(80, 200);
    imagefilledrectangle($image, 0, 0, 79, 99, (int) imagecolorallocate($image, 255, 0, 0));
    imagefilledrectangle($image, 0, 100, 79, 199, (int) imagecolorallocate($image, 0, 0, 255));
    ob_start();
    imagepng($image);
    $frame = (string) ob_get_clean();

    $gif = (new AnimatedGifEncoder(0, 100))->encode([$frame], 100);

    // The kept half is the top one, at its original resolution.
    $this->assertSame([80, 100], $this->readCanvasSize($gif));
    $this->assertColorNear([255, 0, 0], $this->readPixelColor($gif, 40, 50));
  }

  public static function dataProviderConstrainKeepsTheTopLeftOfAnOversizedFrame(): array {
    return [
      'truecolor frame' => [TRUE],
      'palette frame' => [FALSE],
    ];
  }

  public function testConstrainCopiesSemiTransparentPixelsUnblended(): void {
    $image = imagecreatetruecolor(80, 200);
    imagealphablending($image, FALSE);
    imagesavealpha($image, TRUE);
    imagefilledrectangle($image, 0, 0, 79, 99, (int) imagecolorallocatealpha($image, 255, 0, 0, 63));
    imagefilledrectangle($image, 0, 100, 79, 199, (int) imagecolorallocate($image, 0, 0, 255));
    ob_start();
    imagepng($image);
    $frame = (string) ob_get_clean();

    $gif = (new AnimatedGifEncoder(0, 100))->encode([$frame], 100);

    // GIF has no alpha channel, so GD writes the pixel's colour without its
    // alpha. A crop that blends the pixel onto the new image darkens it.
    $this->assertSame([80, 100], $this->readCanvasSize($gif));
    $this->assertColorNear([255, 0, 0], $this->readPixelColor($gif, 40, 50));
  }

  #[DataProvider('dataProviderConstrainKeepsTheTransparentColor')]
  public function testConstrainKeepsTheTransparentColor(bool $is_truecolor): void {
    $image = $this->createTransparentImage(40, 30, $is_truecolor);
    $transparent_index = imagecolortransparent($image);

    // imagegif() drops a truecolor image's transparent colour when GD is built
    // with libimagequant, so the crop is checked on the image itself.
    $cropped = self::callProtectedMethod(new AnimatedGifEncoder(0, 20), 'constrain', [$image]);

    if (!$cropped instanceof \GdImage) {
      $this->fail('The constrained frame is not an image.');
    }

    $this->assertSame([40, 20], [imagesx($cropped), imagesy($cropped)]);
    $this->assertSame($transparent_index, imagecolortransparent($cropped));
    $this->assertSame($transparent_index, imagecolorat($cropped, 0, 0));
    $this->assertNotSame($transparent_index, imagecolorat($cropped, 39, 0));
  }

  public static function dataProviderConstrainKeepsTheTransparentColor(): array {
    return [
      'truecolor image' => [TRUE],
      'palette image' => [FALSE],
    ];
  }

  public function testConstrainAppliesToEachFrameIndependently(): void {
    $frames = [
      $this->createPngFrame(400, 200, [10, 20, 30]),
      $this->createPngFrame(50, 40, [200, 100, 50]),
    ];

    $gif = (new AnimatedGifEncoder(100, 0))->encode($frames, 100);

    $this->assertSame([
      ['left' => 0, 'top' => 0, 'width' => 100, 'height' => 200],
      ['left' => 0, 'top' => 0, 'width' => 50, 'height' => 40],
    ], $this->readFrameGeometry($gif));
  }

  /**
   * Create a solid-colour PNG frame.
   *
   * @param int $width
   *   Frame width.
   * @param int $height
   *   Frame height.
   * @param array<int,int> $rgb
   *   Red, green and blue colour components.
   *
   * @return string
   *   Binary PNG content.
   */
  protected function createPngFrame(int $width, int $height, array $rgb): string {
    $image = imagecreatetruecolor(max(1, $width), max(1, $height));

    if (!$image instanceof \GdImage) {
      return '';
    }

    $color = (int) imagecolorallocate($image, min(255, max(0, $rgb[0])), min(255, max(0, $rgb[1])), min(255, max(0, $rgb[2])));
    imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $color);

    ob_start();
    imagepng($image);
    $content = ob_get_clean();

    return (string) $content;
  }

  /**
   * Create a PNG frame whose content scales with its pixel count.
   *
   * @param int $width
   *   Frame width.
   * @param int $height
   *   Frame height.
   *
   * @return string
   *   Binary PNG content.
   */
  protected function createGradientPngFrame(int $width, int $height): string {
    $image = imagecreatetruecolor(max(1, $width), max(1, $height));

    if (!$image instanceof \GdImage) {
      return '';
    }

    for ($y = 0; $y < $height; $y++) {
      for ($x = 0; $x < $width; $x++) {
        $color = (int) imagecolorallocate($image, $x % 256, $y % 256, ($x + $y) % 256);
        imagesetpixel($image, $x, $y, $color);
      }
    }

    ob_start();
    imagepng($image);
    $content = ob_get_clean();

    return (string) $content;
  }

  /**
   * Create an image whose left half uses a transparent colour.
   *
   * The transparent colour is rgb(0, 255, 0) and the right half is opaque
   * rgb(200, 30, 30).
   *
   * @param int $width
   *   Image width.
   * @param int $height
   *   Image height.
   * @param bool $is_truecolor
   *   Whether to create a truecolor image rather than a palette image.
   *
   * @return \GdImage
   *   Image with a transparent colour.
   */
  protected function createTransparentImage(int $width, int $height, bool $is_truecolor): \GdImage {
    $image = $is_truecolor ? imagecreatetruecolor(max(1, $width), max(1, $height)) : imagecreate(max(1, $width), max(1, $height));

    if (!$image instanceof \GdImage) {
      $this->fail('GD could not create the image.');
    }

    $opaque = (int) imagecolorallocate($image, 200, 30, 30);
    // Green keeps the transparent colour distinct from the black a new image
    // starts with.
    $transparent = (int) imagecolorallocate($image, 0, 255, 0);
    imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $opaque);
    imagecolortransparent($image, $transparent);
    imagefilledrectangle($image, 0, 0, intdiv($width, 2), $height - 1, $transparent);

    return $image;
  }

  /**
   * Create a palette frame whose left half uses a transparent colour.
   *
   * @param int $width
   *   Frame width.
   * @param int $height
   *   Frame height.
   * @param string $format
   *   Format the frame is written in: 'png' or 'gif'.
   *
   * @return string
   *   Binary image content.
   */
  protected function createTransparentFrame(int $width, int $height, string $format = 'png'): string {
    $image = $this->createTransparentImage($width, $height, FALSE);

    ob_start();

    if ($format === 'gif') {
      imagegif($image);
    }
    else {
      imagepng($image);
    }

    return (string) ob_get_clean();
  }

  /**
   * Extract a structural signature from a GIF binary.
   *
   * The signature holds the version, canvas dimensions, frame count,
   * per-frame delays and looping flag, which the encoder sets. It excludes
   * the colour tables and image data that GD generates.
   *
   * @param string $gif
   *   Binary GIF content.
   *
   * @return array<string,mixed>
   *   Structural signature of the GIF.
   */
  protected function readGifSignature(string $gif): array {
    $frames = $this->parseFrames($gif);
    $size = $this->readCanvasSize($gif);

    return [
      'version' => substr($gif, 0, 6),
      'width' => $size[0],
      'height' => $size[1],
      'frame_count' => count($frames),
      'delays' => array_column($frames, 'delay'),
      'has_loop' => str_contains($gif, 'NETSCAPE2.0'),
    ];
  }

  /**
   * Decode the first frame of an image and return its dimensions.
   *
   * GD reads the first image block rather than the logical screen, so this
   * reports the size the first frame was written at.
   *
   * @param string $content
   *   Binary image content.
   *
   * @return array<int,int>
   *   The width and height, or [0, 0] when the content cannot be decoded.
   */
  protected function readFirstFrameSize(string $content): array {
    $image = @imagecreatefromstring($content);

    if (!$image instanceof \GdImage) {
      return [0, 0];
    }

    return [imagesx($image), imagesy($image)];
  }

  /**
   * Read the RGB colour of a pixel in the first frame of an image.
   *
   * @param string $content
   *   Binary image content.
   * @param int $x
   *   Pixel x coordinate.
   * @param int $y
   *   Pixel y coordinate.
   *
   * @return array<int,int>
   *   The red, green and blue components, or [-1, -1, -1] when undecodable.
   */
  protected function readPixelColor(string $content, int $x, int $y): array {
    $image = @imagecreatefromstring($content);

    if (!$image instanceof \GdImage) {
      return [-1, -1, -1];
    }

    $colors = imagecolorsforindex($image, (int) imagecolorat($image, $x, $y));

    return [$colors['red'], $colors['green'], $colors['blue']];
  }

  /**
   * Check whether a pixel in the first frame uses the transparent colour.
   *
   * @param string $content
   *   Binary image content.
   * @param int $x
   *   Pixel x coordinate.
   * @param int $y
   *   Pixel y coordinate.
   *
   * @return bool
   *   TRUE when the decoded frame has a transparent colour and the pixel uses
   *   it, FALSE otherwise or when the content cannot be decoded.
   */
  protected function isPixelTransparent(string $content, int $x, int $y): bool {
    $image = @imagecreatefromstring($content);

    if (!$image instanceof \GdImage) {
      return FALSE;
    }

    $transparent_index = imagecolortransparent($image);

    return $transparent_index !== -1 && imagecolorat($image, $x, $y) === $transparent_index;
  }

  /**
   * Assert two colours match within a tolerance to allow for GIF quantisation.
   *
   * @param array<int,int> $expected
   *   Expected red, green and blue components.
   * @param array<int,int> $actual
   *   Actual red, green and blue components.
   */
  protected function assertColorNear(array $expected, array $actual): void {
    foreach ($expected as $channel => $value) {
      $this->assertEqualsWithDelta($value, $actual[$channel], 24);
    }
  }

}
