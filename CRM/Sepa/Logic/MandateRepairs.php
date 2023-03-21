<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2022                                     |
| Author: B. Endres (endres@systopia.de)                 |
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
 * This class provides a repair mechanism to fix typical mandate issues,
 *   and logs any applied changes to a log file
 */
class CRM_Sepa_Logic_MandateRepairs {

  /** @var string log file path, will be filled on demand */
  protected $log_file = null;

  /** @var bool should session status information be generated for the user to see? */
  protected $generate_ui_notifications = false;

  /** @var array collected user notifications to be shown in the end */
  protected $ui_notifications = [];

  /** @var string SQL expression to select the mandates to be examined */
  protected $mandate_selector = null;

  /**
   * Create a new instance of this runner.
   *
   * @param string $mandate_selector
   *   SQL expression to select the mandates
   */
  public function __construct($mandate_selector)
  {
    $this->mandate_selector = $mandate_selector;
  }

  /**
   * Add a UI notification line to be shown to the user in the end
   * @param string $message
   */
  public function addUINotification($message)
  {
    if ($this->generate_ui_notifications) {
      $this->ui_notifications[] = $message;
    }
  }

  /**
   * Log a single message to the log file
   *
   * @param string|array $messages
   *
   */
  public function log($messages)
  {
    // make sure the log file name is there
    if (!$this->log_file) {
      $log_folder = Civi::paths()->getPath('[civicrm.files]/ConfigAndLog');
      $this->log_file = $log_folder . DIRECTORY_SEPARATOR . 'CiviSEPA_repairs.log';
    }

    // make sure the messages are an array
    if (!is_array($messages)) {
      $messages = [$messages];
    };

    // using fopen here allows us to avoid issues with parallel logging.
    $log_stream = fopen($this->log_file, 'aw');
    $prefix = date('[Y-m-d H:i:s] ');
    foreach ($messages as $message) {
      fputs($log_stream, $prefix);
      fputs($log_stream, $message);
      fputs($log_stream, "\n");
    }
    fclose($log_stream);
  }

  /**
   * Run all safe repairs
   */
  public function runAllRepairs()
  {
    $this->detectOrphanedInProgressContributions();
    $this->repairOpenGroupContributionStatus();
    $this->repairFrstPaymentInstruments();
    $this->repairInstallmentPaymentInstruments();

    // detect/repair
    //$this->detectOrphanedPendingContributions();

    $this->showSessionStatusNotification();
  }


  /**
   * This process will identify all Pending CiviSEPA contributions
   *  that are not part of a SEPA transaction group which most likely means,
   *  that the group has been deleted without them. They clog up the system
   *
   * @see https://github.com/Project60/org.project60.sepa/issues/629
   *
   * @todo currently don't do that - they don't cause any harm (afaik), and they might simply get re-assigned to a group
   *
   * @return void
   */
  protected function detectOrphanedPendingContributions()
  {
    static $already_run = false; // run this one only once per process, since it doesn't refer to any mandates
    if (!$already_run) {
      $contribution_status_pending = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
      $orphaned_pending_contribution_ids = $this->getOrphanedContributions($contribution_status_pending);
      if ($orphaned_pending_contribution_ids) {
        $this->log("Orphaned pending contributions detected: " . implode(',', $orphaned_pending_contribution_ids));
        $this->addUINotification(E::ts("%1 orphaned open (pending) SEPA contributions were found in the system, i.e. they are not part of a SEPA transaction group, and will not be collected any more. You should delete them by searching for contributions in status 'Pending' with payment instruments RCUR and FRST.",
          [1 => count($orphaned_pending_contribution_ids)]));
      }
    }
    $already_run = true;
  }

