<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 SYSTOPIA                       |
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
 * This class holds all the functions transacleanupction group life cycle
 */
class CRM_Sepa_Logic_Group {

  /**
   * This function will close a transaction group,
   * and perform the necessary logical changes to the mandates contained
   *
   * @return error message, unless successful
   */
  static function close($txgroup_id) {
    // step 0: check lock
    $lock = CRM_Sepa_Logic_Settings::getLock();
    if (empty($lock)) {
      return "Batching in progress. Please try again later.";
    }

    // step 1: gather data
    $skip_closed = CRM_Sepa_Logic_Settings::getGenericSetting('sdd_skip_closed');
    if ($skip_closed) {
      $status_inprogress = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');
      $group_status_id_closed = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Received');
    } else {
      $status_inprogress = (int)  CRM_Sepa_Logic_Settings::contributionInProgressStatusId();
      $group_status_id_closed = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Closed');
    }
    $status_closed = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');
    $group_status_id_open = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Open');
    $txgroup = civicrm_api('SepaTransactionGroup', 'getsingle', array('id'=>$txgroup_id, 'version'=>3));
    if (isset($txgroup['is_error']) && $txgroup['is_error']) {
      $lock->release();
      return "Cannot find transaction group ".$txgroup_id;
    }
    $collection_date = $txgroup['collection_date'];


    // step 2: update the mandates
    if ($txgroup['type']=='OOFF') {
      // OOFFs get new status 'SENT'
      $sql = "
      UPDATE civicrm_sdd_mandate AS mandate
      SET status='SENT'
      WHERE mandate.entity_table = 'civicrm_contribution'
        AND mandate.entity_id IN (SELECT contribution_id
                                  FROM civicrm_sdd_contribution_txgroup
                                  WHERE txgroup_id=$txgroup_id);";
      CRM_Core_DAO::executeQuery($sql);

    } else if ($txgroup['type']=='FRST') {
      // update first_contribution and status
      $sql = "UPDATE civicrm_sdd_mandate
              LEFT JOIN civicrm_contribution_recur       ON civicrm_contribution_recur.id = civicrm_sdd_mandate.entity_id
              LEFT JOIN civicrm_contribution             ON civicrm_contribution.contribution_recur_id = civicrm_contribution_recur.id
              LEFT JOIN civicrm_sdd_contribution_txgroup ON civicrm_sdd_contribution_txgroup.contribution_id = civicrm_contribution.id
              SET
                status                = 'RCUR',
                first_contribution_id = civicrm_contribution.id
              WHERE civicrm_sdd_mandate.entity_table = 'civicrm_contribution_recur'
                AND civicrm_sdd_mandate.status = 'FRST'
                AND civicrm_sdd_contribution_txgroup.txgroup_id = {$txgroup_id}";
      CRM_Core_DAO::executeQuery($sql);

      // update the recurring contribution payment instruments
      $creditor_id = CRM_Core_DAO::singleValueQuery("SELECT sdd_creditor_id FROM civicrm_sdd_txgroup WHERE id = {$txgroup_id};");
      $frst2rcur_pis = CRM_Sepa_Logic_PaymentInstruments::getFrst2RcurMapping($creditor_id);
      foreach ($frst2rcur_pis as $frst_pi_id => $rcur_pi_id) {
        // do this for every frst/rcur type tuple separately
        $sql = "
            UPDATE civicrm_contribution_recur
            LEFT JOIN civicrm_contribution             ON civicrm_contribution.contribution_recur_id = civicrm_contribution_recur.id
            LEFT JOIN civicrm_sdd_contribution_txgroup ON civicrm_sdd_contribution_txgroup.contribution_id = civicrm_contribution.id
            SET civicrm_contribution_recur.payment_instrument_id = {$rcur_pi_id}
            WHERE civicrm_contribution_recur.payment_instrument_id = {$frst_pi_id}
              AND civicrm_sdd_contribution_txgroup.txgroup_id = {$txgroup_id}";
        CRM_Core_DAO::executeQuery($sql);
      }

    } else if ($txgroup['type']=='RCUR') {
      // AFAIK there's nothing to do for RCURs...

    } else if ($txgroup['type']=='RTRY') {
      // AFAIK there's nothing to do for RTRYs...

    } else {
      $lock->release();
      return "Group type '".$txgroup['type']."' not yet supported.";
    }

    // step 3.1: update all the contributions to status 'in progress', and set the receive_date as collection
    //  remark: don't set receive_date to collection_date any more, it confuses the RCUR batcher (see https://github.com/Project60/sepa_dd/issues/190)
    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_contribution
      LEFT JOIN civicrm_sdd_contribution_txgroup ON contribution_id = civicrm_contribution.id
      SET contribution_status_id = $status_inprogress
      WHERE txgroup_id = $txgroup_id;");

    // step 3.2: update next_sched_contribution_date
    // TODO: get $collection_date
    CRM_Sepa_Logic_NextCollectionDate::advanceNextCollectionDate($txgroup_id);

    // step 4: create the sepa file
    $xmlfile = civicrm_api('SepaAlternativeBatching', 'createxml', array('txgroup_id'=>$txgroup_id, 'version'=>3));
    if (isset($xmlfile['is_error']) && $xmlfile['is_error']) {
      $lock->release();
      return "Cannot create sepa xml file for group ".$txgroup_id;
    }

    // step 5: close the txgroup object
    $result = civicrm_api('SepaTransactionGroup', 'create', array(
          'id'                      => $txgroup_id,
          'status_id'               => $group_status_id_closed,
          'version'                 => 3));
    if (isset($result['is_error']) && $result['is_error']) {
      $lock->release();
      sprintf(ts("Cannot close transaction group! Error was: '%s'", array('domain' => 'org.project60.sepa')), $result['error_message']);
    }

    $lock->release();
  }




