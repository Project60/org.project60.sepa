<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2018 SYSTOPIA                            |
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

use CRM_Sepa_ExtensionUtil as E;

/**
 * SepaMandateLink.create API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_sepa_mandate_link_create_spec(&$spec) {
  $spec['id'] = array(
      'name'         => 'id',
      'api.required' => 1,
      'type'         => CRM_Utils_Type::T_INT,
      'title'        => 'SepaMandateLink ID',
      'description'  => 'ID of existing SepaMandateLink entity',
  );
  $spec['mandate_id'] = array(
      'name'         => 'mandate_id',
      'api.required' => 1,
      'type'         => CRM_Utils_Type::T_INT,
      'title'        => 'Mandate ID',
      'description'  => 'SepaMandate this link relates to',
  );
  $spec['entity_id'] = array(
      'name'         => 'entity_id',
      'api.required' => 1,
      'type'         => CRM_Utils_Type::T_INT,
      'title'        => 'Entity ID',
      'description'  => 'Linked entity ID',
  );
  $spec['entity_table'] = array(
      'name'         => 'entity_table',
      'api.required' => 1,
      'type'         => CRM_Utils_Type::T_STRING,
      'title'        => 'Entity Table',
      'description'  => 'Linked entity table name',
  );
  $spec['class'] = array(
      'name'         => 'class',
      'api.required' => 1,
      'type'         => CRM_Utils_Type::T_STRING,
      'title'        => 'Link Class',
      'description'  => 'Link class string, e.g. "REPLACES" or "MEMBERSHIP". No more than 16 characters',
  );
  $spec['is_active'] = array(
      'name'         => 'is_active',
      'api.default'  => 1,
      'type'         => CRM_Utils_Type::T_BOOLEAN,
      'title'        => 'Is Active?',
      'description'  => 'Is the link currently active?',
  );
  $spec['start_date'] = array(
      'name'         => 'start_date',
      'api.required' => 0,
      'type'         => CRM_Utils_Type::T_DATE,
      'title'        => '(Start) Date',
      'description'  => 'When did this link relationship happen or start?',
  );
  $spec['end_date'] = array(
      'name'         => 'end_date',
      'api.required' => 0,
      'type'         => CRM_Utils_Type::T_DATE,
      'title'        => 'End Date',
      'description'  => 'When did this link relationship end?',
  );
}

/**
 * SepaMandateLink.create API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */

function civicrm_api3_sepa_mandate_link_create($params) {
  return _civicrm_api3_basic_create('CRM_Sepa_BAO_SepaMandateLink', $params);
}

/**
 * SepaMandateLink.delete API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_sepa_mandate_link_delete($params) {
  return _civicrm_api3_basic_delete('CRM_Sepa_BAO_SepaMandateLink', $params);
}

/**
 * SepaMandateLink.get API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_sepa_mandate_link_get($params) {
  return _civicrm_api3_basic_get('CRM_Sepa_BAO_SepaMandateLink', $params);
}


/**
 * SepaMandateLink.getactive API specification
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_sepa_mandate_link_getactive_spec(&$spec) {
  $spec['mandate_id'] = array(
      'name'         => 'mandate_id',
      'api.required' => 0,
      'type'         => CRM_Utils_Type::T_INT,
      'title'        => 'Mandate ID',
      'description'  => 'SepaMandate this link relates to',
  );
  $spec['entity_id'] = array(
      'name'         => 'entity_id',
      'api.required' => 0,
      'type'         => CRM_Utils_Type::T_INT,
      'title'        => 'Entity ID',
      'description'  => 'Entity this link relates to',
  );
  $spec['entity_table'] = array(
      'name'         => 'entity_table',
      'api.required' => 0,
      'type'         => CRM_Utils_Type::T_STRING,
      'title'        => 'Entity Table',
      'description'  => 'Entity this link relates to',
  );
  $spec['class'] = array(
      'name'         => 'class',
      'api.required' => 0,
      'type'         => CRM_Utils_Type::T_STRING,
      'title'        => 'Link Class(es)',
      'description'  => 'Link class string, or array, or comma-separated',
  );
  $spec['date'] = array(
      'name'         => 'date',
      'api.required' => 0,
      'type'         => CRM_Utils_Type::T_DATE,
      'title'        => 'Date',
      'description'  => 'What point in time are we looking at? Default: now',
  );
}


/**
 * SepaMandateLink.getactive API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_sepa_mandate_link_getactive($params) {
  try {
    $result = CRM_Sepa_BAO_SepaMandateLink::getActiveLinks(
        CRM_Utils_Array::value('mandate_id', $params, NULL),
        CRM_Utils_Array::value('class', $params, NULL),
        CRM_Utils_Array::value('entity_id', $params, NULL),
        CRM_Utils_Array::value('entity_table', $params, NULL),
        CRM_Utils_Array::value('date', $params, 'now'));
    return civicrm_api3_create_success($result);
  } catch (Exception $ex) {
    throw new API_Exception($ex->getMessage());
  }
}
