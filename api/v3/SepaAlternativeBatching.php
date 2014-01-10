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


function civicrm_api3_sepa_alternative_batching_update($params) {
  if ($params['type']=='OOFF') {
    $result = _sepa_alternative_batching_update_ooff($params);
  } else {
    return civicrm_api3_create_error(sprintf("Unknown batching mode '%s'.", $params['type']));
  }
  return civicrm_api3_create_success($result, $params, NULL, NULL, $dao, $extraReturnValues = array("total_amount"=>$total));
}




function _sepa_alternative_batching_update_ooff($params) {
  $horizon = (int) _sepa_alternative_batching_get_parameter('org.project60.alternative_batching.ooff.horizon_days');
  $ooff_notice = (int) _sepa_alternative_batching_get_parameter('org.project60.alternative_batching.ooff.notice');

  // step 1: find all active/pending OOFF mandates within the horizon that are NOT in a closed batch
  $sql_query =
    "SELECT ".
    "  mandate.id AS mandate_id, ".
    "  mandate.date AS mandate_date, ".
    "  mandate.contact_id AS mandate_contact_id, ".
    "  mandate.entity_id AS mandate_entity_id ".
    "FROM civicrm_sdd_mandate AS mandate ".
    "INNER JOIN civicrm_contribution AS contribution  ON mandate.entity_id = contribution.id ".
    "WHERE mandate.date <= (NOW() + INTERVAL $horizon DAY) ".
    "  AND mandate.type = 'OOFF' ".
    "  AND mandate.status = 'OOFF';";
  $results = CRM_Core_DAO::executeQuery($sql_query);
  $relevant_mandates = array();
  while ($results->fetch()) {
    // TODO: sanity checks?
    $relevant_mandates[$results->mandate_id] = array(
        'mandate_id'          => $results->mandate_id,
        'mandate_date'        => $results->mandate_date,
        'mandate_contact_id'  => $results->mandate_contact_id,
        'mandate_entity_id'   => $results->mandate_entity_id,
      );
  }

  // step 2: group mandates in collection dates
  $calculated_groups = array();
  $earliest_collection_date = date('Y-m-d', strtotime("+$ooff_notice days"));
  $latest_collection_date = '';

  foreach ($relevant_mandates as $mandate_id => $mandate) {
    $collection_date = date('Y-m-d', strtotime($mandate['mandate_date']));
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
  foreach ($calculated_groups as $collection_date => $mandates) {
    print_r("Looking into $collection_date<br/>");
    if (!isset($existing_groups[$collection_date])) {
      print_r("Not found<br/>");
      // this group does not yet exist -> create
      $group = civicrm_api('SepaTransactionGroup', 'create', array(
          'version'                 => 3, 
          'reference'               => "Test",
          'type'                    => 'OOFF',
          'collection_date'         => $collection_date,
          'latest_submission_date'  => date('Y-m-d', strtotime("-$ooff_notice days", strtotime($collection_date))),
          'created_date'            => date('Y-m-d'),
          'status_id'               => 2,
          'sdd_creditor_id'         => 3,
          ));
      // TODO: error handling
    } else {
      print_r("Found<br/>");
      $group = civicrm_api('SepaTransactionGroup', 'getsingle', array('version' => 3, 'id' => $existing_groups[$collection_date]));
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

  print_r("<pre>");
  print_r($calculated_groups);
  print_r("</pre>");


  // finally, remove unwanted groups alltogether...
  foreach ($existing_groups as $collection_date => $group_id) {
    $result = civicrm_api('SepaTransactionGroup', 'delete', array('version' => 3, 'id' => $group_id));
    // TODO: error handling
  }

  return array();
}



// TODO: use config
function _sepa_alternative_batching_get_parameter($parameter_name) {
  if ($parameter_name=='org.project60.alternative_batching.ooff.horizon_days') {
    return 30;
  } else if ($parameter_name=='org.project60.alternative_batching.ooff.notice') {
    return 5;
  }
}