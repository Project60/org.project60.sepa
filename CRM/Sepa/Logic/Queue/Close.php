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

define('SDD_CLOSE_RUNNER_BATCH_SIZE', 250);


/**
 * Queue Item for updating a sepa group
 */
class CRM_Sepa_Logic_Queue_Close {

  public $title               = NULL;
  protected $txgroup          = NULL;
  protected $target_status_id = NULL;
  protected $group_name       = NULL;
  protected $counter          = NULL;

  /**
   * Use CRM_Queue_Runner to close a SDD group
   * This doesn't return, but redirects to the runner
   */
  public static function launchCloseRunner($txgroup_ids, $target_group_status, $target_contribution_status) {
    // create a queue
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type'  => 'Sql',
      'name'  => 'sdd_close',
      'reset' => TRUE,
    ));

    // fetch the groups
    $txgroup_query = civicrm_api3('SepaTransactionGroup', 'get', array(
      'id'           => array('IN' => $txgroup_ids),
      'option.limit' => 0
      ));

    $group_status_id_busy = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Data Entry');

    foreach ($txgroup_query['values'] as $txgroup) {
      // first: set group status to busy

      $queue->createItem(new CRM_Sepa_Logic_Queue_Close('set_group_status', $txgroup, $group_status_id_busy));

      // count the contributions and create an appropriate amount of items
      $contribution_count = CRM_Core_DAO::singleValueQuery("SELECT COUNT(contribution_id) FROM civicrm_sdd_contribution_txgroup WHERE txgroup_id={$txgroup['id']}");
      $contribution_count += SDD_CLOSE_RUNNER_BATCH_SIZE; // security margin
      for ($offset=0; $offset <= $contribution_count; $offset+=SDD_CLOSE_RUNNER_BATCH_SIZE) {
        $queue->createItem(new CRM_Sepa_Logic_Queue_Close('update_contribution', $txgroup, $target_contribution_status, $offset));
      }

      // finally: render XML and mark the group
      $queue->createItem(new CRM_Sepa_Logic_Queue_Close('create_xml', $txgroup, $target_group_status));
      $queue->createItem(new CRM_Sepa_Logic_Queue_Close('set_group_status', $txgroup, $target_group_status));
    }

    // create a runner and launch it
    $runner = new CRM_Queue_Runner(array(
      'title'     => ts("Closing SDD Group(s) [%1]", array(1 => implode(',', $txgroup_ids), 'domain' => 'org.project60.sepa')),
      'queue'     => $queue,
      'errorMode' => CRM_Queue_Runner::ERROR_ABORT,
      // 'onEnd'     => array('CRM_Admin_Page_ExtensionsUpgrade', 'onEnd'),
      'onEndUrl'  => CRM_Utils_System::url('civicrm/sepa/dashboard', 'status=closed'),
    ));
    $runner->runAllViaWeb(); // does not return
  }


  protected function __construct($mode, $txgroup, $target_status_id, $counter = NULL) {
    $this->mode             = $mode;
    $this->txgroup          = $txgroup;
    $this->target_status_id = $target_status_id;
    $this->counter          = $counter;

    // set title
    switch ($this->mode) {
      case 'update_contribution':
        $this->title = ts("Updating contributions in group '%1'... (%2)", array(
          1 => $txgroup['reference'], 2 => $counter, 'domain' => 'org.project60.sepa'));
        break;

      case 'set_group_status':
        $this->title = ts("Updating status of group '%1'", array(
          1 => $txgroup['reference'], 'domain' => 'org.project60.sepa'));
        break;

      case 'create_xml':
        $this->title = ts("Compiling XML for group '%1'", array(
          1 => $txgroup['reference'], 'domain' => 'org.project60.sepa'));
        break;

      default:
        $this->title = "Unknown";
    }
  }

  public function run($context) {
    switch ($this->mode) {
      case 'update_contribution':
        // this one needs a lock
        $exception = NULL;
        $lock = CRM_Sepa_Logic_Settings::getLock();
        if (empty($lock)) {
          throw new Exception("Batching in progress. Please try again later.");
        }
        try {
          $this->updateContributions();
        } catch (Exception $e) {
          $exception = $e; // store and throw later
        }
        $lock->release();
        if ($exception) throw $exception;
        break;

      case 'create_xml':
        // create the sepa file
        civicrm_api3('SepaAlternativeBatching', 'createxml', array(
          'txgroup_id' => $this->txgroup['id'],
          ));
        break;

      case 'set_group_status':
        // simply change the status
        civicrm_api3('SepaTransactionGroup', 'create', array(
          'id'        => $this->txgroup['id'],
          'status_id' => $this->target_status_id,
          ));
        break;

      default:
        $this->title = "Unknown";
        return FALSE;
    }

    return TRUE;
  }

  /**
   * will select the next batch of up to SDD_CLOSE_RUNNER_BATCH_SIZE
   * contributions and update their status
   */
  protected function updateContributions() {
    $status_pending    = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
    $status_inProgress =  CRM_Sepa_Logic_Settings::contributionInProgressStatusId();

    // get eligible contributions (slightly different queries for OOFF/RCUR)
    if ($this->txgroup['type'] == 'OOFF') {
      $query = CRM_Core_DAO::executeQuery("
        SELECT
          civicrm_sdd_mandate.id                      AS mandate_id,
          civicrm_sdd_mandate.status                  AS mandate_status,
          civicrm_contribution.id                     AS contribution_id,
          civicrm_contribution.contribution_status_id AS contribution_status_id
        FROM civicrm_sdd_contribution_txgroup
        LEFT JOIN civicrm_contribution       ON civicrm_contribution.id = civicrm_sdd_contribution_txgroup.contribution_id
        LEFT JOIN civicrm_sdd_mandate        ON civicrm_sdd_mandate.entity_id = civicrm_contribution.id AND civicrm_sdd_mandate.entity_table = 'civicrm_contribution'
        WHERE civicrm_sdd_contribution_txgroup.txgroup_id = %1
          AND (civicrm_contribution.contribution_status_id = %2 OR civicrm_contribution.contribution_status_id = %3)
          AND (civicrm_contribution.contribution_status_id <> %4)
          LIMIT %5", array(
            1 => array($this->txgroup['id'], 'Integer'),
            2 => array($status_pending, 'Integer'),
            3 => array($status_inProgress, 'Integer'),
            4 => array($this->target_status_id, 'Integer'),
            5 => array(SDD_CLOSE_RUNNER_BATCH_SIZE, 'Integer'),
          ));

    } elseif ($this->txgroup['type'] == 'RCUR' || $this->txgroup['type'] == 'FRST' || $this->txgroup['type'] == 'RTRY') {
      $query = CRM_Core_DAO::executeQuery("
        SELECT
          civicrm_sdd_mandate.id                      AS mandate_id,
          civicrm_sdd_mandate.status                  AS mandate_status,
          civicrm_contribution.id                     AS contribution_id,
          civicrm_contribution.contribution_status_id AS contribution_status_id
        FROM civicrm_sdd_contribution_txgroup
        LEFT JOIN civicrm_contribution       ON civicrm_contribution.id = civicrm_sdd_contribution_txgroup.contribution_id
        LEFT JOIN civicrm_contribution_recur ON civicrm_contribution_recur.id = civicrm_contribution.contribution_recur_id
        LEFT JOIN civicrm_sdd_mandate        ON civicrm_sdd_mandate.entity_id = civicrm_contribution_recur.id AND civicrm_sdd_mandate.entity_table = 'civicrm_contribution_recur'
        WHERE civicrm_sdd_contribution_txgroup.txgroup_id = %1
          AND (civicrm_contribution.contribution_status_id = %2 OR civicrm_contribution.contribution_status_id = %3)
          AND (civicrm_contribution.contribution_status_id <> %4)
          LIMIT %5", array(
            1 => array($this->txgroup['id'], 'Integer'),
            2 => array($status_pending, 'Integer'),
            3 => array($status_inProgress, 'Integer'),
            4 => array($this->target_status_id, 'Integer'),
            5 => array(SDD_CLOSE_RUNNER_BATCH_SIZE, 'Integer')));

    } else {
      throw new Exception("Illegal group type '{$this->txgroup['type']}'", 1);
    }

    // collect the data
    $contributions = array();
    while ($query->fetch()) {
      $contributions[$query->contribution_id] = array(
        'id'                     => $query->contribution_id,
        'mandate_id'             => $query->mandate_id,
        'contribution_status_id' => $query->contribution_status_id,
        'mandate_status'         => $query->mandate_status);
    }

    // if ther's nothing to do, stop right here
    if (empty($contributions)) return;

    // now: first update the contribution status
    $this->updateContributionStatus($contributions);

    // then: update the mandate status
    if ($this->txgroup['type'] == 'OOFF') {
      $this->updateMandateStatus($contributions, 'SENT', 'OOFF');
    } elseif ($this->txgroup['type'] == 'FRST') {
      // TODO: GET $collection_date
      $this->updateMandateStatus($contributions, 'RCUR', 'FRST');
    }

    // also update next collection date
    if ($this->txgroup['type'] == 'FRST' || $this->txgroup['type'] == 'RCUR' || $this->txgroup['type'] == 'RTRY') {
      CRM_Sepa_Logic_NextCollectionDate::advanceNextCollectionDate(NULL, array_keys($contributions));
    }
  }


  /**
   * Update the status of all the given contributions'
   */
  protected function updateMandateStatus($contributions, $new_status, $for_old_status) {
    foreach ($contributions as $contribution) {
      if ($contribution['mandate_status'] == $for_old_status) {
        // the mandate has the required status
        $update = array(
          'id'     => $contribution['mandate_id'],
          'status' => $new_status);
        if ($new_status=='RCUR' && $contribution['mandate_status'] == 'FRST') {
          // in this case we also want to set the contribution as first
          $update['first_contribution_id'] = $contribution['id'];
        }
        civicrm_api3('SepaMandate', 'create', $update);
      }
    }
  }

  /**
   * Update the status of all the given contributions'
   * @deprecated currently unused in favour of updateMandateStatus
   */
  protected function updateMandateStatusSQL($contributions, $new_status, $for_old_status) {
    // generate a mandate_id list
    $mandate_ids = array();
    foreach ($contributions as $contribution) {
      $mandate_ids[] = $contribution['mandate_id'];
    }
    $mandate_id_list = implode(',', $mandate_ids);

    // UPDATE via SQL
    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_sdd_mandate
         SET status = '{$new_status}'
       WHERE status = '{$for_old_status}'
         AND id IN ({$mandate_id_list}");
  }

  /**
   * Update the status of all given contributions to $this->target_status_id
   */
  protected function updateContributionStatus($contributions) {
    $contribution_id_list = implode(',', array_keys($contributions));
    $status_inProgress =  CRM_Sepa_Logic_Settings::contributionInProgressStatusId();
    if (empty($contribution_id_list)) {
      // this would cause SQL errors
      return;
    }
    if ($this->target_status_id == $status_inProgress) {
      // this status cannot be set via the API -> use SQL
      CRM_Core_DAO::executeQuery("UPDATE civicrm_contribution SET contribution_status_id={$status_inProgress} WHERE id IN ({$contribution_id_list});");

    } else { // this should be status 'Completed', but it doesn't really matter
      // first, some sanity checks:
      if (version_compare(CRM_Utils_System::version(), '4.7.0', '>=')) {
        // make sure they're all in status 'In Progress' to avoid SEPA-514
        CRM_Core_DAO::executeQuery("UPDATE civicrm_contribution SET contribution_status_id={$status_inProgress} WHERE id IN ({$contribution_id_list});");
      }

      // then: set them all to the new status
      foreach ($contributions as $contribution) {
        civicrm_api3('Contribution', 'create', array(
            'id'                       => $contribution['id'],
            'contribution_status_id'   => $this->target_status_id,
            'receive_date'             => date('YmdHis', strtotime($this->txgroup['collection_date']))));
      }
    }
  }
}


