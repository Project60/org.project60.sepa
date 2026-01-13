<?php
declare(strict_types = 1);

use Composer\Autoload\ClassLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;

ini_set('memory_limit', '2G');

if (file_exists(__DIR__ . '/bootstrap.local.php')) {
  require_once __DIR__ . '/bootstrap.local.php';
}

/*
 * The return value of this function call is used in the strftime()
 * implementation used in CiviCRM. If it is 'C' this results in this error:
 * datefmt_create: invalid locale: U_ILLEGAL_ARGUMENT_ERROR
 *
 * Patch applied by CiviCRM containing strftime():
 * https://patch-diff.githubusercontent.com/raw/pear/Log/pull/23.patch
 *
 * https://lab.civicrm.org/dev/core/-/issues/4739
 * Fixed in 5.67.0 https://github.com/civicrm/civicrm-core/pull/27981
 */
if ('C' === setlocale(LC_TIME, '0')) {
  setlocale(LC_TIME, 'en_US.UTF-8');
}

// phpcs:disable Drupal.Functions.DiscouragedFunctions.Discouraged
eval(cv('php:boot --level=classloader', 'phpcode'));
// phpcs:enable

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
  require_once __DIR__ . '/../../vendor/autoload.php';
}

// Make CRM_Sepa_ExtensionUtil available.
require_once __DIR__ . '/../../sepa.civix.php';

// phpcs:disable PSR1.Files.SideEffects

// Add test classes to class loader.
addExtensionDirToClassLoader(__DIR__);
addExtensionToClassLoader('org.project60.sepa');

if (!function_exists('ts')) {
  // Ensure function ts() is available - it's declared in the same file as CRM_Core_I18n in CiviCRM < 5.74.
  // In later versions the function is registered following the composer conventions.
  \CRM_Core_I18n::singleton();
}

/**
 * Modify DI container for tests.
 */
function _sepa_test_civicrm_container(ContainerBuilder $container): void {
}

function addExtensionToClassLoader(string $extension): void {
  addExtensionDirToClassLoader(__DIR__ . '/../../../' . $extension);
}

function addExtensionDirToClassLoader(string $extensionDir): void {
  $loader = new ClassLoader();
  $loader->add('CRM_', [$extensionDir]);
  $loader->addPsr4('Civi\\', [$extensionDir . '/Civi']);
  $loader->add('api_', [$extensionDir]);
  $loader->addPsr4('api\\', [$extensionDir . '/api']);
  $loader->register();

  if (file_exists($extensionDir . '/autoload.php')) {
    require_once $extensionDir . '/autoload.php';
  }
}

/**
 * Call the "cv" command.
 *
 * @param string $cmd
 *   The rest of the command to send.
 * @param string $decode
 *   Ex: 'json' or 'phpcode'.
 * @return mixed
 *   Response output (if the command executed normally).
 *   For 'raw' or 'phpcode', this will be a string. For 'json', it could be any JSON value.
 * @throws \RuntimeException
 *   If the command terminates abnormally.
 */
function cv(string $cmd, string $decode = 'json') {
  $cmd = 'cv ' . $cmd;
  $descriptorSpec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => STDERR];
  $oldOutput = getenv('CV_OUTPUT');
  putenv('CV_OUTPUT=json');

  // Execute `cv` in the original folder. This is a work-around for
  // phpunit/codeception, which seem to manipulate PWD.
  $cmd = sprintf('cd %s; %s', escapeshellarg(getenv('PWD')), $cmd);

  $process = proc_open($cmd, $descriptorSpec, $pipes, __DIR__);
  putenv("CV_OUTPUT=$oldOutput");
  fclose($pipes[0]);
  $result = stream_get_contents($pipes[1]);
  fclose($pipes[1]);
  if (proc_close($process) !== 0) {
    throw new \RuntimeException("Command failed ($cmd):\n$result");
  }
  switch ($decode) {
    case 'raw':
      return $result;

    case 'phpcode':
      // If the last output is /*PHPCODE*/, then we managed to complete execution.
      if (substr(trim($result), 0, 12) !== '/*BEGINPHP*/' || substr(trim($result), -10) !== '/*ENDPHP*/') {
        throw new \RuntimeException("Command failed ($cmd):\n$result");
      }
      return $result;

    case 'json':
      return json_decode($result, TRUE);

    default:
      throw new \RuntimeException("Bad decoder format ($decode)");
  }
}

