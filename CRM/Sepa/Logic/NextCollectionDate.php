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


/**
 * This class holds all SEPA logic wrt to the next collection date
 *
 * @see https://github.com/Project60/org.project60.sepa/issues/431
 */
class CRM_Sepa_Logic_NextCollectionDate {

  /** fields for Mandate/RecurringContribution edit/create event processing */
  protected static $currently_edited_mandate_id = NULL;
  protected static $currently_edited_mandate_params = NULL;
  protected static $currently_edited_recurring_contribution_id = NULL;
  protected static $currently_edited_recurring_contribution_params = NULL;

  protected $now;
  protected $creditor_id;

  function __construct($creditor_id = NULL, $mode = 'RCUR') {
    if (empty($creditor_id)) {
      $creditor_id =(int) CRM_Sepa_Logic_Settings::getSetting('batching_default_creditor');
    }
    $grace_period = (int) CRM_Sepa_Logic_Settings::getSetting("batching.{$mode}.grace", $creditor_id);
    $rcur_notice  = (int) CRM_Sepa_Logic_Settings::getSetting("batching.{$mode}.notice", $creditor_id);
    $this->now    = strtotime("+$rcur_notice days -$grace_period days");
    $this->creditor_id = $creditor_id;
  }

  /**
   * check if this NextCollectionDate instance uses the
   * given creditor ID
   */
  public function usesCreditor($creditor_id) {
    return $creditor_id == $this->creditor_id;
  }

  /**
   * update the next scheduled collection date for the SepaMandate
   * identified by either $contribution_recur_id or $mandate_id
   */
  public function updateNextCollectionDate($contribution_recur_id, $mandate_id) {
    $contribution_recur_id = (int) $contribution_recur_id;
    $mandate_id = (int) $mandate_id;

    if (!$contribution_recur_id) {
      if ($mandate_id) {
        $contribution_recur_id = CRM_Core_DAO::singleValueQuery("SELECT entity_id FROM civicrm_sdd_mandate WHERE id = {$mandate_id} AND entity_table='civicrm_contribution_recur'");
      }
    }

    if (!$contribution_recur_id) {
      // error
      error_log("org.project60.sepa: updateNextCollectionDate: couldn't identify recurring contribution.");
      return;
    }

    $next_sched_contribution_date = $this->calculateNextCollectionDate($contribution_recur_id);
    if ($next_sched_contribution_date) {
      CRM_Core_DAO::executeQuery("UPDATE civicrm_contribution_recur SET next_sched_contribution_date = '{$next_sched_contribution_date}' WHERE id = {$contribution_recur_id}");
    } else {
      CRM_Core_DAO::executeQuery("UPDATE civicrm_contribution_recur SET next_sched_contribution_date = NULL WHERE id = {$contribution_recur_id}");
    }
  }

