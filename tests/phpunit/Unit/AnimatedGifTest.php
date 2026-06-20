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
    // One Graphic Control Extension is emitted per frame.
    $this->assertEquals(3, substr_count($gif, "\x21\xF9\x04"));

    // The assembled GIF is decodable and preserves the first frame's size.
    $this->assertSame([120, 90], $this->imageSize($gif));
  }

  public function testEncodeNormalisesDifferentlySizedFrames(): void {
    $frames = [
      $this->createPngFrame(100, 100, [10, 20, 30]),
      $this->createPngFrame(64, 48, [200, 100, 50]),
      $this->createPngFrame(150, 120, [0, 0, 0]),
    ];

    $gif = (new AnimatedGif())->encode($frames, 200);

    $this->assertStringStartsWith('GIF89a', $gif);
    $this->assertEquals(3, substr_count($gif, "\x21\xF9\x04"));

    // All frames are normalised to the first frame's dimensions.
    $this->assertSame([100, 100], $this->imageSize($gif));
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
    $this->assertEquals(2, substr_count($gif, "\x21\xF9\x04"));
    $this->assertSame([40, 30], $this->imageSize($gif));
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
    $this->assertSame([80, 60], $this->imageSize($produced));
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

  #[DataProvider('dataProviderEncodeConvertsDelayToCentiseconds')]
  public function testEncodeConvertsDelayToCentiseconds(int $milliseconds, int $expected_centiseconds): void {
    $frames = [
      $this->createPngFrame(20, 20, [1, 2, 3]),
      $this->createPngFrame(20, 20, [4, 5, 6]),
    ];

    $gif = (new AnimatedGif())->encode($frames, $milliseconds);

    $needle = "\x21\xF9\x04\x04" . pack('v', $expected_centiseconds);
    $this->assertStringContainsString($needle, $gif);
  }

  public static function dataProviderEncodeConvertsDelayToCentiseconds(): array {
    return [
      'half second' => [500, 50],
      'one second' => [1000, 100],
      'zero delay' => [0, 0],
      'rounds to nearest' => [44, 4],
    ];
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

    return is_string($data) ? $data : '';
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

    return is_string($data) ? $data : '';
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
    $delays = [];
    $offset = 0;
    while (($position = strpos($gif, "\x21\xF9\x04", $offset)) !== FALSE) {
      $delay = unpack('v', substr($gif, $position + 4, 2));
      $delays[] = is_array($delay) ? $delay[1] : 0;
      $offset = $position + 1;
    }

    $width = unpack('v', substr($gif, 6, 2));
    $height = unpack('v', substr($gif, 8, 2));

    return [
      'version' => substr($gif, 0, 6),
      'width' => is_array($width) ? $width[1] : 0,
      'height' => is_array($height) ? $height[1] : 0,
      'frame_count' => count($delays),
      'delays' => $delays,
      'has_loop' => str_contains($gif, 'NETSCAPE2.0'),
    ];
  }

  /**
   * Decode an image and return its dimensions.
   *
   * @param string $data
   *   Binary image data.
   *
   * @return array<int,int>
   *   The width and height, or [0, 0] when the data cannot be decoded.
   */
  protected function imageSize(string $data): array {
    $image = imagecreatefromstring($data);
    if (!$image instanceof \GdImage) {
      return [0, 0];
    }

    $size = [imagesx($image), imagesy($image)];
    imagedestroy($image);

    return $size;
  }

}
