<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Functional;

use DrevOps\BehatScreenshot\Tests\Traits\GifParserTrait;
use DrevOps\BehatScreenshotExtension\AnimatedGif;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Produce inspectable animation artifacts for a mixed-height scenario.
 *
 * Frame geometry is asserted here as it is elsewhere, and the encoded GIFs and
 * cropped frames are also written to .logs/animation so the visual outcome can
 * be checked by eye. CI uploads .logs as a build artifact, and .logs is
 * ignored by git, so the images never enter the repository.
 */
#[CoversClass(AnimatedGif::class)]
class AnimationArtifactsTest extends TestCase {

  use GifParserTrait;

  /**
   * Directory the artifacts are written to.
   */
  protected string $dir;

  /**
   * Frame sizes standing in for a scenario that visits pages of every length.
   *
   * @var array<int,array<int,int>>
   */
  protected const FRAME_SIZES = [
    [800, 400],
    [800, 1200],
    [800, 6000],
    [800, 400],
  ];

  /**
   * Height cap applied to the cropped variants.
   */
  protected const MAX_HEIGHT = 1500;

  /**
   * Width cap applied to the both-axes variant.
   */
  protected const MAX_WIDTH = 500;

  protected function setUp(): void {
    parent::setUp();

    $this->dir = dirname(__DIR__, 3) . '/.logs/animation';
    if (!is_dir($this->dir) && !mkdir($this->dir, 0755, TRUE) && !is_dir($this->dir)) {
      throw new \RuntimeException(sprintf('Unable to create the artifact directory %s.', $this->dir));
    }
  }

  public function testUncappedAnimationKeepsEveryFrameWhole(): void {
    $frames = $this->scenarioFrames();

    $gif = (new AnimatedGif())->encode($frames, 500);
    $this->write('uncapped.gif', $gif);

    // Every frame is written at the size it was captured, against a canvas as
    // tall as the longest page.
    $this->assertSame([800, 6000], $this->canvasSize($gif));
    $this->assertSame([[800, 400], [800, 1200], [800, 6000], [800, 400]], $this->frameSizes($gif));
  }

  public function testHeightCappedAnimationCropsOnlyTheLongPages(): void {
    $frames = $this->scenarioFrames();

    $gif = (new AnimatedGif(0, self::MAX_HEIGHT))->encode($frames, 500);
    $this->write('cropped-height.gif', $gif);
    $this->writeConstrainedFrames('cropped-height', $frames, 0, self::MAX_HEIGHT);

    // Frames within the cap are untouched and every frame keeps its width.
    $this->assertSame([800, self::MAX_HEIGHT], $this->canvasSize($gif));
    $this->assertSame([[800, 400], [800, 1200], [800, self::MAX_HEIGHT], [800, 400]], $this->frameSizes($gif));
  }

  public function testBothCapsCropEachAxisIndependently(): void {
    $frames = $this->scenarioFrames();

    $gif = (new AnimatedGif(self::MAX_WIDTH, self::MAX_HEIGHT))->encode($frames, 500);
    $this->write('cropped-both.gif', $gif);
    $this->writeConstrainedFrames('cropped-both', $frames, self::MAX_WIDTH, self::MAX_HEIGHT);

    $this->assertSame([self::MAX_WIDTH, self::MAX_HEIGHT], $this->canvasSize($gif));
    $this->assertSame([
      [self::MAX_WIDTH, 400],
      [self::MAX_WIDTH, 1200],
      [self::MAX_WIDTH, self::MAX_HEIGHT],
      [self::MAX_WIDTH, 400],
    ], $this->frameSizes($gif));
  }

