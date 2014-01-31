<?php

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

    // handle creation of a new mandate 

    if (!CRM_Utils_Array::value('id', $params) && !CRM_Utils_Array::value('reference', $params)) {
      CRM_Utils_SepaCustomisationHooks::create_mandate($params);

      if (!array_key_exists("reference", $params)) {
        $fallback_reference = true;
        // Just need something unique at this point. (Will generate a nicer one once we have the auto ID from the DB -- see further down.)
        $params['reference'] = time() . rand();
      }
      //      CRM_Sepa_Logic_Mandates::fix_initial_contribution($this); not possible to fix from here this undefined, id undefined
    }

    // fix payment processor-created contributions before continuing
    // if (CRM_Utils_Array::value('is_enabled', $params)) {
    //   CRM_Sepa_Logic_Mandates::fix_recurring_contribution($params);
    // }
    
    // handle 'normal' creation process inlcuding hooks
    
    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'SepaMandate', CRM_Utils_Array::value('id', $params), $params);

    // set default date to today
    if (!array_key_exists("date", $params)) {
      $params["date"] = date("YmdHis");
    }

    $dao = new CRM_Sepa_DAO_SEPAMandate();
    $dao->copyValues($params);
    if (self::is_active(CRM_Utils_Array::value('status', $params))) {
      $dao->validation_date = date("YmdHis");
    }
    $dao->save();

    if (isset($fallback_reference) && $fallback_reference) {
      // If no mandate reference was supplied by the caller nor the customisation hook, create a nice default one.
      $creditor = civicrm_api3 ('SepaCreditor', 'getsingle', array ('id' => $params['creditor_id'], 'return' => 'mandate_prefix'));
      $dao->reference = $creditor['mandate_prefix'] . '-' . $params['type'] . '-' . date("Y") . '-' . $dao->id;
      $dao->save();
    }
    
    // if the mandate is enabled, kick off the batching process
    // if (self::is_active(CRM_Utils_Array::value('status', $params))) {
    //   CRM_Sepa_Logic_Batching::batch_initial_contribution($dao->id, $dao);
    // }
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
   * gracefully terminates RCUR mandates 
   */
  static function terminateMandate($mandate_id, $new_end_date_str, $cancel_reason=NULL) {
    $contribution_id_pending = CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name');

     // first, load the mandate
    $mandate = civicrm_api("SepaMandate", "getsingle", array('id'=>$mandate_id, 'version'=>3));
    if (isset($mandate['is_error'])) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot read mandate [%s]. Error was: '%s'"), $mandate_id, $mandate['error_message']), ts('Error'), 'error');
      return;
    }
    
    // check the mandate type
    if ( $mandate['type']!="RCUR" ) {
      CRM_Core_Session::setStatus(ts("You can only modify the end date of recurring contribution mandates."), ts('Error'), 'error');
      return;
    }
    
    // load the contribution
    $contribution_id = $mandate['entity_id'];
    $contribution = civicrm_api('ContributionRecur', "getsingle", array('id'=>$contribution_id, 'version'=>3));
    if (isset($contribution['is_error']) && $contribution['is_error']) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot read contribution [%s]. Error was: '%s'"), $contribution_id, $contribution['error_message']), ts('Error'), 'error');
      return;
    }

    // check the date
    $today = strtotime("today");
    $new_end_date = strtotime($new_end_date_str);

    if ($new_end_date < $today) {
      CRM_Core_Session::setStatus(sprintf(ts("You cannot set an end date in the past."), $contribution_id, $contribution['error_message']), ts('Error'), 'error');
      return;      
    }

    // actually set the date
    $query = array(
      'version'   => 3,
      'id'        => $contribution_id,
      'end_date'  => date('YmdHis', $new_end_date));
    if ($cancel_reason) {
      // FIXME: cancel_reason does not exist in contribution_recur!!
      //$query['cancel_reason'] = $cancel_reason;
      $query['cancel_date'] = $query['end_date'];
    }

    $result = civicrm_api("ContributionRecur", "create", $query);
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Session::setStatus(sprintf(ts("Cannot modify recurring contribution [%s]. Error was: '%s'"), $contribution_id, $result['error_message']), ts('Error'), 'error');
        return;
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
  }
}

