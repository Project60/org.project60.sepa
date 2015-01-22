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
   * This method provides a workaround for CRM-14114
   * SEPA-225 (https://github.com/Project60/sepa_dd/issues/225)
   *
   * The problem is, that in current CiviCRM versions, payment processors
   * write recurring interval _labels_ into the frequency_unit field of 
   * contribution_recur. This is very wrong, since this value is translated!
   * 
   * As a workaround, we check the labels of recurring frequency units and reset them if necessary
   *
   * @param $reset    boolean  resets altered labels to standard values
   * @param $warning  boolean  displays a warning if a label has been reset
   */
  public static function checkRecurringFrequencyUnits($reset = FALSE, $warning = TRUE) {
    // compare option group values
    $checkUnits = array('month', 'year');

    // get group id
    $params = array(
      'name' => 'recur_frequency_units',
    );
    $result = civicrm_api3('OptionGroup', 'getsingle', $params);
    if(!empty($result['is_error'])) {
      $message = sprintf("Option group '%s' does not exist. Error was: %s", $params['name'], $result['error_message']);
      error_log("org.project60.sepa_dd: ".$message);
      if($warning) {
        CRM_Core_Session::setStatus("CiviSEPA CRM-14114 workaround: ".$message, ts('Warning'), 'warn');
      }
      return;
    }
    $oid = $result['id'];


    // get all values
    $params = array(
      'option.limit'    => 99999,
      'option_group_id' => $oid,
    );
    $result = civicrm_api3('OptionValue', 'get', $params);
    if(!empty($result['is_error'])) {
      $message = sprintf("Could not retrieve values of group '%d'. Error was: %s", $oid, $result['error_message']);
      error_log("org.project60.sepa_dd: ".$message);
      if($warning) {
        CRM_Core_Session::setStatus("CiviSEPA CRM-14114 workaround: ".$message, ts('Warning'), 'warn');
      }
      return;
    }
    $frequencyUnits = $result['values'];

    // check all the values for
    foreach($checkUnits as $c) {
      foreach($frequencyUnits as $f) {
        if($c == $f['name'] && ($f['label'] != $c || $f['value'] != $c)) {
          error_log(sprintf("org.project60.sepa_dd: label '%s' of option group 'recur_frequency_units' has been changed ['%s']", $c, $f['label']));

          if ($reset) {
            $params = array(
              'option_group_id' => $oid,
              'name'            => $c,
              'label'           => $c,
              'value'           => $c,
              'id'              => $f['id']
            );
            $result = civicrm_api3('OptionValue', 'create', $params);
            if(!empty($result['is_error'])) {
              $message = sprintf("Could not reset option value [%d] ('%s'). Error was: %s", $f['id'], $c, $result['error_message']);
              error_log("org.project60.sepa_dd: ".$message);
              if($warning) {
                CRM_Core_Session::setStatus("CiviSEPA CRM-14114 workaround: ".$message, ts('Warning'), 'warn');
              }
              // FIXME: why not try again? return;
            } else {
              $message = sprintf("Label '%s' of option group 'recur_frequency_units' reset to '%s'", $c, $c);
              error_log("org.project60.sepa_dd: ".$message);
              if($warning) {
                CRM_Core_Session::setStatus("CiviSEPA CRM-14114 workaround: ".$message, ts('Warning'), 'warn');
              }
            }
          }
        }
      }
    }
  }

  /**
   * Offers a textual representation for the donation interval
   *
   * @param unit      unit of time: 'month' or 'year'
   * @param interval  payment interval, like 1 or 6
   * @param ts        set to true, if you want a localised version
   */
  public static function getFrequencyText($interval, $unit, $ts=false) {
    if ($unit == 'month') {
      if ($interval == 1) {
        return $ts?ts('monthly'):'monthly';
      } elseif ($interval == 3) {
        return $ts?ts('quarterly'):'quarterly';
      } elseif ($interval == 6) {
          return $ts?ts('semi-annually'):'semi-annually';
      } elseif ($interval == 12) {
        return $ts?ts('annually'):'annually';
      } else {
        if ($ts) {
          return sprintf(ts("every %1 months"), $interval);
        } else {
          return sprintf("every %1 months", $interval);
        }
      }
    } elseif ($unit == 'year') {
      if ($interval == 1) {
        return $ts?ts('annually'):'annually';
      } else {
        if ($ts) {
          return sprintf(ts("every %1 years"), $interval);
        } else {
          return sprintf("every %1 years", $interval);
        }
      }
    } else {
      return $ts?ts('on an irregular basis'):'on an irregular basis';
    }
  }
}
