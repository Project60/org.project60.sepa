<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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
 * This class holds all the functions transaction group life cycle
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
    $group_status_id_open = (int) CRM_Core_OptionGroup::getValue('batch_status', 'Open', 'name');  
    $group_status_id_closed = (int) CRM_Core_OptionGroup::getValue('batch_status', 'Closed', 'name');  
    $status_closed = (int) CRM_Core_OptionGroup::getValue('contribution_status', 'Completed', 'name');  
    $status_inprogress = (int) CRM_Core_OptionGroup::getValue('contribution_status', 'In Progress', 'name');  
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
      WHERE 
        mandate.entity_table = 'civicrm_contribution' AND mandate.entity_id IN (SELECT contribution_id
                              FROM civicrm_sdd_contribution_txgroup
                              WHERE txgroup_id=$txgroup_id);";
      CRM_Core_DAO::executeQuery($sql);    

    } else if ($txgroup['type']=='FRST') {
      // SET first contributions
      $sql = "
      SELECT 
        civicrm_sdd_mandate.id  AS mandate_id,
        civicrm_contribution.id AS contribution_id
      FROM 
        civicrm_sdd_contribution_txgroup
      LEFT JOIN civicrm_contribution       ON civicrm_contribution.id = civicrm_sdd_contribution_txgroup.contribution_id
      LEFT JOIN civicrm_contribution_recur ON civicrm_contribution_recur.id = civicrm_contribution.contribution_recur_id
      LEFT JOIN civicrm_sdd_mandate        ON civicrm_sdd_mandate.entity_id = civicrm_contribution_recur.id
      WHERE civicrm_sdd_contribution_txgroup.txgroup_id=$txgroup_id;";

      $rcontributions = CRM_Core_DAO::executeQuery($sql);
      while ($rcontributions->fetch()) {
        CRM_Core_DAO::executeQuery('UPDATE civicrm_sdd_mandate SET `first_contribution_id`='.$rcontributions->contribution_id.' WHERE `id`='.$rcontributions->mandate_id.';');
      }

      // FRSTs get new status 'RCUR'
      $sql = "
      UPDATE civicrm_sdd_mandate AS mandate
      SET status='RCUR'
      WHERE 
        mandate.entity_table = 'civicrm_contribution_recur' AND mandate.entity_id IN (SELECT civicrm_contribution_recur.id
                              FROM civicrm_sdd_contribution_txgroup
                              LEFT JOIN civicrm_contribution ON civicrm_contribution.id = civicrm_sdd_contribution_txgroup.contribution_id
                              LEFT JOIN civicrm_contribution_recur ON civicrm_contribution_recur.id = civicrm_contribution.contribution_recur_id
                              WHERE civicrm_sdd_contribution_txgroup.txgroup_id=$txgroup_id);";
      CRM_Core_DAO::executeQuery($sql);

    } else if ($txgroup['type']=='RCUR') {
      // AFAIK there's nothing to do with RCURs...

    } else {
      $lock->release();
      return "Group type '".$txgroup['type']."' not yet supported.";
    }

    // step 3: update all the contributions to status 'in progress', and set the receive_date as collection
    //  remark: don't set receive_date to collection_date any more, it confuses the RCUR batcher (see https://github.com/Project60/sepa_dd/issues/190)
    CRM_Core_DAO::executeQuery("
      UPDATE 
        civicrm_contribution 
      SET 
        contribution_status_id = $status_inprogress
      WHERE id IN 
        (SELECT contribution_id FROM civicrm_sdd_contribution_txgroup WHERE txgroup_id=$txgroup_id);");

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
    $group_status_id_open     = (int) CRM_Core_OptionGroup::getValue('batch_status', 'Open', 'name');  
    $group_status_id_closed   = (int) CRM_Core_OptionGroup::getValue('batch_status', 'Closed', 'name');  
    $group_status_id_received = (int) CRM_Core_OptionGroup::getValue('batch_status', 'Received', 'name');
    $status_pending    = (int) CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name');  
    $status_closed     = (int) CRM_Core_OptionGroup::getValue('contribution_status', 'Completed', 'name');  
    $status_inprogress = (int) CRM_Core_OptionGroup::getValue('contribution_status', 'In Progress', 'name');  
    
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

    // step 1.1: fix contributions, that have no financial transactions. (happens due to a status-bug in civicrm)
    $find_rotten_contributions_sql = "
    SELECT
     contribution.id AS contribution_id
    FROM
      civicrm_sdd_contribution_txgroup AS txn_to_contribution
    LEFT JOIN
      civicrm_contribution AS contribution ON contribution.id = txn_to_contribution.contribution_id
    WHERE
      txn_to_contribution.txgroup_id IN ($txgroup_id)
    AND
      contribution.id NOT IN (SELECT entity_id FROM civicrm_entity_financial_trxn WHERE entity_table='civicrm_contribution');
    ";
    $rotten_contribution = CRM_Core_DAO::executeQuery($find_rotten_contributions_sql);
    while ($rotten_contribution->fetch()) {
      $contribution_id = $rotten_contribution->contribution_id;
      // set these rotten contributions to 'Pending', no 'pay_later'
      CRM_Core_DAO::executeQuery("UPDATE civicrm_contribution SET contribution_status_id=$status_pending, is_pay_later=0 WHERE id=$contribution_id;");
      // now they will get their transactions back when they get set to 'completed' in the next step...
      error_log("org.project60.sepa: reset bad contribution [$contribution_id] to 'Pending'.");
    }

    // step 1.2: in CiviCRM before 4.4.4, the status 'In Progress' => 'Completed' was not allowed:
    if (version_compare(CRM_Utils_System::version(), '4.4.4', '<')) {
      // therefore, we change all these contributions' statuses back to 'Pending'
      $fix_status_query = "
      UPDATE
          civicrm_contribution
      SET
          contribution_status_id = $status_pending,
          is_pay_later = 0
      WHERE 
          contribution_status_id = $status_inprogress
      AND id IN (SELECT contribution_id FROM civicrm_sdd_contribution_txgroup WHERE txgroup_id=$txgroup_id);
      ";
      CRM_Core_DAO::executeQuery($fix_status_query);
    }

    // step 2: update all the contributions
    $find_txgroup_contributions_sql = "
    SELECT
     contribution.id AS contribution_id
    FROM
      civicrm_sdd_contribution_txgroup AS txn_to_contribution
    LEFT JOIN
      civicrm_contribution AS contribution ON contribution.id = txn_to_contribution.contribution_id
    WHERE
      contribution_status_id IN ($status_pending,$status_inprogress)
    AND
      txn_to_contribution.txgroup_id IN ($txgroup_id);
    ";
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
        error_log("org.project60.sepa: ".$result['error_message']);
      }
    }

    // step 3: update group status
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
}
