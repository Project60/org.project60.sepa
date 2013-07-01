<?php
/**
 * Class contains functions for Sepa mandates
 */
class CRM_Sepa_BAO_SEPASddFile extends CRM_Sepa_DAO_SEPASddFile {


  /**
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return object       CRM_Core_BAO_SEPASddFile object on success, null otherwise
   * @access public
   * @static
   */
  static function add(&$params) {
    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'SepaSddFile', CRM_Utils_Array::value('id', $params), $params);

    $dao = new CRM_Sepa_DAO_SEPASddFile();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'SepaSddFile', $dao->id, $dao);
    return $dao;
  }

}

