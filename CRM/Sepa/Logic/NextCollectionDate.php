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
  public static function advanceNextCollectionDate($last_collection_date, $txgroup_id, $contribution_id_list = NULL) {
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

    // FIRST: set all to the last collection date, so we can rule out
    //  dropped/skipped instances with older dates
    $last_collection_date = date('YmdHis', strtotime($last_collection_date));
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
