<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Unit;

use DrevOps\BehatScreenshotExtension\AnimatedGif;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test AnimatedGif.
 */
#[CoversClass(AnimatedGif::class)]
class AnimatedGifTest extends TestCase {

  public function testEncodeProducesValidAnimatedGif(): void {
    $frames = [
      $this->createPngFrame(120, 90, [255, 0, 0]),
      $this->createPngFrame(120, 90, [0, 255, 0]),
      $this->createPngFrame(120, 90, [0, 0, 255]),
    ];

    $gif = (new AnimatedGif())->encode($frames, 500);

    $this->assertStringStartsWith('GIF89a', $gif);
    // Looping is requested via the Netscape Application Extension.
    $this->assertStringContainsString('NETSCAPE2.0', $gif);
    $this->assertCount(3, $this->parseFrames($gif));
    $this->assertSame([120, 90], $this->canvasSize($gif));
    $this->assertSame([120, 90], $this->firstFrameSize($gif));
  }

  public function testEncodeSizesCanvasToLargestFrame(): void {
    $frames = [
      $this->createPngFrame(100, 100, [10, 20, 30]),
      $this->createPngFrame(64, 48, [200, 100, 50]),
      $this->createPngFrame(150, 120, [0, 0, 0]),
    ];

    $gif = (new AnimatedGif())->encode($frames, 200);

    $this->assertSame([150, 120], $this->canvasSize($gif));
  }

  public function testEncodeWritesFramesAtCapturedSize(): void {
    $frames = [
      $this->createPngFrame(100, 100, [10, 20, 30]),
      $this->createPngFrame(64, 48, [200, 100, 50]),
      $this->createPngFrame(150, 120, [0, 0, 0]),
    ];

    $gif = (new AnimatedGif())->encode($frames, 200);

    // Each image block declares the size of the frame it was captured at.
    $this->assertSame([
      ['left' => 0, 'top' => 0, 'width' => 100, 'height' => 100],
      ['left' => 0, 'top' => 0, 'width' => 64, 'height' => 48],
      ['left' => 0, 'top' => 0, 'width' => 150, 'height' => 120],
    ], $this->frameGeometry($gif));
  }

  public function testEncodeDoesNotStretchSmallerFrames(): void {
    // A small red frame followed by a larger blue frame.
    $frames = [
      $this->createPngFrame(40, 30, [255, 0, 0]),
      $this->createPngFrame(80, 60, [0, 0, 255]),
    ];

    $gif = (new AnimatedGif())->encode($frames, 100);

    // The logical screen is the larger frame's size, while the small frame
    // keeps its own size at the top-left rather than being stretched to fill.
    $this->assertSame([80, 60], $this->canvasSize($gif));
    $this->assertSame([40, 30], $this->firstFrameSize($gif));
    $this->assertColorNear([255, 0, 0], $this->pixelColor($gif, 5, 5));
  }

  public function testEncodeExposesWhiteAroundSmallerFrames(): void {
    $frames = [
      $this->createPngFrame(40, 30, [255, 0, 0]),
      $this->createPngFrame(80, 60, [0, 0, 255]),
    ];

    $gif = (new AnimatedGif())->encode($frames, 100);

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

    $gif = (new AnimatedGif())->encode($frames, 100);

    // Same-sized frames always cover the previous one, so none needs clearing.
    $this->assertSame([1, 1, 1], array_column($this->parseFrames($gif), 'disposal'));
  }

  #[DataProvider('dataProviderEncodeSetsDisposalPerFrame')]
  public function testEncodeSetsDisposalPerFrame(array $sizes, array $expected_disposals): void {
    $frames = [];
    foreach ($sizes as $size) {
      $frames[] = $this->createPngFrame($size[0], $size[1], [10, 20, 30]);
    }

    $gif = (new AnimatedGif())->encode($frames, 100);

    $this->assertSame($expected_disposals, array_column($this->parseFrames($gif), 'disposal'));
  }

  public static function dataProviderEncodeSetsDisposalPerFrame(): array {
    return [
      'single frame' => [[[80, 60]], [1]],
      'growing frames' => [[[40, 30], [80, 60]], [1, 2]],
      'shrinking frames' => [[[80, 60], [40, 30]], [2, 1]],
      'narrower next frame' => [[[80, 60], [40, 60]], [2, 1]],
      'shorter next frame' => [[[80, 60], [80, 30]], [2, 1]],
      'uniform then shorter' => [[[80, 60], [80, 60], [80, 30]], [1, 2, 1]],
    ];
  }

