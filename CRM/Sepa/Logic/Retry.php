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
   * Generate a new SEPA RTRY group
   *
   * @param $params array see SepaLogic.get_retry_stats API call
   * @return string reference of the newly created group
   * @throws Exception
   */
  public static function createRetryGroup($params) {
    // make sure there is a collection date
    if (empty($params['collection_date'])) {
      // TODO: use notice period
      $collection_date  = date('Y-m-d', strtotime("now +3 days"));
    } else {
      $collection_date = date('Y-m-d', strtotime($params['collection_date']));
    }

    // first: get some values
    $group_status_id_open        = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Open');
    $payment_instrument_id       = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', 'RCUR');
    $contribution_status_pending = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');

    // first: fetch contributions and group by creditor
    $contributions_by_creditor = array();
    $contributions_found = self::getRetryContributions($params);
    foreach ($contributions_found as $contribution) {
      $contributions_by_creditor[$contribution['creditor_id']][] = $contribution;
    }

    // create RTRY group(s)
    foreach ($contributions_by_creditor as $creditor_id => $contributions) {
      // get some info
      $notice = (int) CRM_Sepa_Logic_Settings::getSetting("batching.RCUR.notice", $creditor_id);

      // create new group's reference
      $reference = "TXG-{$creditor_id}-RTRY-{$collection_date}";
      $counter = 0;
      while (CRM_Sepa_Logic_Batching::referenceExists($reference)) {
        $counter += 1;
        $reference = "TXG-{$creditor_id}-RTRY-{$collection_date}--" . $counter;
      }
      CRM_Utils_SepaCustomisationHooks::modify_txgroup_reference($reference, $creditor_id, 'RTRY', $collection_date);

      // create new group
      $group = civicrm_api3('SepaTransactionGroup', 'create', array(
          'reference'               => $reference,
          'type'                    => 'RTRY',
          'collection_date'         => $collection_date,
          'latest_submission_date'  => date('Y-m-d', strtotime("-{$notice} days", strtotime($collection_date))),
          'created_date'            => date('Y-m-d'),
          'status_id'               => $group_status_id_open,
          'sdd_creditor_id'         => $creditor_id,
      ));

      // create contributions
      foreach ($contributions as $contribution_data) {
        unset($contribution_data['id']);
        $contribution_data['payment_instrument']     = $payment_instrument_id;
        $contribution_data['contribution_status_id'] = $contribution_status_pending;
        $contribution_data['receive_date']           = $collection_date;
        $contribution = civicrm_api3('Contribution', 'create', $contribution_data);

        // add contribution to tx_group
        CRM_Core_DAO::executeQuery("INSERT INTO civicrm_sdd_contribution_txgroup (txgroup_id, contribution_id) VALUES (%1, %2);",
            array( 1 => array($group['id'],        'Integer'),
                   2 => array($contribution['id'], 'Integer')));

        // finally: call our installment_created Hook
        CRM_Utils_SepaCustomisationHooks::installment_created($contribution_data['mandate_id'], $contribution_data['contribution_recur_id'], $contribution['id']);
      }
    }

    if (isset($group) && is_array($group)) {
      // Create a transaction message as a CiviCRM note:
      if ((array_key_exists('transaction_message', $params)) && !empty($params['transaction_message'])) {
        self::createOrUpdateNote($group['id'], 'transaction_message', $params['transaction_message']);
      }

      // Create a transaction note as a CiviCRM note:
      if ((array_key_exists('transaction_note', $params)) && !empty($params['transaction_note'])) {
        self::createOrUpdateNote($group['id'], 'transaction_note', $params['transaction_note']);
      }
    }

    return $group['id'];
  }

  /**
   * @param int|string $groupId The ID of the transaction group associated with the note.
   * @param string $subject The subject for the note.
   * @param string $text The text content of the note.
   */
  private static function createOrUpdateNote($groupId, $subject, $text)
  {
    // NOTE: We cannot use the API here as it seems to only allow a fixed set of values for entity_table.

    CRM_Core_DAO::executeQuery(
      "DELETE FROM
        civicrm_note
      WHERE
        entity_table = 'civicrm_sdd_txgroup'
        AND entity_id = %1
        AND `subject` = %2",
      [
        1 => [(int)$groupId, 'Integer'],
        2 => [$subject, 'String'],
      ]
    );
    // TODO: Is simply deleting any existing notes a good practice or should we check if it exists and then update?

    CRM_Core_DAO::executeQuery(
      "INSERT INTO
        civicrm_note (entity_table, entity_id, `subject`, note)
      VALUES
        ('civicrm_sdd_txgroup', %1, %2, %3)",
      [
        1 => [(int)$groupId, 'Integer'],
        2 => [$subject, 'String'],
        3 => [$text, 'String'],
      ]
    );
  }

  /**
   * Get the list of contributions to retry:
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
   *
   * @return array contribution_id => contribution_data
   * @throws Exception
   */
  public static function getRetryContributions($params) {
    $contribution_query_sql = self::getQuery("
      contribution.id                     AS contribution_id,
      contribution.total_amount           AS total_amount,
      contribution.currency               AS currency,
      contribution.contact_id             AS contact_id,
      mandate.creditor_id                 AS creditor_id,
      mandate.id                          AS mandate_id,
      mandate.source                      AS source,
      contribution.contribution_recur_id  AS contribution_recur_id,
      contribution.contribution_status_id AS contribution_status_id,
      contribution.financial_type_id      AS financial_type_id,
      contribution.campaign_id            AS campaign_id
      ", $params);
    $contributions_raw = CRM_Core_DAO::executeQuery($contribution_query_sql);
    $contributions = array();
    while ($contributions_raw->fetch()) {
      $contributions[] = array(
          'id'                    => (int) $contributions_raw->contribution_id,
          'total_amount'          => $contributions_raw->total_amount,
          'currency'              => $contributions_raw->currency,
          'contact_id'            => $contributions_raw->contact_id,
          'source'                => $contributions_raw->source,
          'creditor_id'           => $contributions_raw->creditor_id,
          'mandate_id'            => $contributions_raw->mandate_id,
          'financial_type_id'     => $contributions_raw->financial_type_id,
          'contribution_recur_id' => $contributions_raw->contribution_recur_id,
          'campaign_id'           => $contributions_raw->campaign_id,
      );
    }

    return $contributions;
  }

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
    $stats_query_sql = self::getQuery("
      COUNT(contribution.id)                             AS contribution_count,
      SUM(contribution.total_amount)                     AS total_amount,
      GROUP_CONCAT(DISTINCT(contribution.currency))      AS currency,
      COUNT(DISTINCT(contribution.contact_id))           AS contact_count,
      GROUP_CONCAT(DISTINCT(mandate.creditor_id))        AS creditor_list,
      GROUP_CONCAT(DISTINCT(ctxg.txgroup_id))            AS txgroup_list,
      GROUP_CONCAT(DISTINCT(contribution.cancel_reason)) AS cancel_reason_list,
      GROUP_CONCAT(DISTINCT(CONCAT(rcontrib.frequency_interval, rcontrib.frequency_unit)))
                                                         AS frequencies", $params);
    $stats_raw = CRM_Core_DAO::executeQuery($stats_query_sql);
    $stats_raw->fetch();
    $stats = array(
        'contribution_count' => (int) $stats_raw->contribution_count,
        'total_amount'       => $stats_raw->total_amount,
        'contact_count'      => (int) $stats_raw->contact_count,
        'creditor_list'      => $stats_raw->creditor_list,
        'txgroup_list'       => $stats_raw->txgroup_list,
        'cancel_reason_list' => $stats_raw->cancel_reason_list,
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

    // copy query ID
    if (isset($params['query_id'])) {
      $stats['query_id'] = $params['query_id'];
    }

    return $stats;
  }

  /**
   * Generate a SQL selection query for the
   * @param $select_clause     string  SQL select clause
   * @param $params            array   query parameters
   */
  protected static function getQuery($select_clause, $params) {
    // get some IDs
    $group_status_id_closed   = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Closed');
    $group_status_id_received = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Received');

    // then: some general conditions
    $where_clauses = array();
    $where_clauses[] = "contribution.is_test = 0";
    $where_clauses[] = "contact.is_deleted = 0";
    $where_clauses[] = "txg.status_id IN ({$group_status_id_closed},{$group_status_id_received})";
    $where_clauses[] = "mandate.type = 'RCUR'";
    $where_clauses[] = "mandate.status IN ('RCUR', 'FRST')";

    // CONDITION: contribution_status_id
    if (empty($params['contribution_status_list'])) {
      $where_clauses[] = "contribution.contribution_status_id IN (3,4,7)";
    } else {
      $contribution_status_ids = array_map('intval', $params['contribution_status_list']);
      $contribution_status_list = implode(',', $contribution_status_ids);
      $where_clauses[] = "contribution.contribution_status_id IN ({$contribution_status_list})";
    }

    // CONDITION: date_from
    if (!empty($params['date_from'])) {
      $date_from = strtotime($params['date_from']);
      if ($date_from) {
        $where_clauses[] = "DATE(txg.collection_date) >= DATE('" . date('Y-m-d', $date_from) . "')";
      } else {
        throw new Exception("Cannot parse date '{$params['date_from']}'");
      }
    }

    // CONDITION: date_to
    if (!empty($params['date_to'])) {
      $date_to = strtotime($params['date_to']);
      if ($date_to) {
        $where_clauses[] = "DATE(txg.collection_date) <= DATE('" . date('Y-m-d', $date_to) . "')";
      } else {
        throw new Exception("Cannot parse date '{$params['date_to']}'");
      }
    }

    // CONDITION: amount min
    if (isset($params['amount_min']) && $params['amount_min'] != '') {
      $amount_min = (float) $params['amount_min'];
      $where_clauses[] = "contribution.total_amount >= {$amount_min}";
    }

    // CONDITION: amount max
    if (isset($params['amount_max']) && $params['amount_max'] != '') {
      $amount_max = (float) $params['amount_max'];
      $where_clauses[] = "contribution.total_amount <= {$amount_max}";
    }

    // CONDITION: creditor_list
    if (!empty($params['creditor_list'])) {
      $creditor_list = self::getIDList($params['creditor_list'], TRUE);
      if (!empty($creditor_list)) {
        $where_clauses[] = "mandate.creditor_id IN ({$creditor_list})";
      }
    }

    // CONDITION: txgroup_list
    if (!empty($params['txgroup_list'])) {
      $txgroup_list = self::getIDList($params['txgroup_list'], TRUE);
      if (!empty($txgroup_list)) {
        $where_clauses[] = "txg.id IN ({$txgroup_list})";
      }
    }

    // CONDITION: cancel_reason_list
    if (!empty($params['cancel_reason_list'])) {
      $cancel_reason_list = $params['cancel_reason_list'];
      if (!is_array($cancel_reason_list)) {
        $cancel_reason_list = explode(',', $cancel_reason_list);
      }
      $where_clauses[] = "contribution.cancel_reason IN (" . CRM_Core_DAO::escapeStrings($cancel_reason_list) . ")";
    }

    // CONDITION: frequencies
    if (!empty($params['frequencies'])) {
      $frequency_clauses = array();
      $frequencies = self::getIDList($params['frequencies'], FALSE);
      foreach ($frequencies as $frequency) {
        if ($frequency == 1) {
          $frequency_clauses[] = "(rcontrib.frequency_interval = 12 && rcontrib.frequency_unit = 'month')";
          $frequency_clauses[] = "(rcontrib.frequency_interval = 1  && rcontrib.frequency_unit = 'year')";
        } else {
          $interval = 12 / $frequency;
          $frequency_clauses[] = "(rcontrib.frequency_interval = {$interval} && rcontrib.frequency_unit = 'month')";
        }
      }
      $where_clauses[] = '((' . implode(') OR (', $frequency_clauses) . '))';
    }

    // finally: compile the query SQL:
    $where_clause_sql = "(" . implode(') AND (', $where_clauses) . ')';
    $stats_query_sql = "
    SELECT {$select_clause}
    FROM civicrm_contribution contribution
    LEFT JOIN civicrm_contribution_recur   rcontrib ON rcontrib.id = contribution.contribution_recur_id
    LEFT JOIN civicrm_contact               contact ON contact.id = contribution.contact_id
    LEFT JOIN civicrm_sdd_contribution_txgroup ctxg ON ctxg.contribution_id = contribution.id
    LEFT JOIN civicrm_sdd_txgroup               txg ON txg.id = ctxg.txgroup_id
    LEFT JOIN civicrm_sdd_mandate           mandate ON mandate.entity_id = contribution.contribution_recur_id
                                                    AND mandate.entity_table = 'civicrm_contribution_recur'
    WHERE {$where_clause_sql}";
    return $stats_query_sql;
  }

  /**
   * Takes a comma-separated string and extracts
   * an integer array
   *
   * @param $string
   */
  protected static function getIDList($elements, $as_string = FALSE) {
    $result = array();
    if (!is_array($elements)) {
      $elements = explode(',', $elements);
    }
    foreach ($elements as $element) {
      $result[] = (int) $element;
    }
    if ($as_string) {
      return implode(',', $result);
    } else {
      return $result;
    }
  }
}
