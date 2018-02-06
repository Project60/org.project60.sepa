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

/**
 * Collection of upgrade steps.
 */
class CRM_Sepa_Upgrader extends CRM_Sepa_Upgrader_Base {

  /**
   * Example: Run a couple simple queries.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1260() {
    $this->ctx->log->info('Applying update 1.2.6');

    // update status (see https://github.com/Project60/org.project60.sepa/issues/416)
    // 'Pending' -> 'In Progress'
    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_contribution_recur
      SET   contribution_status_id = 5
      WHERE contribution_status_id = 2
        AND civicrm_contribution_recur.id IN (SELECT entity_id
                                              FROM civicrm_sdd_mandate
                                              WHERE entity_table = 'civicrm_contribution_recur')
      ");
    return TRUE;
  }
}
