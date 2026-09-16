<?php

declare(strict_types=1);

use Behat\Config\Config;
use Symfony\Component\Yaml\Yaml;

// Behat 4 reads PHP configuration only, so this file loads the behat.yml that
// Behat 3 reads directly.
return new Config(Yaml::parseFile(__DIR__ . '/behat.yml'));