  /**
   * This process will identify all In Progress CiviSEPA contributions
   *  that are not part of a SEPA transaction group which most likely means,
   *  that the group has been deleted without them.
   * They may cause serious ramifications with the collection system
   *
   * @see https://github.com/Project60/org.project60.sepa/issues/629
   *
   * @return void
   */
  protected function detectOrphanedInProgressContributions()
  {
    static $already_run = false; // run this one only once per process, since it doesn't refer to any mandates
    if (!$already_run) {
      $contribution_status_in_progress =  CRM_Sepa_Logic_Settings::contributionInProgressStatusId();
      $orphaned_in_progress_contribution_ids = $this->getOrphanedContributions($contribution_status_in_progress);
      if ($orphaned_in_progress_contribution_ids) {
        $this->log("WARNING: Orphaned contributions in status 'In Progress' detected: " . implode(',', $orphaned_in_progress_contribution_ids));
        $this->addUINotification(E::ts("WARNING: %1 orphaned active (in Progress) SEPA contributions detected. These may cause irregularities in the generation of the SEPA collection groups, and in particular might cause the same installment to be collected multiple times. You should find them by searching for contributions in status 'in Progress' with the SEPA payment instruments (e.g. RCUR and FRST), and then export (to be safe) and delete them.",
          [1 => count($orphaned_in_progress_contribution_ids)]));
      }
    }
    $already_run = true;
  }


  /**
   * Contribution is open transaction groups should be in status 'Pending'. However,
   *  if the corresponding recurring contributions are (wrongly) 'in Progress',
   *  the generated contributions are as well. This repair task tries to fix that
   *
   * @see https://github.com/Project60/org.project60.sepa/issues/629
   *
   * @return void
   */
  protected function repairOpenGroupContributionStatus()
  {
    // get the status IDs
    $contribution_status_pending = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
    $contribution_status_in_progress =  CRM_Sepa_Logic_Settings::contributionInProgressStatusId();
    $batch_status_open = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Open');

    // run the search for contributions in the wrong status
    CRM_Core_DAO::disableFullGroupByMode();
    $case = CRM_Core_DAO::executeQuery("
        SELECT
               open_contribution.id                     AS contribution_id,
               open_contribution.contribution_status_id AS contribution_status_id
        FROM civicrm_sdd_txgroup txgroup
        LEFT JOIN civicrm_sdd_contribution_txgroup txgroup_contribution
               ON txgroup.id = txgroup_contribution.txgroup_id
        LEFT JOIN civicrm_contribution open_contribution
               ON open_contribution.id = txgroup_contribution.contribution_id
        WHERE txgroup.status_id = %1
          AND open_contribution.contribution_status_id = %2",
        [
            1 => [$batch_status_open, 'Integer'],
            2 => [$contribution_status_in_progress, 'Integer'],
        ]
    );
    CRM_Core_DAO::reenableFullGroupByMode();

    // apply changes
    $status_adjustment_counter = 0;
    while ($case->fetch()) {
      $status_adjustment_counter++;
      /* sadly, can't do this via API
      civicrm_api3('Contribution', 'create', [
          'id' => $case->contribution_id,
          'contribution_status_id' => $contribution_status_pending
      ]);*/
      CRM_Core_DAO::executeQuery("
         UPDATE civicrm_contribution SET contribution_status_id = %1 WHERE id = %2",
         [
           1 => [$contribution_status_pending, 'Integer'],
           2 => [$case->contribution_id, 'Integer'],
         ]);
      $this->log("Adjusted status of SEPA contribution [{$case->contribution_id}] from [{$case->contribution_status_id}] to status 'Pending'.");
    }
    if ($status_adjustment_counter) {
      $this->addUINotification(E::ts("Warning: had to adjusted the status of %1 contribution(s) to 'Pending', as they are part of an open transaction group.", [1 => $status_adjustment_counter]));
    }
  }


