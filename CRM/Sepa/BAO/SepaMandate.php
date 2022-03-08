<?php
use CRM_Sepa_ExtensionUtil as E;

class CRM_Sepa_BAO_SepaMandate extends CRM_Sepa_DAO_SepaMandate {

  /**
   * Create a new SepaMandate based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_Sepa_DAO_SepaMandate|NULL
   *
  public static function create($params) {
    $className = 'CRM_Sepa_DAO_SepaMandate';
    $entityName = 'SepaMandate';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  } */

}
