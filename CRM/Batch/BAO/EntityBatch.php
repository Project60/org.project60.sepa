<?php

/**
 * Class contains functions for Sepa mandates
 */
class CRM_Batch_BAO_EntityBatch extends CRM_Batch_DAO_EntityBatch {

  /**
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return object       CRM_Core_BAO_EntityBatch object on success, null otherwise
   * @access public
   * @static (I do apologize, I don't want to)
   */
  static function add(&$params) {
    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'EntityBatch', CRM_Utils_Array::value('id', $params), $params);

    $dao = new CRM_Batch_DAO_EntityBatch();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'EntityBatch', $dao->id, $dao);
    return $dao;
  }

}