  /**
   * This goes back to an issue with faulty mandates, probably because of some bad payment processors.
   * The issue is, that for combined payment instruments that indicate the first contribution with
   *  a different payment instrument (e.g. FRST-RCUR):
   *   - the recurring contributions should have PI RCUR
   *   - the follow-up contributions should have PI RCUR
   *
   * @see https://github.com/Project60/org.project60.sepa/issues/629
   *
   * @return void
   */
  protected function repairFrstPaymentInstruments()
  {
    $creditors = CRM_Sepa_Logic_PaymentInstruments::getAllSddCreditors();
    $pi_adjustment_counter = 0;
    foreach ($creditors as $creditor) {
      $mapping = CRM_Sepa_Logic_PaymentInstruments::getFrst2RcurMapping($creditor['id']);
      if (count($mapping) > 1) {
        $this->log("repairFrstPaymentInstruments doesn't work with multiple mappings (creditor [{$creditor['id']}]");
      } else {
        foreach ($mapping as $frst_pi_id => $rcur_pi_id) {
          // fix the recurring contributions
          CRM_Core_DAO::disableFullGroupByMode();
          $case = CRM_Core_DAO::executeQuery(
            "
            SELECT rcur.id AS rcur_id,
                   rcur.payment_instrument_id AS rcur_pi
            FROM civicrm_sdd_mandate AS mandate
            LEFT JOIN civicrm_contribution_recur rcur
                   ON mandate.entity_id = rcur.id
            WHERE mandate.creditor_id = %1
              AND rcur.payment_instrument_id <> %2
              AND {$this->mandate_selector}",
            [
              1 => [$creditor['id'], 'Integer'],
              2 => [$rcur_pi_id, 'Integer'],
            ]
          );
          CRM_Core_DAO::reenableFullGroupByMode();

          while ($case->fetch()) {
            $pi_adjustment_counter++;
            civicrm_api3('ContributionRecur', 'create', [
              'id' => $case->rcur_id,
              'payment_instrument_id' => $rcur_pi_id
            ]);
            $this->log("Adjusted SEPA recurring contribution [{$case->rcur_id}] payment instrument from [{$case->rcur_pi}] to [{$rcur_pi_id}]");
          }
        }
      }
    }

    if ($pi_adjustment_counter) {
      $this->addUINotification(E::ts("Adjusted the payment instruments of %1 recurring mandate(s).", [1 => $pi_adjustment_counter]));
    }
  }

  /**
   * This goes back to an issue with faulty mandates, probably because of some bad payment processors.
   * The issue is, that for combined payment instruments that indicate the first contribution with
   *  a different payment instrument (e.g. FRST-RCUR):
   *   - the recurring contributions should have PI RCUR
   *   - the follow-up contributions should have PI RCUR
   *
   * @see https://github.com/Project60/org.project60.sepa/issues/629
   *
   * @return void
   */
  protected function repairInstallmentPaymentInstruments()
  {
    $creditors = CRM_Sepa_Logic_PaymentInstruments::getAllSddCreditors();
    $pi_adjustment_counter = 0;
    foreach ($creditors as $creditor) {
      $mapping = CRM_Sepa_Logic_PaymentInstruments::getFrst2RcurMapping($creditor['id']);
      if (count($mapping) > 1) {
        $this->log("repairInstallmentPaymentInstruments doesn't work with multiple mappings (creditor [{$creditor['id']}]");
      } else {
        foreach ($mapping as $frst_pi_id => $rcur_pi_id) {
          // fix the recurring contributions
          CRM_Core_DAO::disableFullGroupByMode();
          $case = CRM_Core_DAO::executeQuery(
            "
            SELECT contribution.id                    AS contribution_id,
                   contribution.payment_instrument_id AS contribution_pi,
                   contribution.financial_type_id     AS financial_type_id,
                   mandate.first_contribution_id      AS first_contribution_id
            FROM civicrm_sdd_mandate AS mandate
            LEFT JOIN civicrm_contribution_recur rcur
                   ON mandate.entity_id = rcur.id
            LEFT JOIN civicrm_contribution contribution
                   ON contribution.contribution_recur_id = rcur.id
            WHERE mandate.creditor_id = %1
              AND ( (mandate.first_contribution_id = contribution.id AND contribution.payment_instrument_id <> %2)
                        OR
                    (mandate.first_contribution_id <> contribution.id AND contribution.payment_instrument_id <> %3)
                  )
              AND {$this->mandate_selector}",
            [
              1 => [$creditor['id'], 'Integer'],
              2 => [$frst_pi_id, 'Integer'],
              3 => [$rcur_pi_id, 'Integer'],
            ]
          );
          CRM_Core_DAO::reenableFullGroupByMode();

          while ($case->fetch()) {
            $pi_adjustment_counter++;
            $new_pi = $case->first_contribution_id == $case->contribution_id ? $frst_pi_id : $rcur_pi_id;
            try {
              civicrm_api3('Contribution', 'create', [
                'id' => $case->contribution_id,
                'payment_instrument_id' => $new_pi,
                'financial_type_id' => $case->financial_type_id, // just to avoid warnings in unit tests
              ]);
              $this->log("Adjusted SEPA contribution [{$case->contribution_id}] payment instrument from [{$case->contribution_pi}] to [{$new_pi}]");
            } catch (CiviCRM_API3_Exception $ex) {
              // this is probably an issue with interference with other processes, but we HAVE to fix this:
              CRM_Core_DAO::executeQuery("UPDATE civicrm_contribution SET payment_instrument_id = %1 WHERE id = %2",
                [
                  1 => [$new_pi, 'Integer'],
                  2 => [$case->contribution_id, 'Integer'],
              ]);
              $this->log("Adjusted SEPA contribution [{$case->contribution_id}] payment instrument from [{$case->contribution_pi}] to [{$new_pi}] via SQL.");
            }
          }
        }
      }
    }

    if ($pi_adjustment_counter) {
      $this->addUINotification(E::ts("Adjusted the payment instruments of %1 recurring mandate(s).", [1 => $pi_adjustment_counter]));
    }
  }


