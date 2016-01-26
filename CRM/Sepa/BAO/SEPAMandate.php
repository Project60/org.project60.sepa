<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 TTTP                           |
| Author: X+                                             |
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
 * File for the CiviCRM sepa_mandate business logic
 *
 * @package CiviCRM_SEPA
 *
 */


/**
 * Class contains functions for Sepa mandates
 */
class CRM_Sepa_BAO_SEPAMandate extends CRM_Sepa_DAO_SEPAMandate {

  /**
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return object       CRM_Core_BAO_SEPAMandate object on success, null otherwise
   * @access public
   * @static (I do apologize, I don't want to)
   */
  static function add(&$params) {

    // handle 'normal' creation process inlcuding hooks    
    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'SepaMandate', CRM_Utils_Array::value('id', $params), $params);

    // set default date to today
    if (!array_key_exists("date", $params)) {
      $params["date"] = date("YmdHis");
    }

    if (empty($params['id'])) {
      CRM_Utils_SepaCustomisationHooks::create_mandate($params);

      if (empty($params['reference'])) {
        // If no mandate reference was supplied by the caller nor the customisation hook, create a nice default one.
        $creditor = civicrm_api3 ('SepaCreditor', 'getsingle', array ('id' => $params['creditor_id'], 'return' => 'mandate_prefix'));
        $dao = new CRM_Core_DAO();
        $database = $dao->database();
        $next_id = CRM_Core_DAO::singleValueQuery("SELECT auto_increment FROM information_schema.tables WHERE table_schema='$database' and table_name='civicrm_sdd_mandate';");
        $params['reference'] = $creditor['mandate_prefix'] . '-' . $params['creditor_id'] . '-' . $params['type'] . '-' . date("Y") . '-' . $next_id;
      }
    }

    // validate IBAN / BIC
    if (!empty($params['iban'])) {
      $params['iban'] = strtoupper($params['iban']);           // create uppercase string
      $params['iban'] = str_replace(' ', '', $params['iban']); // strip spaces
      $iban_error = CRM_Sepa_Logic_Verification::verifyIBAN($params['iban']);
      if ($iban_error) throw new CRM_Exception($iban_error . ':' . $params['iban']);      
    }
    
    if (!empty($params['bic'])) {
      $bic_error = CRM_Sepa_Logic_Verification::verifyBIC($params['bic']);
      if ($bic_error) throw new CRM_Exception($bic_error . ':' . $params['bic']);      
    }


    // create the DAO object
    $dao = new CRM_Sepa_DAO_SEPAMandate();
    $dao->copyValues($params);
    if (self::is_active(CRM_Utils_Array::value('status', $params))) {
      if ($dao->validation_date == NULL) {
        $dao->validation_date = date("YmdHis");
      }
    }
    $dao->save();

