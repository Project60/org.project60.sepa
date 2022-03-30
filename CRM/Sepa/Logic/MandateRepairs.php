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
    $this->repairFrstPaymentInstruments();
    $this->repairInstallmentPaymentInstruments();
    $this->showSessionStatusNotification();
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
          while ($case->fetch()) {
            $pi_adjustment_counter++;
            $new_pi = $case->first_contribution_id == $case->contribution_id ? $frst_pi_id : $rcur_pi_id;
            civicrm_api3('Contribution', 'create', [
              'id' => $case->contribution_id,
              'payment_instrument_id' => $new_pi,
              'financial_type_id' => $case->financial_type_id, // just to avoid warnings in unit tests
            ]);
            $this->log("Adjusted SEPA contribution [{$case->contribution_id}] payment instrument from [{$case->contribution_pi}] to [{$new_pi}]");
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
      CRM_Core_Session::setStatus($message, E::ts("CiviSEPA Health Check"), 'warn');
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
