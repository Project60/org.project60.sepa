<?php
/**
 * Class contains  functions for Sepa mandates
 */
class CRM_Sepa_BAO_SEPATransactionGroup extends CRM_Sepa_DAO_SEPATransactionGroup {


  /**
   * @param array  $params         (reference ) an assoc array of name/value pairs
   *
   * @return object       CRM_Core_BAO_SEPATransactionGroup object on success, null otherwise
   * @access public
   * @static
   */
  static function add(&$params) {
    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'SepaTransactionGroup', CRM_Utils_Array::value('id', $params), $params);

    $dao = new CRM_Sepa_DAO_SEPATransactionGroup();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'SepaTransactionGroup', $dao->id, $dao);
    return $dao;
  }

  function generateXML () {
    if (empty ($this->id)) {
      CRM_Core_Error::fatal("missing id of the transaction group");
    } 
    $queryParams= array (1=>array($this->id, 'Positive'));
    $query="SELECT c.id, currency, total_amount,receive_date,contribution_recur_id, contribution_status_id, mandate.* FROM civicrm_contribution as c JOIN civicrm_sdd_contribution_txgroup as g on g.contribution_id=c.id JOIN civicrm_sdd_mandate as mandate on c.contribution_recur_id = mandate.entity_id WHERE g.txgroup_id= %1";
    $contrib = CRM_Core_DAO::executeQuery($query, $queryParams);
    $r=array(); 
    $template = CRM_Core_Smarty::singleton();
    while ($contrib->fetch()) {
      $r[]=$contrib->toArray();
    }
print_r($r);
    $template->assign("contributions",$r);
    return $template->fetch('CRM/Sepa/xml/TransactionGroup.tpl');
  }
}

