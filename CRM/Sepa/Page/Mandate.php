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
 * SEPA_DD payment processor view component
 *
 * @package CiviCRM_SEPA
 * @todo: deprecated, fix
 */

require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_Mandate extends CRM_Core_Page {
  function run() {

    $r = civicrm_api ("ContributionRecur","getfull", array("version"=>3));
    $this->assign('contributions', $r);

    parent::run();
  }
}
