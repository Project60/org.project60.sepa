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
   * @static (I do appologize, I don't want to)
   */
  static function add(&$params) {
    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'SepaMandate', CRM_Utils_Array::value('id', $params), $params);

    $dao = new CRM_Sepa_DAO_SEPAMandate();
    $dao->copyValues($params);
    try {
      $dao->save();
    } catch(PEAR_Exception $e) {
      return civicrm_api3_create_error($e->getMessage());
    }
    CRM_Utils_Hook::post($hook, 'SepaMandate', $dao->id, $dao);
    return $this;
  }

}

