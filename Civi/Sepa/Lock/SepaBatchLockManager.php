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

use Civi\Core\Lock\LockInterface;
use Civi\Core\Lock\LockManager;

final class SepaBatchLockManager {

  private const LOCK_NAME = 'data.sepa.batch';

  private ?SepaAsyncBatchLock $asyncLock = NULL;

  private ?LockInterface $civiLock = NULL;

  private ?int $defaultLockTimeout = NULL;

  private ?SepaBatchLock $lock = NULL;

  private LockManager $lockManager;


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
    return $this->lock ??= new SepaBatchLock(
      $this->civiLock ??= $this->lockManager->create(self::LOCK_NAME),
      $this->getAsyncLock(),
      $this->getDefaultLockTimeout());
  }

  public function release(string $asyncLockId): bool {
    $rv = $this->getAsyncLock()->release($asyncLockId);
    $this->asyncLock = NULL;
    $this->civiLock = NULL;
    $this->lock = NULL;

    return $rv;
  }

  private function getDefaultLockTimeout(): int {
    return $this->defaultLockTimeout ??=
      (int) (\CRM_Sepa_Logic_Settings::getSetting('batching.UPDATE.lock.timeout') ?? 0);
  }

  private function getAsyncLock(): SepaAsyncBatchLock {
    return $this->asyncLock ??= new SepaAsyncBatchLock(
      \Civi::paths()->getPath('[civicrm.files]/custom') . '/civisepa_' . self::LOCK_NAME . '.lock'
    );
  }

  private function getPrivateLock(): SepaBatchLock {
    return $this->lock ??= $this->getLock();
  }

}
