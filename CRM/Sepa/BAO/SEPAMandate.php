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

}

