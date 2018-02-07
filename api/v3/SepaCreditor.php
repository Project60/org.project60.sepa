<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 TTTP                           |
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
 * File for the CiviCRM APIv3 sepa_creditor functions
 *
 * @package CiviCRM_SEPA
 *
 */


/**
 * Add an SepaCreditor for a contact
 *
 * Allowed @params array keys are:
 *
 * @example SepaCreditorCreate.php Standard Create Example
 *
 * @return array API result array
 * {@getfields sepa_creditor_create}
 * @access public
 */
function civicrm_api3_sepa_creditor_create($params) {
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Adjust Metadata for Create action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_sepa_creditor_create_spec(&$params) {
  // TODO a 'clever' default should be introduced
  $params['mandate_prefix']['api.default'] = "SEPA";
  $params['identifier']['api.default'] = "FIXME";
  $params['name']['api.default'] = "FIXME";
}

/**
 * Deletes an existing SepaCreditor
 *
 * @param  array  $params
 *
 * @example SepaCreditorDelete.php Standard Delete Example
 *
 * @return boolean | error  true if successfull, error otherwise
 * {@getfields sepa_creditor_delete}
 * @access public
 */
function civicrm_api3_sepa_creditor_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Retrieve one or more sepa_creditors
 *
 * @param  array input parameters
 *
 *
 * @example SepaCreditorGet.php Standard Get Example
 *
 * @param  array $params  an associative array of name/value pairs.
 *
 * @return  array api result array
 * {@getfields sepa_creditor_get}
 * @access public
 */
function civicrm_api3_sepa_creditor_get($params) {

  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