  /**
   * Generate a user session status note with all the collected user notifications
   *
   * @return void
   */
  public function showSessionStatusNotification()
  {
    if ($this->ui_notifications) {
      // render message
      $message = E::ts("The following irregularities have been detected and fixed in your database:");
      $message.= "<ul>";
      foreach ($this->ui_notifications as $notification) {
        $message.= "<li>" . $notification . "</li>";
      }
      $message.= "</ul>";

      // add footer
      $message.= "<div>";
      $message.= E::ts("You can find a detailed log of the changes here: <code>%1</code>", [1 => $this->log_file]);
      $message.= "</div>";

      // set status
      CRM_Core_Session::setStatus($message, E::ts("CiviSEPA Health Check"), 'warn', ['expires' => 0]);
    }
  }

  /**
   * Identify all 'orphaned' contributions by contribution status,
   *  i.e. SEPA contributions that are not part of a sepa transaction group
   *
   * @param integer $contribution_status_id
   *   contribution status ID
   *
   * @return array
   *   list of contribution IDs
   */
  protected function getOrphanedContributions($contribution_status_id)
  {
    // get recurring payment instruments
    $rcur_pis = [];
    $creditors = CRM_Sepa_Logic_PaymentInstruments::getAllSddCreditors();
    foreach ($creditors as $creditor) {
      $mapping = CRM_Sepa_Logic_PaymentInstruments::getFrst2RcurMapping($creditor['id']);
      foreach ($mapping as $frst_pi_id => $rcur_pi_id) {
        $rcur_pis[] = $frst_pi_id;
        $rcur_pis[] = $rcur_pi_id;
      }
    }

    // run the query
    CRM_Core_DAO::disableFullGroupByMode();
    $case = CRM_Core_DAO::executeQuery("
        SELECT contribution.id AS contribution_id
        FROM civicrm_contribution contribution
        LEFT JOIN civicrm_sdd_contribution_txgroup txgroup_contribution
               ON txgroup_contribution.contribution_id = contribution.id
        WHERE txgroup_contribution.id IS NULL
          AND contribution.contribution_status_id = %1
          AND contribution.payment_instrument_id IN (%2)
          ",
      [
        1 => [$contribution_status_id, 'Integer'],
        2 => [implode(',', $rcur_pis), 'CommaSeparatedIntegers'],
      ]
    );
    CRM_Core_DAO::reenableFullGroupByMode();

    // collect all ids
    $contribution_ids = [];
    while ($case->fetch()) {
      $contribution_ids[] = (int) $case->contribution_id;
    }
    return $contribution_ids;
  }

  /**
   * Run all mandate repairs for the given IDs
   *
   * @param array $mandate_ids
   *    list of the mandate IDs to be used
   *
   * @return void
   */
  public static function runWithMandateIDs($mandate_ids, $ui_notifications = false)
  {
    if (!empty($mandate_ids)) {
      // generate the selector
      $mandate_id_string = implode(',', array_map('intval', $mandate_ids));
      $mandate_repairs = new CRM_Sepa_Logic_MandateRepairs("mandate.id IN ({$mandate_id_string})");
      $mandate_repairs->generate_ui_notifications = $ui_notifications;
      $mandate_repairs->runAllRepairs();
    }
  }

  /**
   * Run all mandate repairs for the given IDs
   *
   * @param array $mandate_ids
   *    list of the mandate IDs to be used
   *
   * @return void
   */
  public static function runWithMandateSelector($mandate_selector, $ui_notifications = false)
  {
    $mandate_repairs = new CRM_Sepa_Logic_MandateRepairs($mandate_selector);
    $mandate_repairs->generate_ui_notifications = $ui_notifications;
    $mandate_repairs->runAllRepairs();
  }
}
