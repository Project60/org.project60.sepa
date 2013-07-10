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

  function generateXML ($id = null) {
    $template = CRM_Core_Smarty::singleton();
    if ($id) {
      $this->id=$id;
    }
    if (empty ($this->id)) {
      CRM_Core_Error::fatal("missing id of the transaction group");
    } 
    $r=array(); 
    $total=0;
    $nbtransactions=0;

    $group = civicrm_api ("SepaTransactionGroup","getsingle",array("sequential"=>1,"version"=>3,"id"=>$this->id));
    $creditor_id = $group["sdd_creditor_id"];
    $template->assign("group",$group );
    $creditor = civicrm_api ("SepaCreditor","getsingle",array("sequential"=>1,"version"=>3,"id"=>$creditor_id));
    $template->assign("creditor",$creditor );
    $queryParams= array (1=>array($this->id, 'Positive'));
    $query="SELECT c.id, invoice_id,currency, total_amount,receive_date,contribution_recur_id, contribution_status_id, mandate.* FROM civicrm_contribution as c JOIN civicrm_sdd_contribution_txgroup as g on g.contribution_id=c.id JOIN civicrm_sdd_mandate as mandate on c.contribution_recur_id = mandate.entity_id WHERE g.txgroup_id= %1 AND contribution_status_id != 3"; //and not cancelled
    $contrib = CRM_Core_DAO::executeQuery($query, $queryParams);
    while ($contrib->fetch()) {
      $r[]=$contrib->toArray();
      if ($creditor_id == null) {
        $creditor_id = $contrib->creditor_id;
      } elseif ($creditor_id != $contrib->creditor_id){
        CRM_Core_Error::fatal("mixed creditors ($creditor_id <> {$contrib->creditor_id}) in the group - contribution {$contrib->id}");
        //to fix the mandate: update civicrm_sdd_mandate set creditor_id=1;
      }
      $total += $contrib->total_amount;
      $nbtransactions++;
    }
    $template->assign("total",$total );
    $template->assign("message","thanks" );
    $template->assign("nbtransactions",$nbtransactions);
    $template->assign("contributions",$r);
die ($template->fetch('CRM/Sepa/xml/TransactionGroup.tpl'));
    return $template->fetch('CRM/Sepa/xml/TransactionGroup.tpl');
  }
}

