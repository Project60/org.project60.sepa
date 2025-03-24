<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2017-2018 SYSTOPIA                       |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

use Civi\Sepa\SepaBatchLock;
use Civi\Sepa\SepaBatchLockManager;
use CRM_Sepa_ExtensionUtil as E;


/**
 * Queue Item for updating a sepa group
 */
class CRM_Sepa_Logic_Queue_Update {

  public const ASYNC_LOCK_NAME = 'sdd_async_update_lock';

  private const ASYNC_LOCK_TIMEOUT = 600;

  private const BATCH_SIZE = 250;

  public $title          = NULL;
  protected $cmd         = NULL;
  protected $mode        = NULL;
  protected $creditor_id = NULL;
  protected $offset      = NULL;
  protected $limit       = NULL;

  /**
   * Use CRM_Queue_Runner to do the SDD group update
   * This doesn't return, but redirects to the runner
   */
  public static function launchUpdateRunner(string $mode): void {
    if (!SepaBatchLockManager::getInstance()->acquire(0)
      || !CRM_Sepa_Logic_Settings::acquireAsyncLock(self::ASYNC_LOCK_NAME, self::ASYNC_LOCK_TIMEOUT)
    ) {
      CRM_Core_Session::setStatus(E::ts('Cannot run update, another update is in progress!'), E::ts('Error'), 'error');
      $redirect_url = CRM_Utils_System::url('civicrm/sepa/dashboard', 'status=active');
      CRM_Utils_System::redirect($redirect_url);
      return; // shouldn't be necessary
    }
    // create a queue
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type'  => 'Sql',
      'name'  => 'sdd_update',
      'reset' => TRUE,
    ));

    // first thing: close outdated groups
    $queue->createItem(new CRM_Sepa_Logic_Queue_Update('PREPARE', $mode));
    $queue->createItem(new CRM_Sepa_Logic_Queue_Update('CLOSE', $mode));

    // then iterate through all creditors
    $creditors = civicrm_api3('SepaCreditor', 'get', array('option.limit' => 0));
    foreach ($creditors['values'] as $creditor) {
      $sdd_modes = ($mode=='RCUR') ? array('FRST','RCUR') : array('OOFF');
      foreach ($sdd_modes as $sdd_mode) {
        $count = self::getMandateCount($creditor['id'], $sdd_mode) + self::BATCH_SIZE; // safety margin
        for ($offset=0; $offset < $count; $offset+=self::BATCH_SIZE) {
          // add an item for each batch
          $queue->createItem(new CRM_Sepa_Logic_Queue_Update('UPDATE', $sdd_mode, $creditor['id'], $offset, self::BATCH_SIZE));
        }
        $queue->createItem(new CRM_Sepa_Logic_Queue_Update('CLEANUP', $sdd_mode));
      }
    }

    $queue->createItem(new CRM_Sepa_Logic_Queue_Update('FINISH', $mode));

    // create a runner and launch it
    $runner = new CRM_Queue_Runner(array(
      'title'     => ts("Updating %1 SEPA Groups", array(1 => $mode, 'domain' => 'org.project60.sepa')),
      'queue'     => $queue,
      'errorMode' => CRM_Queue_Runner::ERROR_ABORT,
      // 'onEnd'     => array('CRM_Admin_Page_ExtensionsUpgrade', 'onEnd'),
      'onEndUrl'  => CRM_Utils_System::url('civicrm/sepa/dashboard', 'status=active'),
    ));
    $runner->runAllViaWeb(); // does not return
  }


  protected function __construct($cmd, $mode, $creditor_id = NULL, $offset = NULL, $limit = NULL) {
    $this->cmd         = $cmd;
    $this->mode        = $mode;
    $this->creditor_id = $creditor_id;
    $this->offset      = $offset;
    $this->limit       = $limit;

    // set title
    switch ($this->cmd) {
      case 'PREPARE':
        $this->title = ts("Preparing to clean up ended mandates", array('domain' => 'org.project60.sepa'));
        break;

      case 'CLOSE':
        $this->title = ts("Cleaning up ended mandates", array('domain' => 'org.project60.sepa'));
        break;

      case 'UPDATE':
        $this->title = ts("Process %1 mandates (%2-%3)",
          array(1 => $this->mode, 2 => $this->offset, 3 => $this->offset+$this->limit, 'domain' => 'org.project60.sepa'));
        break;

      case 'CLEANUP':
        $this->title = ts("Cleaning up %1 groups",
          array(1 => $this->mode, 'domain' => 'org.project60.sepa'));
        break;

      case 'FINISH':
        $this->title = ts("Lock released", array('domain' => 'org.project60.sepa'));
        break;

      default:
        $this->title = "Unknown";
      }
  }

  public function run($context): bool {
    if (!SepaBatchLockManager::getInstance()->acquire(10, SepaBatchLock::FLAG_IGNORE_ASYNC_LOCK)) {
      throw new \RuntimeException('Unable to acquire lock');
    }

    switch ($this->cmd) {
      case 'PREPARE':
        // nothing to do
        break;

      case 'CLOSE':
        CRM_Sepa_Logic_Batching::closeEnded();
        CRM_Sepa_Logic_Settings::renewAsyncLock(self::ASYNC_LOCK_NAME, self::ASYNC_LOCK_TIMEOUT);
        break;

      case 'UPDATE':
        if ($this->mode == 'OOFF') {
          CRM_Sepa_Logic_Batching::updateOOFF($this->creditor_id, 'now', $this->offset, $this->limit);
        } else {
          CRM_Sepa_Logic_Batching::updateRCUR($this->creditor_id, $this->mode, 'now', $this->offset, $this->limit);
        }
        CRM_Sepa_Logic_Settings::renewAsyncLock(self::ASYNC_LOCK_NAME, self::ASYNC_LOCK_TIMEOUT);
        break;

      case 'CLEANUP':
        CRM_Sepa_Logic_Group::cleanup($this->mode);
        break;

      case 'FINISH':
        CRM_Sepa_Logic_Settings::releaseAsyncLock(self::ASYNC_LOCK_NAME);
        break;

      default:
        return FALSE;
    }

    return TRUE;
  }



  /**
   * determine the count of mandates to be investigated
   */
  protected static function getMandateCount($creditor_id, $sdd_mode) {
    if ($sdd_mode == 'OOFF') {
      $horizon = (int) CRM_Sepa_Logic_Settings::getSetting('batching.OOFF.horizon', $creditor_id);
      $date_limit = date('Y-m-d', strtotime("+$horizon days"));
      return CRM_Core_DAO::singleValueQuery("
        SELECT COUNT(mandate.id)
        FROM civicrm_sdd_mandate AS mandate
        INNER JOIN civicrm_contribution AS contribution  ON mandate.entity_id = contribution.id
        WHERE contribution.receive_date <= DATE('$date_limit')
          AND mandate.type = 'OOFF'
          AND mandate.status = 'OOFF'
          AND mandate.creditor_id = $creditor_id;");
    } else {
      return CRM_Core_DAO::singleValueQuery("
        SELECT
          COUNT(mandate.id)
        FROM civicrm_sdd_mandate AS mandate
        WHERE mandate.type = 'RCUR'
          AND mandate.status = '$sdd_mode'
          AND mandate.creditor_id = $creditor_id;");
    }
  }
}
