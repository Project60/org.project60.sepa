<?php
// $Id$

/*
   +--------------------------------------------------------------------+
   | Project60 version 4.3                                              |
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
 * File for the CiviCRM APIv3 sepa_transaction_group functions
 *
 * @package CiviCRM_SEPA
 *
 */


/**
 * Add an SepaTransactionGroup for a contact
 *
 * Allowed @params array keys are:
 *
 * @example SepaTransactionGroupCreate.php Standard Create Example
 *
 * @return array API result array
 * {@getfields sepa_transaction_group_create}
 * @access public
 */
function civicrm_api3_sepa_transaction_group_create($params) {
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Adjust Metadata for Create action
 * 
 * The metadata is used for setting defaults, documentation & validation
 * @param array $params array or parameters determined by getfields
 */
function _civicrm_api3_sepa_transaction_group_create_spec(&$params) {
  $params['reference']['api.required'] = 1;
  $params['type']['api.required'] = 1;
  $params['status_id']['api.default'] = 2; //Close
  $params['sdd_creditor_id']['api.required'] = 1;
  $params['created_date']['api.default'] = 'now';
}

/**
 * Deletes an existing SepaTransactionGroup
 *
 * @param  array  $params
 *
 * @example SepaTransactionGroupDelete.php Standard Delete Example
 *
 * @return boolean | error  true if successfull, error otherwise
 * {@getfields sepa_transaction_group_delete}
 * @access public
 */
function civicrm_api3_sepa_transaction_group_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * Retrieve one or more sepa_transaction_groups
 *
 * @param  array input parameters
 *
 *
 * @example SepaTransactionGroupGet.php Standard Get Example
 *
 * @param  array $params  an associative array of name/value pairs.
 *
 * @return  array api result array
 * {@getfields sepa_transaction_group_get}
 * @access public
 */
function civicrm_api3_sepa_transaction_group_get($params) {
  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

function civicrm_api3_sepa_transaction_group_getdetail($params) {
//  $where = "txgroup.id= txgroup_contrib.txgroup_id AND txgroup_contrib.contribution_id = contrib.id";
  $where = " (1=1) ";
  if (array_key_exists("id",$params)) {
    $group = (int) $params["id"];
    $where .= " AND txgroup.id = $group ";
  }
  if (array_key_exists("file_id",$params)) {
    $file_id = (int) $params["file_id"];
    $where .= " AND sdd_file_id = $file_id ";
  }
  $sql = "
    SELECT
      txgroup.id,
      txgroup.reference,
      sdd_file_id AS file_id,
      txgroup.type,
      txgroup.collection_date,
      txgroup.status_id,
      txgroup.sdd_creditor_id,
      (SELECT count(*) FROM civicrm_sdd_contribution_txgroup WHERE txgroup_id=txgroup.id) AS nb_contrib,
      (SELECT IFNULL(SUM(total_amount), 0) FROM civicrm_contribution WHERE id IN (SELECT contribution_id FROM civicrm_sdd_contribution_txgroup WHERE txgroup_id=txgroup.id)) AS total,
      civicrm_sdd_file.reference AS file
    FROM civicrm_sdd_txgroup AS txgroup
    LEFT JOIN civicrm_sdd_file ON sdd_file_id = civicrm_sdd_file.id
    WHERE $where
    ORDER BY id DESC
  ";
  $dao = CRM_Core_DAO::executeQuery($sql);
  $result= array();
  $total =0;
  while ($dao->fetch()) {
    $result[] = $dao->toArray();
    $total += $dao->total;
  }
  return civicrm_api3_create_success($result, $params, NULL, NULL, $dao, $extraReturnValues = array("total_amount"=>$total));
}


function _civicrm_api3_sepa_transaction_group_close_spec (&$params) {
  $params['id']['api.required'] = 1;
}

function civicrm_api3_sepa_transaction_group_close($params) {
  $result=array();
  // a single query much better for perf, but no hook called. To be revised later?
  $sql="update civicrm_contribution as contrib, civicrm_sdd_contribution_txgroup
    set contribution_status_id=1
    where contribution_id = contrib.id and txgroup_id=%1";
  $dao = CRM_Core_DAO::executeQuery($sql, array(1 => array($params['id'], 'Integer')));
  return civicrm_api3_sepa_transaction_group_create (array("id"=>$params['id'],status_id=>2));
}


function _civicrm_api3_sepa_transaction_group_createnext_spec (&$params) {
  $params['id']['api.required'] = 1;
}

function civicrm_api3_sepa_transaction_group_createnext ($params) {
  $errors=$counter=0;
  $values=array();
  $group = (int) $params["id"];
  if (!$group)
    throw new API_Exception("Incorrect or missing value for group id");
  $contribs = civicrm_api("sepa_contribution_group","getdetail", $params);

  foreach ($contribs["values"] as $old) {
    if (!$old['recur_id'])
      throw new API_Exception("Trying to create next payment for non-recurrent contribution?");
    $date = strtotime(substr($old["receive_date"], 0, 10));
    $next_collectionDate = strtotime ("+". $old["frequency_interval"] . " ".$old["frequency_unit"],$date);
    $next_collectionDate = date('YmdHis', $next_collectionDate);
    $new = $old;
    $new["hash"] = md5(uniqid(rand(), true));
    $new["source"] = "SEPA recurring contribution";
    unset($new["id"]);
    unset($new["contribution_id"]);
    $new["receive_date"] = $next_collectionDate;
    $new["contribution_status_id"]= 2;
    $new["contribution_recur_id"] = $new["recur_id"];     
    unset($new["recur_id"]);

    /*
       CRM_Core_DAO::executeQuery("
       UPDATE civicrm_contribution_recur 
       SET next_sched_contribution = %1 
       WHERE id = %2
       ", array(
       1 => array($next_collectionDate, 'String'),
       2 => array($new["contribution_recur_id"], 'Integer')
       )
       );
     */
    $new["version"] =3;       
    $new["sequential"] =1;       

/*
$total += $new["total_amount"];
++$counter;
continue;
*/
    $result = civicrm_api('contribution', 'create',$new);
    if ($result['is_error']) {
      $output[] = $result['error_message'];
      ++$errors;
      continue;
    } else {
      ++$counter;
      $total += $result["total_amount"];
      $mandate = new CRM_Sepa_BAO_SEPAMandate();
      $contrib = new CRM_Contribute_BAO_Contribution();
      $contrib->get('id', $result["id"]);//it sucks to have to fetch again, just to get the BAO
//      $mandate->get('id', $old["mandate_id"]);
//      $values[] = $result["values"];
      $group = CRM_Sepa_Logic_Batching::batchContributionByCreditor ($contrib, $old["creditor_id"],$old["payment_instrument_id"]);
      $values = $group->toArray();
    }
  }
  if (!$errors) {
    $values["nb_contrib"] = $counter;
    $values["total"]=$total;
    return civicrm_api3_create_success(array($values), $params, 'address', $contrib);
  } else {
    civicrm_api3_create_error("Could not create ".$errors. " new contributions",$output);
  }
}

/**
 */
function _civicrm_api3_sepa_transaction_group_cancelsubmit_spec($params) {
  $params['id']['api.required'] = 1;
}

/**
 */
function civicrm_api3_sepa_transaction_group_cancelsubmit($params) {
  CRM_Sepa_Logic_Batching::cancelSubmit(array('id' => $params['id']));
}
