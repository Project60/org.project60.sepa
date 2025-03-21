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

  private SepaAsyncBatchLock $asyncLock;

  private LockInterface $civiLock;

  private int $defaultTimeout;

  public function __construct(LockInterface $civiLock, SepaAsyncBatchLock $asyncLock, int $defaultTimeout) {
    $this->civiLock = $civiLock;
    $this->asyncLock = $asyncLock;
    $this->defaultTimeout = $defaultTimeout;
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

    if ($this->asyncLock->isFree()) {
      return TRUE;
    }

    // There is a small chance that the CiviCRM lock is acquired during a page
    // switch when doing an async run. Thus, we release the lock wait a short
    // time to give a possible async run the possibility to acquire the lock
    // again. If the lock isn't acquired then, there's no async update running,
    // but the async lock wasn't released for some reason.
    $this->civiLock->release();
    sleep(3);
    if (!$this->civiLock->acquire(0)) {
      return FALSE;
    }

    $this->asyncLock->releaseAny();

    return TRUE;
  }

  public function release(?string $asyncLockId = NULL): bool {
    $civiLockRelease = $this->civiLock->release();

    return (NULL === $asyncLockId || $this->asyncLock->release($asyncLockId))
      && (NULL === $civiLockRelease || (bool) $civiLockRelease);
  }

  public function isAcquired(?string $asyncLockId = NULL): bool {
    return $this->civiLock->isAcquired()
      && (NULL === $asyncLockId || $this->asyncLock->isAcquired($asyncLockId));
  }

}
