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
 * File for the CiviCRM sepa_creditor business logic
 *
 * @package CiviCRM_SEPA
 *
 */

/**
 * Class contains functions for Sepa mandates
 */
class CRM_Sepa_BAO_SEPACreditor extends CRM_Sepa_DAO_SEPACreditor {


  /**
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return object       CRM_Core_BAO_SEPACreditor object on success, null otherwise
   * @access public
   * @static
   */
  static function add(&$params) {
    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'SepaCreditor', CRM_Utils_Array::value('id', $params), $params);

    $dao = new CRM_Sepa_DAO_SEPACreditor();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'SepaCreditor', $dao->id, $dao);
    return $dao;
  }

  /**
   * Will set the inital parameters 'status', 'validation_date' and 'date', 'is_enabled'
   * in the $mandate_data array with respect to the creditor settings
   * 
   * Caution: will NOT modify the mandata on the database!
   */
  public static function initialiseMandateData($creditor_id, &$mandate_data) {
    if (empty($creditor_id) || empty($mandate_data['id']) || empty($mandate_data['type'])) return;

    $creditor = civicrm_api3('SepaCreditor', 'getsingle', array('id'=>$creditor_id));
    if (empty($creditor['mandate_active'])) {
      // mandate is being created as 'not activated'
      $mandate_data['is_enabled'] = 0;
      if (empty($mandate_data['creation_date']))   $mandate_data['creation_date'] = date('YmdHis');

      if ($mandate_data['type'] == 'RCUR') {
        $mandate_data['status'] = 'INIT';
      } elseif ($mandate_data['type'] == 'OOFF') {
        $mandate_data['status'] = 'INIT';
      }

    } else {
      // mandate is activated right away
      $mandate_data['is_enabled'] = 1; 
      if (empty($mandate_data['date']))            $mandate_data['date']            = date('YmdHis');
      if (empty($mandate_data['creation_date']))   $mandate_data['creation_date']   = date('YmdHis');
      if (empty($mandate_data['validation_date'])) $mandate_data['validation_date'] = date('YmdHis');
      
      if ($mandate_data['type'] == 'RCUR') {
        $mandate_data['status'] = 'FRST';
      } elseif ($mandate_data['type'] == 'OOFF') {
        $mandate_data['status'] = 'OOFF';
      }
    }
  }
}

