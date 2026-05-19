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
 * Provides the SYSTOPIA alternative batching algorithm
 *  and other workflow support functions
 *
 * @package CiviCRM_SEPA
 *
 */

/**
 * Get stats to support the retry collection form
 *
 * @param array<string, mixed> $params
 *
 * @return array<string, mixed>
 */
function civicrm_api3_sepa_logic_get_retry_stats(array $params): array {
  $stats = CRM_Sepa_Logic_Retry::getStats($params);
  $dao = NULL;
  return civicrm_api3_create_success(
    $stats['contribution_count'],
    $params,
    'SepaLogic',
    'get_retry_stats',
    $dao,
    $stats
  );
}

/**
 * Get stats to support the retry collection form
 *
 * @param array<string, array<string, mixed>> $params
 */
function _civicrm_api3_sepa_logic_get_retry_stats_spec(array &$params): void {
  // CONTACT BASE
  $params['date_from'] = [
    'name'         => 'date_from',
    'api.required' => 1,
    'title'        => 'Date Range From',
    'description'  => 'Start of the date range',
  ];
  $params['date_to'] = [
    'name'         => 'date_to',
    'api.required' => 1,
    'title'        => 'Date Range To',
    'description'  => 'End of the date range',
  ];
  $params['creditor_list'] = [
    'name'         => 'creditor_list',
    'api.required' => 0,
    'title'        => 'Creditor IDs',
    'description'  => 'List of creditor IDs. Default is: all',
  ];
  $params['txgroup_list'] = [
    'name'         => 'txgroup_list',
    'api.required' => 0,
    'title'        => 'TxGroup IDs',
    'description'  => 'List SDD transaction group IDs. Default is: all',
  ];
  $params['cancel_reason_list'] = [
    'name'         => 'cancel_reason_list',
    'api.required' => 0,
    'title'        => 'Cancel Reasons',
    'description'  => 'List of cancel reasons. Default is: all',
  ];
  $params['frequencies'] = [
    'name'         => 'frequencies',
    'api.required' => 0,
    'title'        => 'Frequency List',
    'description'  => 'List of frequencies (collections per year). Default is: all',
  ];
  $params['amount_min'] = [
    'name'         => 'amount_min',
    'api.required' => 0,
    'title'        => 'Amount Minimum',
    'description'  => 'Minimal collection amount. Default is: all',
  ];
  $params['amount_max'] = [
    'name'         => 'amount_max',
    'api.required' => 0,
    'title'        => 'Amount Maximum',
    'description'  => 'Maximal collection amount. Default is: all',
  ];
}

/**
 * This function will close a transaction group,
 * and perform the necessary logical changes to the mandates contained
 *
 * @param array{txgroup_id: int|numeric-string} $params
 *
 * @return array<string, mixed>
 */
function civicrm_api3_sepa_logic_close(array $params): array {
  if (!is_numeric($params['txgroup_id'])) {
    return civicrm_api3_create_error('Required field txgroup_id was not properly set.');
  }

  $error_message = CRM_Sepa_Logic_Group::close((int) $params['txgroup_id']);
  if (empty($error_message)) {
    return civicrm_api3_create_success();
  }
  else {
    return civicrm_api3_create_error($error_message);
  }
}

/**
 * @param array<string, array<string, mixed>> $params
 */
function _civicrm_api3_sepa_logic_close_spec(array &$params): void {
  $params['txgroup_id']['api.required'] = 1;
}

/**
 *
 * This method will create the SDD file for the given group
 *
 * @param array{txgroup_id: int|numeric-string, override: bool|scalar} $params
 *   txgroup_id: the transaction group for which the file should be created.
 *   override: if true, will override an already existing file and create a new one.
 *
 * @return array<string, mixed>
 */
function civicrm_api3_sepa_logic_createxml(array $params): array {
  $override = (bool) ($params['override'] ?? FALSE);

  $result = CRM_Sepa_BAO_SEPATransactionGroup::createFile((int) $params['txgroup_id'], $override);
  if (is_numeric($result)) {
    // this was successful -> load the sepa file
    /** @var array<string, mixed> */
    return civicrm_api3('SepaSddFile', 'getsingle', ['id' => $result]);
  }
  else {
    // there was an error:
    return civicrm_api3_create_error($result);
  }
}

/**
 * @param array<string, array<string, mixed>> $params
 */
function civicrm_api3_sepa_logic_createxml_spec(array &$params): void {
  $params['txgroup_id']['api.required'] = 1;
}

/**
 * API CALL TO MARK TXGROUPs AS 'RECEIVED':
 *    - set txgroup status to 'received'
 *    - change status from 'In Progress' to 'Completed' for all contributions
 *    - (store/update the bank account information)
 *
 * @param array{txgroup_id: int|numeric-string} $params
 *
 * @return array<string, mixed>
 */
function civicrm_api3_sepa_logic_received(array $params): array {
  if (!is_numeric($params['txgroup_id'])) {
    return civicrm_api3_create_error('Required field txgroup_id was not properly set.');
  }

  $error = CRM_Sepa_Logic_Group::received((int) $params['txgroup_id']);
  if (empty($error)) {
    return civicrm_api3_create_success();
  }
  else {
    return civicrm_api3_create_error($error);
  }
}

/**
 * @param array<string, array<string, mixed>> $params
 */
function _civicrm_api3_sepa_logic_received_spec(array &$params): void {
  $params['txgroup_id']['api.required'] = 1;
}

/**
 * API CALL TO CLOSE MANDATES THAT ENDED
 *
 * @param array{} $params
 *
 * @return array<string, mixed>
 */
function civicrm_api3_sepa_logic_closeended(array $params): array {
  $error = CRM_Sepa_Logic_Batching::closeEnded();
  if (empty($error)) {
    return civicrm_api3_create_success();
  }
  else {
    return civicrm_api3_create_error($error);
  }
}

/**
 * API CALL TO UPDATE TXGROUPs ("Batching")
 *
 * @param array{type: string} $params
 *
 * @return array<string, mixed>
 */
function civicrm_api3_sepa_logic_update(array $params): array {
  // get creditor list
  $creditor_query = civicrm_api3('SepaCreditor', 'get', ['option.limit' => 99999]);

  if (!empty($creditor_query['is_error'])) {
    return civicrm_api3_create_error('Cannot get creditor list: ' . $creditor_query['error_message']);
  }
  else {
    $creditors = [];
    foreach ($creditor_query['values'] as $creditor) {
      if ($creditor['mandate_active']) {
        $creditors[] = $creditor['id'];
      }
    }
  }

  if ($params['type'] == 'OOFF') {
    foreach ($creditors as $creditor_id) {
      CRM_Sepa_Logic_Batching::updateOOFF($creditor_id);
    }

  }
  elseif ($params['type'] == 'RCUR' || $params['type'] == 'FRST') {
    // first: make sure, that there are no outdated mandates:
    CRM_Sepa_Logic_Batching::closeEnded();

    // then, run the update for recurring mandates
    foreach ($creditors as $creditor_id) {
      CRM_Sepa_Logic_Batching::updateRCUR($creditor_id, $params['type']);
    }

  }
  else {
    return civicrm_api3_create_error(sprintf("Unknown batching mode '%s'.", $params['type']));
  }

  return civicrm_api3_create_success();
}
