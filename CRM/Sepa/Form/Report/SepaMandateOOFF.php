<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2016 SYSTOPIA                       |
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
 * Report on OOFF SEPA mandates
 */
class CRM_Sepa_Form_Report_SepaMandateOOFF extends CRM_Sepa_Form_Report_SepaMandateGeneric {

  /**
   * internal function to init the configuration array (_columns)
   */
  protected function _initColumns() {
    parent::_initColumns();

    // remove filter for type (always OOFF)
    unset($this->_columns['civicrm_sdd_mandate']['fields']['mandate_type']);
    unset($this->_columns['civicrm_sdd_mandate']['filters']['mandate_type']);
  }

  /**
   * internal function to generate where clauses
   */
  protected function _extendWhereClause(&$clauses) {
    $clauses[] = "( type = 'OOFF' )";
  }

  // function alterDisplay(&$rows) {
  //   parent::alterDisplay($rows);
  //   // TODO: further alterations
  // }
}
