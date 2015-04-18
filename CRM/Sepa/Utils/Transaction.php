<?php

/**
 * Wrapper for CRM_Core_Transaction,
 * to work around the problem that the original implementation
 * automatically does commit when the destructor is invoked,
 * unless an explicit rollback has been done before.
 *
 * This wrapper reverses the behaviour --
 * i.e. the destructor does an automatic rollback,
 * unless an explicit commit has been done before.
 *
 * This is much more useful if an Exception is thrown
 * (directly or through CRM_Core_Error::fatal() )
 * while a transaction is in progress.
 *
 * Note that with CiviCRM 4.6,
 * the CRM_Core_Transaction API can optionally be used in a new way
 * (using CRM_Core_Transaction::create()->run(function() {...}) ),
 * which apparently avoids this problem.
 */
class CRM_Sepa_Utils_Transaction extends CRM_Core_Transaction {
  private $commited = false;

  function __destruct() {
    if (!$this->commited) {
      parent::rollback();
    }
    parent::__destruct();
  }

  function commit() {
    parent::commit();
    $this->commited = true;
  }
}
