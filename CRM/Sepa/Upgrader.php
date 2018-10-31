<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2018 Project60                           |
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
   * Fixes the damages caused by SEPA-514
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1260() {
    $this->ctx->log->info('Applying update 1260');
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
}
