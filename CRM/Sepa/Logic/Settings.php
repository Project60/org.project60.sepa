<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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


/**
 * This class holds all the functions SEPA settings and configuration
 */
class CRM_Sepa_Logic_Settings {

  /**
   * Get SEPA a setting.
   * We have extended the system used in CRM_Core_BAO_Setting by 
   * an override mechanism, so creditors can indivudally have
   * different values than the default
   * 
   * @return string
   */
  static function getSetting($param_name, $creditor_id=NULL) {
    $param_name = str_replace('.', '_', $param_name);
    $override = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', $param_name . "_override");
    $stdvalue = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', $param_name);
    if (($override == NULL && $stdvalue == NULL) || $stdvalue == NULL) {
        error_log("org.project60.sepa: get_parameter for unknown key: $param_name");
        return NULL;
    }else if ($override == NULL) {
      return $stdvalue;
    }else{
      $override = json_decode($override);
      if (isset($override->{$creditor_id})) {
        return $override->{$creditor_id};
      }
      return $stdvalue;
    }
  }

  /**
   * Checks if a given contribution is a SEPA contribution.
   * This works for contribution and contribution_recur entities
   *
   * It simply checks, whether the contribution uses a SEPA payment instrument
   *  
   * @param $contribution   an array with the attributes of the contribution
   *
   * @return true if the contribution is a SEPA contribution
   */
  public static function isSDD($contribution) {
    $payment_instrument_id = $contribution["payment_instrument_id"];
    $name = CRM_Core_OptionGroup::getValue('payment_instrument', $payment_instrument_id, 'value', 'String', 'name');
    switch ($name) {
      case 'FRST' :
      case 'RCUR' :
      case 'OOFF' :
        return true;
    }
    return false;
  }


  /**
   * Get a batching lock
   * 
   * the lock is needed so that only one relevant process can access the 
   * SEPA data structures at a time
   * 
   * @return lock object. check if it ->isAcquired() before use
   */
  static function getLock() {
    $timeout = CRM_Sepa_Logic_Settings::getSetting('batching.UPDATE.lock.timeout');
    return new CRM_Core_Lock('org.project60.sepa.batching.update', $timeout);
  }

}
