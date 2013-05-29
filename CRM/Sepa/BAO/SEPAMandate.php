<?php

/**
 * Class contains functions for Sepa mandates
 */
class CRM_Sepa_BAO_SEPAMandate extends CRM_Sepa_DAO_SEPAMandate {

  // TODO: generate a more meaningful reference?
  /**
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
      $params["reference"] = CRM_Sepa_BAO_SEPAMandate::generateReference();
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
    $bao->get('id',$dao->id);
    CRM_Sepa_Logic_Mandates::fix_initial_contribution( $bao );

    CRM_Utils_Hook::post($hook, 'SepaMandate', $dao->id, $dao);
    return $dao;
  }

}