    CRM_Utils_Hook::post($hook, 'SepaMandate', $dao->id, $dao);
    return $dao;
  }

  static function is_active($mandateStatus) {
    switch ($mandateStatus) {
      case 'INIT' :
      case 'ONHOLD' :
      case 'CANCELLED' :
      case 'INVALID' :
        return false;
        break;
      default :
        return true;
    }
  }
  
  /**
   * getContract() returns the contribution or recurring contribution this mandate uses as a contract
   */
  function getContract() {
    $etp = $this->entity_table;
    $eid = $this->entity_id;
    switch ($etp) {
      case 'civicrm_contribution_recur' :
        $recur = new CRM_Contribute_BAO_ContributionRecur();
        $recur->get('id', $eid);
        return $recur;
        break;
      case 'civicrm_contribution' :
        $contr = new CRM_Contribute_BAO_Contribution();
        $contr->get('id', $eid);
        return $contr;
        break;
      default:
        echo 'Huh ? ' . $etp;
    }
    return null;
  }

  /**
   * findContribution() locates a contribution created within the scope of the contract. 
   *   MANDATE -> CONTRIBUTION_RECUR -> CONTRIBUTION
   * or
   *   MANDATE -> CONTRIBUTION
   */
  public function findContribution() {
    $etp = $this->entity_table;
    $eid = $this->entity_id;
    switch ($etp) {
      case 'civicrm_contribution_recur' :
        $contr = new CRM_Contribute_BAO_Contribution();
        $contr->get('contribution_recur_id', $eid);
        return $contr;
        break;
      case 'civicrm_contribution' :
        $contr = new CRM_Contribute_BAO_Contribution();
        $contr->get('id', $eid);
        return $contr;
        break;
      default:
        echo 'Huh ? ' . $etp;
    }
    return null;
  }

  
  /**
   * Looks like an unused function, candidate for deprecation because of nasty code
   * 
   * @deprecated 
   * @return type
   */
  public function getUnbatchedContributionIds() {
    // 1.determine the contract (ie. find out how to get contributions)
    $contrib = $bao->getContract();

    // 2. if it is a recurring one, get the contribs that match the pattern
    if (is_a($contrib, 'CRM_Contribute_BAO_ContributionRecur')) {
      $query = "SELECT        c.id AS id, b.id  AS batch_id 
                FROM          civicrm_contribution c
                LEFT JOIN     civicrm_entity_batch eb ON ( c.id = eb.entity_id AND eb.entity_table = 'civicrm_contribution' )
                LEFT JOIN     civicrm_batch b ON ( b.id = eb.batch_id )
                WHERE         c.contribution_recur_id = " . $contrib->id . "
                AND           b.type_id IN ( " . 222 . " )
                HAVING        batch_id IS NULL
                ";
      $dao = CRM_Core_DAO::executeQuery($query);
      while ($dao->fetch()) {
        $contribs[] = $dao->id;
      }
      return $contribs;
    }

    if (is_a($contrib, 'CRM_Contribute_BAO_ContributionRecur')) {
      $contribs = array($contrib->id);
    }

    return array();
  }

  /**
   * gracefully terminates OOFF mandates
   * 
   * @return success as boolean
   * @author endres -at- systopia.de 
   */
  static function terminateOOFFMandate($mandate_id, $new_end_date_str, $cancel_reason=NULL, $mandate=NULL) {
    // use a lock, in case somebody is batching just now
    $lock = CRM_Sepa_Logic_Settings::getLock();
    if (empty($lock)) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot close mandate [%s], batching in progress!"), $mandate_id), ts('Error'), 'error');
      return FALSE;
    }

    // if not passed by param, load the mandate
    if ($mandate==NULL || $mandate_id != $mandate['id']) {
      $mandate = civicrm_api("SepaMandate", "getsingle", array('id'=>$mandate_id, 'version'=>3));
      if (isset($mandate['is_error'])) {
        CRM_Core_Session::setStatus(sprintf(ts("Cannot read mandate [%s]. Error was: '%s'"), $mandate_id, $mandate['error_message']), ts('Error'), 'error');
        $lock->release();
        return FALSE;
      }      
    }

    // check if it's really a OOFF mandate
    if ( $mandate['type']!="OOFF" ) {
      error_log("org.project60.sepa: the terminateOOFFMandate method can only modify OOFF mandates!");
      $lock->release();
      return FALSE;
    }

    // check if it's not been SENT yet
    if ( $mandate['status']!='OOFF' && $mandate['status']!='INIT') {
      error_log("org.project60.sepa: the terminateOOFFMandate method can only modify OOFF mandates!");
      $lock->release();
      return FALSE;
    }

    // check if it's not in a closed group (should not be possible, but just to be sure...)
    $contribution_id = $mandate['entity_id'];
    $group_status_id_open = (int) CRM_Core_OptionGroup::getValue('batch_status', 'Open', 'name');
    $is_in_closed_group = CRM_Core_DAO::singleValueQuery("
      SELECT COUNT(civicrm_sdd_contribution_txgroup.id) 
      FROM civicrm_sdd_contribution_txgroup 
      LEFT JOIN civicrm_sdd_txgroup ON civicrm_sdd_contribution_txgroup.txgroup_id = civicrm_sdd_txgroup.id
      WHERE contribution_id = $contribution_id 
      AND status_id <> $group_status_id_open;");
    if ($is_in_closed_group) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot close mandate [%s], it's alread batched in a non-open group!"), $mandate_id), ts('Error'), 'error');
      $lock->release();
      return FALSE;
    }

    // NOW: cancel this mandate
    // first: cancel the mandate entity
    $result = civicrm_api('SepaMandate', 'create', array(
      'version'   => 3,
      'id'        => $mandate_id,
      'status'    => 'INVALID',
    ));
    if (!empty($result['is_error'])) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot properly end mandate [%s]. Error was: '%s'"), $mandate_id, $result['error_message']), ts('Error'), 'warn');
    }

    // then: cancel the associated contribution
    $contribution_id_cancelled = (int) CRM_Core_OptionGroup::getValue('contribution_status', 'Cancelled', 'name');
    $result = civicrm_api('Contribution', 'create', array(
      'version'                   => 3,
      'id'                        => $contribution_id,
      'contribution_status_id'    => $contribution_id_cancelled,
      'cancel_reason'             => $cancel_reason,
      'cancel_date'               => date('YmdHis', strtotime($new_end_date_str))
    ));
    if (!empty($result['is_error'])) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot properly end mandate [%s]. Error was: '%s'"), $mandate_id, $result['error_message']), ts('Error'), 'warn');
    }

    // finally: remove contribution from any open SEPA groups
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_sdd_contribution_txgroup WHERE contribution_id = $contribution_id;");

    $lock->release();
    return TRUE;
  }

  /**
   * gracefully terminates RCUR mandates
   * 
   * @return success as boolean
   * @author endres -at- systopia.de 
   */
  static function terminateMandate($mandate_id, $new_end_date_str, $cancel_reason=NULL) {
    $contribution_id_pending = CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name');
    // use a lock, in case somebody is batching just now
    $lock = CRM_Sepa_Logic_Settings::getLock();
    if (empty($lock)) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot terminate mandate [%s], batching in progress!"), $mandate_id), ts('Error'), 'error');
      return FALSE;
    }

     // first, load the mandate
    $mandate = civicrm_api("SepaMandate", "getsingle", array('id'=>$mandate_id, 'version'=>3));
    if (isset($mandate['is_error'])) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot read mandate [%s]. Error was: '%s'"), $mandate_id, $mandate['error_message']), ts('Error'), 'error');
      $lock->release();
      return FALSE;
    }
    
    // check the mandate type
    if ( $mandate['type']=="OOFF" ) {
      return CRM_Sepa_BAO_SEPAMandate::terminateOOFFMandate($mandate_id, $new_end_date_str, $cancel_reason, $mandate);
    } elseif ( $mandate['type']!="RCUR" ) {
      CRM_Core_Session::setStatus(ts("You can only modify the end date of recurring contribution mandates."), ts('Error'), 'error');
      $lock->release();
      return FALSE;
    }

    // load the contribution
    $contribution_id = $mandate['entity_id'];
    $contribution = civicrm_api('ContributionRecur', "getsingle", array('id'=>$contribution_id, 'version'=>3));
    if (isset($contribution['is_error']) && $contribution['is_error']) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot read contribution [%s]. Error was: '%s'"), $contribution_id, $contribution['error_message']), ts('Error'), 'error');
      $lock->release();
      return FALSE;
    }

    // check the date
    $today = strtotime("today");
    $new_end_date = strtotime($new_end_date_str);

    if ($new_end_date < $today) {
      CRM_Core_Session::setStatus(sprintf(ts("You cannot set an end date in the past."), $contribution_id, $contribution['error_message']), ts('Error'), 'error');
      $lock->release();
      return FALSE;
    }

    // actually set the date
    $query = array(
      'version'   => 3,
      'id'        => $contribution_id,
      'currency'  => $contribution['currency'],
      'end_date'  => date('YmdHis', $new_end_date));
    if ($cancel_reason) {
      // FIXME: cancel_reason does not exist in contribution_recur!!
      //$query['cancel_reason'] = $cancel_reason;
      $query['cancel_date'] = $query['end_date'];
    }

    $result = civicrm_api("ContributionRecur", "create", $query);
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot modify recurring contribution [%s]. Error was: '%s'"), $contribution_id, $result['error_message']), ts('Error'), 'error');
      $lock->release();
      return FALSE;
    }

    // set the cancel reason
    if ($cancel_reason) {
      // ..and create a note, since the contribution_recur does not have cancel_reason
      $note_result = civicrm_api("Note", "create", array(
        'version'       => 3,
        'entity_table'  => 'civicrm_contribution_recur',
        'entity_id'     => $contribution_id,
        'modified_date' => date('YmdHis'),
        'subject'       => 'cancel_reason',
        'note'          => $cancel_reason,
        'privacy'       => 0));
      if (isset($note_result['is_error']) && $note_result['is_error']) {
        CRM_Core_Session::setStatus(sprintf(ts("Cannot set cancel reason for mandate [%s]. Error was: '%s'"), $mandate_id, $note_result['error_message']), ts('Error'), 'warn');
      }
    }

    // find already created contributions that are now obsolete...
    $obsolete_ids = array();
    $deleted_ids = array();
    $obsolete_query = "
    SELECT id
    FROM civicrm_contribution
    WHERE receive_date > '$new_end_date_str'
      AND contribution_recur_id = $contribution_id
      AND contribution_status_id = $contribution_id_pending;";
    $obsolete_ids_query = CRM_Core_DAO::executeQuery($obsolete_query);
    while ($obsolete_ids_query->fetch()) {
      array_push($obsolete_ids, $obsolete_ids_query->id);
    }    

    // ...and delete them:
    foreach ($obsolete_ids as $obsolete_id) {
      $delete_result = civicrm_api("Contribution", "delete", array('id'=>$obsolete_id, 'version'=>3));
      if (isset($delete_result['is_error']) && $delete_result['is_error']) {
        CRM_Core_Session::setStatus(sprintf(ts("Cannot delete scheduled contribution [%s]. Error was: '%s'"), $obsolete_id, $delete_result['error_message']), ts('Error'), 'warn');
      } else {
        array_push($deleted_ids, $obsolete_id);
      }
    }
    if (count($deleted_ids)) {
      // also, remove them from the groups
      $deleted_ids_string = implode(',', $deleted_ids);
      CRM_Core_DAO::executeQuery("DELETE FROM civicrm_sdd_contribution_txgroup WHERE contribution_id IN ($deleted_ids_string);");
    }

    // finally, let the API close the mandate if end_date is now
    if ($new_end_date<=$today) {
      $close_result = civicrm_api("SepaAlternativeBatching", "closeended", array('version'=>3));
      if (isset($close_result['is_error']) && $close_result['is_error']) {
        CRM_Core_Session::setStatus(sprintf(ts("Closing Mandate failed. Error was: '%s'"), $close_result['error_message']), ts('Error'), 'warn');
      }
    }

    CRM_Core_Session::setStatus(ts("New end date set."), ts('Mandate updated.'), 'info');
    CRM_Core_Session::setStatus(ts("Please note, that any <i>closed</i> batches that include this mandate cannot be changed any more - all pending contributions will still be executed."), ts('Mandate updated.'), 'warn');    
  
    if (count($deleted_ids)) {
      CRM_Core_Session::setStatus(sprintf(ts("Successfully deleted %d now obsolete contributions."), count($deleted_ids)), ts('Mandate updated.'), 'info');
    }

    $lock->release();
    return TRUE;
  }


  /**
   * changes the amount of a SEPA mandate
   * 
   * @return success as boolean
   * @author endres -at- systopia.de 
   */
  static function adjustAmount($mandate_id, $adjusted_amount) {
    $adjusted_amount = (float) $adjusted_amount;
    $contribution_id_pending = CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name');

    // use a lock, in case somebody is batching just now
    $lock = CRM_Sepa_Logic_Settings::getLock();
    if (empty($lock)) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot adjust mandate [%s], batching in progress!"), $mandate_id), ts('Error'), 'error');
      return FALSE;
    }

     // first, load the mandate
    $mandate = civicrm_api("SepaMandate", "getsingle", array('id'=>$mandate_id, 'version'=>3));
    if (isset($mandate['is_error'])) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot read mandate [%s]. Error was: '%s'"), $mandate_id, $mandate['error_message']), ts('Error'), 'error');
      $lock->release();
      return FALSE;
    }
    
    // check the mandate type
    if ( $mandate['type']!="RCUR" ) {
      CRM_Core_Session::setStatus(ts("You can only adjust the amount of recurring contribution mandates."), ts('Error'), 'error');
      $lock->release();
      return FALSE;
    }

    // load the contribution
    $contribution_id = $mandate['entity_id'];
    $contribution = civicrm_api('ContributionRecur', "getsingle", array('id'=>$contribution_id, 'version'=>3));
    if (isset($contribution['is_error']) && $contribution['is_error']) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot read contribution [%s]. Error was: '%s'"), $contribution_id, $contribution['error_message']), ts('Error'), 'error');
      $lock->release();
      return FALSE;
    }

    // check the amount
    if ($adjusted_amount <= 0) {
      CRM_Core_Session::setStatus(ts("The amount cannot be changed to zero or less."), ts('Error'), 'error');
      $lock->release();
      return FALSE;
    }

    // check the amount
    $old_amount = (float) $contribution['amount'];
    if ($old_amount == $adjusted_amount) {
      CRM_Core_Session::setStatus(ts("The requested amount is the same as the current one."), ts('Error'), 'error');
      $lock->release();
      return FALSE;
    }

    // modify the amount in the recurring contribution
    $query = array(
      'version'   => 3,
      'id'        => $contribution_id,
      'amount'    => $adjusted_amount,
      'currency'  => $contribution['currency']);
    $result = civicrm_api("ContributionRecur", "create", $query);
    if (!empty($result['is_error'])) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot modify recurring contribution [%s]. Error was: '%s'"), $contribution_id, $result['error_message']), ts('Error'), 'error');
      $lock->release();
      return FALSE;
    }

    // find already created contributions. Those also need to be modified
    $contributions2adjust = array();
    $adjusted_ids = array();
    $find_need2adjust = "
    SELECT id
    FROM civicrm_contribution
    WHERE receive_date >= DATE(NOW())
      AND contribution_recur_id = $contribution_id
      AND contribution_status_id = $contribution_id_pending;";
    $contributions2adjust_query = CRM_Core_DAO::executeQuery($find_need2adjust);
    while ($contributions2adjust_query->fetch()) {
      $contributions2adjust[] = $contributions2adjust_query->id;
    }

    // ...and adjust them:
    foreach ($contributions2adjust as $contribution2adjust_id) {
      $update_result = civicrm_api("Contribution", "create", array(
        'version'                => 3,
        'id'                     => $contribution2adjust_id, 
        'total_amount'           => $adjusted_amount,
        'contribution_status_id' => $contribution_id_pending));
      if (!empty($update_result['is_error'])) {
        CRM_Core_Session::setStatus(sprintf(ts("Cannot update scheduled contribution [%s]. Error was: '%s'"), $contribution2adjust_id, $update_result['error_message']), ts('Error'), 'warn');
      } else {
        array_push($adjusted_ids, $contribution2adjust_id);
      }
    }
  
    if (count($adjusted_ids)) {
      CRM_Core_Session::setStatus(sprintf(ts("Successfully updated %d generated contributions."), count($adjusted_ids)), ts('Mandate updated.'), 'info');
    }

    $lock->release();
    return TRUE;    
  }
}


