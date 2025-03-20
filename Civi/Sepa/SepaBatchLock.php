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

namespace Civi\Sepa;

use Civi\Core\Lock\LockInterface;

final class SepaBatchLock implements LockInterface {

  public const FLAG_IGNORE_ASYNC_LOCK = 1;

  private int $defaultTimeout;

  private LockInterface $lock;

  public function __construct(LockInterface $lock, int $defaultTimeout) {
    $this->lock = $lock;
    $this->defaultTimeout = $defaultTimeout;
  }

  public function acquire($timeout = NULL, int $flags = 0): bool {
    if ($this->isAcquired()) {
      return TRUE;
    }

    if (!$this->lock->acquire($timeout ?? $this->defaultTimeout)) {
      return FALSE;
    }

    if (0 !== ($flags & self::FLAG_IGNORE_ASYNC_LOCK)
      || \CRM_Sepa_Logic_Settings::isAsyncLockFree(\CRM_Sepa_Logic_Queue_Update::ASYNC_LOCK_NAME)
    ) {
      return TRUE;
    }

    // There is a small chance that a lock is acquired during a page switch
    // when doing an async run. Thus, we release the lock wait a short time to
    // give a possible async run the possibility to acquire the lock again. If
    // the lock isn't acquired then, there's no async update running, but the
    // async lock wasn't released for some reason.
    $this->lock->release();
    sleep(3);
    if (!$this->lock->acquire(0)) {
      return FALSE;
    }

    \CRM_Sepa_Logic_Settings::releaseAsyncLock(\CRM_Sepa_Logic_Queue_Update::ASYNC_LOCK_NAME);

    return TRUE;
  }

  public function release(): bool {
    return (bool) $this->lock->release();
  }

  public function isFree(): bool {
    return (bool) $this->lock->isFree();
  }

  public function isAcquired(): bool {
    return $this->lock->isAcquired();
  }

}
