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
 * Provides the SYSTOPIA alternative batching algorithm
 *
 * @package CiviCRM_SEPA
 *
 */

/**
 * API CALL TO CLOSE TXGROUPs
 */
function civicrm_api3_sepa_alternative_batching_close($params) {
  if (!is_numeric($params['txgroup_id'])) {
    return civicrm_api3_create_error("Required field txgroup_id was not properly set.");
  }

  // step 0: check lock
  $timeout = _sepa_alternative_batching_get_parameter('org.project60.alternative_batching.update.lock_timeout');
  $lock = new CRM_Core_Lock('org.project60.sepa.alternative_batching.update', $timeout);
  if (!$lock->isAcquired()) {
    return civicrm_api3_create_error("alternative_batching is busy. Please wait, process should complete within {$timeout}s.");
  }

  // step 1: gather data
  $txgroup_id = (int) $params['txgroup_id'];
  
  $group_status_id_open = (int) CRM_Core_OptionGroup::getValue('batch_status', 'Open', 'name');  
  $group_status_id_closed = (int) CRM_Core_OptionGroup::getValue('batch_status', 'Closed', 'name');  
  $status_closed = (int) CRM_Core_OptionGroup::getValue('contribution_status', 'Completed', 'name');  
  $status_inprogress = (int) CRM_Core_OptionGroup::getValue('contribution_status', 'In Progress', 'name');  
  $txgroup = civicrm_api('SepaTransactionGroup', 'getsingle', array('id'=>$txgroup_id, 'version'=>3));
  if (isset($result['is_error']) && $result['is_error']) {
    $lock->release();
    return civicrm_api3_create_error("Cannot find transaction group ".$txgroup_id);
  }
  $collection_date = $txgroup['collection_date'];


  // step 2: update the mandates
  if ($txgroup['type']=='OOFF') {
    // OOFFs get new status 'SENT'
    $sql = "
    UPDATE civicrm_sdd_mandate AS mandate
    SET status='SENT'
    WHERE 
      mandate.entity_id IN (SELECT contribution_id 
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
      mandate.entity_id IN (SELECT civicrm_contribution_recur.id 
                            FROM civicrm_sdd_contribution_txgroup
                            LEFT JOIN civicrm_contribution ON civicrm_contribution.id = civicrm_sdd_contribution_txgroup.contribution_id
                            LEFT JOIN civicrm_contribution_recur ON civicrm_contribution_recur.id = civicrm_contribution.contribution_recur_id
                            WHERE civicrm_sdd_contribution_txgroup.txgroup_id=$txgroup_id);";
    CRM_Core_DAO::executeQuery($sql);

  } else if ($txgroup['type']=='RCUR') {
    // AFAIK there's nothing to do with RCURs...

  } else {
    $lock->release();
    return civicrm_api3_create_error("Group type '".$txgroup['type']."' not yet supported.");
  }

  // step 3: update all the contributions to status 'in progress', and set the receive_date as collection
  CRM_Core_DAO::executeQuery("
    UPDATE 
      civicrm_contribution 
    SET 
      contribution_status_id = $status_inprogress,
      receive_date = '$collection_date'
    WHERE id IN 
      (SELECT contribution_id FROM civicrm_sdd_contribution_txgroup WHERE txgroup_id=$txgroup_id);");

  // step 4: create the sepa file
  if (!isset($txgroup['sdd_file_id']) || !$txgroup['sdd_file_id']) {
    // find an available txgroup reference
    $available_name = $name = "SDDXML-".$txgroup['reference'];
    $counter = 1;
    $test_sql = "SELECT id FROM civicrm_sdd_file WHERE reference='%s';";
    while (CRM_Core_DAO::executeQuery(sprintf($test_sql, $available_name))->fetch()) {
      // i.e. available_name is already taken, modify it
      $available_name = $name.'_'.$counter;
      $counter += 1;
      if ($counter>1000) {
        $lock->release();
        return civicrm_api3_create_error("Cannot create file! Unable to find an available file reference.");
      }
    }

    // now that we found an available reference, create the file
    $sepa_file = civicrm_api('SepaSddFile', 'create', array(
          'version'                 => 3,
          'reference'               => $available_name,
          'filename'                => $available_name.'.xml',
          'latest_submission_date'  => $txgroup['latest_submission_date'],
          'created_date'            => date('YmdHis'),
          'created_id'              => CRM_Core_Session::singleton()->get('userID'),
          'status_id'               => $group_status_id_closed)
      );
    if (isset($sepa_file['is_error']) && $sepa_file['is_error']) {
      $lock->release();
      return civicrm_api3_create_error(sprintf(ts("Cannot create file! Error was: '%s'"), $sepa_file['error_message']));
    } else {
      $txgroup['sdd_file_id'] = $sepa_file['id'];
    } 
  }

  // step 5: close the txgroup object
  $result = civicrm_api('SepaTransactionGroup', 'create', array(
        'id'                      => $txgroup_id, 
        'status_id'               => $group_status_id_closed, 
        'sdd_file_id'             => $txgroup['sdd_file_id'],
        'version'                 => 3));
  if (isset($result['is_error']) && $result['is_error']) {
    $lock->release();
    return civicrm_api3_create_error(sprintf(ts("Cannot close transaction group! Error was: '%s'"), $result['error_message']));
  } 

  $lock->release();
  return civicrm_api3_create_success($result, $params);  
}

function _civicrm_api3_sepa_alternative_batching_close_spec (&$params) {
  $params['txgroup_id']['api.required'] = 1;
}


/**
 * API CALL TO MARK TXGROUPs AS 'RECEIVED':
 *    - set txgroup status to 'received'
 *    - change status from 'In Progress' to 'Completed' for all contributions
 *    - (store/update the bank account information)
 *
 * @package CiviCRM_SEPA
 */
function civicrm_api3_sepa_alternative_batching_received($params) {
  if (!is_numeric($params['txgroup_id'])) {
    return civicrm_api3_create_error("Required field txgroup_id was not properly set.");
  }

  // step 0: check lock
  $timeout = _sepa_alternative_batching_get_parameter('org.project60.alternative_batching.update.lock_timeout');
  $lock = new CRM_Core_Lock('org.project60.sepa.alternative_batching.update', $timeout);
  if (!$lock->isAcquired()) {
    return civicrm_api3_create_error("alternative_batching is busy. Please wait, process should complete within {$timeout}s.");
  }

  // step 1: gather data
  $txgroup_id = (int) $params['txgroup_id'];
  
  $group_status_id_open = (int) CRM_Core_OptionGroup::getValue('batch_status', 'Open', 'name');  
  $group_status_id_closed = (int) CRM_Core_OptionGroup::getValue('batch_status', 'Closed', 'name');  
  $group_status_id_received = (int) CRM_Core_OptionGroup::getValue('batch_status', 'Received', 'name');
  $status_pending = (int) CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name');  
  $status_closed = (int) CRM_Core_OptionGroup::getValue('contribution_status', 'Completed', 'name');  
  $status_inprogress = (int) CRM_Core_OptionGroup::getValue('contribution_status', 'In Progress', 'name');  
  
  if (empty($group_status_id_received)) return civicrm_api3_create_error("Status 'Received' does not exist!");

  // step 0: load the group object  
  $txgroup = civicrm_api('SepaTransactionGroup', 'getsingle', array('id'=>$txgroup_id, 'version'=>3));
  if (!empty($txgroup['is_error'])) {
    $lock->release();
    return civicrm_api3_create_error("Cannot find transaction group ".$txgroup_id);
  }
  
  // check status
  if ($txgroup['status_id'] != $group_status_id_closed) {
    $lock->release();
    return civicrm_api3_create_error("Transaction group ".$txgroup_id." is not 'closed'.");
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

  // step 1.2: in CiviCRM pre 4.4.4, the status 'In Progress' => 'Completed' was not allowed:
  if (CRM_Utils_System::version() < '4.4.4') {
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
    txn_to_contribution.txgroup_id IN ($txgroup_id);
  ";
  $contribution = CRM_Core_DAO::executeQuery($find_txgroup_contributions_sql);
  $error_count = 0;
  while ($contribution->fetch()) {
    // update status for $contribution->contribution_id
    $result = civicrm_api('Contribution', 'create', array('id'=>$contribution->contribution_id, 'contribution_status_id'=>$status_closed, 'version'=>3));
    if (!empty($result['is_error'])) {
      $error_count += 1;
      error_log("org.project60.sepa: ".$result['error_message']);
    }
  }

  // step 3: update group status
  $result = civicrm_api('SepaTransactionGroup', 'create', array('id'=>$txgroup_id, 'status_id'=>$group_status_id_received, 'version'=>3));
  if (!empty($result['is_error'])) {
    $lock->release();
    return civicrm_api3_create_error("Cannot update transaction group status for ID ".$txgroup_id);
  }

  // check if there was problems
  if ($error_count) {
    $lock->release();
    return civicrm_api3_create_error("$error_count contributions could not be updated to status 'completed'.");
  }

  $lock->release();
  return civicrm_api3_create_success($result, $params);  
}

function _civicrm_api3_sepa_alternative_batching_received_spec (&$params) {
  $params['txgroup_id']['api.required'] = 1;
}




/**
 * API CALL TO CLOSE MANDATES THAT ENDED
 *
 * @package CiviCRM_SEPA
 *
 */
function civicrm_api3_sepa_alternative_batching_closeended($params) {
  if (!isset($params['mode']) || ($params['mode']!='FRST' && $params['mode']!='RCUR')) {
    // as a default, do both
    $modes = "'FRST','RCUR'";
  } else {
    $modes = "'".$params['mode']."'";
  }

  // check lock
  $timeout = _sepa_alternative_batching_get_parameter('org.project60.alternative_batching.update.lock_timeout');
  $lock = new CRM_Core_Lock('org.project60.sepa.alternative_batching.update', $timeout);
  if (!$lock->isAcquired()) {
    return civicrm_api3_create_error("alternative_batching is busy. Please wait, process should complete within {$timeout}s.");
  }

  $contribution_status_closed = (int) CRM_Core_OptionGroup::getValue('contribution_status', 'Completed', 'name');  

  // first, load all of the mandates, that have run out
  $sql_query = "
    SELECT
      mandate.id AS mandate_id,
      mandate.entity_id AS mandate_entity_id,
      rcontribution.end_date AS end_date
    FROM civicrm_sdd_mandate AS mandate
    INNER JOIN civicrm_contribution_recur AS rcontribution       ON mandate.entity_id = rcontribution.id
    WHERE mandate.type = 'RCUR'
      AND mandate.status IN ($modes)
      AND end_date <= DATE(NOW());";
  $results = CRM_Core_DAO::executeQuery($sql_query);
  $mandates_to_end = array();
  while ($results->fetch()) {
    array_push($mandates_to_end, array('mandate_id'=>$results->mandate_id, 'recur_id'=>$results->mandate_entity_id));
  }
  
  // then, end them one by one
  foreach ($mandates_to_end as $mandate_to_end) {
    $change_mandate = civicrm_api('SepaMandate', 'create', array(
      'id'                      => $mandate_to_end['mandate_id'],
      'status'                  => 'COMPLETE',
      'version'                 => 3));
    if (isset($change_mandate['is_error']) && $change_mandate['is_error']) {
      $lock->release();
      return civicrm_api3_create_error(sprintf("Couldn't set mandate '%s' to 'complete. Error was: '%s'", $mandates_to_end['mandate_id']), $change_mandate['error_message']);
    }

    $change_rcur = civicrm_api('ContributionRecur', 'create', array(
      'id'                      => $mandate_to_end['recur_id'],
      'contribution_status_id'  => $contribution_status_closed,
      'modified_date'           => date('YmdHis'),
      'version'                 => 3));
    if (isset($change_rcur['is_error']) && $change_rcur['is_error']) {
      $lock->release();
      return civicrm_api3_create_error(sprintf("Couldn't set recurring contribution '%s' to 'complete. Error was: '%s'", $mandates_to_end['recur_id']), $change_rcur['error_message']);
    }
  }

  $lock->release();
  return civicrm_api3_create_success($mandates_to_end, $params);
}



/**
 * API CALL TO UPDATE TXGROUPs ("Batching")
 *
 * @package CiviCRM_SEPA
 *
 */
function civicrm_api3_sepa_alternative_batching_update($params) {
  $timeout = _sepa_alternative_batching_get_parameter('org.project60.alternative_batching.update.lock_timeout');
  $lock = new CRM_Core_Lock('org.project60.sepa.alternative_batching.update', $timeout);
  if (!$lock->isAcquired()) {
    return civicrm_api3_create_error("alternative_batching is busy. Please wait, process should complete within {$timeout}s.");
  }

  // get creditor list
  $creditor_query = civicrm_api('SepaCreditor', 'get', array('version' => 3, 'option.limit' => 99999));
  if (!empty($creditor_query['is_error'])) {
    return civicrm_api3_create_error("Cannot get creditor list: ".$creditor_query['error_message']);
  } else {
    $creditors = array();
    foreach ($creditor_query['values'] as $creditor) {
      if ($creditor['mandate_active']) {
        $creditors[] = $creditor['id'];
      }
    }
  }

  if ($params['type']=='OOFF') {
    foreach ($creditors as $creditor_id) {
      $result = _sepa_alternative_batching_update_ooff($params, $creditor_id);
      if (!empty($result['is_error'])) {
        return civicrm_api3_create_error("Error while batching for creditor [$creditor_id]: ".$result['error_message']);
      }
    }

  } elseif ($params['type']=='RCUR' || $params['type']=='FRST') {
    // first: make sure, that there are no outdated mandates:
    civicrm_api3_sepa_alternative_batching_closeended(array("mode"=>$params['type']=='FRST'));

    // then, run the update for recurring mandates
    foreach ($creditors as $creditor_id) {
      $result = _sepa_alternative_batching_update_rcur($params, $creditor_id);
      if (!empty($result['is_error'])) {
        return civicrm_api3_create_error("Error while batching for creditor [$creditor_id]: ".$result['error_message']);
      }
    }


  } else {
    return civicrm_api3_create_error(sprintf("Unknown batching mode '%s'.", $params['type']));
  }

  $lock->release();
  return civicrm_api3_create_success();
}







// ############################################################################
//                              Helper functions
// ############################################################################


/**
 * runs a batching update for all RCUR mandates of the given type
 */
function _sepa_alternative_batching_update_rcur($params, $creditor_id) {
  $mode = $params['type'];
  $horizon = (int) _sepa_alternative_batching_get_parameter("org.project60.alternative_batching.$mode.horizon_days");
  $latest_date = date('Y-m-d', strtotime("+$horizon days"));
  $rcur_notice = (int) _sepa_alternative_batching_get_parameter("org.project60.alternative_batching.$mode.notice");
  $now = strtotime("+$rcur_notice days");
  $group_status_id_open = (int) CRM_Core_OptionGroup::getValue('batch_status', 'Open', 'name');

  // RCUR-STEP 1: find all active/pending RCUR mandates within the horizon that are NOT in a closed batch
  $sql_query = "
    SELECT
      mandate.id AS mandate_id,
      mandate.contact_id AS mandate_contact_id,
      mandate.entity_id AS mandate_entity_id,
      mandate.source AS mandate_source,
      first_contribution.receive_date AS mandate_first_executed,
      rcontribution.cycle_day AS cycle_day,
      rcontribution.frequency_interval AS frequency_interval,
      rcontribution.frequency_unit AS frequency_unit,
      rcontribution.start_date AS start_date,
      rcontribution.cancel_date AS cancel_date,
      rcontribution.end_date AS end_date,
      rcontribution.amount AS rc_amount,
      rcontribution.contact_id AS rc_contact_id,
      rcontribution.financial_type_id AS rc_financial_type_id,
      rcontribution.contribution_status_id AS rc_contribution_status_id,
      rcontribution.campaign_id AS rc_campaign_id,
      rcontribution.payment_instrument_id AS rc_payment_instrument_id
    FROM civicrm_sdd_mandate AS mandate
    INNER JOIN civicrm_contribution_recur AS rcontribution       ON mandate.entity_id = rcontribution.id
    LEFT  JOIN civicrm_contribution       AS first_contribution  ON mandate.first_contribution_id = first_contribution.id
    WHERE mandate.type = 'RCUR'
      AND mandate.status = '$mode'
      AND mandate.creditor_id = $creditor_id;";
  $results = CRM_Core_DAO::executeQuery($sql_query);
  $relevant_mandates = array();
  while ($results->fetch()) {
    // TODO: sanity checks?
    $relevant_mandates[$results->mandate_id] = array(
        'mandate_id'                    => $results->mandate_id,
        'mandate_contact_id'            => $results->mandate_contact_id,
        'mandate_entity_id'             => $results->mandate_entity_id,
        'mandate_first_executed'        => $results->mandate_first_executed,
        'mandate_source'                => $results->mandate_source,
        'cycle_day'                     => $results->cycle_day,
        'frequency_interval'            => $results->frequency_interval,
        'frequency_unit'                => $results->frequency_unit,
        'start_date'                    => $results->start_date,
        'end_date'                      => $results->end_date,
        'cancel_date'                   => $results->cancel_date,
        'rc_contact_id'                 => $results->rc_contact_id,
        'rc_amount'                     => $results->rc_amount,
        'rc_financial_type_id'          => $results->rc_financial_type_id,
        'rc_contribution_status_id'     => $results->rc_contribution_status_id,
        'rc_campaign_id'                => $results->rc_campaign_id,
        'rc_payment_instrument_id'      => $results->rc_payment_instrument_id,
      );
  }

  // RCUR-STEP 2: calculate next execution date
  $mandates_by_nextdate = array();
  foreach ($relevant_mandates as $mandate) {
    $next_date = _sepa_alternative_get_next_execution_date($mandate, $now);
    if ($next_date==NULL) continue;
    if ($next_date > $latest_date) continue;

    if (!isset($mandates_by_nextdate[$next_date]))
      $mandates_by_nextdate[$next_date] = array();
    array_push($mandates_by_nextdate[$next_date], $mandate);
  }


  // RCUR-STEP 3: find already created contributions
  $existing_contributions_by_recur_id = array();  
  foreach ($mandates_by_nextdate as $collection_date => $mandates) {
    $rcontrib_ids = array();
    foreach ($mandates as $mandate) {
      array_push($rcontrib_ids, $mandate['mandate_entity_id']);
    }
    $rcontrib_id_strings = implode(',', $rcontrib_ids);

    $sql_query = "
      SELECT
        contribution_recur_id, id
      FROM civicrm_contribution
      WHERE contribution_recur_id in ($rcontrib_id_strings)
        AND receive_date = '$collection_date';";
    $results = CRM_Core_DAO::executeQuery($sql_query);
    while ($results->fetch()) {
      $existing_contributions_by_recur_id[$results->contribution_recur_id] = $results->id;
    }
  }

  // RCUR-STEP 4: create the missing contributions, store all in $mandate['mandate_entity_id']
  foreach ($mandates_by_nextdate as $collection_date => $mandates) {
    foreach ($mandates as $index => $mandate) {
      $recur_id = $mandate['mandate_entity_id'];
      if (isset($existing_contributions_by_recur_id[$recur_id])) {
        // if the contribution already exists, store it
        $contribution_id = $existing_contributions_by_recur_id[$recur_id];
        unset($existing_contributions_by_recur_id[$recur_id]);
        $mandates_by_nextdate[$collection_date][$index]['mandate_entity_id'] = $contribution_id;
      } else {
        // else: create it
        $contribution_data = array(
            "version"                             => 3,
            "total_amount"                        => $mandate['rc_amount'],
            "receive_date"                        => $collection_date,
            "contact_id"                          => $mandate['rc_contact_id'],
            "contribution_recur_id"               => $recur_id,
            "source"                              => $mandate['mandate_source'],
            "financial_type_id"                   => $mandate['rc_financial_type_id'],
            "contribution_status_id"              => $mandate['rc_contribution_status_id'],
            "campaign_id"                         => $mandate['rc_campaign_id'],
            "payment_instrument_id"               => $mandate['rc_payment_instrument_id'],
          );
        $contribution = civicrm_api('Contribution', 'create', $contribution_data);
        if (!empty($contribution['is_error'])) {
          // TODO: Error handling
          error_log("org.project60.sepa: alternative_batching:updateRCUR/createContrib ".$contribution['error_message']);
        }
        $mandates_by_nextdate[$collection_date][$index]['mandate_entity_id'] = $contribution['id'];
        unset($existing_contributions_by_recur_id[$recur_id]);
      }
    }
  }

  // delete unused contributions:
  foreach ($existing_contributions_by_recur_id as $contribution_id) {
    // TODO: code...
    print_r("TODO: DELETE!!!");
  }

  // step 5: find all existing OPEN groups in the horizon
  $sql_query = "
    SELECT
      txgroup.collection_date AS collection_date,
      txgroup.id AS txgroup_id
    FROM civicrm_sdd_txgroup AS txgroup
    WHERE txgroup.collection_date <= '$latest_date'
      AND txgroup.type = '$mode'
      AND txgroup.status_id = $group_status_id_open;";
  $results = CRM_Core_DAO::executeQuery($sql_query);
  $existing_groups = array();
  while ($results->fetch()) {
    $collection_date = date('Y-m-d', strtotime($results->collection_date));
    $existing_groups[$collection_date] = $results->txgroup_id;
  }

  // step 6: sync calculated group structure with existing (open) groups
  return _sepa_alternative_batching_sync_groups($mandates_by_nextdate, $existing_groups, $mode, 'RCUR', $rcur_notice, $creditor_id);
}


/**
 * runs a batching update for all OOFF mandates
 */
function _sepa_alternative_batching_update_ooff($params, $creditor_id) {
  $horizon = (int) _sepa_alternative_batching_get_parameter('org.project60.alternative_batching.OOFF.horizon_days');
  $ooff_notice = (int) _sepa_alternative_batching_get_parameter('org.project60.alternative_batching.OOFF.notice');
  $group_status_id_open = (int) CRM_Core_OptionGroup::getValue('batch_status', 'Open', 'name');
  
  // step 1: find all active/pending OOFF mandates within the horizon that are NOT in a closed batch
  $sql_query = "
    SELECT
      mandate.id                AS mandate_id,
      mandate.contact_id        AS mandate_contact_id,
      mandate.entity_id         AS mandate_entity_id,
      contribution.receive_date AS start_date
    FROM civicrm_sdd_mandate AS mandate
    INNER JOIN civicrm_contribution AS contribution  ON mandate.entity_id = contribution.id
    WHERE contribution.receive_date <= (NOW() + INTERVAL $horizon DAY)
      AND mandate.type = 'OOFF'
      AND mandate.status = 'OOFF'
      AND mandate.creditor_id = $creditor_id;";
  $results = CRM_Core_DAO::executeQuery($sql_query);
  $relevant_mandates = array();
  while ($results->fetch()) {
    // TODO: sanity checks?
    $relevant_mandates[$results->mandate_id] = array(
        'mandate_id'          => $results->mandate_id,
        'mandate_contact_id'  => $results->mandate_contact_id,
        'mandate_entity_id'   => $results->mandate_entity_id,        
        'start_date'          => $results->start_date,
      );
  }

  // step 2: group mandates in collection dates
  $calculated_groups = array();
  $earliest_collection_date = date('Y-m-d', strtotime("+$ooff_notice days"));
  $latest_collection_date = '';

  foreach ($relevant_mandates as $mandate_id => $mandate) {
    $collection_date = date('Y-m-d', strtotime($mandate['start_date']));
    if ($collection_date <= $earliest_collection_date) {
      $collection_date = $earliest_collection_date;
    }

    if (!isset($calculated_groups[$collection_date])) {
      $calculated_groups[$collection_date] = array();
    }

    array_push($calculated_groups[$collection_date], $mandate);

    if ($collection_date > $latest_collection_date) {
      $latest_collection_date = $collection_date;
    }
  }
  if (!$latest_collection_date) {
    // nothing to do...
    return array();
  }

  // step 3: find all existing OPEN groups in the horizon
  $sql_query = "
    SELECT
      txgroup.collection_date AS collection_date,
      txgroup.id AS txgroup_id
    FROM civicrm_sdd_txgroup AS txgroup
    WHERE txgroup.collection_date <= '$latest_collection_date'
      AND txgroup.type = 'OOFF'
      AND txgroup.status_id = $group_status_id_open;";
  $results = CRM_Core_DAO::executeQuery($sql_query);
  $existing_groups = array();
  while ($results->fetch()) {
    $collection_date = date('Y-m-d', strtotime($results->collection_date));
    $existing_groups[$collection_date] = $results->txgroup_id;
  }

  // step 4: sync calculated group structure with existing (open) groups
  return _sepa_alternative_batching_sync_groups($calculated_groups, $existing_groups, 'OOFF', 'OOFF', $ooff_notice, $creditor_id);
}



/**
 * subroutine to create the group/contribution structure as calculated
 */
function _sepa_alternative_batching_sync_groups($calculated_groups, $existing_groups, $mode, $type, $notice, $creditor_id) {
  $group_status_id_open = (int) CRM_Core_OptionGroup::getValue('batch_status', 'Open', 'name');

  foreach ($calculated_groups as $collection_date => $mandates) {
    CRM_Utils_SepaCustomisationHooks::defer_collection_date($collection_date, $creditor_id);
    if (!isset($existing_groups[$collection_date])) {
      // this group does not yet exist -> create
      
      // find unused reference
      $reference = "TXG-${creditor_id}-${mode}-${collection_date}";
      $counter = 0;
      while (_sepa_alternative_batching_groups_reference_exists($reference)) {
        $counter += 1;
        $reference = "TXG-${creditor_id}-${mode}-${collection_date}_".$counter;
      }

      $group = civicrm_api('SepaTransactionGroup', 'create', array(
          'version'                 => 3, 
          'reference'               => $reference,
          'type'                    => $mode,
          'collection_date'         => $collection_date,
          'latest_submission_date'  => date('Y-m-d', strtotime("-$notice days", strtotime($collection_date))),
          'created_date'            => date('Y-m-d'),
          'status_id'               => $group_status_id_open,
          'sdd_creditor_id'         => $creditor_id,
          ));
      if (!empty($group['is_error'])) {
        // TODO: Error handling
        error_log("org.project60.sepa: alternative_batching:syncGroups/createGroup ".$group['error_message']);
      }
    } else {
      $group = civicrm_api('SepaTransactionGroup', 'getsingle', array('version' => 3, 'id' => $existing_groups[$collection_date], 'status_id' => $group_status_id_open));
      if (!empty($group['is_error'])) {
        // TODO: Error handling
        error_log("org.project60.sepa: alternative_batching:syncGroups/getGroup ".$group['error_message']);
      }
      unset($existing_groups[$collection_date]);      
    }

    // now we have the right group. Prepare some parameters...
    $group_id = $group['id'];
    $entity_ids = array();
    foreach ($mandates as $mandate) {
      // remark: "mandate_entity_id" in this case means the contribution ID
      if (empty($mandate['mandate_entity_id'])) {
        // this shouldn't happen
        error_log("org.project60.sepa: alternative_batching:syncGroups mandate with bad mandate_entity_id ignored:" . $mandate['mandate_id']);
      } else {
        array_push($entity_ids, $mandate['mandate_entity_id']);
      }
    }
    if (count($entity_ids)<=0) continue;

    // now, filter out the entity_ids that are are already in a non-open group
    //   (DO NOT CHANGE CLOSED GROUPS!)
    $entity_ids_list = implode(',', $entity_ids);
    $already_sent_contributions = CRM_Core_DAO::executeQuery("
      SELECT contribution_id 
      FROM civicrm_sdd_contribution_txgroup 
      LEFT JOIN civicrm_sdd_txgroup ON civicrm_sdd_contribution_txgroup.txgroup_id = civicrm_sdd_txgroup.id
      WHERE contribution_id IN ($entity_ids_list)
       AND  civicrm_sdd_txgroup.status_id <> $group_status_id_open;");
    while ($already_sent_contributions->fetch()) {
      $index = array_search($already_sent_contributions->contribution_id, $entity_ids);
      if ($index !== false) unset($entity_ids[$index]);
    }
    if (count($entity_ids)<=0) continue;

    // remove all the unwanted entries from our group
    $entity_ids_list = implode(',', $entity_ids);
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_sdd_contribution_txgroup WHERE txgroup_id=$group_id AND contribution_id NOT IN ($entity_ids_list);");

    // remove all our entries from other groups, if necessary
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_sdd_contribution_txgroup WHERE txgroup_id!=$group_id AND contribution_id IN ($entity_ids_list);");

    // now check which ones are already in our group...
    $existing = CRM_Core_DAO::executeQuery("SELECT * FROM civicrm_sdd_contribution_txgroup WHERE txgroup_id=$group_id AND contribution_id IN ($entity_ids_list);");
    while ($existing->fetch()) {
      // remove from entity ids, if in there:
      if(($key = array_search($existing->contribution_id, $entity_ids)) !== false) {
        unset($entity_ids[$key]);
      } 
    }

    // the remaining must be added
    foreach ($entity_ids as $entity_id) {
      CRM_Core_DAO::executeQuery("INSERT INTO civicrm_sdd_contribution_txgroup (txgroup_id, contribution_id) VALUES ($group_id, $entity_id);");
    }
  }

  // print_r("<pre>");
  // print_r($calculated_groups);
  // print_r("</pre>");

  // CLEANUP: remove nonexisting contributions from groups
  CRM_Core_DAO::executeQuery("
    DELETE FROM civicrm_sdd_contribution_txgroup 
    WHERE contribution_id NOT IN (SELECT id FROM civicrm_contribution);");

  // CLEANUP: delete empty groups
  $empty_group_query = CRM_Core_DAO::executeQuery("
    SELECT id, sdd_file_id 
    FROM civicrm_sdd_txgroup 
    WHERE type = '$mode'
      AND status_id = $group_status_id_open
      AND id NOT IN (SELECT txgroup_id FROM civicrm_sdd_contribution_txgroup);");
  while ($empty_group_query->fetch()) {
    if (!in_array($empty_group_query->id, $existing_groups))
      array_push($existing_groups, $empty_group_query->id);
    if ($empty_group_query->sdd_file_id) {
      error_log("org.project60.sepa: WARNING: txgroup ".$empty_group_query->id." should be deleted, but already has a file. Trouble ahead.");
    }
  }

  // now, use the API to delete all these groups
  foreach ($existing_groups as $group_id) {
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_sdd_contribution_txgroup WHERE txgroup_id=$group_id;");
    $result = civicrm_api('SepaTransactionGroup', 'delete', array('version' => 3, 'id' => $group_id));
    if (isset($result['is_error']) && $result['is_error']) {
      error_log("org.project60.sepa: Cannot delete txgroup ".$group_id.". Error was ".$result['error_message']);
    }
  }

  return civicrm_api3_create_success();
}



// TODO: use config
function _sepa_alternative_batching_get_parameter($parameter_name) {
  if ($parameter_name=='org.project60.alternative_batching.OOFF.horizon_days') {
    return 30;
  } else if ($parameter_name=='org.project60.alternative_batching.OOFF.notice') {
    return 8;
  } else if ($parameter_name=='org.project60.alternative_batching.RCUR.horizon_days') {
    return 30;
  } else if ($parameter_name=='org.project60.alternative_batching.RCUR.notice') {
    return 8;
  } else if ($parameter_name=='org.project60.alternative_batching.FRST.horizon_days') {
    return 30;
  } else if ($parameter_name=='org.project60.alternative_batching.FRST.notice') {
    return 8;
  } else if ($parameter_name=='org.project60.alternative_batching.update.lock_timeout') {
    return 170;
  } else {
    error_log("org.project60.sepa: get_parameter for unknown key: $parameter_name");
  }
}

function _sepa_alternative_get_next_execution_date($rcontribution, $now) {
  $now =  strtotime(date('Y-m-d', $now));     // ignore time of day
  $cycle_day = $rcontribution['cycle_day'];
  $interval = $rcontribution['frequency_interval'];
  $unit = $rcontribution['frequency_unit'];

  // calculate the first date
  $start_date = strtotime($rcontribution['start_date']);
  $next_date = mktime(0, 0, 0, date('n', $start_date) + (date('j', $start_date) > $cycle_day), $cycle_day, date('Y', $start_date));
  $last_run = 0; 
  if (isset($rcontribution['mandate_first_executed']) && strlen($rcontribution['mandate_first_executed'])>0) {
    $last_run = strtotime($rcontribution['mandate_first_executed']);
  }
  
  // take the first next_date that is in the future
  while ( ($next_date < $now) || ($next_date <= $last_run) ) {
    $next_date = strtotime("+$interval $unit", $next_date);
  }

  // and check if it's not after the end_date
  $return_date = date('Y-m-d', $next_date);
  if ($rcontribution['end_date'] && strtotime($rcontribution['end_date'])<$next_date) {
    return NULL;
  }
  // ..or the cancel_date
  if ($rcontribution['cancel_date'] && strtotime($rcontribution['cancel_date'])<$next_date) {
    return NULL;
  }

  return $return_date;
}


function _sepa_alternative_batching_groups_reference_exists($reference) {
  $query = civicrm_api('SepaTransactionGroup', 'getsingle', array('reference'=>$reference, 'version'=>3));
  // this should return an error, if the group exists
  return !(isset($query['is_error']) && $query['is_error']);
}
