<?php
/*
 * Copyright (C) 2022 SYSTOPIA GmbH
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation in version 3.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types = 1);

// phpcs:disable Drupal.Commenting.DocComment.ContentAfterOpen
/** @var \PHPStan\DependencyInjection\Container $container */
/** @phpstan-var array<string> $bootstrapFiles */

use Composer\Autoload\ClassLoader;

$bootstrapFiles = $container->getParameter('bootstrapFiles');
foreach ($bootstrapFiles as $bootstrapFile) {
  if (str_ends_with($bootstrapFile, 'vendor/autoload.php')) {
    $vendorDir = dirname($bootstrapFile);
    // Installation via composer (e.g. as Drupal module)
    $civiCrmVendorDir = $vendorDir . '/civicrm';
    $civiCrmCoreDir = $civiCrmVendorDir . '/civicrm-core';
    $civiCrmPackagesDir = $civiCrmVendorDir . '/civicrm-packages';
    // Installation without composer (e.g. as WordPress plugin)
    if (!is_dir($civiCrmCoreDir) || !is_dir($civiCrmPackagesDir)) {
      $civiCrmCoreDir = $vendorDir . '/..';
      $civiCrmPackagesDir = $civiCrmCoreDir . '/packages';
    }
    if (!is_dir($civiCrmCoreDir) || !is_dir($civiCrmPackagesDir)) {
      continue;
    }
    if (file_exists($civiCrmCoreDir)) {
      set_include_path(
        get_include_path()
        . PATH_SEPARATOR . $civiCrmCoreDir
        . PATH_SEPARATOR . $civiCrmPackagesDir
      );

      $loader = new ClassLoader();
      $loader->add('CRM_', [$civiCrmCoreDir]);
      $loader->add('HTML_', [$civiCrmPackagesDir]);

      // The class \Smarty extended by \CRM_Core_SmartyCompatibility uses the
      // __call() method to delegate method calls to \Smarty\Smarty, but hasn't
      // defined the methods itself which results in method not found errors. By
      // aliasing \Smarty\Smarty to \Smarty we avoid these errors.
      $smartyAutoloadFile = $civiCrmPackagesDir . '/smarty5/vendor/autoload.php';
      if (file_exists($smartyAutoloadFile)) {
        require_once $smartyAutoloadFile;
        class_alias(\Smarty\Smarty::class, 'Smarty');
      }

      $coreExtDirs = glob("$civiCrmCoreDir/ext/*");
      if (FALSE !== $coreExtDirs) {
        foreach ($coreExtDirs as $extensionDir) {
          if (is_dir("$extensionDir/CRM")) {
            $loader->add('CRM_', [$extensionDir]);
          }
          if (is_dir("$extensionDir/Civi")) {
            $loader->addPsr4('Civi\\', [$extensionDir . '/Civi']);
          }
        }
      }

      $loader->register();

      break;
    }
  }
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
  require_once __DIR__ . '/vendor/autoload.php';
}
