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

declare(strict_types = 1);


/**
 * File for the CiviCRM APIv3 sepa_sdd_file functions
 *
 * @package CiviCRM_SEPA
 *
 */

/**
 * Add an SepaSddFile for a contact
 *
 * Allowed @params array keys are:
 *
 * @example SepaSddFileCreate.php Standard Create Example
 *
 * @param array<string, mixed> $params
 *
 * @return array<string, mixed> API result array
 *   {@getfields sepa_sdd_file_create}
 * @access public
 */
function civicrm_api3_sepa_sdd_file_create(array $params): array {
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Adjust Metadata for Create action
 *
 * The metadata is used for setting defaults, documentation & validation
 * @param array<string, array<string, mixed>> $params array or parameters determined by getfields
 */
function _civicrm_api3_sepa_sdd_file_create_spec(array &$params): void {
  $params['reference']['api.required'] = 1;
  $params['filename']['api.required'] = 1;
  $params['created_date']['api.default'] = 'now';
  $params['created_id']['api.default'] = 'user_contact_id';
}

/**
 * Deletes an existing SepaSddFile
 *
 * @param array<string, mixed> $params
 *
 * @example SepaSddFileDelete.php Standard Delete Example
 *
 * @return array<string, mixed>
 *   {@getfields sepa_sdd_file_delete}
 * @access public
 */
function civicrm_api3_sepa_sdd_file_delete(array $params): array {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Retrieve one or more sepa_sdd_files
 *
 * @example SepaSddFileGet.php Standard Get Example
 *
 * @param array<string, mixed> $params an associative array of name/value pairs.
 *
 * @return array<string, mixed> api result array
 *   {@getfields sepa_sdd_file_get}
 * @access public
 */
function civicrm_api3_sepa_sdd_file_get(array $params): array {
  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * @param array<string, array<string, mixed>> $params
 */
function _civicrm_api3_sepa_sdd_file_generatexml_spec(array &$params): void {
  $params['id']['api.required'] = 1;
}

/**
 * FIXME: This method has no return value. Is that intended? Is that function
 * actually used?
 *
 * @param array{id: int|numeric-string} $params
 */
function civicrm_api3_sepa_sdd_file_generatexml(array $params): void {
  //fetch the file, then the group
  $file = new CRM_Sepa_BAO_SEPASddFile();
  $file->generatexml((int) $params['id']);
}
