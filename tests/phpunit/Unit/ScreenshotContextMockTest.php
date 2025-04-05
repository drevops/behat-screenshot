<?php

declare(strict_types=1);

namespace DrevOps\BehatScreenshot\Tests\Unit;

use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Test ScreenshotContext with advanced mocking.
 */
#[CoversClass(ScreenshotContext::class)]
class ScreenshotContextMockTest extends TestCase {
  use PHPMock;

  /**
   * Test the ScreenshotContext::renderInfo method.
   */
  public function testRenderInfoWithEmptyInfo(): void {
    $screenshot_context = new ScreenshotContext();

    // No info has been added.
    $this->assertEquals('', $screenshot_context->renderInfo());
  }

}