  /**
   * This method will mark the given transaction group as 'received':
   *   - set txgroup status to 'received'
   *   - change status from 'In Progress' to 'Completed' for all contributions
   *   - (store/update the bank account information)
   *
   * @return error message, unless successful
   */
  static function received($txgroup_id) {
    // step 0: check lock
    $lock = CRM_Sepa_Logic_Settings::getLock();
    if (empty($lock)) {
      return "Batching in progress. Please try again later.";
    }

    // step 1: gather data
    $group_status_id_open     = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Open');
    $group_status_id_closed   = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Closed');
    $group_status_id_received = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Received');
    $status_pending    = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
    $status_closed     = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');
    $status_inprogress = (int)  CRM_Sepa_Logic_Settings::contributionInProgressStatusId();

    if (empty($group_status_id_received))
      return civicrm_api3_create_error("Status 'Received' does not exist!");

    if (empty($status_pending) || empty($status_closed) || empty($status_inprogress))
      return civicrm_api3_create_error("Status 'Pending', 'Completed' or 'In Progress' does not exist!");

    // step 0: load the group object
    $txgroup = civicrm_api('SepaTransactionGroup', 'getsingle', array('id'=>$txgroup_id, 'version'=>3));
    if (!empty($txgroup['is_error'])) {
      $lock->release();
      return "Cannot find transaction group ".$txgroup_id;
    }

    // check status
    if ($txgroup['status_id'] != $group_status_id_closed) {
      $lock->release();
      return "Transaction group ".$txgroup_id." is not 'closed'.";
    }

    //  this should only be done in CiviCRM < 4.7.0, otherwise it causes the linked recurring contribution to
    //  switch status, see SEPA-514
    if (version_compare(CRM_Utils_System::version(), '4.7.0', '<')) {
      $find_rotten_contributions_sql = "
        SELECT contribution.id AS contribution_id
        FROM civicrm_sdd_contribution_txgroup AS txn_to_contribution
        LEFT JOIN civicrm_contribution AS contribution ON contribution.id = txn_to_contribution.contribution_id
        WHERE txn_to_contribution.txgroup_id IN ($txgroup_id)
          AND contribution.id NOT IN (SELECT entity_id FROM civicrm_entity_financial_trxn WHERE entity_table='civicrm_contribution');";
      $rotten_contribution = CRM_Core_DAO::executeQuery($find_rotten_contributions_sql);
      while ($rotten_contribution->fetch()) {
        $contribution_id = $rotten_contribution->contribution_id;
        // set these rotten contributions to 'Pending', no 'pay_later'
        CRM_Core_DAO::executeQuery("UPDATE civicrm_contribution SET contribution_status_id=$status_pending, is_pay_later=0 WHERE id=$contribution_id;");
        // now they will get their transactions back when they get set to 'completed' in the next step...
        Civi::log()->debug("org.project60.sepa: reset bad contribution [$contribution_id] to 'Pending'.");
      }
    }

    // step 2: update all the contributions
    $find_txgroup_contributions_sql = "
      SELECT contribution.id AS contribution_id
      FROM civicrm_sdd_contribution_txgroup AS txn_to_contribution
      LEFT JOIN civicrm_contribution AS contribution ON contribution.id = txn_to_contribution.contribution_id
      WHERE contribution_status_id IN ($status_pending,$status_inprogress)
      AND txn_to_contribution.txgroup_id IN ($txgroup_id);";
    $contribution = CRM_Core_DAO::executeQuery($find_txgroup_contributions_sql);
    $error_count = 0;
    while ($contribution->fetch()) {
      // update status for $contribution->contribution_id
      //   and set receive_date to collection_date (see https://github.com/Project60/sepa_dd/issues/190)
      $result = civicrm_api('Contribution', 'create', array(
          'version'                  => 3,
          'id'                       => $contribution->contribution_id,
          'contribution_status_id'   => $status_closed,
          'receive_date'             => date('YmdHis', strtotime($txgroup['collection_date']))));
      if (!empty($result['is_error'])) {
        $error_count += 1;
        Civi::log()->debug("org.project60.sepa: ".$result['error_message']);
      }
    }

    // step 3.1: update next_sched_contribution_date
    // TODO: get $collection_date
    CRM_Sepa_Logic_NextCollectionDate::advanceNextCollectionDate($txgroup_id);

    // step 3.2: update group status
    $result = civicrm_api('SepaTransactionGroup', 'create', array('id'=>$txgroup_id, 'status_id'=>$group_status_id_received, 'version'=>3));
    if (!empty($result['is_error'])) {
      $lock->release();
      return "Cannot update transaction group status for ID ".$txgroup_id;
    }

    // check if there was problems
    if ($error_count) {
      $lock->release();
      return "$error_count contributions could not be updated to status 'completed'.";
    }

    $lock->release();
  }


