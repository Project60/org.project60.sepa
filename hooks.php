<?php

/* should we move to a method in the logic class? not so keen visually on a CRM_Contribute_Form_Contribution_Confirm_validate method
This hook has two aims:
- if there is an iban that is set, validate it
- set the context (it's a sepa payment) to be used in the pre hook
*/

function sepa_civicrm_validateForm ( $formName, &$fields, &$files, &$form, &$errors ){
  if ("CRM_Contribute_Form_Contribution_Main"  == $formName) { 
    require_once("packages/php-iban-1.4.0/php-iban.php");
    if (array_key_exists ("bank_iban",$fields)) {
      if (!verify_iban($fields["bank_iban"])) {
         $errors['bank_iban'] = ts( 'invalid IBAN' );
         return;
      }
      // we use the same function that cleans iban to clean bic
      $fields["bank_bic"] = iban_to_machine_format($fields["bank_bic"]);
      if (!preg_match("/^[0-9a-z]{4}[a-z]{2}[0-9a-z]{2}([0-9a-z]{3})?\z/i", $fields["bank_bic"])) {
         $errors['bank_bic'] = ts( 'invalid BIC' );
      } 
    }
  }

  if ("CRM_Contribute_Form_Contribution_Confirm" == $formName) { 
    $pp= civicrm_api("PaymentProcessor","getsingle"
      ,array("version"=>3,"id"=>$form->_values["payment_processor"]));
    if("Payment_SEPA_DD" != $pp["class_name"])
      return;
    $GLOBALS["sepa_context"]["processor"]=1;
    return;
  }
}

/**
 * This hook makes it possible to implement PRE ooks by definine the appropriate method in a logic class
 * 
 * @param type $op
 * @param type $objectName
 * @param type $id
 * @param type $params
 */
function sepa_civicrm_pre($op, $objectName, $id, &$params) {
  $parts = array(
      'hook',
      'pre',
      strtolower($objectName),
      strtolower($op)
  );
  $methodName = implode('_', $parts);
  CRM_Core_Session::setStatus(ts('SEPA hook response'), $methodName, 'alert');
  if (method_exists('CRM_Sepa_Logic_Mandates', $methodName))
    CRM_Sepa_Logic_Mandates::$methodName($id, $params);
}

/**
 * This hook makes it possible to implement POST ooks by definine the appropriate method in a logic class
 * 
 * @param type $op
 * @param type $objectName
 * @param type $id
 * @param type $params
 */
function sepa_civicrm_post( $op, $objectName, $objectId, &$objectRef ) {
  $parts = array(
      'hook',
      'post',
      strtolower($objectName),
      strtolower($op)
  );
  $methodName = implode('_', $parts);
  //CRM_Core_Session::setStatus(ts('SEPA hook response'), $methodName, 'alert');
  if (method_exists('CRM_Sepa_Logic_Mandates', $methodName)) {
    CRM_Sepa_Logic_Mandates::$methodName($objectId, $objectRef);
  }
}
