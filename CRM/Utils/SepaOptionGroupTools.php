<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 SYSTOPIA                       |
| Author: N. Bochan (bochan -at- systopia.de)            |
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

/*
* This class holds methods to manipulate option groups
*/
class CRM_Utils_SepaOptionGroupTools {

  /**
   * This method provides a workaround for issue #225
   * (https://github.com/Project60/sepa_dd/issues/225)
   *
   * Checks labels of recurring frequency units and resets them if necessary
   * @param $reset boolean resets altered labels to standard values
   * @param $warning boolean displays a warning if a label has been reset
   */
  public static function checkRecurringFrequencyUnits($reset = FALSE, $warning = TRUE) {
    // compare option group values
    $checkUnits = array('month', 'year');

    // get group id
    $params = array(
      'name' => 'recur_frequency_units',
    );
    $result = civicrm_api3('OptionGroup', 'get', $params);
    if(isset($result['is_error']) && $result['is_error'] != 0) {
      error_log(sprintf("org.project60.sepa_dd: option group '%s' does not exist.",
      $params['name']));
      return;
    }
    $oid = $result['id'];

    // get all values
    $params = array(
      'option.limit' => 99999,
      'option_group_id' => $oid,
    );
    $result = civicrm_api3('OptionValue', 'get', $params);
    if(isset($result['is_error']) && $result['is_error'] != 0) {
      error_log(sprintf("org.project60.sepa_dd: could not retrieve values of group '%d'.",
      $oid));
      return;
    }
    $frequencyUnits = $result['values'];

    foreach($checkUnits as $c) {
      foreach($frequencyUnits as $f) {
        if($c == $f['name'] && ($f['label'] != $c || $f['value'] != $c)) {
          error_log(sprintf("org.project60.sepa_dd: label '%s' of option group 'recur_frequency_units' has been changed ['%s']",
          $c,
          $f['label']));

          if ($reset) {
            $params = array(
              'option_group_id' => $oid,
              'name' => $c,
              'label' => $c,
              'value' => $c,
              'id' => $f['id']
            );
            $result = civicrm_api3('OptionValue', 'create', $params);

            if($warning) {
              CRM_Core_Session::setStatus(sprintf(ts("org.project60.sepa_dd: resetting label '%s' of option group 'recur_frequency_units' to '%s'"), $c, $c), ts('Warning'), 'warn');
            }

            error_log(sprintf("org.project60.sepa_dd: resetting label '%s' of option group 'recur_frequency_units' to '%s'",
            $c,
            $c));
          }

        }
      }
    }

  }
}
