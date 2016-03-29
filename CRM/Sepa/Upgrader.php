<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2016 Project60                      |
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
 * Collection of upgrade steps.
 */
class CRM_Sepa_Upgrader extends CRM_Sepa_Upgrader_Base {

  /**
   * Upgrade to CiviSEPA 1.3 schema (currency column for sdd_mandate)
   */
  public function upgrade_130() {
    $this->ctx->log->info('Applying upgrade to 1.3.0 (default currency for creditor)');
    $this->executeSqlFile('sql/upgrade_130.sql');
    return TRUE;
  }
}
