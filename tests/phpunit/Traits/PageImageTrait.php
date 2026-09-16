<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Tests\Traits;

/**
 * Provides methods to render synthetic page images.
 *
 * @phpstan-ignore trait.unused
 */
trait PageImageTrait {

  /**
   * Render a page with text-like rows and depth markings.
   *
   * @param int $width
   *   Page width, in pixels.
   * @param int $height
   *   Page height, in pixels.
   * @param string $title
   *   Text shown in the page header.
   *
   * @return string
   *   Binary PNG content.
   */
  protected function createPage(int $width, int $height, string $title = ''): string {
    $image = imagecreatetruecolor(max(1, $width), max(1, $height));

    $paper = (int) imagecolorallocate($image, 252, 252, 254);
    $header = (int) imagecolorallocate($image, 26, 38, 84);
    $rule = (int) imagecolorallocate($image, 208, 212, 224);
    $ink = (int) imagecolorallocate($image, 40, 44, 60);
    $muted = (int) imagecolorallocate($image, 150, 155, 170);
    $paper_white = (int) imagecolorallocate($image, 255, 255, 255);

    imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $paper);
    imagefilledrectangle($image, 0, 0, $width - 1, 56, $header);
    imagestring($image, 5, 16, 20, $title, $paper_white);

    for ($y = 96; $y < $height - 30; $y += 26) {
      $length = 300 + (($y * 7) % max(1, (int) ($width * 0.55)));
      imagefilledrectangle($image, 90, $y, 90 + $length, $y + 11, $ink);
      imagefilledrectangle($image, 90, $y + 14, 90 + (int) ($length * 0.7), $y + 20, $muted);
    }

    // A ruler every 200px, so a crop's cut-off point is readable off the image.
    for ($y = 200; $y < $height; $y += 200) {
      imageline($image, 0, $y, $width - 1, $y, $rule);
      imagestring($image, 3, 16, $y + 6, sprintf('y = %d', $y), $ink);
    }

    // A band down the right edge, so a width crop is equally visible.
    imagefilledrectangle($image, $width - 40, 60, $width - 1, $height - 1, (int) imagecolorallocate($image, 220, 90, 60));

    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
  }

}
