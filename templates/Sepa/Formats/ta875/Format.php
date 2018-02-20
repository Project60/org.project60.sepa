<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2016-2018                                |
| Author: @scardinius                                    |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

class CRM_Sepa_Logic_Format_ta875 extends CRM_Sepa_Logic_Format {

  /**
   * gives the option of setting extra variables to the template
   */
  public function assignExtraVariables($template) {
    // TODO: settings?
    $template->assign('ta875_BC_ZP',  '781');     // max: 5 chars
    $template->assign('ta875_EDAT',   date('Ymd'));
    $template->assign('ta875_BC_ZE',  '8390');    // max: 5 chars
    $template->assign('ta875_ESR_TN', '010092520');
  }

  public function getDDFilePrefix() {
    return 'AVNC-';
  }

  public function getFilename($variable_string) {
    return $variable_string.'.LSV';
  }
}
