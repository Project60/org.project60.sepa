<?php

/**
 * File for the CiviCRM APIv3 sepa_mandate_file functions
 *
 * @package CiviCRM_SEPA
 *
 */


/**
 * Add an SepaMandateFile for a contact
 *
 * Allowed @params array keys are:
 *
 * @example SepaMandateFileCreate.php Standard Create Example
 *
 * @return array API result array
 * {@getfields sepa_mandate_file_create}
 * @access public
 */
function civicrm_api3_sepa_mandate_file_create($params) {
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Adjust Metadata for Create action
 * 
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_sepa_mandate_file_create_spec(&$params) {
}

/**
 * Deletes an existing SepaMandateFile
 *
 * @param  array  $params
 *
 * @example SepaMandateFileDelete.php Standard Delete Example
 *
 * @return boolean | error  true if successfull, error otherwise
 * {@getfields sepa_mandate_file_delete}
 * @access public
 */
function civicrm_api3_sepa_mandate_file_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Retrieve one or more sepa_mandate_files
 *
 * @param  array input parameters
 *
 *
 * @example SepaMandateFileGet.php Standard Get Example
 *
 * @param  array $params  an associative array of name/value pairs.
 *
 * @return  array api result array
 * {@getfields sepa_mandate_file_get}
 * @access public
 */
function civicrm_api3_sepa_mandate_file_get($params) {
  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}
