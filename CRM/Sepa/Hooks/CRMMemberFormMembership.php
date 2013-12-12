<?php

class CRM_Sepa_Hooks_CRMMemberFormMembership {

  public static function buildForm ( &$form ) {
    // check if the mandate exists already
    // get $id for membership if exists, is not available in a variable 
    echo $form->getVar( '_crid' );
    $mandate = array();
    if (false) {
      $mandate = civicrm_api("SepaMandate","getsingle",array("version"=>3, "entity_table"=>"civicrm_contibution_recur", "entity_id"=>$id));
    }
    
    // field to select the SDD option
    $form->addElement( 'checkbox',  'is_sdd',      ts('Record SDD Mandate ?'));
    // detail fields
    $form->addElement( 'text',      'mref',        ts('Mandate reference'),   array("size"=>35,"maxlength"=>35))->setValue($mandate["reference"]);
    $form->addElement( 'text',      'bank_iban',   ts('Debtor IBAN'),         array("size"=>34,"maxlength"=>34))->setValue($mandate["iban"]);
    $form->addElement( 'text',      'bank_bic',    ts('Debtor BIC'),          array("size"=>11,"maxlength"=>11))->setValue($mandate["bic"]);
    $form->addElement( 'checkbox',  'sepa_active', ts('Is this mandate active ?'))->setValue($mandate["is_enabled"]);
    
    $form->addElement( 'text',      'sdd_amount',  ts('Amount'),              array("size"=>8,"maxlength"=>8));
    $form->addCurrency( 'sdd_curr', ts('Currency'), NULL);
    
    $form->addElement( 'radio',     'sdd_frequency',  NULL, ' ' . ts('a one-time debit'), '0');
    $form->addElement( 'radio',     'sdd_frequency',  NULL, ' ' . ts('every month'), 1)->setChecked('checked');
    $form->addElement( 'radio',     'sdd_frequency',  NULL, ' ' . ts('every quarter'), '3');
    $form->addElement( 'radio',     'sdd_frequency',  NULL, ' ' . ts('every 6 months'), '6');
    $form->addElement( 'radio',     'sdd_frequency',  NULL, ' ' . ts('every year'), '12');
//      $this->addElement('radio', 'radio_ts', NULL, '', 'ts_all',
//        array('onchange' => $this->getName() . ".toggleSelect.checked = false; toggleCheckboxVals('mark_x_',this); toggleTaskAction( true );")
//      );
      
    $form->addDate('sdd_start_date', ts('First debit on'));
    
    // get this from link between membership_type and creditor
    $form->assign("creditor_name", "HERE GOES CREDITOR NAME");
    
    CRM_Core_Region::instance('page-body')->add(array(
      'template' => 'Sepa/Admin/Form/SepaMandate.tpl'
    ));
  }

  
  public static function validateForm(&$fields, &$files, &$form, &$errors) {
    $is_sdd = CRM_Utils_Array::value('is_sdd', $fields);
    if ($is_sdd) {
      $bank_iban = CRM_Utils_Array::value('bank_iban', $fields);
      if (!$bank_iban) {
        $errors['bank_iban'] = ts('IBAN is a required field');
      }
      $bank_bic = CRM_Utils_Array::value('bank_bic', $fields);
      if (!$bank_bic) {
        $errors['bank_bic'] = ts('BIC is a required field');
      }
      $amt = floatval(CRM_Utils_Array::value('sdd_amount', $fields));
      if (!$amt) {
        $errors['sdd_amount'] = ts('Debit amount invalid/empty');
      }
      // validate uniqueness of the mandate reference
      $mref = CRM_Utils_Array::value('mref', $fields);
      if ($mref) {
        $r = civicrm_api3('SepaMandate', 'get', array('reference' => $mref));
        if ($r['count'] > 0) {
          $errors['mref'] = ts('This mandate reference already exists');
        }
      }
    }
  }
 
  
public static function postProcess( &$form ) {
    $membership_id = isset($GLOBALS["sepa_context"]["membership_id"]) ? $GLOBALS["sepa_context"]["membership_id"] : null;
    if ($membership_id) {
      $is_sdd = $form->_submitValues['is_sdd'];
      if ($is_sdd) {
        $newMandate = array();
        $sepa_fields = array (
            "mref"=>"reference",
            "bank_iban"=>"iban",
            'bank_bic'=>"bic",
            "sepa_active"=>"is_enabled",
            "sdd_start_date"=>"contract_start_date",
            'sdd_frequency'=>"contract_frequency",
            'sdd_amount'=>"contract_amount",
            'sdd_curr'=>"contract_currency",
            );
        foreach ($sepa_fields as $field => $api) {
          $newMandate[$api] = $form->_submitValues[$field];
        }
        $newMandate['contract_start_date'] = CRM_Utils_Date::processDate($newMandate['contract_start_date']);
        CRM_Sepa_Logic_Mandates::handleMembershipSepaPayment($membership_id,$newMandate);
      }
    }
}

}
