<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension;

/**
 * Assembles an animated GIF from a sequence of raster image frames.
 *
 * GD cannot write multi-frame GIFs, so each frame is first encoded to a
 * single-frame GIF with GD - which performs the colour quantisation and LZW
 * compression - and the resulting frames are then stitched into a GIF89a
 * stream with the looping and per-frame delay control blocks.
 */
class AnimatedGif {

  /**
   * Image Separator byte that introduces an image block.
   */
  protected const IMAGE_SEPARATOR = 0x2C;

  /**
   * Extension Introducer byte that introduces an extension block.
   */
  protected const EXTENSION_INTRODUCER = 0x21;

  /**
   * Trailer byte that terminates the GIF stream.
   */
  protected const TRAILER = 0x3B;

  /**
   * Encode a sequence of image frames into an animated GIF.
   *
   * @param array<int,string> $frames
   *   Raw image data for each frame, in any format readable by GD (e.g. PNG).
   *   Frames are normalised to the dimensions of the first decodable frame.
   * @param int $frame_delay
   *   Delay between frames, in milliseconds.
   *
   * @return string
   *   Binary content of the animated GIF.
   */
  public function encode(array $frames, int $frame_delay): string {
    $frames = array_values(array_filter($frames, 'is_string'));

    if ($frames === []) {
      throw new \InvalidArgumentException('At least one frame is required to build an animated GIF.');
    }

    // GIF frame delays are expressed in hundredths of a second.
    $delay = max(0, (int) round($frame_delay / 10));

    $gif_frames = $this->normaliseFrames($frames);

    return $this->assemble($gif_frames, $delay);
  }

  /**
   * Convert raw image frames into same-sized single-frame GIF binaries.
   *
   * @param array<int,string> $frames
   *   Raw image data for each frame.
   *
   * @return array<int,string>
   *   Single-frame GIF binaries, all sharing the first frame's dimensions.
   */
  protected function normaliseFrames(array $frames): array {
    $gif_frames = [];
    $width = NULL;
    $height = NULL;

    foreach ($frames as $frame) {
      $image = @imagecreatefromstring($frame);
      if (!$image instanceof \GdImage) {
        continue;
      }

      if ($width === NULL || $height === NULL) {
        $width = imagesx($image);
        $height = imagesy($image);
      }
      elseif (imagesx($image) !== $width || imagesy($image) !== $height) {
        $image = $this->resize($image, $width, $height);
      }

      ob_start();
      imagegif($image);
      $gif = ob_get_clean();
      imagedestroy($image);

      if (is_string($gif) && $gif !== '') {
        $gif_frames[] = $gif;
      }
    }

    if ($gif_frames === []) {
      throw new \InvalidArgumentException('None of the provided frames could be decoded as an image.');
    }

    return $gif_frames;
  }

  /**
   * Resize an image onto a new canvas of the given dimensions.
   *
   * @param \GdImage $image
   *   Source image. Destroyed once copied onto the new canvas.
   * @param int $width
   *   Target width.
   * @param int $height
   *   Target height.
   *
   * @return \GdImage
   *   Resized image on a new canvas.
   */
  protected function resize(\GdImage $image, int $width, int $height): \GdImage {
    $canvas = imagecreatetruecolor($width, $height);
    imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
    imagedestroy($image);

    return $canvas;
  }

  /**
   * Stitch single-frame GIFs into one animated GIF89a stream.
   *
   * @param array<int,string> $gif_frames
   *   Single-frame GIF binaries sharing the same dimensions.
   * @param int $delay
   *   Delay between frames, in hundredths of a second.
   *
   * @return string
   *   Binary content of the animated GIF.
   */
  protected function assemble(array $gif_frames, int $delay): string {
    $first = $gif_frames[0];

    // The 7-byte Logical Screen Descriptor follows the 6-byte header.
    $screen_descriptor = substr($first, 6, 7);
    $packed = ord($first[10]);
    $global_color_table = ($packed & 0x80) ? substr($first, 13, $this->colorTableBytes($packed)) : '';

    $output = 'GIF89a' . $screen_descriptor . $global_color_table;
    // Netscape Application Extension instructing viewers to loop forever.
    $output .= "\x21\xFF\x0B" . 'NETSCAPE2.0' . "\x03\x01" . pack('v', 0) . "\x00";

    foreach ($gif_frames as $gif_frame) {
      $output .= $this->frameBlock($gif_frame, $delay);
    }

    return $output . chr(self::TRAILER);
  }

  /**
   * Build the graphic-control and image blocks for a single frame.
   *
   * @param string $frame
   *   Single-frame GIF binary.
   * @param int $delay
   *   Delay before the next frame, in hundredths of a second.
   *
   * @return string
   *   Concatenated Graphic Control Extension and image block for the frame.
   */
  protected function frameBlock(string $frame, int $delay): string {
    // GD writes each frame's palette as a global colour table; lift it so it
    // can be re-emitted as a local colour table on the frame's image block.
    $packed = ord($frame[10]);
    $size_bits = $packed & 0x07;
    $color_table = substr($frame, 13, $this->colorTableBytes($packed));
    $offset = 13 + strlen($color_table);

    // Skip any extension blocks - such as a transparency Graphic Control
    // Extension - until the image separator is reached.
    while (ord($frame[$offset]) === self::EXTENSION_INTRODUCER) {
      $offset += 2;
      $offset = $this->skipSubBlocks($frame, $offset);
    }

    // The Image Descriptor is 10 bytes; bytes 1-8 hold the frame geometry.
    $geometry = substr($frame, $offset + 1, 8);
    $offset += 10;

    // Everything up to the trailing Trailer byte is the LZW image data.
    $image_data = substr($frame, $offset, -1);

    // Graphic Control Extension carrying the delay (disposal method 1).
    $graphic_control = "\x21\xF9\x04\x04" . pack('v', $delay) . "\x00\x00";

    // Image Descriptor flagged to use the frame's own local colour table.
    $descriptor = chr(self::IMAGE_SEPARATOR) . $geometry . chr(0x80 | $size_bits);

    return $graphic_control . $descriptor . $color_table . $image_data;
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