  /**
   * Do some generic group cleanup:
   * 1) remove stale entries from groups (i.e. contribution doesn't exist any more)
   * 2) delete empty groups
   */
  public static function cleanup($mode) {
    $group_status_id_open = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Open');
    if (empty($group_status_id_open)) return;

    // CLEANUP: remove nonexisting contributions from groups
    CRM_Core_DAO::executeQuery("
      DELETE FROM civicrm_sdd_contribution_txgroup
      WHERE contribution_id NOT IN (SELECT id FROM civicrm_contribution);");

    // CLEANUP: delete empty groups
    $empty_group_query = CRM_Core_DAO::executeQuery("
      SELECT id AS group_id
      FROM civicrm_sdd_txgroup
      WHERE type = '{$mode}'
        AND status_id = {$group_status_id_open}
        AND id NOT IN (SELECT txgroup_id FROM civicrm_sdd_contribution_txgroup);");
    while ($empty_group_query->fetch()) {
      // delete group
      $group_id = $empty_group_query->group_id;
      //CRM_Core_DAO::executeQuery("DELETE FROM civicrm_sdd_contribution_txgroup WHERE txgroup_id={$group_id};");
      $result = civicrm_api3('SepaTransactionGroup', 'delete', array('id' => $group_id));
    }
  }
}
