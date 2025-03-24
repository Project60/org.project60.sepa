<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2025 SYSTOPIA GmbH                       |
| https://www.systopia.de/                               |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

declare(strict_types = 1);

namespace Civi\Sepa\Lock;

use Civi\Core\Lock\LockManager;

final class SepaBatchLockManager {

  private const LOCK_NAME = 'data.sepa.batch';

  private ?int $defaultLockTimeout = NULL;

  private LockManager $lockManager;

  private ?SepaBatchLock $lock;

  public static function getInstance(): self {
    return \Civi::service(self::class);
  }

  public function __construct(?LockManager $lockManager = NULL) {
    $this->lockManager = $lockManager ?? \Civi::lockManager();
  }

  /**
   * Lock will be acquired until script terminates.
   */
  public function acquire(?int $timeout = NULL, ?string $asyncLockId = NULL): bool {
    return $this->getPrivateLock()->acquire($timeout, $asyncLockId);
  }

  public function getLock(): SepaBatchLock {
    return new SepaBatchLock(
      $this->lockManager->create(self::LOCK_NAME),
      new SepaAsyncBatchLock(self::LOCK_NAME),
      $this->getDefaultLockTimeout());
  }

  private function getDefaultLockTimeout(): int {
    return $this->defaultLockTimeout ??=
      (int) (\CRM_Sepa_Logic_Settings::getSetting('batching.UPDATE.lock.timeout') ?? 0);
  }

  private function getPrivateLock(): SepaBatchLock {
    return $this->lock ??= $this->getLock();
  }

}
