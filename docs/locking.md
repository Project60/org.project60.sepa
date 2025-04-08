# Locking

This chapter describes how the locking mechanism works that prevents parallel
changes that might lead to inconsistencies. It's rather technical and not
relevant for most users.

Because of batch processing using
[queues](https://docs.civicrm.org/dev/en/latest/framework/queues/) we cannot
purely rely on standard blocking locks. CiviCRM uses the database function
[`GET_LOCK`](https://mariadb.com/kb/en/get_lock/) for these. The complete queue
processing has to be protected, but every batch runs in a separate HTTP call. At
the end of every HTTP request all locks acquired with `GET_LOCK` are
automatically released. This would make it possible to acquire the database lock
and perform changes in between the HTTP calls of the batch processing.

To prevent this an additional lock is used. It is a file-based non-blocking lock
that has the following characteristics:

* Acquiring is done with an ID (random string) that is written into the file.
* The time of the last acquiring is the modification time of the file.
* If the file exists, it means that the lock is acquired. (Re-)acquiring is then
  only possible with the ID that was used previously.
* On release the file is removed.

How this two types of locks are combined is described in the following. If not
otherwise stated failing to acquire a lock will prevent a change request.

When running queue items at first a database lock is acquired (with wait time)
then the file-based lock is (re-)acquired using the same ID for every batch.

When performing an action that make changes (inside or outside of batch
processing) a database lock is acquired, and it is checked if the file-based
lock is already acquired (batch processing) or free (no batch processing).

If the file-based lock is not free, there might be a batch processing where
currently a queue item switch is done. In that case the database lock is
released and and the process is interrupted for a short time (`sleep()`). This
gives a possible batch running the chance to acquire the database lock and to
continue its work. In case a queue is running, it would re-acquire the
file-based lock and so change the acquire time. Thus, when the interruption is
finished the acquire time is checked. If it has been changed, there's a queue
running and the requested change is prevented. Otherwise, there's no queue
running, but the file-based lock wasn't released for some reason. In that case
the database lock is acquired, the file-based lock is released and the change is
executed.

For implementation details see
[`SepaBatchLockManager`](../Civi/Sepa/Lock/SepaBatchLockManager.php),
[`SepaBatchLock`](../Civi/Sepa/Lock/SepaBatchLock.php), and
[`SepaAsyncBatchLock`](../Civi/Sepa/Lock/SepaAsyncBatchLock.php).
