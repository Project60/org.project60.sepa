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


/**
 * This class holds all SEPA logic wrt to the next collection date
 *
 * @see https://github.com/Project60/org.project60.sepa/issues/431
 */
class CRM_Sepa_Logic_NextCollectionDate {

  /**
   * Will update the next collection date after a transaction group has been closed
   *
   * the recurring contributions in question can be identified either
   * by $txgroup_id (i.e. the whole group) or as list of individual recurring contributions
   */
  public static function advanceNextCollectionDate($txgroup_id, $contribution_id_list = NULL) {
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
    $info_query = CRM_Core_DAO::executeQuery("
      SELECT
        civicrm_contribution.receive_date    AS receive_date,
        civicrm_contribution_recur.cycle_day AS cycle_day
      FROM civicrm_contribution_recur
      {$joins}
      WHERE {$where} LIMIT 1");
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
    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_contribution_recur
      {$joins}
      SET next_sched_contribution_date = '{$last_collection_date}'
      WHERE {$where}");


    // SECONDLY: advance all values by one period
    $periods = array('month' => 'MONTH', 'year' => 'YEAR'); // TODO: more?
    foreach ($period as $civi_unit => $sql_unit) {
      CRM_Core_DAO::executeQuery("
        UPDATE civicrm_contribution_recur
        {$joins}
        SET next_sched_contribution_date = next_sched_contribution_date + INTERVAL frequency_interval {$sql_unit}
        WHERE {$where}
          AND frequency_interval = '{$civi_unit}'");
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
}