  /**
   * Calculate the next collection date for the given mandate
   */
  public function calculateNextCollectionDate($contribution_recur_id) {
    $contribution_recur_id = (int) $contribution_recur_id;
    if (!$contribution_recur_id) {
      return NULL;
    }

    $query = CRM_Core_DAO::executeQuery("
      SELECT
        civicrm_contribution_recur.cycle_day          AS cycle_day,
        civicrm_contribution_recur.frequency_interval AS frequency_interval,
        civicrm_contribution_recur.frequency_unit     AS frequency_unit,
        civicrm_contribution_recur.start_date         AS start_date,
        first_contribution.receive_date               AS mandate_first_executed,
        civicrm_contribution_recur.end_date           AS end_date,
        civicrm_sdd_mandate.status                    AS status,
        civicrm_contribution_recur.cancel_date        AS cancel_date
      FROM civicrm_contribution_recur
      LEFT JOIN civicrm_sdd_mandate ON civicrm_sdd_mandate.entity_id = civicrm_contribution_recur.id
                                    AND civicrm_sdd_mandate.entity_table = 'civicrm_contribution_recur'
      LEFT JOIN civicrm_contribution AS first_contribution  ON civicrm_sdd_mandate.first_contribution_id = first_contribution.id
      WHERE civicrm_contribution_recur.id = {$contribution_recur_id}");
    if ($query->fetch()) {
      $mode = $query->status;
      $mandate = array(
        'cycle_day'              => $query->cycle_day,
        'frequency_interval'     => $query->frequency_interval,
        'frequency_unit'         => $query->frequency_unit,
        'start_date'             => $query->start_date,
        'mandate_first_executed' => $query->mandate_first_executed,
        'end_date'               => $query->end_date,
        'cancel_date'            => $query->cancel_date,
        );
      return CRM_Sepa_Logic_Batching::getNextExecutionDate($mandate, $this->now, ($mode=='FRST'));
    }
  }

  /**
   * Will update the next collection date after a transaction group has been closed
   *
   * the recurring contributions in question can be identified either
   * by $txgroup_id (i.e. the whole group) or as list of individual recurring contributions
   */
  public static function advanceNextCollectionDate($txgroup_id, $contribution_id_list = NULL) {
    // error_log("ADVANCE $txgroup_id / " . json_encode($contribution_id_list));
    // PREPARE: generate the right identification snippets
    $txgroup_id = (int) $txgroup_id;
    if (!empty($txgroup_id)) {
      $joins = "LEFT JOIN civicrm_contribution ON civicrm_contribution_recur.id = civicrm_contribution.contribution_recur_id
                LEFT JOIN civicrm_sdd_contribution_txgroup ON civicrm_contribution.id = civicrm_sdd_contribution_txgroup.contribution_id";
      $where = "civicrm_sdd_contribution_txgroup.txgroup_id = {$txgroup_id}";
    } elseif (!empty($contribution_id_list)) {
      $joins = "LEFT JOIN civicrm_contribution ON civicrm_contribution_recur.id = civicrm_contribution.contribution_recur_id";
      $where = 'civicrm_contribution.id IN (' . implode(',', $contribution_id_list) . ')';
    } else {
      error_log("org.project60.sepa: advanceNextCollectionDate failed - no identifier given.");
      return;
    }

    // PREPARE: extract theoretical collection day from contribution
    //          (they should all have the same date, but it might be delayed)
    //          FIXME: is there a better way?
    $info_query_sql = "
      SELECT
        civicrm_contribution.receive_date    AS receive_date,
        civicrm_contribution_recur.cycle_day AS cycle_day
      FROM civicrm_contribution_recur
      {$joins}
      WHERE {$where} LIMIT 1";
    // error_log($info_query_sql);
    $info_query = CRM_Core_DAO::executeQuery($info_query_sql);
    if (!$info_query->fetch() || empty($info_query->receive_date) || empty($info_query->cycle_day) || $info_query->cycle_day < 1 || $info_query->cycle_day > 31) {
      // i.e. there's something wrong
      error_log('org.project60.sepa: advanceNextCollectionDate failed - contribution data incomplete');
      return;
    }
    $last_collection_date = strtotime($info_query->receive_date);
    while (date('d', $last_collection_date) != $info_query->cycle_day) {
      $last_collection_date = strtotime("-1 day", $last_collection_date);
    }
    $last_collection_date = date('YmdHis', $last_collection_date);


    // FIRST: set all to the last collection date, so we can rule out
    //  dropped/skipped instances with older dates
    $update_query_sql = "
      UPDATE civicrm_contribution_recur
      {$joins}
      SET next_sched_contribution_date = '{$last_collection_date}'
      WHERE {$where}";
    // error_log($update_query_sql);
    CRM_Core_DAO::executeQuery($update_query_sql);

    // SECONDLY: advance all values by one period
    $periods = array('month' => 'MONTH', 'year' => 'YEAR'); // TODO: more?
    foreach ($periods as $civi_unit => $sql_unit) {
      $advance_query_sql = "
        UPDATE civicrm_contribution_recur
        {$joins}
        SET next_sched_contribution_date = (next_sched_contribution_date + INTERVAL frequency_interval {$sql_unit})
        WHERE {$where}
          AND frequency_unit = '{$civi_unit}'";
      // error_log($advance_query_sql);
      CRM_Core_DAO::executeQuery($advance_query_sql);
    }

    // THIRDLY: clear the next date again if it exceeds the current end date
    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_contribution_recur
      {$joins}
      SET next_sched_contribution_date = NULL
      WHERE {$where}
        AND end_date <= next_sched_contribution_date");

    // DONE
  }


  /**
   * preparation for self::processMandatePostEdit
   */
  public static function processMandatePreEdit($op, $objectName, $id, $params) {
    self::$currently_edited_mandate_id = $id;
    self::$currently_edited_mandate_params = $params;
  }

  /**
   * process SepaMandate edit/create events
   */
  public static function processMandatePostEdit($op, $objectName, $objectId, $objectRef) {
    if (empty(self::$currently_edited_mandate_params)) {
      return;
    }

    $update_required = FALSE;
    if ($op == 'edit') {
      $relevant_changes = array('status', 'is_enabled', 'first_contribution_id', 'type', 'entity_id', 'type', 'creditor_id');
      foreach ($relevant_changes as $critical_attribute) {
        if (array_key_exists($critical_attribute, self::$currently_edited_mandate_params)) {
          $update_required = TRUE;
        }
      }
    } elseif ($op == 'create') {
      self::$currently_edited_mandate_id = $objectId;
      $type = CRM_Utils_Array::value('type', self::$currently_edited_mandate_params);
      $update_required = ($type == 'RCUR');
    }

    if ($update_required && self::$currently_edited_mandate_id) {
      // get creditor_id
      if (!empty(self::$currently_edited_mandate_params['creditor_id'])) {
        $creditor_id = self::$currently_edited_mandate_params['creditor_id'];
      } else {
        $creditor_id = CRM_Core_DAO::singleValueQuery("SELECT creditor_id FROM civicrm_sdd_mandate WHERE id = " . self::$currently_edited_mandate_id);
      }
      $updater = new CRM_Sepa_Logic_NextCollectionDate($creditor_id);
      $updater->updateNextCollectionDate(NULL, self::$currently_edited_mandate_id);
    }

    // just to be safe
    self::$currently_edited_mandate_params = NULL;
    self::$currently_edited_mandate_id = NULL;
  }


  /**
   * preparation for self::processRecurPostEdit
   */
  public static function processRecurPreEdit($op, $objectName, $id, $params) {
    self::$currently_edited_recurring_contribution_id = $id;
    self::$currently_edited_recurring_contribution_params = $params;
  }

  /**
   * process RecurringContribution edit/create events
   */
  public static function processRecurPostEdit($op, $objectName, $objectId, $objectRef) {
    if (empty(self::$currently_edited_recurring_contribution_params)) {
      return;
    }

    $update_required = FALSE;
    if ($op == 'edit') {
      if (empty(self::$currently_edited_recurring_contribution_params['next_sched_contribution_date'])) {
        $type = CRM_Utils_Array::value('type', self::$currently_edited_recurring_contribution_params);

        $relevant_changes = array('frequency_unit', 'frequency_interval', 'start_date', 'end_date', 'cancel_date', 'contribution_status_id', 'cycle_day');
        foreach ($relevant_changes as $critical_attribute) {
          if (array_key_exists($critical_attribute, self::$currently_edited_recurring_contribution_params)) {
            $update_required = TRUE;
          }
        }
      } else {
        // if the date is passed, no need to calculate
      }
    } elseif ($op == 'create') {
      self::$currently_edited_recurring_contribution_id = $objectId;

      // we won't deal with recurring contribution upon creation,
      //  because there won't be a mandate connected to it yet

      // if (empty(self::$currently_edited_recurring_contribution_params['next_sched_contribution_date'])) {
      //   // we want to calculate this for all RCUR mandates:
      //   $type = CRM_Utils_Array::value('type', self::$currently_edited_recurring_contribution_params);
      //   $update_required = ($type == 'RCUR');
      // } else {
      //   // if the date is passed, no need to calculate
      // }
    }

    if ($update_required && self::$currently_edited_recurring_contribution_id) {
      $creditor_id = CRM_Core_DAO::singleValueQuery("SELECT creditor_id FROM civicrm_sdd_mandate WHERE entity_table='civicrm_contribution_recur' AND entity_id = " . self::$currently_edited_recurring_contribution_id);
      $updater = new CRM_Sepa_Logic_NextCollectionDate($creditor_id);
      $updater->updateNextCollectionDate(self::$currently_edited_recurring_contribution_id, NULL);
    }

    // just to be safe
    self::$currently_edited_recurring_contribution_params = NULL;
    self::$currently_edited_recurring_contribution_id = NULL;
  }
}
