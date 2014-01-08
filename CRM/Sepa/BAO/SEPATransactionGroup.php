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
    $this->total=0;
    $this->nbtransactions=0;

    $group = civicrm_api ("SepaTransactionGroup","getsingle",array("sequential"=>1,"version"=>3,"id"=>$this->id));
    $creditor_id = $group["sdd_creditor_id"];
    $template->assign("group",$group );
    $creditor = civicrm_api ("SepaCreditor","getsingle",array("sequential"=>1,"version"=>3,"id"=>$creditor_id));
    $template->assign("creditor",$creditor );
    $this->fileFormat = CRM_Core_OptionGroup::getValue('sepa_file_format', $creditor['sepa_file_format_id'], 'value', 'Integer', 'name');
    $template->assign("fileFormat",$this->fileFormat);
    $queryParams= array (1=>array($this->id, 'Positive'));
    $query="
      SELECT
        c.id AS contribution_id,
        civicrm_contact.display_name,
        invoice_id,
        currency,
        total_amount,
        receive_date,
        contribution_recur_id,
        contribution_status_id,
        mandate.*
      FROM civicrm_contribution AS c
      JOIN civicrm_sdd_contribution_txgroup AS g ON g.contribution_id=c.id
      JOIN civicrm_sdd_mandate AS mandate ON mandate.id = IF(c.contribution_recur_id IS NOT NULL,
        (SELECT id FROM civicrm_sdd_mandate WHERE entity_table = 'civicrm_contribution_recur' AND entity_id = c.contribution_recur_id),
        (SELECT id FROM civicrm_sdd_mandate WHERE entity_table = 'civicrm_contribution' AND entity_id = c.id)
      )
      JOIN civicrm_contact ON c.contact_id = civicrm_contact.id
      WHERE g.txgroup_id = %1
        AND contribution_status_id != 3
        AND mandate.is_enabled = true
    "; //and not cancelled
    $contrib = CRM_Core_DAO::executeQuery($query, $queryParams);

    setlocale(LC_CTYPE, 'en_US.utf8');
    //dear dear, it might work, but seems to be highly dependant of the system running it, without any way to know what is available, or if the setting was done properly #phpeature
 
    while ($contrib->fetch()) {
      $t=$contrib->toArray();
      $t["iban"]=str_replace(array(' ','-'), '', $t["iban"]);
      $t["display_name"]=str_replace('&','+',$t["display_name"]);// french banks don't like & nor &amp;
      if (function_exists("iconv")){
        $t["display_name"]=iconv("UTF-8", "ASCII//TRANSLIT", $t["display_name"]);
        //french banks like utf8 as long as it's ascii7 only
      }

      // create an individual transaction message
      $tx_message = "Merci";
//TODO @systopia      CRM_Utils_SepaCustomisationHooks::modify_txmessage($tx_message, $t, $creditor);
      $t["message"] = $tx_message;

      $r[]=$t;
      if ($creditor_id == null) {
        $creditor_id = $contrib->creditor_id;
      } elseif ($contrib->creditor_id == null) { // it shouldn't happen.
        $contrib->creditor_id = $creditor_id;
      } elseif ($creditor_id != $contrib->creditor_id){
        CRM_Core_Error::fatal("mixed creditors ($creditor_id != {$contrib->creditor_id}) in the group - contribution {$contrib->contribution_id}");
        //to fix the mandate: update civicrm_sdd_mandate set creditor_id=1;
      }
      $this->total += $contrib->total_amount;
      $this->nbtransactions++;
    }
    $template->assign("total",$this->total );
    $template->assign("nbtransactions",$this->nbtransactions);
    $template->assign("contributions",$r);
    return $template->fetch('CRM/Sepa/xml/TransactionGroup.tpl');
  }
}

