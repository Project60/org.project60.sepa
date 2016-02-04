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
  public function upgrade_0010() {
    $this->ctx->log->info('Applying upgrade 1.0 (default currency for creditor)');
    $this->executeSqlFile('sql/upgrade_0010.sql');
    return TRUE;
  }
}
