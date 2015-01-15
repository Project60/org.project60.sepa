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
    $frequencyUnits = CRM_Core_OptionGroup::values('recur_frequency_units');

    foreach($checkUnits as $c) {
      if (array_key_exists($c, $frequencyUnits)) {
        if($frequencyUnits[$c] != $c) {
          error_log(sprintf("org.project60.sepa_dd: label '%s' of option group 'recur_frequency_units' has been changed ['%s']",
          $c,
          $frequencyUnits[$c]));

          if ($reset) {
            $query = "UPDATE civicrm_option_value v,
                             civicrm_option_group g
                      SET v.label = '{$c}'
                      WHERE  v.option_group_id = g.id
                      AND    g.name            = 'recur_frequency_units'
                      AND v.value = '{$c}';";
            CRM_Core_DAO::singleValueQuery($query);

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
