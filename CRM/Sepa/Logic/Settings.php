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
   * Get a SEPA setting.
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
    $exception = array('cycledays');
    if (($override == NULL && $stdvalue == NULL) || ($stdvalue == NULL && !in_array($param_name, $exception))) {
        error_log("org.project60.sepa: get_parameter for unknown key: $param_name");
        return NULL;
    }else if ($override == NULL) {
      return $stdvalue;
    }else{
      $override = json_decode($override);
      if (isset($override->{$creditor_id})) {
        return $override->{$creditor_id};
      } else {
        return $stdvalue;
      }
    }
  }

  /**
   * generate a transaction message for the given mandate/creditor
   *
   * @return a SEPA compliant transaction message
   */
  static function getTransactionMessage($mandate, $creditor) {
    // get tx message from settings
    $transaction_message = self::getSetting('custom_txmsg', $creditor['id']);

    // run hook for further customisation
    CRM_Utils_SepaCustomisationHooks::modify_txmessage($transaction_message, $mandate, $creditor);

    // fallback is "Thanks."
    if (empty($transaction_message)) {
      $transaction_message = ts("Thank you", array('domain' => 'org.project60.sepa'));
    }

    // make sure that it doesn't contain any special characters
    $transaction_message = preg_replace("#[^a-zA-Z0-9\/\-\:\(\)\'\+ \.\*]#", '?', $transaction_message);

    return $transaction_message;
  }

  /**
   * Get a SEPA setting as a list
   *
   * @see self::getSetting
   * 
   * @return string
   */
  static function getListSetting($param_name, $default, $creditor_id=NULL) {
    $value = self::getSetting($param_name, $creditor_id);
    if (empty($value)) {
      $list = $default;
    } else {
      $list = explode(',', $value);
    }

    // make it into an associative array (for dropdown elements) 
    $result = array();
    foreach ($list as $item) {
      $result[$item] = $item;
    }
    return $result;
  }


  /**
   * Set SEPA a setting.
   * We have extended the system used in CRM_Core_BAO_Setting by 
   * an override mechanism, so creditors can indivudally have
   * different values than the default
   * 
   * @param string
   */
  static function setSetting($param_name, $value, $creditor_id=NULL) {
    $param_name = str_replace('.', '_', $param_name);
    if (empty($creditor_id)) {
      // set the general setting
      CRM_Core_BAO_Setting::setItem($value, 'SEPA Direct Debit Preferences', $param_name);
    } else {
      // set the individual override
      $override_string = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', $param_name . "_override");
      $override = json_decode($override_string);
      if ($value==NULL || $value=='') {
        // remove override
        unset($override->{$creditor_id});
      } else {
        // add override
        $override->{$creditor_id} = $value;
      }
      CRM_Core_BAO_Setting::setItem(json_encode($override), 'SEPA Direct Debit Preferences', $param_name . "_override");
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
    * Gets the mandate of the given contribution
    *
    * @param contribution_id  ID of the contribution
    * @return a map <mandate ID> => <mandate type> of all mandates (should be 0 or 1!), e.g. array(7 => 'OOFF')
    */
  public static function getMandateFor($contribution_id) {
    $mandates = array();
    $contribution_id = (int) $contribution_id;
    if (empty($contribution_id)) return $mandates;
    $sql_query = "
    SELECT 
      ooff.id AS ooff_id, ooff.type AS ooff_type, ooff.status AS ooff_status,
      rcur.id AS rcur_id, rcur.type AS rcur_type, rcur.status AS rcur_status
    FROM civicrm_contribution
    LEFT JOIN civicrm_sdd_mandate ooff ON ooff.entity_id = civicrm_contribution.id AND ooff.entity_table = 'civicrm_contribution'
    LEFT JOIN civicrm_sdd_mandate rcur ON rcur.entity_id = civicrm_contribution.contribution_recur_id AND rcur.entity_table = 'civicrm_contribution_recur'
    WHERE civicrm_contribution.id = $contribution_id;";
    $mandate_ids = CRM_Core_DAO::executeQuery($sql_query);

    while ($mandate_ids->fetch()) {
      if ($mandate_ids->ooff_id) {
        $mandates[$mandate_ids->ooff_id] = 'OOFF';
      } 
      if ($mandate_ids->rcur_id) {
        if ($mandate_ids->rcur_status == 'FRST') {
          $mandates[$mandate_ids->rcur_id] = 'FRST';
        } else {
          $mandates[$mandate_ids->rcur_id] = 'RCUR';
        }
      } 
    }
    return $mandates;
  }


  /**
   * Get a batching lock
   * 
   * the lock is needed so that only one relevant process can access the 
   * SEPA data structures at a time
   * 
   * @return CRM_Utils_SepaSafeLock object, or NULL if acquisition timed out
   */
  static function getLock() {
    $timeout = CRM_Sepa_Logic_Settings::getSetting('batching.UPDATE.lock.timeout');
    return CRM_Utils_SepaSafeLock::acquireLock('org.project60.sepa.batching.update', $timeout);
  }


  /**
   * Reads the default creditor from the settings
   * Will only return a creditor if it exists and if it's active
   * 
   * @return CRM_Sepa_BAO_SEPACreditor object or NULL
   */
  static function defaultCreditor() {
    $default_creditor_id = (int) CRM_Sepa_Logic_Settings::getSetting('batching_default_creditor');
    if (empty($default_creditor_id)) return NULL;
    $default_creditor = new CRM_Sepa_DAO_SEPACreditor();
    $default_creditor->get('id', $default_creditor_id);
    if (empty($default_creditor->mandate_active)) {
      return NULL;
    } else {
      return $default_creditor;
    }
  }

  /**
   * Form rule to only allow empty value or a list of
   * valid days (e.g. 1 <= x <= 28)
   */
  static function sepa_cycle_day_list($value) {
    if (!empty($value)) {
      $days = explode(',', $value);
      foreach ($days as $day) {
        if (!is_numeric($day) || $day < 1 || $day > 28) {
          return false;
        }
      }
    }
    return true;
  }

  /**
   * Will check if the "Little BIC Extension" is accessible in the current user context
   * 
   * @return bool TRUE if it is
   */
  public static function isLittleBicExtensionAccessible() {
    try {
      $result = civicrm_api3('Bic', 'findbyiban', array('iban' => 'TEST'));
      return empty($result['is_error']);
    } catch (Exception $e) {
      return FALSE;
    }
  }
}
