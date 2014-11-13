<?php
/**
 * Class contains functions for Sepa mandates
 */
class CRM_Sepa_BAO_SEPACreditor extends CRM_Sepa_DAO_SEPACreditor {


  /**
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return object       CRM_Core_BAO_SEPACreditor object on success, null otherwise
   * @access public
   * @static
   */
  static function add(&$params) {
    $errors = CRM_Sepa_BAO_SEPAMandate::validate_account($params['iban'], $params['bic'], null);
    if ($errors) {
      throw new CRM_Exception(implode('; ', $errors));
    }

    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'SepaCreditor', CRM_Utils_Array::value('id', $params), $params);

    $dao = new CRM_Sepa_DAO_SEPACreditor();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'SepaCreditor', $dao->id, $dao);
    return $dao;
  }

}

