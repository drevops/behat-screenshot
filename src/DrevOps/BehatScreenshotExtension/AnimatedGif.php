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
 *
 * Every frame keeps the size it was captured at. GIF89a gives each image block
 * its own geometry, so a frame smaller than the logical screen is written as
 * is rather than padded up to the largest frame - the number of pixels encoded
 * is the number of pixels captured.
 */
class AnimatedGif implements \Countable {

  /**
   * Image Separator byte that introduces an image block.
   */
  public const IMAGE_SEPARATOR = 0x2C;

  /**
   * Extension Introducer byte that introduces an extension block.
   */
  public const EXTENSION_INTRODUCER = 0x21;

  /**
   * Trailer byte that terminates the GIF stream.
   */
  public const TRAILER = 0x3B;

  /**
   * Disposal method leaving a frame on screen for the next one to draw over.
   */
  public const DISPOSAL_KEEP = 1;

  /**
   * Disposal method clearing a frame to the background colour after it shows.
   */
  public const DISPOSAL_BACKGROUND = 2;

  /**
   * Added frames as single-frame GIF binaries with their pixel dimensions.
   *
   * @var array<int,array{gif:string,width:int,height:int}>
   */
  protected array $frames = [];

  /**
   * AnimatedGif constructor.
   *
   * @param int $maxWidth
   *   Maximum frame width in pixels; 0 leaves the width unbounded.
   * @param int $maxHeight
   *   Maximum frame height in pixels; 0 leaves the height unbounded.
   */
  public function __construct(
    protected int $maxWidth = 0,
    protected int $maxHeight = 0,
  ) {
  }

  /**
   * Encode a sequence of image frames into an animated GIF.
   *
   * @param array<int,string> $frames
   *   Raw image data for each frame, in any format readable by GD (e.g. PNG).
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

    $this->reset();

    foreach ($frames as $frame) {
      $this->addFrame($frame);
    }

    return $this->render($frame_delay);
  }

  /**
   * Add a frame to the animation.
   *
   * The frame is quantised and compressed on the spot, so the caller can
   * release the raw image data rather than hold every frame until the
   * animation is rendered.
   *
   * @param string $frame
   *   Raw image data, in any format readable by GD (e.g. PNG).
   *
   * @return bool
   *   TRUE when the frame was decoded and added, FALSE when it was skipped.
   */
  public function addFrame(string $frame): bool {
    $image = @imagecreatefromstring($frame);

    if (!$image instanceof \GdImage) {
      return FALSE;
    }

    $image = $this->constrain($image);
    $width = imagesx($image);
    $height = imagesy($image);

    ob_start();
    imagegif($image);
    $gif = ob_get_clean();
    imagedestroy($image);

    // @codeCoverageIgnoreStart
    // ob_get_clean() is typed string|false; keep this check even where
    // static analysis narrows the local type to string.
    if (!is_string($gif) || $gif === '') {
      return FALSE;
    }

    // @codeCoverageIgnoreEnd
    $this->frames[] = ['gif' => $gif, 'width' => $width, 'height' => $height];

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function count(): int {
    return count($this->frames);
  }

  /**
   * Discard every frame added so far.
   */
  public function reset(): void {
    $this->frames = [];
  }

  /**
   * Render the added frames into one animated GIF89a stream.
   *
   * @param int $frame_delay
   *   Delay between frames, in milliseconds.
   *
   * @return string
   *   Binary content of the animated GIF.
   */
  public function render(int $frame_delay): string {
    if ($this->frames === []) {
      throw new \InvalidArgumentException('None of the provided frames could be decoded as an image.');
    }

    // GIF frame delays are expressed in hundredths of a second.
    $delay = max(0, (int) round($frame_delay / 10));

    $width = max(array_column($this->frames, 'width'));
    $height = max(array_column($this->frames, 'height'));

    $output = 'GIF89a' . $this->screenDescriptor($width, $height);
    // Netscape Application Extension instructing viewers to loop forever.
    $output .= "\x21\xFF\x0B" . 'NETSCAPE2.0' . "\x03\x01" . pack('v', 0) . "\x00";

    $total = count($this->frames);

    foreach ($this->frames as $index => $frame) {
      // Playback loops, so the frame after the last one is the first.
      $next = $this->frames[($index + 1) % $total];
      $is_covered = $next['width'] >= $frame['width'] && $next['height'] >= $frame['height'];
      $disposal = $is_covered ? self::DISPOSAL_KEEP : self::DISPOSAL_BACKGROUND;

      $output .= $this->frameBlock($frame['gif'], $delay, $disposal);
    }

    return $output . chr(self::TRAILER);
  }

  /**
   * Scale an image down to fit within the configured maximum dimensions.
   *
   * @param \GdImage $image
   *   Source image. Destroyed once a scaled copy replaces it.
   *
   * @return \GdImage
   *   The source image when it already fits, a scaled copy otherwise.
   */
  protected function constrain(\GdImage $image): \GdImage {
    $width = imagesx($image);
    $height = imagesy($image);

    $scale = 1.0;

    if ($this->maxWidth > 0 && $width > $this->maxWidth) {
      $scale = $this->maxWidth / $width;
    }

    if ($this->maxHeight > 0 && $height > $this->maxHeight) {
      $scale = min($scale, $this->maxHeight / $height);
    }

    if ($scale >= 1.0) {
      return $image;
    }

    $scaled = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));

    // @codeCoverageIgnoreStart
    if (!$scaled instanceof \GdImage) {
      return $image;
    }

    // @codeCoverageIgnoreEnd
    imagedestroy($image);

    return $scaled;
  }

  /**
   * Build the Logical Screen Descriptor and its global colour table.
   *
   * @param int $width
   *   Logical screen width, in pixels.
   * @param int $height
   *   Logical screen height, in pixels.
   *
   * @return string
   *   Logical Screen Descriptor followed by a two-entry global colour table.
   */
  protected function screenDescriptor(int $width, int $height): string {
    // Global colour table present, 8-bit colour resolution, two entries. Each
    // frame carries its own local colour table, so the global one only gives
    // the background colour index a value to point at.
    $packed = chr(0xF0);
    $background_index = chr(0);
    $aspect_ratio = chr(0);
    // Index 0 is the white shown around a frame smaller than the screen.
    $color_table = "\xFF\xFF\xFF\x00\x00\x00";

    return pack('vv', $width, $height) . $packed . $background_index . $aspect_ratio . $color_table;
  }

  /**
   * Build the graphic-control and image blocks for a single frame.
   *
   * @param string $frame
   *   Single-frame GIF binary.
   * @param int $delay
   *   Delay before the next frame, in hundredths of a second.
   * @param int $disposal
   *   Disposal method applied once the frame has been shown.
   *
   * @return string
   *   Concatenated Graphic Control Extension and image block for the frame.
   */
  protected function frameBlock(string $frame, int $delay, int $disposal): string {
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

    // Graphic Control Extension carrying the delay and the disposal method.
    $graphic_control = "\x21\xF9\x04" . chr($disposal << 2) . pack('v', $delay) . "\x00\x00";

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
