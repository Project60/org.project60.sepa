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
    if (!CRM_Utils_Array::value('id', $params) && !CRM_Utils_Array::value('reference', $params)) {
      // i.e. this mandate is being newly created (no reference set yet...)
      CRM_Utils_SepaCustomisationHooks::create_mandate($params);

      if (!array_key_exists("reference", $params)) {
        // if no mandate reference was set, fallback to this:
        $reference = "SDD-" . date("Y");
        if ($params) {
          $reference .="-" . $params["entity_id"];
        } else {
          $reference .= "-RAND" . sprintf("%08d", rand(0, 999999));
        }
        $params['reference'] = $reference;
      }

      //      CRM_Sepa_Logic_Mandates::fix_initial_contribution($this); not possible to fix from here this undefined, id undefined
    }

   if (CRM_Utils_Array::value('is_enabled', $params)) {
      CRM_Sepa_Logic_Mandates::fix_recurring_contribution($params);     
   }
    $hook = empty($params['id']) ? 'create' : 'edit';
   CRM_Utils_Hook::pre($hook, 'SepaMandate', CRM_Utils_Array::value('id', $params), $params);

    if (!array_key_exists("date", $params)) {
      $params["date"] = date("YmdHis");
    }
    
    $dao = new CRM_Sepa_DAO_SEPAMandate();
    $dao->copyValues($params);
    if (CRM_Utils_Array::value('is_enabled', $params)) { 
      $dao->validation_date  = date("YmdHis");
    }
    $dao->save();
    if (CRM_Utils_Array::value('is_enabled', $params)) { //only batching enabled
      CRM_Sepa_Logic_Batching::batch_initial_contribution($dao->id, $dao);
    }
    CRM_Utils_Hook::post($hook, 'SepaMandate', $dao->id, $dao);
    return $dao;
  }

  /**
   * getContract() returns the contribution or recurring contribution this mandate uses as a contract
   */
  function getContract() {
    $etp = $this->entity_table;
    $eid = $this->entity_id;
//    echo "<br>Entity type is $etp($eid).";
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

