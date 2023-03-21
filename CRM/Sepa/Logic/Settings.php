<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 SYSTOPIA                       |
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
   * Get a generic configuration setting, not related to any of the creditors
   *
   * @param $setting_name string setting name
   * @return mixed value
   */
  public static function getGenericSetting($setting_name) {
    $setting_name = str_replace('.', '_', $setting_name);
    return Civi::settings()->get($setting_name);
  }

  /**
   * Get a generic configuration setting, not related to any of the creditors
   *
   * @param $value        mixed  new value
   * @param $setting_name string setting name
   */
  public static function setGenericSetting($value, $setting_name) {
    $setting_name = str_replace('.', '_', $setting_name);
    Civi::settings()->set($setting_name, $value);
  }

  /**
   * Get a SEPA setting.
   * We have extended the system used by CiviCRM by
   * an override mechanism, so creditors can individually have
   * different values than the default
   *
   * @param $param_name  string   the parameter name. note that '.' will be replaced with '_'
   * @param $creditor_id int|null creditor context
   * @return mixed|null the current value
   */
  public static function getSetting($param_name, $creditor_id = NULL) {
    $param_name = str_replace('.', '_', $param_name);
    $stdvalue = Civi::settings()->get($param_name);
    $override = Civi::settings()->get("{$param_name}_override");
    $exception = array('cycledays','pp_buffer_days');
    if (($override == NULL && $stdvalue == NULL) || ($stdvalue == NULL && !in_array($param_name, $exception))) {
      Civi::log()->debug("org.project60.sepa: get_parameter for unknown key: $param_name");
      return NULL;
    } else if ($override == NULL) {
      return $stdvalue;
    } else {
      $override = json_decode($override);
      if (isset($override->{$creditor_id})) {
        return $override->{$creditor_id};
      } else {
        return $stdvalue;
      }
    }
  }

  /**
   * Set SEPA a setting.
   * We have extended the system used by CiviCRM by
   * an override mechanism, so creditors can individually have
   * different values than the default
   *
   * @param string
   */
  static function setSetting($value, $param_name, $creditor_id=NULL) {
    $param_name = str_replace('.', '_', $param_name);
    if (empty($creditor_id)) {
      // set the general setting
      Civi::settings()->set($param_name, $value);
    } else {
      // set the individual override
      $override_string = CRM_Sepa_Logic_Settings::getGenericSetting("{$param_name}_override");
      $override = json_decode($override_string, TRUE);
      if (!$override) {
        $override = [];
      }
      if ($value==NULL || $value=='') {
        // remove override
        unset($override[$creditor_id]);
      } else {
        // add override
        $override[$creditor_id] = $value;
      }
      Civi::settings()->set("{$param_name}_override", json_encode($override));
    }
  }

  /**
   * Generate a transaction message for the given mandate/creditor
   *
   * @param array $mandate
   *   mandate / contribution data
   * @param array $creditor
   *   creditor data
   * @param integer $txgroup_id
   *   ID of the transactino group
   *
   * @return string a SEPA compliant transaction message
   */
  static function getTransactionMessage($mandate, $creditor, $txgroup_id = 0) {
    // get tx message from settings
    $transaction_message = self::getSetting('custom_txmsg', $creditor['id']);

    // override with custom txgroup message
    $custom_transaction_message = CRM_Sepa_BAO_SEPATransactionGroup::getCustomGroupTransactionMessage($txgroup_id);
    if ($custom_transaction_message) {
      $transaction_message = $custom_transaction_message;
    }

    // run hook for further customisation
    CRM_Utils_SepaCustomisationHooks::modify_txmessage($transaction_message, $mandate, $creditor);

    // fallback is "Thanks."
    if (empty($transaction_message)) {
      $transaction_message = ts("Thank you", array('domain' => 'org.project60.sepa'));
    }

    // make sure that it doesn't contain any special characters
    $transaction_message = CRM_Sepa_Logic_Verification::convert2SepaCharacterSet($transaction_message);

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

  /**
   * Return the ID of the contributions' 'In Progress' status.
   *
   * @see https://github.com/Project60/org.project60.sepa/issues/632
   * @see https://lab.civicrm.org/dev/financial/-/issues/201
   *
   * @return integer
   */
  public static function contributionInProgressStatusId()
  {
    static $in_progress_status = null;
    if ($in_progress_status === null) {
      // add mitigation for CiviCRM 5.55+
      CRM_Core_BAO_OptionValue::ensureOptionValueExists([
        'option_group_id' => 'contribution_status',
        'name' => 'In Progress',
        'value' => 5,
        'label' => ts('In Progress'),
        'is_active' => TRUE,
        'component_id' => 'CiviContribute',
      ]);

      // the look up the status
      $in_progress_status = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'In Progress');
      if (empty($in_progress_status)) {
        throw new Exception("Contribution status 'In Progress' is missing, but required.");
      }
      if ($in_progress_status != 5) {
        Civi::log()->debug("Contribution status 'In Progress' is not value 5. Hope that's fine.");
      }
    }
    return $in_progress_status;
  }

  /**
   * Acquire async lock
   *
   * This is a mutex that can be kept over various processes,
   * Caution: this is not completely thread-safe
   *
   * @param $name    lock name
   * @param $timeout lock timeout in seconds
   * @param $renew   TRUE if you want to renew the lock (make sure it's yours!)
   * @return TRUE if lock could be acquired
   */
  public static function acquireAsyncLock($name, $timeout, $renew = FALSE) {
    $now = time();
    $locks = CRM_Sepa_Logic_Settings::getGenericSetting('sdd_async_batching_lock');
    if (!is_array($locks)) {
      // data invalid -> reset
      $locks = array();
    }
    if (!$renew && !empty($locks[$name])) {
      $lock_valid_until = $locks[$name];
      if ($lock_valid_until > $now) {
        // CURRENT LOCK STILL VALID
        return FALSE;
      }
    }
    // NO (VALID) LOCK
    $locks[$name] = $now + (int)$timeout;
    CRM_Sepa_Logic_Settings::setSetting($locks, 'sdd_async_batching_lock');
    return TRUE;
  }

  /**
   * Renew async lock (make sure it's yours!)
   *
   * This is a mutex that can be kept over various processes,
   * Caution: this is not completely thread-safe
   *
   * @param $name    lock name
   * @param $timeout lock timeout in seconds
   */
  public static function renewAsyncLock($name, $timeout) {
    return self::acquireAsyncLock($name, $timeout, TRUE);
  }

  /**
   * Release a async lock.
   *  This method does NOT check whether you acquired the lock in the first place!!
   *
   * Caution: this is not completely thread-safe
   *
   * @return TRUE if lock could be acquired
   */
  public static function releaseAsyncLock($name) {
    $locks = CRM_Sepa_Logic_Settings::getGenericSetting('sdd_async_batching_lock');
    if (!is_array($locks)) {
      // data invalid -> reset
      $locks = array();
    }
    $locks[$name] = 0;
    CRM_Sepa_Logic_Settings::setSetting($locks, 'sdd_async_batching_lock');
  }

}