  public function testEncodeDoesNotPayForPaddingPixels(): void {
    $tall = $this->createGradientPngFrame(400, 400);
    $short = $this->createGradientPngFrame(20, 20);

    $mixed = (new AnimatedGif())->encode([$tall, $short, $short, $short, $short], 100);
    $uniform = (new AnimatedGif())->encode([$tall, $tall, $tall, $tall, $tall], 100);

    // The four small frames are encoded at 20x20 rather than padded to
    // 400x400, so the mixed animation costs about one large frame.
    $this->assertLessThan(intdiv(strlen($uniform), 3), strlen($mixed));
  }

  public function testEncodeHandlesFramesWithExtensionBlocks(): void {
    // Transparent frames cause GD to emit a Graphic Control Extension block
    // ahead of the image data, which the encoder must skip over.
    $frames = [
      $this->createTransparentPngFrame(40, 30),
      $this->createTransparentPngFrame(40, 30),
    ];

    $gif = (new AnimatedGif())->encode($frames, 100);

    $this->assertStringStartsWith('GIF89a', $gif);
    $this->assertCount(2, $this->parseFrames($gif));
    $this->assertSame([40, 30], $this->canvasSize($gif));
  }

  public function testEncodeMatchesFixture(): void {
    $dir = __DIR__ . '/../fixtures/animation';

    $frames = [
      (string) file_get_contents($dir . '/frame_001.png'),
      (string) file_get_contents($dir . '/frame_002.png'),
      (string) file_get_contents($dir . '/frame_003.png'),
    ];

    $produced = (new AnimatedGif())->encode($frames, 300);
    $expected = (string) file_get_contents($dir . '/expected.gif');

    // The per-frame colour tables and LZW byte stream are produced by GD and
    // are not guaranteed to be identical across libgd versions, so the GIFs
    // are compared on the structure the encoder is responsible for rather than
    // byte for byte.
    $this->assertEquals($this->gifSignature($expected), $this->gifSignature($produced));
    $this->assertSame([80, 60], $this->firstFrameSize($produced));
  }

  public function testEncodeSkipsUndecodableFrames(): void {
    $frames = [
      $this->createPngFrame(30, 20, [0, 128, 0]),
      'not-an-image',
    ];

    $gif = (new AnimatedGif())->encode($frames, 100);

    $this->assertStringStartsWith('GIF89a', $gif);
    // Only the single decodable frame ends up in the animation.
    $this->assertCount(1, $this->parseFrames($gif));
    $this->assertSame([30, 20], $this->canvasSize($gif));
  }

  public function testEncodeThrowsWhenNoFramesProvided(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('At least one frame is required');

    (new AnimatedGif())->encode([], 500);
  }

  public function testEncodeThrowsWhenNoFramesDecodable(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('None of the provided frames could be decoded');

    (new AnimatedGif())->encode(['not-an-image'], 500);
  }

  public function testEncodeDiscardsFramesFromAPreviousCall(): void {
    $encoder = new AnimatedGif();

    $encoder->encode([$this->createPngFrame(40, 30, [255, 0, 0])], 100);
    $gif = $encoder->encode([$this->createPngFrame(80, 60, [0, 0, 255])], 100);

    $this->assertCount(1, $this->parseFrames($gif));
    $this->assertSame([80, 60], $this->canvasSize($gif));
  }

  #[DataProvider('dataProviderEncodeConvertsDelayToCentiseconds')]
  public function testEncodeConvertsDelayToCentiseconds(int $milliseconds, int $expected_centiseconds): void {
    $frames = [
      $this->createPngFrame(20, 20, [1, 2, 3]),
      $this->createPngFrame(20, 20, [4, 5, 6]),
    ];

    $gif = (new AnimatedGif())->encode($frames, $milliseconds);

    $this->assertSame([$expected_centiseconds, $expected_centiseconds], array_column($this->parseFrames($gif), 'delay'));
  }

  public static function dataProviderEncodeConvertsDelayToCentiseconds(): array {
    return [
      'half second' => [500, 50],
      'one second' => [1000, 100],
      'zero delay' => [0, 0],
      'rounds to nearest' => [44, 4],
    ];
  }

  public function testAddFrameReportsWhetherTheFrameWasAdded(): void {
    $encoder = new AnimatedGif();

    $this->assertTrue($encoder->addFrame($this->createPngFrame(20, 20, [1, 2, 3])));
    $this->assertFalse($encoder->addFrame('not-an-image'));
    $this->assertCount(1, $encoder);
  }

  public function testResetDiscardsAddedFrames(): void {
    $encoder = new AnimatedGif();
    $encoder->addFrame($this->createPngFrame(20, 20, [1, 2, 3]));
    $encoder->reset();

    $this->assertCount(0, $encoder);
  }

