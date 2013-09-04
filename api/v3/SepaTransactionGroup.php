<?php
// $Id$

/*
 +--------------------------------------------------------------------+
 | Project60 version 4.3                                              |
 +--------------------------------------------------------------------+
 | Copyright TTTP (c) 2004-2013                                       |
 +--------------------------------------------------------------------+
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 * File for the CiviCRM APIv3 sepa_transaction_group functions
 *
 * @package CiviCRM_SEPA
 *
 */


/**
 * Add an SepaTransactionGroup for a contact
 *
 * Allowed @params array keys are:
 *
 * @example SepaTransactionGroupCreate.php Standard Create Example
 *
 * @return array API result array
 * {@getfields sepa_transaction_group_create}
 * @access public
 */
function civicrm_api3_sepa_transaction_group_create($params) {
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Adjust Metadata for Create action
 * 
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_sepa_transaction_group_create_spec(&$params) {
  $params['reference']['api.required'] = 1;
  $params['type']['api.required'] = 1;
  $params['status_id']['api.default'] = 2; //not that sure of the meaning of 2
  $params['sdd_creditor_id']['api.required'] = 1;
  $params['created_date']['api.default'] = 'now';
}

/**
 * Deletes an existing SepaTransactionGroup
 *
 * @param  array  $params
 *
 * @example SepaTransactionGroupDelete.php Standard Delete Example
 *
 * @return boolean | error  true if successfull, error otherwise
 * {@getfields sepa_transaction_group_delete}
 * @access public
 */
function civicrm_api3_sepa_transaction_group_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Retrieve one or more sepa_transaction_groups
 *
 * @param  array input parameters
 *
 *
 * @example SepaTransactionGroupGet.php Standard Get Example
 *
 * @param  array $params  an associative array of name/value pairs.
 *
 * @return  array api result array
 * {@getfields sepa_transaction_group_get}
 * @access public
 */
function civicrm_api3_sepa_transaction_group_get($params) {
  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

function civicrm_api3_sepa_transaction_group_getdetail_spec (&$params) {
  $params['id']['api.required'] = 1;
}

function civicrm_api3_sepa_transaction_group_getdetail($params) {
  $group = (int) $params["id"];
  if (!$group)
    throw new API_Exception("Incorrect or missing value for group id");
  $sql = "select contribution_id, contrib.contact_id, contrib.financial_type_id, contrib.payment_instrument_id, total_amount, receipt_date, mandate.reference, mandate.validation_date, recur.id as recur_id, recur.frequency_unit, recur.cycle_day FROM civicrm_sdd_contribution_txgroup, civicrm_contribution as contrib, civicrm_contribution_recur as recur, civicrm_sdd_mandate as mandate where mandate.entity_id= recur.id and contribution_id = contrib.id and contribution_recur_id=recur.id AND mandate.is_enabled=1 AND txgroup_id=$group";
  $dao = CRM_Core_DAO::executeQuery($sql);
  $result= array();
  $total =0;
  while ($dao->fetch()) {
    $result[] = $dao->toArray();
    $total += $dao->total_amount;
  }
  return civicrm_api3_create_success($result, $params, NULL, NULL, $dao, $extraReturnValues = array("total_amount"=>$total));
}

function civicrm_api3_sepa_transaction_group_createnext_spec (&$params) {
  $params['id']['api.required'] = 1;
die ("toto");
}

function civicrm_api3_sepa_transaction_group_createnext ($params) {
  $group = (int) $params["id"];
  if (!$group)
    throw new API_Exception("Incorrect or missing value for group id");
  $contribs = civicrm_api("sepa_transaction_group","getdetail", $params);
  foreach ($contribs["values"] as $contrib) {
print_r($contrib);
    $m = date_format($contrib["receipt_date"], "m");
die ("$m");
  }
}

