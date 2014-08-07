<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 TTTP                           |
| Author: X+                                             |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/


/**
 * File for the CiviCRM sepa_transaction_group business logic
 *
 * @package CiviCRM_SEPA
 *
 */


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
        c.id,
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
      
      // try to convert the name into a more acceptable format
      if (function_exists("iconv")){
        $t["display_name"]=iconv("UTF-8", "ASCII//TRANSLIT", $t["display_name"]);
        //french banks like utf8 as long as it's ascii7 only
      }

      // ...but to be sure, replace any remainig illegit characters with '?'
      $t["display_name"] = preg_replace("/[^ 0-9a-zA-Z':?,\-(+.)\/\"]/", '?', $t["display_name"]);

      // create an individual transaction message
      $tx_message = "Thanks.";
      CRM_Utils_SepaCustomisationHooks::modify_txmessage($tx_message, $t, $creditor);
      $t["message"] = $tx_message;

      $r[]=$t;
      if ($creditor_id == null) {
        $creditor_id = $contrib->creditor_id;
      } elseif ($contrib->creditor_id == null) { // it shouldn't happen.
        $contrib->creditor_id = $creditor_id;
      } elseif ($creditor_id != $contrib->creditor_id){
        CRM_Core_Error::fatal("mixed creditors ($creditor_id != {$contrib->creditor_id}) in the group - contribution {$contrib->id}");
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


  /**
   * This method will create the SDD file for the given group
   * 
   * @param txgroup_id  the transaction group for which the file should be created
   * @param override    if true, will override an already existing file and create a new one
   * 
   * @return int id of the sepa file entity created, or an error message string
   */
  static function createFile($txgroup_id, $override = false) {
    $txgroup = civicrm_api('SepaTransactionGroup', 'getsingle', array('id'=>$txgroup_id, 'version'=>3));
    if (isset($txgroup['is_error']) && $txgroup['is_error']) {
      return "Cannot find transaction group ".$txgroup_id;
    }

    if ($override || (!isset($txgroup['sdd_file_id']) || !$txgroup['sdd_file_id'])) {
      // find an available txgroup reference
      $available_name = $name = "SDDXML-".$txgroup['reference'];
      $counter = 1;
      $test_sql = "SELECT id FROM civicrm_sdd_file WHERE reference='%s';";
      while (CRM_Core_DAO::executeQuery(sprintf($test_sql, $available_name))->fetch()) {
        // i.e. available_name is already taken, modify it
        $available_name = $name.'--'.$counter;
        $counter += 1;
        if ($counter>1000) {
          return "Cannot create file! Unable to find an available file reference.";
        }
      }

      $group_status_id_closed = (int) CRM_Core_OptionGroup::getValue('batch_status', 'Closed', 'name');

      // now that we found an available reference, create the file
      $sepa_file = civicrm_api('SepaSddFile', 'create', array(
            'version'                 => 3,
            'reference'               => $available_name,
            'filename'                => $available_name.'.xml',
            'latest_submission_date'  => $txgroup['latest_submission_date'],
            'created_date'            => date('YmdHis'),
            'created_id'              => CRM_Core_Session::singleton()->get('userID'),
            'status_id'               => $group_status_id_closed)
        );
      if (isset($sepa_file['is_error']) && $sepa_file['is_error']) {
        return sprintf(ts("Cannot create file! Error was: '%s'"), $sepa_file['error_message']);
      } else {

        // update the txgroup object
          $result = civicrm_api('SepaTransactionGroup', 'create', array(
                'id'                      => $txgroup_id, 
                'sdd_file_id'             => $sepa_file['id'],
                'version'                 => 3));
          if (isset($result['is_error']) && $result['is_error']) {
            sprintf(ts("Cannot update transaction group! Error was: '%s'"), $result['error_message']);
          } 


        return $sepa_file['id'];
      } 
    }
  }  
}
