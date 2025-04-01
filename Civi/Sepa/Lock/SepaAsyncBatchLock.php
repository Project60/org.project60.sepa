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

  private ?string $acquiredId = NULL;

  private string $lockFile;

  public function __construct(string $lockFile) {
    $this->lockFile = $lockFile;
  }

  public function acquire(string $id): bool {
    if (($this->getAsyncLockId() ?? $id) !== $id) {
      return FALSE;
    }

    $this->setAsyncLock($id);

    return TRUE;
  }

  public function isAcquired(?string $id = NULL): bool {
    if ($id === NULL) {
      return NULL !== $this->acquiredId && $this->acquiredId === $this->getAsyncLockId();
    }

    return $this->acquiredId === $id && $this->getAsyncLockId() === $id;
  }

  /**
   * @return null|int
   *   UNIX timestamp of the time the lock was last acquired. NULL if the lock
   *   is free.
   */
  public function getAcquireTime(): ?int {
    if (!file_exists($this->lockFile)) {
      return NULL;
    }

    clearstatcache(TRUE, $this->lockFile);
    $time = filemtime($this->lockFile);
    if (FALSE === $time) {
      throw new \RuntimeException("Couldn't get modification time of {$this->lockFile}");
    }

    return $time;
  }

  public function isFree(): bool {
    return NULL === $this->acquiredId && $this->getAcquireTime() === NULL;
  }

  public function release(string $id): bool {
    if (($this->getAsyncLockId() ?? $id) !== $id) {
      return FALSE;
    }

    $this->setAsyncLock(NULL);

    return TRUE;
  }

  /**
   * Releases the lock without specifying the ID.
   */
  public function releaseAny(): void {
    $this->setAsyncLock(NULL);
  }

  private function getAsyncLockId(): ?string {
    if (!file_exists($this->lockFile)) {
      return NULL;
    }

    $id = file_get_contents($this->lockFile);
    if (FALSE === $id) {
      throw new \RuntimeException("Couldn't read {$this->lockFile}");
    }

    return $id;
  }

  /**
   * @phpstan-param array<string, array{id: string, acquireTime: int}> $locks
   */
  private function setAsyncLock(?string $id): void {
    $this->acquiredId = $id;
    if (NULL === $this->acquiredId) {
      if (!unlink($this->lockFile)) {
        throw new \RuntimeException("Couldn't remove {$this->lockFile}");
      }
    }
    else if (FALSE === file_put_contents($this->lockFile, $id)) {
      throw new \RuntimeException("Couldn't write {$this->lockFile}");
    }
  }

}
