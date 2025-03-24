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

/**
 * A lock that isn't released on script termination. It has to be manually
 * released or by SepaBatchLock. It should only be used in combination with
 * SepaBatchLock. It is used to ensure that there's no parallel async batch run.
 *
 * The lock is "protected" with an ID that has to be the same until the lock is
 * released. The ID should be some random string.
 *
 * @see \Civi\Sepa\Lock\SepaBatchLock
 */
final class SepaAsyncBatchLock {

  private string $name;

  public function __construct(string $name) {
    $this->name = $name;
  }

  public function acquire(string $id): bool {
    $locks = $this->getAsyncLocks();
    if (($locks[$this->name]['id'] ?? $id) !== $id) {
      return FALSE;
    }

    $locks[$this->name] = [
      'id' => $id,
      'acquireTime' => time(),
    ];
    $this->setAsyncLocks($locks);

    return TRUE;
  }

  public function isAcquired(string $id): bool {
    return ($this->getAsyncLock()['id'] ?? NULL) === $id;
  }

  /**
   * @return null|int
   *   UNIX timestamp of the time the lock was last acquired. NULL if the lock
   *   is free.
   */
  public function getAcquireTime(): ?int {
    return $this->getAsyncLock()['acquireTime'] ?? NULL;
  }

  public function isFree(): bool {
    return $this->getAcquireTime() === NULL;
  }

  public function release(string $id): bool {
    $locks = $this->getAsyncLocks();
    if (($locks[$this->name]['id'] ?? $id) !== $id) {
      return FALSE;
    }

    unset($locks[$this->name]);
    $this->setAsyncLocks($locks);

    return TRUE;
  }

  /**
   * Releases the lock without specifying the ID.
   */
  public function releaseAny(): void {
    $locks = $this->getAsyncLocks();
    unset($locks[$this->name]);
    $this->setAsyncLocks($locks);
  }

  /**
   * @phpstan-return array{id: string, acquireTime: int}|null
   */
  private function getAsyncLock(): ?array {
    return $this->getAsyncLocks()[$this->name] ?? NULL;
  }

  /**
   * @phpstan-return array<string, array{id: string, acquireTime: int}>
   */
  private function getAsyncLocks(): array {
    return \CRM_Sepa_Logic_Settings::getGenericSetting('sdd_async_batching_lock') ?? [];
  }

  /**
   * @phpstan-param array<string, array{id: string, acquireTime: int}> $locks
   */
  private function setAsyncLocks(array $locks): void {
    \CRM_Sepa_Logic_Settings::setGenericSetting($locks, 'sdd_async_batching_lock');
  }

}
