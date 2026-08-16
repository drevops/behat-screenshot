<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Traits;

/**
 * Trait GifParserTrait.
 *
 * Decodes the structure of a GIF stream for assertions.
 *
 * The block layout is decoded here rather than through AnimatedGif's own
 * helpers, so a fault in how the encoder lays out blocks cannot be hidden by
 * reading them back with the same code that wrote them.
 *
 * @phpstan-ignore trait.unused
 */
trait GifParserTrait {

  /**
   * Value reported for a frame that carries no Graphic Control Extension.
   */
  protected const GIF_MISSING_CONTROL = -1;

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
    $disposal = self::GIF_MISSING_CONTROL;
    $delay = self::GIF_MISSING_CONTROL;

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

      // Each frame carries its own control block, so nothing is inherited from
      // the frame before it.
      $disposal = self::GIF_MISSING_CONTROL;
      $delay = self::GIF_MISSING_CONTROL;

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
   * Extract the size of each frame in a GIF stream.
   *
   * @param string $gif
   *   Binary GIF content.
   *
   * @return array<int,array<int,int>>
   *   Width and height for each frame.
   */
  protected function frameSizes(string $gif): array {
    return array_map(
      static fn(array $frame): array => [$frame['width'], $frame['height']],
      $this->parseFrames($gif)
    );
  }

  /**
   * Total the pixels every frame of a GIF stream actually encodes.
   *
   * @param string $gif
   *   Binary GIF content.
   *
   * @return int
   *   Sum of each image block's area, in pixels.
   */
  protected function encodedPixels(string $gif): int {
    return array_sum(array_map(
      static fn(array $frame): int => $frame['width'] * $frame['height'],
      $this->parseFrames($gif)
    ));
  }

  /**
   * Read the logical screen dimensions of a GIF.
   *
   * @param string $gif
   *   Binary GIF content.
   *
   * @return array<int,int>
   *   The canvas width and height.
   */
  protected function canvasSize(string $gif): array {
    $size = @getimagesizefromstring($gif);

    return $size === FALSE ? [0, 0] : [$size[0], $size[1]];
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

}
