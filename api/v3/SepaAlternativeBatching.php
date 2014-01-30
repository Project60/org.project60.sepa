<?php
// $Id$

/*
 +--------------------------------------------------------------------+
 | Project60 version 4.4                                              |
 +--------------------------------------------------------------------+
 | Copyright TTTP (c) 2004-2013                                       |
 +--------------------------------------------------------------------+
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 * File for the CiviCRM APIv3 sepa_contribution_group functions
 *
 * @package CiviCRM_SEPA
 *
 */

function civicrm_api3_sepa_alternative_batching_close($params) {
  if (!is_numeric($params['txgroup_id'])) {
    return civicrm_api3_create_error("Required field txgroup_id was not properly set.");
  }

  // step 1: gather data
  $txgroup_id = (int) $params['txgroup_id'];
  $status_closed = 1;   // TODO: load from option value
  $status_inprogress = 5;   // TODO: load from option value
  $txgroup = civicrm_api('SepaTransactionGroup', 'getsingle', array('id'=>$txgroup_id, 'version'=>3));
  if (isset($result['is_error']) && $result['is_error']) {
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
    // AFAIK nothing to do with RCURs...

  } else {
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
  $sepa_file = civicrm_api('SepaSddFile', 'create', array(
        'version'                 => 3,
        'reference'               => "SDDXML-".$txgroup['reference'],
        'filename'                => "SDDXML-".$txgroup['reference'].'.xml',
        'latest_submission_date'  => $txgroup['latest_submission_date'],
        'created_date'            => date('Ymdhis'),
        'created_id'              => 2,
        'status_id'               => $status_closed)
    );
  if (isset($sepa_file['is_error']) && $sepa_file['is_error']) {
    return civicrm_api3_create_error(sprintf(ts("Cannot create file! Error was: '%s'"), $sepa_file['error_message']));
  }  

  // step 5: close the txgroup object
  $result = civicrm_api('SepaTransactionGroup', 'create', array(
        'id'                      => $txgroup_id, 
        'status_id'               => $status_closed, 
        'sdd_file_id'             => $sepa_file['id'],
        'version'                 => 3));
  if (isset($result['is_error']) && $result['is_error']) {
    return civicrm_api3_create_error(sprintf(ts("Cannot close transaction group! Error was: '%s'"), $result['error_message']));
  } 

  return civicrm_api3_create_success($result, $params);  
}

function _civicrm_api3_sepa_alternative_batching_close_spec (&$params) {
  $params['txgroup_id']['api.required'] = 1;
}






function civicrm_api3_sepa_alternative_batching_update($params) {
  if ($params['type']=='OOFF') {
    $result = _sepa_alternative_batching_update_ooff($params);
  } elseif ($params['type']=='RCUR' || $params['type']=='FRST') {
    $result = _sepa_alternative_batching_update_rcur($params);
  } else {
    return civicrm_api3_create_error(sprintf("Unknown batching mode '%s'.", $params['type']));
  }
  return civicrm_api3_create_success($result, $params);
}



function _sepa_alternative_batching_update_rcur($params) {
  $mode = $params['type'];
  $horizon = (int) _sepa_alternative_batching_get_parameter("org.project60.alternative_batching.$mode.horizon_days");
  $latest_date = date('Y-m-d', strtotime("+$horizon days"));
  $rcur_notice = (int) _sepa_alternative_batching_get_parameter("org.project60.alternative_batching.$mode.notice");
  $now = strtotime("+$rcur_notice days");

  // step 1: find all active/pending OOFF mandates within the horizon that are NOT in a closed batch
  $sql_query = "
    SELECT
      mandate.id AS mandate_id,
      mandate.contact_id AS mandate_contact_id,
      mandate.entity_id AS mandate_entity_id,
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
      AND mandate.status = '$mode';";
  $results = CRM_Core_DAO::executeQuery($sql_query);
  $relevant_mandates = array();
  while ($results->fetch()) {
    // TODO: sanity checks?
    $relevant_mandates[$results->mandate_id] = array(
        'mandate_id'                    => $results->mandate_id,
        'mandate_contact_id'            => $results->mandate_contact_id,
        'mandate_entity_id'             => $results->mandate_entity_id,
        'mandate_first_executed'        => $results->mandate_first_executed,
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

  // step 2: calculate next execution date
  $mandates_by_nextdate = array();
  foreach ($relevant_mandates as $mandate) {
    $next_date = _sepa_alternative_get_next_execution_date($mandate, $now);
    if ($next_date==NULL) continue;
    if ($next_date > $latest_date) continue;

    if (!isset($mandates_by_nextdate[$next_date]))
      $mandates_by_nextdate[$next_date] = array();
    array_push($mandates_by_nextdate[$next_date], $mandate);
  }


  // step 3: find already created contributions
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

  // step 4: create the missing contributions, store all in $mandate['mandate_entity_id']
  foreach ($mandates_by_nextdate as $collection_date => $mandates) {
    foreach ($mandates as $index => $mandate) {
      $recur_id = $mandate['mandate_entity_id'];
      if (isset($existing_contributions_by_recur_id[$recur_id])) {
        // if the contribtion already exists, store it
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
            "financial_type_id"                   => $mandate['rc_financial_type_id'],
            "contribution_status_id"              => $mandate['rc_contribution_status_id'],
            "campaign_id"                         => $mandate['rc_campaign_id'],
            "payment_instrument_id"               => $mandate['rc_payment_instrument_id'],
          );
        $contribtion = civicrm_api('Contribution', 'create', $contribution_data);
        // TODO: Error handling
        $mandates_by_nextdate[$collection_date][$index]['mandate_entity_id'] = $contribtion['id'];
        unset($existing_contributions_by_recur_id[$recur_id]);
      }
    }
  }

  // print_r("<pre>");
  // print_r($mandates_by_nextdate);
  // print_r("</pre>");

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
      AND txgroup.status_id = 2;";
  $results = CRM_Core_DAO::executeQuery($sql_query);
  $existing_groups = array();
  while ($results->fetch()) {
    $collection_date = date('Y-m-d', strtotime($results->collection_date));
    $existing_groups[$collection_date] = $results->txgroup_id;
  }

  // step 6: sync calculated group structure with existing (open) groups
  return _sepa_alternative_batching_sync_groups($mandates_by_nextdate, $existing_groups, $mode, 'RCUR', $rcur_notice);
}


function _sepa_alternative_batching_update_ooff($params) {
  $horizon = (int) _sepa_alternative_batching_get_parameter('org.project60.alternative_batching.OOFF.horizon_days');
  $ooff_notice = (int) _sepa_alternative_batching_get_parameter('org.project60.alternative_batching.OOFF.notice');

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
      AND mandate.status = 'OOFF';";
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
      AND txgroup.status_id = 2;";
  $results = CRM_Core_DAO::executeQuery($sql_query);
  $existing_groups = array();
  while ($results->fetch()) {
    $collection_date = date('Y-m-d', strtotime($results->collection_date));
    $existing_groups[$collection_date] = $results->txgroup_id;
  }

  // step 4: sync calculated group structure with existing (open) groups
  return _sepa_alternative_batching_sync_groups($calculated_groups, $existing_groups, 'OOFF', 'OOFF', $ooff_notice);
}



function _sepa_alternative_batching_sync_groups($calculated_groups, $existing_groups, $mode, $type, $notice) {
  foreach ($calculated_groups as $collection_date => $mandates) {
    if (!isset($existing_groups[$collection_date])) {
      // this group does not yet exist -> create
      // FIXME: creditor ID
      $creditor_id = 3;

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
          'status_id'               => 2,
          'sdd_creditor_id'         => $creditor_id,
          ));
      // TODO: error handling
    } else {
      $group = civicrm_api('SepaTransactionGroup', 'getsingle', array('version' => 3, 'id' => $existing_groups[$collection_date], 'status_id' => 2));
      // TODO: error handling
      unset($existing_groups[$collection_date]);      
    }

    // now we have the right group. Prepare some parameters...
    $group_id = $group['id'];
    $entity_ids = array();
    foreach ($mandates as $mandate) {
      array_push($entity_ids, $mandate['mandate_entity_id']);
    }
    $entity_ids_list = implode(',', $entity_ids);

    // remove all the unwanted entries from our group
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_sdd_contribution_txgroup WHERE txgroup_id=$group_id AND contribution_id NOT IN ($entity_ids_list);");
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_sdd_contribution_txgroup WHERE txgroup_id!=$group_id AND contribution_id IN ($entity_ids_list);");

    // check which ones are already there...
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

  // finally, remove unwanted groups alltogether...
  foreach ($existing_groups as $collection_date => $group_id) {
    $result = civicrm_api('SepaTransactionGroup', 'delete', array('version' => 3, 'id' => $group_id));
    // TODO: error handling
  }

  return civicrm_api3_create_success();
}



// TODO: use config
function _sepa_alternative_batching_get_parameter($parameter_name) {
  if ($parameter_name=='org.project60.alternative_batching.OOFF.horizon_days') {
    return 30;
  } else if ($parameter_name=='org.project60.alternative_batching.OOFF.notice') {
    return 10;
  } else if ($parameter_name=='org.project60.alternative_batching.RCUR.horizon_days') {
    return 30;
  } else if ($parameter_name=='org.project60.alternative_batching.RCUR.notice') {
    return 8;
  } else if ($parameter_name=='org.project60.alternative_batching.FRST.horizon_days') {
    return 30;
  } else if ($parameter_name=='org.project60.alternative_batching.FRST.notice') {
    return 10;
  }
}

function _sepa_alternative_get_next_execution_date($rcontribution, $now) {
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
