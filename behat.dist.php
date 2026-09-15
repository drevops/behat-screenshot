<?php

declare(strict_types=1);

use Behat\Config\Config;
use Behat\Config\Extension;
use Behat\Config\Profile;
use Behat\Config\Suite;
use DrevOps\BehatScreenshotExtension\Context\ScreenshotContext;
use DrevOps\BehatScreenshotExtension\ServiceContainer\BehatScreenshotExtension;

$suite = (new Suite('default'))->withContexts('FeatureContext', ScreenshotContext::class);

$extension = new Extension(BehatScreenshotExtension::class, [
  'dir' => '%paths.base%/screenshots',
  'on_failed' => TRUE,
  // Capture screenshot after every step.
  'on_every_step' => FALSE,
  'animation' => [
    // Build an animated GIF from per-step screenshots for each scenario.
    'enabled' => FALSE,
    // Delay between animated GIF frames, in milliseconds.
    'frame_delay' => 500,
    // Maximum animated GIF frame width, in pixels. Wider frames are cropped to
    // it. 0 leaves the width unbounded.
    'max_width' => 0,
    // Maximum animated GIF frame height, in pixels. Taller frames are cropped
    // to it. 0 leaves the height unbounded.
    'max_height' => 0,
  ],
  'purge' => FALSE,
  'always_fullscreen' => FALSE,
  'info_types' => ['url', 'feature', 'step', 'datetime'],
  'failed_prefix' => 'failed_',
  'filename_pattern' => '{datetime:U}.{feature_file}.feature_{step_line}.{ext}',
  'filename_pattern_failed' => '{datetime:U}.{failed_prefix}{feature_file}.feature_{step_line}.{ext}',
]);

return (new Config())->withProfile((new Profile('default'))->withSuite($suite)->withExtension($extension));
