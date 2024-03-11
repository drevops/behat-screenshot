<?php

/**
 * @file
 * Behat context to enable Screenshot support in tests.
 */

namespace DrevOps\BehatScreenshotExtension\Context;

use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\MinkExtension\Context\RawMinkContext;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class ScreenshotContext.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class ScreenshotContext extends RawMinkContext implements SnippetAcceptingContext, ScreenshotAwareContext
{
    /**
     * Screenshot step line.
     */
    protected string $stepLine;

    /**
     * Makes screenshot when fail.
     */
    protected bool $fail = false;

    /**
     * Screenshot directory name.
     */
    protected string $dir = '';

    /**
     * Prefix for failed screenshot files.
     */
    protected string $failPrefix = '';

    /**
     * Before step scope.
     */
    protected BeforeStepScope $beforeStepScope;

    /**
     * Filename pattern.
     */
    protected string $filenamePattern;

    /**
     * Filename pattern failed.
     */
    protected string $filenamePatternFailed;

    /**
     * {@inheritdoc}
     */
    public function setScreenshotParameters(string $dir, bool $fail, string $failPrefix, string $filenamePattern, string $filenamePatternFailed): static
    {
        $this->dir = $dir;
        $this->fail = $fail;
        $this->failPrefix = $failPrefix;
        $this->filenamePattern = $filenamePattern;
        $this->filenamePatternFailed = $filenamePatternFailed;

        return $this;
    }

    /**
     * Init values required for snapshots.
     *
     * @param BeforeScenarioScope $scope Scenario scope.
     *
     * @BeforeScenario
     */
    public function beforeScenarioInit(BeforeScenarioScope $scope): void
    {
        if ($scope->getScenario()->hasTag('javascript')) {
            $driver = $this->getSession()->getDriver();
            if ($driver instanceof Selenium2Driver) {
                try {
                    // Start driver's session manually if it is not already started.
                    if (!$driver->isStarted()) {
                        $driver->start();
                    }
                    $this->getSession()->resizeWindow(1440, 900, 'current');
                } catch (\Exception $exception) {
                    throw new \RuntimeException(
                        sprintf(
                            'Please make sure that Selenium server is running. %s',
                            $exception->getMessage(),
                        ),
                        $exception->getCode(),
                        $exception,
                    );
                }
            }
        }
    }

    /**
     * Init values required for snapshot.
     *
     *
     * @BeforeStep
     */
    public function beforeStepInit(BeforeStepScope $scope): void
    {
        $featureFile = $scope->getFeature()->getFile();
        if (!$featureFile) {
            throw new \RuntimeException('Feature file not found.');
        }
        $this->beforeStepScope = $scope;
    }

    /**
     * After scope event handler to print last response on error.
     *
     * @param AfterStepScope $event After scope event.
     *
     * @throws DriverException
     * @throws UnsupportedDriverActionException
     *
     * @AfterStep
     */
    public function printLastResponseOnError(AfterStepScope $event): void
    {
        if ($this->fail && !$event->getTestResult()->isPassed()) {
            $this->iSaveScreenshot(true);
        }
    }

    /**
     * Save debug screenshot.
     *
     * Handles different driver types.
     *
     * @param bool        $fail     Denotes if this was called in a context of the failed
     *                              test.
     * @param string|null $filename File name.
     *
     * @throws DriverException
     * @throws UnsupportedDriverActionException
     *
     * @When save screenshot
     * @When I save screenshot
     */
    public function iSaveScreenshot(bool $fail = false, string $filename = null): void
    {
        $driver = $this->getSession()->getDriver();
        $fileName = $this->makeFileName('html', $filename, $fail);
        try {
            $data = $driver->getContent();
        } catch (DriverException) {
            // Do not do anything if the driver does not have any content - most
            // likely the page has not been loaded yet.
            return;
        }

        $this->saveScreenshotData($fileName, $data);

        // Drivers that do not support making screenshots, including Goutte
        // driver that is shipped with Behat, throw exception. For such drivers,
        // screenshot stored as an HTML page (without referenced assets).
        try {
            $data = $driver->getScreenshot();
            // Preserve filename, but change the extension - this is to group
            // content and screenshot files together by name.
            $fileName = $this->makeFileName('png', $filename, $fail);
            $this->saveScreenshotData($fileName, $data);
        } catch (UnsupportedDriverActionException) {
            // Nothing to do here - drivers without support for screenshots
            // simply do not have them created.
        }
    }

    /**
     * Save screenshot with name.
     *
     * @param string $filename File name.
     *
     * @throws DriverException
     * @throws UnsupportedDriverActionException
     *
     * @When I save screenshot with name :filename
     */
    public function iSaveScreenshotWithName(string $filename): void
    {
        $this->iSaveScreenshot(false, $filename);
    }

    /**
     * Save screenshot with specific dimensions.
     *
     * @param string|int $width  Width to resize browser to.
     * @param string|int $height Height to resize browser to.
     *
     * @throws DriverException
     * @throws UnsupportedDriverActionException
     *
     * @When save :width x :height screenshot
     * @When I save :width x :height screenshot
     */
    public function iSaveSizedScreenshot(string|int $width = 1440, string|int $height = 900): void
    {
        try {
            $this->getSession()->resizeWindow((int) $width, (int) $height, 'current');
        } catch (UnsupportedDriverActionException) {
            // Nothing to do here - drivers without resize support may proceed.
        }
        $this->iSaveScreenshot();
    }

    /**
     * Save screenshot data into a file.
     *
     * @param string $filename
     *   File name to write.
     * @param string $data
     *   Data to write into a file.
     */
    protected function saveScreenshotData(string $filename, string $data): void
    {
        $this->prepareDir($this->dir);
        file_put_contents($this->dir.DIRECTORY_SEPARATOR.$filename, $data);
    }

    /**
     * Prepare directory.
     *
     * @param string $dir Name of preparing directory.
     */
    protected function prepareDir(string $dir): void
    {
        $fs = new Filesystem();
        $fs->mkdir($dir, 0755);
    }

    /**
     * Make screenshot filename.
     *
     * Format: microseconds.featurefilename_linenumber.ext
     *
     * @param string      $ext      File extension without dot.
     * @param string|null $filename Optional file name.
     * @param bool        $fail     Make filename for fail case.
     *
     * @return string Unique file name.
     *
     * @throws DriverException
     * @throws UnsupportedDriverActionException
     */
    protected function makeFileName(string $ext, string $filename = null, bool $fail = false): string
    {
        if ($fail) {
            $filename = $this->filenamePatternFailed;
        } elseif (empty($filename)) {
            $filename = $this->filenamePattern;
        }

        // Make sure {ext} token is on filename.
        if (!str_ends_with($filename, '.{ext}')) {
            $filename .= '.{ext}';
        }

        return $this->replaceToken($filename, ['ext' => $ext]);
    }

    /**
     * Replace tokens from the text.
     *
     * @param string       $text Text may contain tokens.
     * @param array<mixed> $data Extra data to provide context to replace token.
     *
     * @return string
     *   String after replace tokens.
     *
     * @throws DriverException
     * @throws UnsupportedDriverActionException
     */
    protected function replaceToken(string $text, array $data = []): string
    {
        $replacement = $text;
        $tokens = $this->scanTokens($text);
        $tokenReplacements = $this->buildTokenReplacements($tokens, $data);

        if (!empty($tokenReplacements)) {
            $replacement = str_replace(array_keys($tokenReplacements), array_values($tokenReplacements), $text);
        }

        return $replacement;
    }

    /**
     * Scan tokens of specific text.
     *
     * @param string $text
     *   The text to scan tokens.
     * @return string[]
     *   The tokens.
     */
    protected function scanTokens(string $text): array
    {
        $pattern = '/\{(.*?)\}/';
        preg_match_all($pattern, $text, $matches);
        $result = [];
        foreach ($matches[0] as $key => $match) {
            $result[$match] = $matches[1][$key];
        }

        return $result;
    }

    /**
     * Build replacements tokens.
     *
     * @param string[]     $tokens Token.
     * @param array<mixed> $data   Extra data to provide context to replace token.
     *
     * @return array<string, string>
     *   Replacements has key as token and value as token replacement.
     *
     * @throws DriverException
     * @throws UnsupportedDriverActionException
     */
    protected function buildTokenReplacements(array $tokens, array $data): array
    {
        $replacements = [];
        foreach ($tokens as $originalToken => $token) {
            $tokenParts = explode(':', $token);
            $qualifier = null;
            $format = null;
            $nameQualifier = $tokenParts[0];
            if (isset($tokenParts[1])) {
                $format = $tokenParts[1];
            }
            $nameQualifierParts = explode('_', $nameQualifier);
            $name = array_shift($nameQualifierParts);
            if (!empty($nameQualifierParts)) {
                $qualifier = implode('_', $nameQualifierParts);
            }
            $replacements[$originalToken] = $this->buildTokenReplacement($originalToken, $name, $qualifier, $format, $data);
        }

        return $replacements;
    }

    /**
     * Build replacement for a token.
     *
     * @param string       $token     Original token.
     * @param string       $name      Token name.
     * @param string|null  $qualifier Token qualifier.
     * @param string|null  $format    Token format.
     * @param array<mixed> $data      Extra data to provide context to replace token.
     *
     * @return string
     *   Token replacement.
     *
     * @throws DriverException
     * @throws UnsupportedDriverActionException
     */
    protected function buildTokenReplacement(string $token, string $name, string $qualifier = null, string $format = null, array $data = []): string
    {
        $replacement = $token;
        switch ($name) {
            case 'feature':
                $replacement = $this->replaceFeatureToken($token, $name, $qualifier, $format, $data);
                break;
            case 'url':
                $replacement = $this->replaceUrlToken($token, $name, $qualifier, $format, $data);
                break;
            case 'datetime':
                $replacement = $this->replaceDatetimeToken($token, $name, $qualifier, $format, $data);
                break;
            case 'step':
                $replacement = $this->replaceStepToken($token, $name, $qualifier, $format, $data);
                break;
            case 'fail':
                $replacement = $this->replaceFailToken($token, $name, $qualifier, $format, $data);
                break;
            case 'ext':
                $replacement = $this->replaceExtToken($token, $name, $qualifier, $format, $data);
                break;
            default:
                break;
        }

        return $replacement;
    }

    /**
     * Replace {feature} token.
     *
     * @param string       $token     Original token.
     * @param string       $name      Token name.
     * @param string|null  $qualifier Token qualifier.
     * @param string|null  $format    Token format.
     * @param array<mixed> $data      Extra data to provide context to replace token.
     *
     * @return string
     *   Token replacement.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function replaceFeatureToken(string $token, string $name, string $qualifier = null, string $format = null, array $data = []): string
    {
        $replacement = $token;
        $featureFile = $this->beforeStepScope->getFeature()->getFile();
        if ($featureFile) {
            $replacement = basename($featureFile, '.feature');
        }

        return $replacement;
    }

    /**
     * Replace {ext} token.
     *
     * @param string       $token     Original token.
     * @param string       $name      Token name.
     * @param string|null  $qualifier Token qualifier.
     * @param string|null  $format    Token format.
     * @param array<mixed> $data      Extra data to provide context to replace token.
     *
     * @return string
     *   Token replacement.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function replaceExtToken(string $token, string $name, string $qualifier = null, string $format = null, array $data = []): string
    {
        $ext = 'html';
        if (isset($data['ext']) && is_string($data['ext']) && $data['ext'] !== '') {
            $ext = $data['ext'];
        }

        return $ext;
    }

    /**
     * Replace {step} token.
     *
     * @param string       $token     Original token.
     * @param string       $name      Token name.
     * @param string|null  $qualifier Token qualifier.
     * @param string|null  $format    Token format.
     * @param array<mixed> $data      Extra data to provide context to replace token.
     *
     * @return string
     *   Token replacement.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function replaceStepToken(string $token, string $name, string $qualifier = null, string $format = null, array $data = []): string
    {
        switch ($qualifier) {
            case 'line':
                $line = $this->beforeStepScope->getStep()->getLine();
                if ($format) {
                    return sprintf($format, $line);
                }

                return (string) $line;
            case 'name':
            default:
                return strtolower($this->beforeStepScope->getStep()->getText());
        }
    }

    /**
     * Replace {datetime} token.
     *
     * @param string       $token     Original token.
     * @param string       $name      Token name.
     * @param string|null  $qualifier Token qualifier.
     * @param string|null  $format    Token format.
     * @param array<mixed> $data      Extra data to provide context to replace token.
     *
     * @return string
     *   Token replacement.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function replaceDatetimeToken(string $token, string $name, string $qualifier = null, string $format = null, array $data = []): string
    {
        if ($format) {
            return date($format);
        }

        return date('Ymd_His');
    }

    /**
     * Replace {url} token.
     *
     * @param string       $token     Original token.
     * @param string       $name      Token name.
     * @param string|null  $qualifier Token qualifier.
     * @param string|null  $format    Token format.
     * @param array<mixed> $data      Extra data to provide context to replace token.
     *
     * @return string
     *   Token replacement.
     *
     * @throws DriverException
     * @throws UnsupportedDriverActionException
     * @throws \Exception
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function replaceUrlToken(string $token, string $name, string $qualifier = null, string $format = null, array $data = []): string
    {
        $currentUrl = $this->getSession()->getDriver()->getCurrentUrl();
        $currentUrlParts = parse_url($currentUrl);
        if (!$currentUrlParts) {
            throw new \Exception('Could not parse url.');
        }
        switch ($qualifier) {
            case 'origin':
                $origin = sprintf('%s://%s', $currentUrlParts['scheme'], $currentUrlParts['host']);

                return urlencode($origin);
            case 'relative':
                $relative = trim($currentUrlParts['path'], '/');
                $relative = (isset($currentUrlParts['query'])) ? $relative.'?'.$currentUrlParts['query'] : $relative;
                $relative = (isset($currentUrlParts['fragment'])) ? $relative.'#'.$currentUrlParts['fragment'] : $relative;

                return urlencode($relative);
            case 'domain':
                return $currentUrlParts['host'];
            case 'path':
                $path = trim($currentUrlParts['path'], '/');

                return urlencode($path);
            case 'query':
                $query = (isset($currentUrlParts['query'])) ? $currentUrlParts['query'] : '';

                return urlencode($query);
            case 'fragment':
                $fragment = (isset($currentUrlParts['fragment'])) ? $currentUrlParts['fragment'] : '';

                return urlencode($fragment);
            default:
                return urlencode($currentUrl);
        }
    }

    /**
     * Replace {fail} token.
     *
     * @param string       $token     Original token.
     * @param string       $name      Token name.
     * @param string|null  $qualifier Token qualifier.
     * @param string|null  $format    Token format.
     * @param array<mixed> $data      Extra data to provide context to replace token.
     *
     * @return string
     *   Token replacement.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function replaceFailToken(string $token, string $name, string $qualifier = null, string $format = null, array $data = []): string
    {
        return $this->failPrefix;
    }
}
