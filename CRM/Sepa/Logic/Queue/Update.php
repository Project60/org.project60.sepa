<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2017 SYSTOPIA                            |
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

define('SDD_UPDATE_RUNNER_BATCH_SIZE', 500);


/**
 * Queue Item for updating a sepa group
 */
class CRM_Sepa_Logic_Queue_Update {

  public $title          = NULL;
  protected $mode        = NULL;
  protected $creditor_id = NULL;
  protected $offset      = NULL;
  protected $limit       = NULL;

  /**
   * Use CRM_Queue_Runner to do the upgrade
   * This doesn't return, but redirects to the runner
   */
  public static function launchUpdateRunner($mode) {
    // create a queue
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type'  => 'Sql',
      'name'  => 'sdd_update',
      'reset' => TRUE,
    ));

    // first thing: close outdated groups
    $queue->createItem(new CRM_Sepa_Logic_Queue_Update('CLOSE'));

    // then iterate through all creditors
    $creditors = civicrm_api3('SepaCreditor', 'get', array('option.limit' => 0));
    foreach ($creditors['values'] as $creditor) {
      $sdd_modes = ($mode=='RCUR') ? array('FRST','RCUR') : array('OOFF');
      foreach ($sdd_modes as $sdd_mode) {
        $count = self::getMandateCount($creditor['id'], $sdd_mode) + 200; // safety margin
        for ($offset=0; $offset < $count; $offset+=SDD_UPDATE_RUNNER_BATCH_SIZE) {
          // add an item for each batch
          $queue->createItem(new CRM_Sepa_Logic_Queue_Update($sdd_mode, $creditor['id'], $offset, SDD_UPDATE_RUNNER_BATCH_SIZE));
        }
      }
    }

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


  protected function __construct($mode, $creditor_id = NULL, $offset = NULL, $limit = NULL) {
    $this->mode        = $mode;
    $this->creditor_id = $creditor_id;
    $this->offset      = $offset;
    $this->limit       = $limit;

    // set title
    switch ($this->mode) {
      case 'CLOSE':
        $this->title = ts("Cleaning up ended mandates", array('domain' => 'org.project60.sepa'));
        break;

      case 'OOFF':
      case 'FRST':
      case 'RCUR':
        $this->title = ts("Analysing {$this->mode} mandates (%1-%2)",
          array(1 => $this->offset, 2 => $this->offset+$this->limit, 'domain' => 'org.project60.sepa'));
        break;

      default:
        $this->title = "Unknown";
      }
  }

  public function run($context) {
    switch ($this->mode) {
      case 'CLOSE':
        CRM_Sepa_Logic_Batching::closeEnded();
        break;

      case 'OOFF':
        error_log("RUNNING {$this->mode} UPDATE FOR CREDITOR {$this->creditor_id} [{$this->offset},{$this->limit}]");
        CRM_Sepa_Logic_Batching::updateOOFF($this->creditor_id, 'now', $this->offset, $this->limit);
        break;

      case 'FRST':
      case 'RCUR':
        error_log("RUNNING {$this->mode} UPDATE FOR CREDITOR {$this->creditor_id} [{$this->offset},{$this->limit}]");
        CRM_Sepa_Logic_Batching::updateRCUR($this->creditor_id, $this->mode, 'now', $this->offset, $this->limit);
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


