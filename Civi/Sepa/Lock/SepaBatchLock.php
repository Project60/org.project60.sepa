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

/**
 * Combines a CiviCRM lock with a SepaAsyncBatchLock. When using in async batch
 * run an async lock ID has to be given.
 *
 * @see SepaAsyncBatchLock
 */
final class SepaBatchLock {

  private static int $instanceCount = 0;

  private SepaAsyncBatchLock $asyncLock;

  private LockInterface $civiLock;

  private int $defaultTimeout;

  public function __construct(LockInterface $civiLock, SepaAsyncBatchLock $asyncLock, int $defaultTimeout) {
    self::$instanceCount++;
    $this->civiLock = $civiLock;
    $this->asyncLock = $asyncLock;
    $this->defaultTimeout = $defaultTimeout;
  }

  public function __destruct() {
    self::$instanceCount--;
    if (self::$instanceCount === 0) {
      $this->civiLock->release();
    }
  }

  public function acquire(?int $timeout = NULL, ?string $asyncLockId = NULL): bool {
    if (!$this->civiLock->acquire($timeout ?? $this->defaultTimeout)) {
      if (NULL !== $asyncLockId) {
        $this->asyncLock->release($asyncLockId);
      }

      return FALSE;
    }

    if (NULL !== $asyncLockId) {
      if (!$this->asyncLock->acquire($asyncLockId)) {
        $this->civiLock->release();

        return FALSE;
      }

      return TRUE;
    }

    $asyncLockAcquireTime = $this->asyncLock->getAcquireTime();
    if (NULL === $asyncLockAcquireTime || $this->asyncLock->isAcquired()) {
      return TRUE;
    }

    /*
     * It is possible that the CiviCRM lock is acquired when during a queue
     * execution an HTTP request was finished and the next one is not running,
     * yet. Thus, we release the lock and wait some time to give a queue item
     * the possibility to acquire the lock again. If the async lock time isn't
     * changed, but the async lock wasn't released for some reason.
     */
    $this->civiLock->release();
    for ($i = 0; $i < 5; ++$i) {
      sleep(1);
      $newAsyncLockAcquireTime = $this->asyncLock->getAcquireTime();
      if (NULL === $newAsyncLockAcquireTime) {
        // Async lock is free now.
        break;
      }
      if ($asyncLockAcquireTime !== $newAsyncLockAcquireTime) {
        return FALSE;
      }
    }

    if (!$this->civiLock->acquire(0)) {
      return FALSE;
    }

    $this->asyncLock->releaseAny();

    return TRUE;
  }

  public function isAcquired(?string $asyncLockId = NULL): bool {
    return $this->civiLock->isAcquired()
      && ($this->asyncLock->isAcquired($asyncLockId) || (NULL === $asyncLockId && $this->asyncLock->isFree()));
  }

}
