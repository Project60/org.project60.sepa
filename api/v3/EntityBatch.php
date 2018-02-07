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
 * File for the CiviCRM APIv3 batch entity
 *
 * @package CiviCRM_SEPA
 *
 */


/**
 * Allowed @params array keys are:
 *
 * @example SepaCreditorCreate.php Standard Create Example
 *
 * @return array API result array
 * {@getfields entity_batch_create}
 * @access public
 */
function civicrm_api3_entity_batch_create($params) {
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Adjust Metadata for Create action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_entity_batch_create_spec(&$params) {
  $params['entity_id']['api.required'] = 1;
  $params['batch_id']['api.required'] = 1;
}

/**
 * Deletes an existing SepaCreditor
 *
 * @param  array  $params
 *
 * @example SepaCreditorDelete.php Standard Delete Example
 *
 * {@getfields entity_batch_delete}
 * @access public
 */
function civicrm_api3_entity_batch_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Retrieve one or more batch_entities
 *
 * @param  array input parameters
 *
 *
 * @example SepaCreditorGet.php Standard Get Example
 *
 * @param  array $params  an associative array of name/value pairs.
 *
 * @return  array api result array
 * {@getfields entity_batch_get}
 * @access public
 */
function civicrm_api3_entity_batch_get($params) {

  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

