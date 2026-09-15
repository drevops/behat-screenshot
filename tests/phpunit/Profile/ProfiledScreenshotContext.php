<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshotExtension\Tests\Profile;

use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;

/**
 * Screenshot context that takes capture content from $pending, not a driver.
 *
 * A scenario of any length can be replayed without a running driver. The
 * hooks, the frame collection and the scenario-end assembly are inherited.
 */
class ProfiledScreenshotContext extends ScreenshotContext {

  /**
   * Image content the next step captures.
   */
  public string $pending = '';

  /**
   * Content of the animated GIF written at the end of the scenario.
   */
  public string $gif = '';

  /**
   * {@inheritdoc}
   */
  public function captureScreenshot(array $config = []): void {
    $this->lastScreenshotContent = $this->pending;
  }

  /**
   * {@inheritdoc}
   */
  public function writeScreenshotContent(string $filename, string $content): void {
    $this->gif = $content;
  }

}
