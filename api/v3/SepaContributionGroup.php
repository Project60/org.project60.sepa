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


