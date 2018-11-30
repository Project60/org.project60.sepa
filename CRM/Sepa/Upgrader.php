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

  /**
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1410() {
    // add currency
    $this->ctx->log->info('Adding creditor_type field');
    $currency_column = CRM_Core_DAO::singleValueQuery("SHOW COLUMNS FROM `civicrm_sdd_creditor` LIKE 'creditor_type';");
    if (!$currency_column) {
      // doesn't exist yet
      $this->executeSql("ALTER TABLE civicrm_sdd_creditor ADD COLUMN `creditor_type` varchar(8) COMMENT 'type of the creditor, values are SEPA (default) and PSP';");
    }

    $this->executeSql("UPDATE civicrm_sdd_creditor SET creditor_type = 'SEPA' WHERE creditor_type IS NULL;");
    return TRUE;
  }

  /**
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1411() {
    $this->ctx->log->info('Adding new file formats');
    $customData = new CRM_Sepa_CustomData('org.project60.sepa');
    $customData->syncOptionGroup(E::path('resources/formats_option_group.json'));
    return TRUE;
  }

  /**
   * Fixes the damages caused by SEPA-514
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1412() {
    $this->ctx->log->info('Applying update 1412');
    // set all SEPA recurring contributions in status 'In Progress' to 'Pending'
    $status_pending    = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
    $status_inprogress = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'In Progress');
    CRM_Core_DAO::executeQuery("
        UPDATE civicrm_contribution_recur rcur
        LEFT JOIN civicrm_sdd_mandate  mandate ON mandate.entity_id = rcur.id 
                                               AND mandate.entity_table = 'civicrm_contribution_recur'
          SET rcur.contribution_status_id = {$status_pending}
        WHERE rcur.contribution_status_id = {$status_inprogress}
          AND mandate.id IS NOT NULL;");

    // count number of loose 'In Progress' SEPA contributions,
    //  i.e. the ones that are not in any batch group
    $lost_contributions = CRM_Core_DAO::singleValueQuery("
        SELECT COUNT(*)
        FROM civicrm_contribution contribution
        LEFT JOIN  civicrm_sdd_contribution_txgroup c2txg ON c2txg.contribution_id = contribution.id 
        LEFT JOIN civicrm_sdd_mandate  mandate ON mandate.entity_id = contribution.contribution_recur_id 
                                               AND mandate.entity_table = 'civicrm_contribution_recur'
        WHERE contribution.contribution_status_id = {$status_inprogress}
          AND mandate.id IS NOT NULL
          AND c2txg.id IS NULL;");
    if ($lost_contributions) {
      CRM_Core_Session::setStatus("There seems to be {$lost_contributions} SEPA contributions in status 'In Progress', that are not in any transaction group. This is likely due to the bug SEPA-514, and you might want to check, if these shouldn't be deleted.");
    }

    return TRUE;
  }

  /**
   * Make sure the new PP is available
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1413() {
    $this->ctx->log->info('Applying update 1413');
    // make sure the new payment processor is available
    sepa_pp_enable();
    return TRUE;
  }

  /**
   * Make sure, the payment_processor_id in civicrm_sdd_creditor is NULL.
   *  This *shouldn't* be the set, but if it is, it can cause errors.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1414() {
    $this->ctx->log->info('Applying update 1414');
    CRM_Core_DAO::executeQuery('UPDATE civicrm_sdd_creditor SET payment_processor_id = NULL;');
    return TRUE;
  }

  /**
   * Apply schema changes from previous upgrades to logging schema
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1415() {
    $this->ctx->log->info('Applying update 1415: Fix logging schema');
    $logging = new CRM_Logging_Schema();
    $logging->fixSchemaDifferences();
    return TRUE;
  }

}

