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
 * @param $params
 */
function civicrm_api3_sepa_logic_get_retry_stats($params) {
  $stats = CRM_Sepa_Logic_Retry::getStats($params);
  $dao = NULL;
  return civicrm_api3_create_success($stats['contribution_count'], $params, 'SepaLogic', 'get_retry_stats', $dao, $stats);
}

/**
 * Get stats to support the retry collection form
 *
 * @param $params
 */
function _civicrm_api3_sepa_logic_get_retry_stats_spec($params) {
  // CONTACT BASE
  $params['date_from'] = array(
      'name'         => 'date_from',
      'api.required' => 1,
      'title'        => 'Date Range From',
      'description'  => 'Start of the date range',
  );
  $params['date_to'] = array(
      'name'         => 'date_to',
      'api.required' => 1,
      'title'        => 'Date Range To',
      'description'  => 'End of the date range',
  );
  $params['creditor_list'] = array(
      'name'         => 'creditor_list',
      'api.required' => 0,
      'title'        => 'Creditor IDs',
      'description'  => 'List of creditor IDs. Default is: all',
  );
  $params['txgroup_list'] = array(
      'name'         => 'txgroup_list',
      'api.required' => 0,
      'title'        => 'TxGroup IDs',
      'description'  => 'List SDD transaction group IDs. Default is: all',
  );
  $params['cancel_reason_list'] = array(
      'name'         => 'cancel_reason_list',
      'api.required' => 0,
      'title'        => 'Cancel Reasons',
      'description'  => 'List of cancel reasons. Default is: all',
  );
  $params['frequencies'] = array(
      'name'         => 'frequencies',
      'api.required' => 0,
      'title'        => 'Frequency List',
      'description'  => 'List of frequencies (collections per year). Default is: all',
  );
  $params['amount_min'] = array(
      'name'         => 'amount_min',
      'api.required' => 0,
      'title'        => 'Amount Minimum',
      'description'  => 'Minimal collection amount. Default is: all',
  );
  $params['amount_max'] = array(
      'name'         => 'amount_max',
      'api.required' => 0,
      'title'        => 'Amount Maximum',
      'description'  => 'Maximal collection amount. Default is: all',
  );
}




/**
 * This function will close a transaction group,
 * and perform the necessary logical changes to the mandates contained
 */
function civicrm_api3_sepa_logic_close($params) {
  if (!is_numeric($params['txgroup_id'])) {
    return civicrm_api3_create_error("Required field txgroup_id was not properly set.");
  }

  $error_message = CRM_Sepa_Logic_Group::close($params['txgroup_id']);
  if (empty($error_message)) {
    return civicrm_api3_create_success();
  } else {
    return civicrm_api3_create_error($error_message);
  }
}

function _civicrm_api3_sepa_logic_close_spec (&$params) {
  $params['txgroup_id']['api.required'] = 1;
}


/*
 * This method will create the SDD file for the given group
 *
 * @param txgroup_id  the transaction group for which the file should be created
 * @param override    if true, will override an already existing file and create a new one
 */
function civicrm_api3_sepa_logic_createxml($params) {
  $override = (isset($params['override'])) ? $params['override'] : false;

  $result = CRM_Sepa_BAO_SEPATransactionGroup::createFile((int) $params['txgroup_id'], $override);
  if (is_numeric($result)) {
    // this was succesfull -> load the sepa file
    return civicrm_api('SepaSddFile', 'getsingle', array('id'=>$result, 'version'=>3));
  } else {
    // there was an error:
    civicrm_api3_create_error($result);
  }
}

function civicrm_api3_sepa_logic_createxml_spec(&$params) {
  $params['txgroup_id']['api.required'] = 1;
}



/**
 * API CALL TO MARK TXGROUPs AS 'RECEIVED':
 *    - set txgroup status to 'received'
 *    - change status from 'In Progress' to 'Completed' for all contributions
 *    - (store/update the bank account information)
 *
 * @package CiviCRM_SEPA
 */
function civicrm_api3_sepa_logic_received($params) {
  if (!is_numeric($params['txgroup_id'])) {
    return civicrm_api3_create_error("Required field txgroup_id was not properly set.");
  }

  $error = CRM_Sepa_Logic_Group::received((int) $params['txgroup_id']);
  if (empty($error)) {
    return civicrm_api3_create_success();
  } else {
    return civicrm_api3_create_error($error);
  }
}

function _civicrm_api3_sepa_logic_received_spec (&$params) {
  $params['txgroup_id']['api.required'] = 1;
}




/**
 * API CALL TO CLOSE MANDATES THAT ENDED
 *
 * @package CiviCRM_SEPA
 *
 */
function civicrm_api3_sepa_logic_closeended($params) {
  $error = CRM_Sepa_Logic_Batching::closeEnded();
  if (empty($error_message)) {
    return civicrm_api3_create_success();
  } else {
    return civicrm_api3_create_error($error);
  }
}




/**
 * API CALL TO UPDATE TXGROUPs ("Batching")
 *
 * @package CiviCRM_SEPA
 *
 */
function civicrm_api3_sepa_logic_update($params) {
  // get creditor list
  $creditor_query = civicrm_api('SepaCreditor', 'get', array('version' => 3, 'option.limit' => 99999));

  if (!empty($creditor_query['is_error'])) {
    return civicrm_api3_create_error("Cannot get creditor list: ".$creditor_query['error_message']);
  } else {
    $creditors = array();
    foreach ($creditor_query['values'] as $creditor) {
      if ($creditor['mandate_active']) {
        $creditors[] = $creditor['id'];
      }
    }
  }

  if ($params['type']=='OOFF') {
    foreach ($creditors as $creditor_id) {
      CRM_Sepa_Logic_Batching::updateOOFF($creditor_id);
    }

  } elseif ($params['type']=='RCUR' || $params['type']=='FRST') {
    // first: make sure, that there are no outdated mandates:
    CRM_Sepa_Logic_Batching::closeEnded();

    // then, run the update for recurring mandates
    foreach ($creditors as $creditor_id) {
      CRM_Sepa_Logic_Batching::updateRCUR($creditor_id, $params['type']);
    }

  } else {
    return civicrm_api3_create_error(sprintf("Unknown batching mode '%s'.", $params['type']));
  }

  return civicrm_api3_create_success();
}
