<?php

/**
 * @file
 * PHP inbuilt server. Useful for running fixtures.
 */

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

/**
 * Trait PhpServerTrait.
 */
trait PhpServerTrait {

  /**
   * Docroot directory.
   *
   * @var string
   */
  protected $docroot;

  /**
   * Server hostname.
   *
   * @var string
   */
  protected $host;

  /**
   * Server port.
   *
   * @var string
   */
  protected $port;

  /**
   * Server process id.
   *
   * @var string
   */
  protected $pid;

  /**
   * PhpServerTrait constructor.
   *
   * @param string $docroot
   *   Server docroot directory.
   * @param string $host
   *   Server host name. Defaults to 'localhost'.
   * @param string $port
   *   Server port. Defaults to '8888'.
   */
  public function __construct($docroot, $host = 'localhost', $port = '8888') {
    $this->docroot = $docroot;
    $this->host = $host;
    $this->port = $port;
  }

  /**
   * Start server before each scenario.
   *
   * @beforeScenario @phpserver
   */
  public function beforeScenarioStartPhpServer(BeforeScenarioScope $scope) {
    if ($scope->getScenario()->hasTag('phpserver')) {
      $this->phpServerStart();
    }
  }

  /**
   * Stop server after each scenario.
   *
   * @afterScenario @phpserver
   */
  public function afterScenarioStopPhpServer(AfterScenarioScope $scope) {
    if ($scope->getScenario()->hasTag('phpserver')) {
      $this->phpServerStop();
    }
  }

  /**
   * Start a server.
   *
   * @throws RuntimeException
   *   If unable to start a server.
   */
  protected function phpServerStart() {
    // If the server already running on this port, stop it.
    // This is a much simpler way of handling previously started servers than
    // having a server manager that would track each instance.
    if ($this->phpServerIsRunning(FALSE)) {
      $pid = $this->phpServerGetPid($this->port);
      $this->phpServerTerminateProcess($pid);
    }
    $command = sprintf('php -S %s:%d -t %s >/dev/null 2>&1 & echo $!', $this->host, $this->port, $this->docroot);
    $output = [];
    $code = 0;
    exec($command, $output, $code);
    if ($code === 0) {
      $this->pid = $output[0];
    }
    if (!$this->pid || !$this->phpServerIsRunning()) {
      throw new \RuntimeException('Unable to start PHP server');
    }
    return $this->pid;
  }

  /**
   * Stop running server.
   *
   * @return bool
   *   TRUE if server process was stopped, FALSE otherwise.
   */
  public function phpServerStop() {
    if (!$this->phpServerIsRunning(FALSE)) {
      return TRUE;
    }
    return $this->phpServerTerminateProcess($this->pid);
  }

  /**
   * Check that a server is running.
   *
   * @param int $timeout
   *   Retry timeout in seconds.
   * @param int $delay
   *   Delay between retries in microseconds. Default to 0.5 of the second.
   *
   * @return bool
   *   TRUE if the server is running, FALSE otherwise.
   */
  public function phpServerIsRunning($timeout = 1, $delay = 500000) {
    if ($timeout === FALSE) {
      return $this->phpServerCanConnect();
    }
    $start = microtime(TRUE);
    while (microtime(TRUE) - $start <= $timeout) {
      if ($this->phpServerCanConnect()) {
        return TRUE;
      }
      usleep($delay);
    }
    return FALSE;
  }

  /**
   * Check if it is possible to connect to a server.
   *
   * @return bool
   *   TRUE if server is running and it is possible to connect to it via socket,
   *   FALSE otherwise.
   */
  protected function phpServerCanConnect() {
    set_error_handler(function () {
      return TRUE;
    });
    $sp = fsockopen($this->host, $this->port);
    restore_error_handler();
    if ($sp === FALSE) {
      return FALSE;
    }
    fclose($sp);
    return TRUE;
  }

  /**
   * Terminate a process.
   *
   * @param int $pid
   *   Process id.
   *
   * @return int
   *   TRUE if the process was successfully terminated, FALSE otherwise.
   */
  protected function phpServerTerminateProcess($pid) {
    // If pid was not provided, do not allow to terminate current process.
    if (!$pid) {
      return 1;
    }
    $output = [];
    $code = 0;
    exec('kill ' . (int) $pid, $output, $code);
    return $code === 0;
  }

  /**
   * Get PID of the running server on the specified port.
   *
   * Note that this will retrieve a PID of the process that could have been
   * started by another process rather then current one.
   *
   * @return int
   *   PID as number.
   */
  protected function phpServerGetPid($port) {
    $pid = 0;
    $output = [];
    // @todo: Add support to OSes other then OSX and Ubuntu.
    exec("netstat -peanut 2>/dev/null|grep ':$port'", $output);
    if (!isset($output[0])) {
      throw new RuntimeException('Unable to determine if PHP server was started on current OS.');
    }
    $parts = explode(' ', preg_replace('/\s+/', ' ', $output[0]));
    if (isset($parts[8]) && $parts[8] != '-') {
      list($pid, $name) = explode('/', $parts[8]);
      if ($name != 'php') {
        $pid = 0;
      }
    }

    return (int) $pid;
  }

}
