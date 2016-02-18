<?php

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
  public function upgrade_0130() {
    $this->ctx->log->info('Applying upgrade to 1.3.0 (default currency for creditor)');
    $this->executeSqlFile('sql/upgrade_0130.sql');
    return TRUE;
  }
}