  public function testRenderThrowsWhenNoFramesAdded(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('None of the provided frames could be decoded');

    (new AnimatedGif())->render(500);
  }

  public function testRenderAssemblesIncrementallyAddedFrames(): void {
    $encoder = new AnimatedGif();
    $encoder->addFrame($this->createPngFrame(40, 30, [255, 0, 0]));
    $encoder->addFrame($this->createPngFrame(80, 60, [0, 0, 255]));

    $gif = $encoder->render(100);

    $this->assertSame([80, 60], $this->canvasSize($gif));
    $this->assertSame([
      ['left' => 0, 'top' => 0, 'width' => 40, 'height' => 30],
      ['left' => 0, 'top' => 0, 'width' => 80, 'height' => 60],
    ], $this->frameGeometry($gif));
  }

  #[DataProvider('dataProviderConstrainFrameSize')]
  public function testConstrainFrameSize(int $max_width, int $max_height, int $width, int $height, array $expected_size): void {
    $gif = (new AnimatedGif($max_width, $max_height))->encode([$this->createPngFrame($width, $height, [10, 20, 30])], 100);

    $this->assertSame($expected_size, $this->canvasSize($gif));
  }

  public static function dataProviderConstrainFrameSize(): array {
    return [
      'no caps' => [0, 0, 400, 200, [400, 200]],
      'width cap scales height with it' => [100, 0, 400, 200, [100, 50]],
      'height cap scales width with it' => [0, 100, 400, 200, [200, 100]],
      'tightest cap wins' => [200, 50, 400, 200, [100, 50]],
      'frame already within caps' => [800, 800, 400, 200, [400, 200]],
      'cap equal to frame size' => [400, 200, 400, 200, [400, 200]],
      'extreme height cap' => [0, 20, 400, 4000, [2, 20]],
      'never scales below one pixel' => [0, 1, 400, 4000, [1, 1]],
    ];
  }

  public function testConstrainAppliesToEachFrameIndependently(): void {
    $frames = [
      $this->createPngFrame(400, 200, [10, 20, 30]),
      $this->createPngFrame(50, 40, [200, 100, 50]),
    ];

    $gif = (new AnimatedGif(100, 0))->encode($frames, 100);

    // Only the oversized frame is scaled; the smaller one is left alone.
    $this->assertSame([
      ['left' => 0, 'top' => 0, 'width' => 100, 'height' => 50],
      ['left' => 0, 'top' => 0, 'width' => 50, 'height' => 40],
    ], $this->frameGeometry($gif));
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
   *   Binary PNG data.
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
    $data = ob_get_clean();
    imagedestroy($image);

    return (string) $data;
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
   *   Binary PNG data.
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
    $data = ob_get_clean();
    imagedestroy($image);

    return (string) $data;
  }

  /**
   * Create a palette PNG frame with a transparent colour.
   *
   * @param int $width
   *   Frame width.
   * @param int $height
   *   Frame height.
   *
   * @return string
   *   Binary PNG data with transparency.
   */
  protected function createTransparentPngFrame(int $width, int $height): string {
    $image = imagecreate(max(1, $width), max(1, $height));
    if (!$image instanceof \GdImage) {
      return '';
    }

    imagecolorallocate($image, 200, 30, 30);
    $transparent = (int) imagecolorallocate($image, 0, 0, 0);
    imagecolortransparent($image, $transparent);
    imagefilledrectangle($image, 0, 0, intdiv($width, 2), $height - 1, $transparent);

    ob_start();
    imagepng($image);
    $data = ob_get_clean();
    imagedestroy($image);

    return (string) $data;
  }

