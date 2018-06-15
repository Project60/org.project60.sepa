<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 SYSTOPIA                       |
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
 * Contains logic required for RETRY: attempt to collect failed debits another time
 */
class CRM_Sepa_Logic_Retry {

  /**
   * Calculate the some stats on selected cancelled contributions:
   *  'contribution_count'  - number of contributions matched by the parameters
   *  'total_amount'        - total amount of those contributions
   *  'currency'            - common currency - empty if multiple involved
   *  'contact_count'       - count of contacts involved
   *  'creditor_list'       - list of creditor IDs involved
   *  'txgroup_list '       - list of txgroups involved
   *  'cancel_reason_list'  - list of cancel reasons involved
   *  'frequencies'         - list of frequencies involved
   *
   * @param $params array see SepaLogic.get_retry_stats API call
   */
  public static function getStats($params) {
    $where_clauses = array();
    $where_clauses[] = "contribution.is_test = 0";
    $where_clauses[] = "contact.is_deleted = 0";

    // TODO: add conditions

    $where_clause_sql = "(" . implode(') AND (', $where_clauses) . ')';
    $stats_query_sql = "
    SELECT
      COUNT(contribution.id)                             AS contribution_count,
      SUM(contribution.total_amount)                     AS total_amount,
      GROUP_CONCAT(DISTINCT(contribution.currency))      AS currency,
      COUNT(DISTINCT(contribution.contact_id))           AS contact_count,
      GROUP_CONCAT(DISTINCT(mandate.creditor_id))        AS creditor_list,
      GROUP_CONCAT(DISTINCT(ctxg.txgroup_id))            AS txgroup_list,
      GROUP_CONCAT(DISTINCT(contribution.cancel_reason)) AS cancel_reason_list,
      GROUP_CONCAT(DISTINCT(CONCAT(rcontrib.frequency_interval, rcontrib.frequency_unit))) 
                                                         AS frequencies
    FROM civicrm_contribution contribution
    LEFT JOIN civicrm_contribution_recur   rcontrib  ON rcontrib.id = contribution.contribution_recur_id
    LEFT JOIN civicrm_contact               contact ON contact.id = contribution.contact_id 
    LEFT JOIN civicrm_sdd_contribution_txgroup ctxg ON ctxg.contribution_id = contribution.id 
    LEFT JOIN civicrm_sdd_mandate           mandate ON mandate.entity_id = contribution.contribution_recur_id 
                                                    AND mandate.entity_table = 'civicrm_contribution_recur'
    WHERE {$where_clause_sql};";
    CRM_Core_Error::debug_log_message($stats_query_sql);
    $stats_raw = CRM_Core_DAO::executeQuery($stats_query_sql);
    $stats_raw->fetch();
    $stats = array(
        'contribution_count' => $stats_raw->contribution_count,
        'total_amount'       => $stats_raw->total_amount,
        'contact_count'      => $stats_raw->contact_count,
        'creditor_list'      => $stats_raw->creditor_list,
        'txgroup_list'       => $stats_raw->txgroup_list,
        'frequencies'        => $stats_raw->frequencies
    );
    
    // add currencies
    $currencies = explode(',', $stats_raw->currency);
    if (count($currencies) == 1) {
      $stats['currency'] = $stats_raw->currency;
    } else {
      $stats['currency'] = '';
    }

    // convert frequencies
    $frequencies = explode(',', $stats['frequencies']);
    $stats['frequencies'] = array();
    foreach ($frequencies as $frequency) {
      if (preg_match("#^(?P<interval>[0-9]+)(?P<unit>(month|year))$#", $frequency, $match)) {
        $freq = 1.0;
        if ($match['unit'] == 'month') {
          $freq *= 12.0;
        }
        $freq /= $match['interval'];
        $stats['frequencies'][] = $freq;
      }
    }
    $stats['frequencies'] = implode(',', $stats['frequencies']);

    return $stats;
  }
}
