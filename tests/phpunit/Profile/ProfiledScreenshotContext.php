<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Profile;

use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;

/**
 * A screenshot context whose captures are supplied rather than driven.
 *
 * A scenario of any length can be replayed without a running driver. The
 * hooks, the frame collection and the scenario-end assembly remain the real
 * ones.
 */
class ProfiledScreenshotContext extends ScreenshotContext {

  /**
   * Image data the next step captures.
   */
  public string $pending = '';

  /**
   * Content of the animated GIF written at the end of the scenario.
   */
  public string $gif = '';

  /**
   * {@inheritdoc}
   */
  public function screenshot(array $options = []): void {
    $this->lastScreenshotData = $this->pending;
  }

  /**
   * {@inheritdoc}
   */
  public function saveScreenshotContent(string $filename, string $content): void {
    $this->gif = $content;
  }

}