  /**
   * Extract a structural signature from a GIF binary.
   *
   * Captures the version, canvas dimensions, frame count, per-frame delays
   * and looping flag - the parts the encoder controls - while ignoring the
   * GD-generated colour tables and image data.
   *
   * @param string $gif
   *   Binary GIF content.
   *
   * @return array<string,mixed>
   *   Structural signature of the GIF.
   */
  protected function gifSignature(string $gif): array {
    $frames = $this->parseFrames($gif);
    $size = $this->canvasSize($gif);

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
   * Walk a GIF stream and describe each of its frames.
   *
   * @param string $gif
   *   Binary GIF content.
   *
   * @return array<int,array<string,int>>
   *   Left, top, width, height, disposal method and delay for each frame.
   */
  protected function parseFrames(string $gif): array {
    $packed = ord($gif[10]);
    $offset = 13 + (($packed & 0x80) !== 0 ? $this->colorTableBytes($packed) : 0);

    $frames = [];
    $disposal = 0;
    $delay = 0;

    while ($offset < strlen($gif) && ord($gif[$offset]) !== 0x3B) {
      $marker = ord($gif[$offset]);

      if ($marker === 0x21) {
        if (ord($gif[$offset + 1]) === 0xF9) {
          $disposal = (ord($gif[$offset + 3]) & 0x1C) >> 2;
          $delay = $this->readShort($gif, $offset + 4);
        }

        $offset = $this->skipSubBlocks($gif, $offset + 2);

        continue;
      }

      if ($marker !== 0x2C) {
        break;
      }

      $image_packed = ord($gif[$offset + 9]);
      $frames[] = [
        'left' => $this->readShort($gif, $offset + 1),
        'top' => $this->readShort($gif, $offset + 3),
        'width' => $this->readShort($gif, $offset + 5),
        'height' => $this->readShort($gif, $offset + 7),
        'disposal' => $disposal,
        'delay' => $delay,
      ];

      $offset += 10 + (($image_packed & 0x80) !== 0 ? $this->colorTableBytes($image_packed) : 0);
      // Skip the LZW minimum code size byte and the image data sub-blocks.
      $offset = $this->skipSubBlocks($gif, $offset + 1);
    }

    return $frames;
  }

  /**
   * Extract the geometry of each frame in a GIF stream.
   *
   * @param string $gif
   *   Binary GIF content.
   *
   * @return array<int,array<string,int>>
   *   Left, top, width and height for each frame.
   */
  protected function frameGeometry(string $gif): array {
    return array_map(
      fn(array $frame): array => array_intersect_key($frame, array_flip(['left', 'top', 'width', 'height'])),
      $this->parseFrames($gif)
    );
  }

  /**
   * Read a little-endian unsigned short.
   *
   * @param string $data
   *   Binary content.
   * @param int $offset
   *   Offset to read from.
   *
   * @return int
   *   The decoded value.
   */
  protected function readShort(string $data, int $offset): int {
    return ord($data[$offset]) + (ord($data[$offset + 1]) << 8);
  }

  /**
   * Calculate the colour table size, in bytes, for a packed field.
   *
   * @param int $packed
   *   Packed field whose low three bits encode the colour table size.
   *
   * @return int
   *   Number of bytes occupied by the colour table.
   */
  protected function colorTableBytes(int $packed): int {
    return 3 * (1 << (($packed & 0x07) + 1));
  }

  /**
   * Advance past a run of GIF data sub-blocks.
   *
   * @param string $data
   *   GIF binary being scanned.
   * @param int $offset
   *   Offset of the first sub-block length byte.
   *
   * @return int
   *   Offset immediately after the block terminator.
   */
  protected function skipSubBlocks(string $data, int $offset): int {
    while (($length = ord($data[$offset])) !== 0) {
      $offset += $length + 1;
    }

    return $offset + 1;
  }

  /**
   * Read the logical screen dimensions of a GIF.
   *
   * @param string $data
   *   Binary GIF data.
   *
   * @return array<int,int>
   *   The canvas width and height.
   */
  protected function canvasSize(string $data): array {
    $size = @getimagesizefromstring($data);

    return $size === FALSE ? [0, 0] : [$size[0], $size[1]];
  }

  /**
   * Decode the first frame of an image and return its dimensions.
   *
   * GD reads the first image block rather than the logical screen, so this
   * reports the size the first frame was written at.
   *
   * @param string $data
   *   Binary image data.
   *
   * @return array<int,int>
   *   The width and height, or [0, 0] when the data cannot be decoded.
   */
  protected function firstFrameSize(string $data): array {
    $image = @imagecreatefromstring($data);
    if (!$image instanceof \GdImage) {
      return [0, 0];
    }

    $size = [imagesx($image), imagesy($image)];
    imagedestroy($image);

    return $size;
  }

  /**
   * Read the RGB colour of a pixel in the first frame of an image.
   *
   * @param string $data
   *   Binary image data.
   * @param int $x
   *   Pixel x coordinate.
   * @param int $y
   *   Pixel y coordinate.
   *
   * @return array<int,int>
   *   The red, green and blue components, or [-1, -1, -1] when undecodable.
   */
  protected function pixelColor(string $data, int $x, int $y): array {
    $image = @imagecreatefromstring($data);
    if (!$image instanceof \GdImage) {
      return [-1, -1, -1];
    }

    $colors = imagecolorsforindex($image, (int) imagecolorat($image, $x, $y));
    imagedestroy($image);

    return [$colors['red'], $colors['green'], $colors['blue']];
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
