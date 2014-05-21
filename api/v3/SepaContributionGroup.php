<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 TTTP                           |
| Author: X+                                             |
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
 * File for the CiviCRM APIv3 sepa_contribution_group functions
 *
 * @package CiviCRM_SEPA
 *
 */


/**
 * Add an SepaContributionGroup for a contact
 *
 * Allowed @params array keys are:
 *
 * @example SepaContributionGroupCreate.php Standard Create Example
 *
 * @return array API result array
 * {@getfields sepa_contribution_group_create}
 * @access public
 */
function civicrm_api3_sepa_contribution_group_create($params) {
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Adjust Metadata for Create action
 * 
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_sepa_contribution_group_create_spec(&$params) {
  $params['contribution_id']['api.required'] = 1;
  $params['txgroup_id']['api.required'] = 1;
}

/**
 * Deletes an existing SepaContributionGroup
 *
 * @param  array  $params
 *
 * @example SepaContributionGroupDelete.php Standard Delete Example
 *
 * @return boolean | error  true if successfull, error otherwise
 * {@getfields sepa_contribution_group_delete}
 * @access public
 */
function civicrm_api3_sepa_contribution_group_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Retrieve one or more sepa_contribution_groups
 *
 * @param  array input parameters
 *
 *
 * @example SepaContributionGroupGet.php Standard Get Example
 *
 * @param  array $params  an associative array of name/value pairs.
 *
 * @return  array api result array
 * {@getfields sepa_contribution_group_get}
 * @access public
 */
function civicrm_api3_sepa_contribution_group_get($params) {

  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

function _civicrm_api3_sepa_contribution_group_getdetail_spec (&$params) {
  $params['id']['api.required'] = 1;
}

function civicrm_api3_sepa_contribution_group_getdetail($params) {
  $group = (int) $params["id"];
  if (!$group)
    throw new API_Exception("Incorrect or missing value for group id");
  $sql = "
    SELECT
      contribution_id,
      contrib.contact_id,
      contrib.financial_type_id,
      contrib.payment_instrument_id,
      total_amount,
      receive_date,
      mandate.id AS mandate_id,
      mandate.reference,
      mandate.creditor_id,
      mandate.validation_date,
      recur.id AS recur_id,
      recur.frequency_interval,
      recur.frequency_unit,
      recur.cycle_day,
      recur.next_sched_contribution_date
    FROM civicrm_sdd_contribution_txgroup
      JOIN civicrm_contribution AS contrib ON contrib.id = contribution_id
      LEFT JOIN civicrm_contribution_recur AS recur ON recur.id = contrib.contribution_recur_id
      JOIN civicrm_sdd_mandate AS mandate ON mandate.id = IF(recur.id IS NOT NULL,
        (SELECT id FROM civicrm_sdd_mandate WHERE entity_table = 'civicrm_contribution_recur' AND entity_id = recur.id),
        (SELECT id FROM civicrm_sdd_mandate WHERE entity_table = 'civicrm_contribution' AND entity_id = contrib.id)
      )
    WHERE txgroup_id=$group
      /* AND mandate.is_enabled=1 */
      AND mandate.status IN ('FRST','OOFF','RCUR')
  ";
  $dao = CRM_Core_DAO::executeQuery($sql);
  $result= array();
  $total =0;
  while ($dao->fetch()) {
    $result[] = $dao->toArray();
    $total += $dao->total_amount;
  }
  return civicrm_api3_create_success($result, $params, NULL, NULL, $dao, $extraReturnValues = array("total_amount"=>$total));
}