  public function testArtifactsAreAccompaniedByReadme(): void {
    $readme = implode("\n", [
      'Animated GIF artifacts',
      '======================',
      '',
      'Produced by ' . self::class . '. Not committed - .logs is ignored by git',
      'and uploaded as a CI build artifact.',
      '',
      'Captured frames: 800x400, 800x1200, 800x6000, 800x400.',
      'Each frame is ruled every 200px and labelled with its own y offset, so',
      'the point at which a cropped frame ends is readable from the image.',
      '',
      'uncapped.gif',
      '  No caps. Canvas is 800x6000, the tallest page. Shorter frames occupy',
      '  the top of the canvas and the area below them shows the white',
      '  background colour. Nothing is cropped or scaled.',
      '',
      'cropped-height.gif',
      '  max_height: ' . self::MAX_HEIGHT . '. The 6000px page is cropped to its top ' . self::MAX_HEIGHT . 'px at',
      '  full resolution; the 400px and 1200px pages are untouched. Every frame',
      '  keeps its full 800px width.',
      '',
      'cropped-both.gif',
      '  max_width: ' . self::MAX_WIDTH . ' and max_height: ' . self::MAX_HEIGHT . '. Each axis is capped on its own,',
      '  so every frame loses its right-hand ' . (800 - self::MAX_WIDTH) . 'px and the 6000px page also',
      '  loses everything below ' . self::MAX_HEIGHT . 'px.',
      '',
      'cropped-*-frame-N.png',
      '  The individual frames as the encoder cropped them, before encoding.',
      '',
    ]);

    $this->write('README.txt', $readme);

    $this->assertFileExists($this->dir . '/README.txt');
  }

  /**
   * Build the scenario's frames.
   *
   * @return array<int,string>
   *   Binary PNG data for each frame.
   */
  protected function scenarioFrames(): array {
    $frames = [];
    foreach (self::FRAME_SIZES as $step => $size) {
      $frames[] = $this->createPage($size[0], $size[1], $step + 1);
    }

    return $frames;
  }

  /**
   * Render a page carrying its own dimensions and depth markings.
   *
   * @param int $width
   *   Page width.
   * @param int $height
   *   Page height.
   * @param int $step
   *   Step number shown in the page header.
   *
   * @return string
   *   Binary PNG data.
   */
  protected function createPage(int $width, int $height, int $step): string {
    $image = imagecreatetruecolor(max(1, $width), max(1, $height));

    $paper = (int) imagecolorallocate($image, 252, 252, 254);
    $header = (int) imagecolorallocate($image, 26, 38, 84);
    $rule = (int) imagecolorallocate($image, 208, 212, 224);
    $ink = (int) imagecolorallocate($image, 40, 44, 60);
    $paper_white = (int) imagecolorallocate($image, 255, 255, 255);

    imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $paper);
    imagefilledrectangle($image, 0, 0, $width - 1, 56, $header);
    imagestring($image, 5, 16, 20, sprintf('STEP %d - captured %dx%d', $step, $width, $height), $paper_white);

    // A ruler every 200px, so a crop's cut-off point is readable off the image.
    for ($y = 200; $y < $height; $y += 200) {
      imageline($image, 0, $y, $width - 1, $y, $rule);
      imagestring($image, 3, 16, $y + 6, sprintf('y = %d', $y), $ink);
    }

    // A band down the right edge, so a width crop is equally visible.
    imagefilledrectangle($image, $width - 40, 60, $width - 1, $height - 1, (int) imagecolorallocate($image, 220, 90, 60));

    ob_start();
    imagepng($image);
    $data = strval(ob_get_clean());
    imagedestroy($image);

    return $data;
  }

  /**
   * Write each frame as the encoder constrains it, before encoding.
   *
   * @param string $prefix
   *   File name prefix.
   * @param array<int,string> $frames
   *   Binary PNG data for each frame.
   * @param int $max_width
   *   Maximum frame width.
   * @param int $max_height
   *   Maximum frame height.
   */
  protected function writeConstrainedFrames(string $prefix, array $frames, int $max_width, int $max_height): void {
    $encoder = new AnimatedGif($max_width, $max_height);
    $constrain = new \ReflectionMethod($encoder, 'constrain');
    $constrain->setAccessible(TRUE);

    foreach ($frames as $index => $frame) {
      $image = imagecreatefromstring($frame);
      if (!$image instanceof \GdImage) {
        continue;
      }

      $result = $constrain->invoke($encoder, $image);
      if (!$result instanceof \GdImage) {
        continue;
      }

      ob_start();
      imagepng($result);
      $this->write(sprintf('%s-frame-%d.png', $prefix, $index + 1), strval(ob_get_clean()));
      imagedestroy($result);
    }
  }

  /**
   * Write an artifact file.
   *
   * @param string $name
   *   File name.
   * @param string $content
   *   File content.
   */
  protected function write(string $name, string $content): void {
    file_put_contents($this->dir . DIRECTORY_SEPARATOR . $name, $content);
  }

}
