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
 * @return array API result array
 * {@getfields sepa_sdd_file_create}
 * @access public
 */
function civicrm_api3_sepa_sdd_file_create($params) {
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Adjust Metadata for Create action
 * 
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_sepa_sdd_file_create_spec(&$params) {
  $params['reference']['api.required'] = 1;
  $params['filename']['api.required'] = 1;
  $params['created_date']['api.default'] = "now";
  $params['created_id']['api.default'] = "user_contact_id";
}

/**
 * Deletes an existing SepaSddFile
 *
 * @param  array  $params
 *
 * @example SepaSddFileDelete.php Standard Delete Example
 *
 * @return boolean | error  true if successfull, error otherwise
 * {@getfields sepa_sdd_file_delete}
 * @access public
 */
function civicrm_api3_sepa_sdd_file_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Retrieve one or more sepa_sdd_files
 *
 * @param  array input parameters
 *
 *
 * @example SepaSddFileGet.php Standard Get Example
 *
 * @param  array $params  an associative array of name/value pairs.
 *
 * @return  array api result array
 * {@getfields sepa_sdd_file_get}
 * @access public
 */
function civicrm_api3_sepa_sdd_file_get($params) {

  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

function _civicrm_api3_sepa_sdd_file_generatexml_spec(&$params) {
  $params['id']['api.required'] = 1;
}

function civicrm_api3_sepa_sdd_file_generatexml($params) {
//fetch the file, then the group
  $file = new CRM_Sepa_BAO_SEPASddFile();
  $xml = $file->generateXML($params["id"]);

  $config = CRM_Core_Config::singleton();

  $sepaFile = civicrm_api3('SepaSddFile', 'getvalue', array('id' => $params['id'], 'return' => 'filename'));
  #$filename = CRM_Utils_File::makeFileName($sepaFile);
  $filename = $sepaFile;
  $outfile = $config->customFileUploadDir . $filename;

  file_put_contents($outfile, $xml);

  $fileDAO = new CRM_Core_DAO_File();
  $fileDAO->uri = $filename;
  $fileDAO->mime_type = 'application/xml';
  $fileDAO->upload_date = date('Ymdhis');
  $fileDAO->save();

  $entityFileDAO = new CRM_Core_DAO_EntityFile();
  $entityFileDAO->entity_table = 'civicrm_sdd_file';
  $entityFileDAO->entity_id = $params['id'];
  $entityFileDAO->file_id = $fileDAO->id;
  $entityFileDAO->save();
}

/**
 */
function _civicrm_api3_sepa_sdd_file_batchforsubmit_spec(&$params) {
  $params['submit_date']['api.default'] = date('Y-m-d', strtotime('today'));
  $params['creditor_id']['api.required'] = 1;
}

/**
 */
function civicrm_api3_sepa_sdd_file_batchforsubmit($params) {
  CRM_Sepa_Logic_Batching::batchForSubmit($params['submit_date'], $params['creditor_id']);
}

/**
 */
function _civicrm_api3_sepa_sdd_file_cancelsubmit_spec(&$params) {
  $params['id']['api.required'] = 1;
}

/**
 */
function civicrm_api3_sepa_sdd_file_cancelsubmit($params) {
  CRM_Sepa_Logic_Batching::cancelSubmit(array('sdd_file_id' => $params['id']));
}
