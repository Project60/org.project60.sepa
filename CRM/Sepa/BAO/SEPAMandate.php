<?php

/**
 * Class contains functions for Sepa mandates
 */
class CRM_Sepa_BAO_SEPAMandate extends CRM_Sepa_DAO_SEPAMandate {

  /**
   * Create a mandate reference. Use the logic class for this. 
   * 
   * @param array ref object, eg. the recurring contribution or membership
   * @param string type, ie. "R"ecurring "M"embership 
   * format type+contact_id+"-"+ref object
   */
  function generateReference(&$ref = null, $type = "R") {
    //format 
    // return md5(uniqid(rand(), TRUE));
    return CRM_Sepa_Logic_Mandates::createMandateReference($ref, $type);
  }

  /**
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return object       CRM_Core_BAO_SEPAMandate object on success, null otherwise
   * @access public
   * @static (I do apologize, I don't want to)
   */
  static function add(&$params) {
    if (!CRM_Utils_Array::value('reference', $params)) {
      $params["reference"] = CRM_Sepa_BAO_SEPAMandate::generateReference($params);
    }

    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'SepaMandate', CRM_Utils_Array::value('id', $params), $params);

    if (!array_key_exists("date", $params)) {
      $params["date"] = date("YmdHis");
    }
    //die(print_r($params));
    $dao = new CRM_Sepa_DAO_SEPAMandate();
    $dao->copyValues($params);
    $dao->save();

    // process the new mandate
    $bao = new CRM_Sepa_BAO_SEPAMandate();
    $bao->get('id', $dao->id);
    CRM_Sepa_Logic_Mandates::fix_initial_contribution($bao);

    CRM_Utils_Hook::post($hook, 'SepaMandate', $dao->id, $dao);
    return $dao;
  }

  /**
   * getParent() returns the contribution or recurring contribution this mandate uses as a contract
   */
  function getParent() {
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
   * getParentContribution() returns the 'end' contribution this mandate uses as a contract
   *   MANDATE -> CONTRIBUTION_RECUR -> CONTRIBUTION
   * or
   *   MANDATE -> CONTRIBUTION
   */
  function getParentContribution() {
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

}

