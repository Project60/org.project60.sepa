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
    $override = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', $param_name . "_override");
    $stdvalue = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', $param_name);
    if (($override == NULL && $stdvalue == NULL) || $stdvalue == NULL) {
        error_log("org.project60.sepa: get_parameter for unknown key: $parameter_name");
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
   * Get a batching lock
   * 
   * the lock is needed so that only one relevant process can access the 
   * SEPA data structures at a time
   * 
   * @return lock object. check if it ->isAcquired() before use
   */
  static function getLock() {
    $timeout = CRM_Sepa_Logic_Settings::getSettingLegacy('org.project60.batching.alt.UPDATE.lock.timeout');
    return new CRM_Core_Lock('org.project60.sepa.batching.update', $timeout);
  }

  /**
   * Get SEPA a setting - LEGACY METHOD
   * 
   * @return string
   */
  static function getSettingLegacy($parameter_name, $creditor_id=NULL) { 
    $param_name = str_replace('.', '_', substr($parameter_name, 14));
    return self::getSetting($param_name, $creditor_id);
  }
}
