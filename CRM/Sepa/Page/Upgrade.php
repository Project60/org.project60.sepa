<?php
require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_Upgrade extends CRM_Core_Page {
  function run() {
    $messages = array();

    if (!CRM_Core_DAO::checkFieldExists('civicrm_sdd_txgroup', 'is_cor1')) {
      CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_sdd_txgroup` ADD `is_cor1` tinyint COMMENT 'Instrument for payments in this group will be COR1 (true/1) or CORE (false/0).' AFTER `reference`");
      $messages[] = 'Added `civicrm_sdd_txgroup`.`is_cor1`.';
    }

    if (!CRM_Core_DAO::checkFieldExists('civicrm_sdd_creditor', 'extra_advance_days')) {
      CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_sdd_creditor` ADD `extra_advance_days` int unsigned DEFAULT 1 COMMENT 'How many banking days (if any) to add on top of all minimum advance presentation deadlines defined in the SEPA rulebook.'");
      $messages[] = 'Added `civicrm_sdd_creditor`.`extra_advance_days`.';
    }
    if (!CRM_Core_DAO::checkFieldExists('civicrm_sdd_creditor', 'maximum_advance_days')) {
      CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_sdd_creditor` ADD `maximum_advance_days` tinyint DEFAULT 14 COMMENT 'When generating SEPA XML files, include payments up to this many calender days from now. (14 is the minimum banks have to allow according to rulebook.)'");
      $messages[] = 'Added `civicrm_sdd_creditor`.`maximum_advance_days`.';
    }

    $this->assign('messages', $messages);
    parent::run();
  }
}
