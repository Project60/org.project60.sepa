<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2022 SYSTOPIA                            |
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

use CRM_Sepa_ExtensionUtil as E;

/**
 * Queue Item for marking a group as 'Received'
 */
class CRM_Sepa_Logic_Queue_Received extends CRM_Sepa_Logic_Queue_Close {

  /**
   * Use CRM_Queue_Runner to close an SDD group
   * This doesn't return, but redirects to the runner
   */
  public static function launchReceivedRunner($txgroup_ids, $target_group_status, $target_contribution_status) {
    // create a queue
    $queue_name = 'sdd_received_' . implode('_', $txgroup_ids);
    $queue = Civi::queue($queue_name, [
        'type'  => 'Sql',
        'reset' => FALSE,
    ]);

    // fetch the groups
    $txgroup_query = civicrm_api3('SepaTransactionGroup', 'get', [
        'id'           => ['IN' => $txgroup_ids],
        'option.limit' => 0
    ]);

    $group_status_id_busy = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Data Entry');

    foreach ($txgroup_query['values'] as $txgroup) {
      // first: set group status to busy
      $queue->createItem(new CRM_Sepa_Logic_Queue_Received('set_group_status', $txgroup, $group_status_id_busy));

      // count the contributions and create an appropriate amount of items
      $contribution_count = CRM_Core_DAO::singleValueQuery("SELECT COUNT(contribution_id) FROM civicrm_sdd_contribution_txgroup WHERE txgroup_id={$txgroup['id']}");
      $contribution_count += SDD_CLOSE_RUNNER_BATCH_SIZE; // security margin
      for ($offset=0; $offset <= $contribution_count; $offset+=SDD_CLOSE_RUNNER_BATCH_SIZE) {
        $queue->createItem(new CRM_Sepa_Logic_Queue_Received('contribution_received', $txgroup, $target_contribution_status, $offset));
      }

      // finally: render XML and mark the group
      $queue->createItem(new CRM_Sepa_Logic_Queue_Received('set_group_status', $txgroup, $target_group_status));
    }

    // create a runner and launch it
    $runner = new CRM_Queue_Runner([
         'title'     => E::ts("Closing SDD Group(s) [%1]", array(1 => implode(',', $txgroup_ids), 'domain' => 'org.project60.sepa')),
         'queue'     => $queue,
         'errorMode' => CRM_Queue_Runner::ERROR_ABORT,
         'onEndUrl'  => CRM_Utils_System::url('civicrm/sepa/dashboard', 'status=closed'),
     ]);
    $runner->runAllViaWeb(); // does not return
  }

  /**
   * Run this job item
   *
   * @param $context
   * @return bool
   * @throws Exception
   */
  public function run($context)
  {
    switch ($this->mode) {
      case 'contribution_received':
        // this one needs a lock
        $exception = null;
        $lock      = CRM_Sepa_Logic_Settings::getLock();
        if (empty($lock)) {
          throw new Exception("Batching in progress. Please try again later.");
        }
        try {
          $this->markContributionsReceived();
        } catch (Exception $e) {
          $exception = $e; // store and throw later
        }
        $lock->release();
        if ($exception) {
          throw $exception;
        }
        break;

      default:
        return parent::run($context);
    }
    return true;
  }


  /**
   * Will select the next batch of up contributions and set them to 'Completed'
   */
  protected function markContributionsReceived() {
    $status_completed = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');

    // get eligible contributions based on group id, OOFF/RCUR, and other factors
    $query = self::getContributionSelectorQuery($this->txgroup['type'], $this->txgroup['id'], $status_pending, $this->target_status_id, $status_inProgress);

    // collect the data
    $contributions = [];
    while ($query->fetch()) {
      $contributions[$query->contribution_id] = array(
          'id'                     => $query->contribution_id,
          'mandate_id'             => $query->mandate_id,
          'contribution_status_id' => $query->contribution_status_id,
          'mandate_status'         => $query->mandate_status);
    }

    // if there's nothing to do, stop right here
    if (empty($contributions)) return;

    // now: first update the contribution status
    $this->updateContributionStatus($contributions);

    // then: update the mandate status
    if ($this->txgroup['type'] == 'OOFF') {
      $this->updateMandateStatus($contributions, 'SENT', 'OOFF');
    } elseif ($this->txgroup['type'] == 'FRST') {
      $this->updateMandateStatus($contributions, 'RCUR', 'FRST');
    }

    // also update next collection date
    if ($this->txgroup['type'] == 'FRST' || $this->txgroup['type'] == 'RCUR' || $this->txgroup['type'] == 'RTRY') {
      CRM_Sepa_Logic_NextCollectionDate::advanceNextCollectionDate(NULL, array_keys($contributions));
    }
  }
}


