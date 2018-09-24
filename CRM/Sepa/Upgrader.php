<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2018 SYSTOPIA                            |
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

use CRM_Sepa_ExtensionUtil as E;

require_once 'CRM/Sepa/CustomData.php';

/**
 * Collection of upgrade steps.
 */
class CRM_Sepa_Upgrader extends CRM_Sepa_Upgrader_Base {

  /**
   * Installation
   */
  public function install() {
    $this->executeSqlFile('sql/sepa.sql');
  }

  /**
   * Example: Work with entities usually not available during the install step.
   *
   * This method can be used for any post-install tasks. For example, if a step
   * of your installation depends on accessing an entity that is itself
   * created during the installation (e.g., a setting or a managed entity), do
   * so here to avoid order of operation problems.
   */
  public function postInstall() {
    // TODO: anything?
  }

  /**
   * Example: Run a simple query when a module is disabled.
   */
  public function disable() {
    // TODO: disable payment processor
    // CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 1 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a simple query when a module is enabled.
   */
  public function enable() {
    $customData = new CRM_Sepa_CustomData('org.project60.sepa');
    $customData->syncOptionGroup(__DIR__ . '/../../resources/batch_status_option_group.json');
    $customData->syncOptionGroup(__DIR__ . '/../../resources/formats_option_group.json');
    $customData->syncOptionGroup(__DIR__ . '/../../resources/msg_tpl_workflow_contribution_option_group.json');
    $customData->syncOptionGroup(__DIR__ . '/../../resources/payment_instrument_option_group.json');

    // TODO: re-enable payment processor
    // CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 1 WHERE bar = "whiz"');
  }


  /**
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1261() {
    $this->ctx->log->info('Adding new file formats');
    $customData = new CRM_Sepa_CustomData('org.project60.sepa');
    $customData->syncOptionGroup(__DIR__ . '/../../resources/formats_option_group.json');
    return TRUE;
  }

  /**
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1400() {
    // add currency
    $this->ctx->log->info('Added currency field');
    $currency_column = CRM_Core_DAO::singleValueQuery("SHOW COLUMNS FROM `civicrm_sdd_creditor` LIKE 'currency';");
    if (!$currency_column) {
      // doesn't exist yet
      $this->executeSql("ALTER TABLE civicrm_sdd_creditor ADD COLUMN `currency` varchar(3) COMMENT 'currency used by this creditor';");
    }
    $this->executeSql("UPDATE civicrm_sdd_creditor SET currency = 'EUR' WHERE currency IS NULL;");
    return TRUE;
  }

  /**
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1403() {
    // add currency
    $this->ctx->log->info('Added SepaMandateLink entity');
    $this->executeSqlFile('sql/update_1403.sql');
    return TRUE;
  }
}
