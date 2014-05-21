<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 Project60                      |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/


/* should we move to a method in the logic class? not so keen visually on a CRM_Contribute_Form_Contribution_Confirm_validate method
This hook has two aims:
- if there is an iban that is set, validate it
- set the context (it's a sepa payment) to be used in the pre hook
*/

function sepa_civicrm_validateForm ( $formName, &$fields, &$files, &$form, &$errors ){
  /* DISABLED
  $tag = str_replace('_', '', $formName);
  if (stream_resolve_include_path('CRM/Sepa/Hooks/'.$tag.'.php')) {
    $className = 'CRM_Sepa_Hooks_' . $tag;
    if (class_exists($className)) {
      if (method_exists($className, 'validateForm')) {
        CRM_Sepa_Logic_Base::debug(ts('Calling SEPA Hook '), $className . '::validateForm', 'alert');
        $className::validateForm($form);
      }
    }
  } */
  
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

  if ("CRM_Contribute_Form_Contribution_Confirm" == $formName || 
      "CRM_Contribute_Form_Contribution_Main" == $formName) { 
    // check whether this is a SDD contribution, in which case we need to build
    // the context for the mandate logic to pickup up some values
    $pp= civicrm_api("PaymentProcessor","getsingle"
      ,array("version"=>3,"id"=>$form->_values["payment_processor"]));
    if("Payment_SEPA_DD" != $pp["class_name"])
      return;
    $GLOBALS["sepa_context"]["processor_id"] = $pp['id'];

    // get the creditor info as well
    $cred = civicrm_api("SepaCreditor","get"
      ,array("version"=>3,"sequential"=>1,"payment_processor_id"=>$pp['id']));
    if ($cred["count"] == 0) {
       CRM_Core_Error::fatal('creditor not set for the payment processor '. $pp["id"]);   
    }
    $cred = $cred["values"][0];
    $GLOBALS["sepa_context"]["creditor_id"] = $cred['id'];

    $type = $fields['is_recur'] ? 'FRST' : 'OOFF';
    $GLOBALS["sepa_context"]["payment_instrument_id"] = CRM_Core_OptionGroup::getValue('payment_instrument', $type, 'name');
    //CRM_Core_Session::setStatus('Set payment instrument in context to ' . $cred['payment_instrument_id'], '', 'info');

    }

}


function sepa_civicrm_pre($op, $objectName, $id, &$params) {
  // FIXME: move this into validation?
  // disallow the deletion of a (recurring) contribution if it is attached to mandates
  if ($op=='delete' && ($objectName=='Contribution' || $objectName=='ContributionRecur')) {
    if ($objectName=='Contribution') {
      $table = 'civicrm_contribution';
    } else {
      $table = 'civicrm_contribution_recur';
    }

    $query = "SELECT id FROM civicrm_sdd_mandate WHERE entity_id=$id AND entity_table='$table';";
    $result = CRM_Core_DAO::executeQuery($query);
    if ($result->fetch()) {
      die(sprintf(ts("You cannot delete this contribution because it is connected to SEPA mandate [%s]. Delete the mandate instead!"), $result->id));
    }
  }
}

// HOOKS DISABLED! We will use an alternative batching method...

// /**
//  * This hook makes it possible to implement PRE ooks by definine the appropriate method in a logic class
//  * 
//  * @param type $op
//  * @param type $objectName
//  * @param type $id
//  * @param type $params
//  */
// function sepa_civicrm_pre($op, $objectName, $id, &$params) {
//   $parts = array(
//       'hook',
//       'pre',
//       strtolower($objectName),
//       strtolower($op)
//   );
// //  CRM_Sepa_Logic_Base::debug('pre-'.$objectName.'-'.$op);
//   $methodName = implode('_', $parts);

//   if (method_exists('CRM_Sepa_Logic_Mandates', $methodName)) {
//     CRM_Sepa_Logic_Base::debug(ts('Calling SEPA Mandate Logic'), $methodName, 'alert');
//     CRM_Sepa_Logic_Mandates::$methodName($id, $params);
//   } else {
//   }
//   if (method_exists('CRM_Sepa_Logic_Batching', $methodName)) {
//     CRM_Sepa_Logic_Base::debug(ts('Calling SEPA Batching Logic'), $methodName, 'alert');
//     CRM_Sepa_Logic_Batching::$methodName($id, $params);
//   }
// }

// *
//  * This hook makes it possible to implement POST hooks by definine the appropriate method in a logic class
//  * 
//  * @param type $op
//  * @param type $objectName
//  * @param type $id
//  * @param type $params
 
// function sepa_civicrm_post( $op, $objectName, $objectId, &$objectRef ) {
//   $parts = array(
//       'hook',
//       'post',
//       strtolower($objectName),
//       strtolower($op)
//   );
// //  CRM_Sepa_Logic_Base::debug('post-'.$objectName.'-'.$op);
//   $methodName = implode('_', $parts);
//   if (method_exists('CRM_Sepa_Logic_Mandates', $methodName)) {
//     CRM_Sepa_Logic_Base::debug(ts('Calling SEPA Mandate Logic'), $methodName, 'alert');
//     CRM_Sepa_Logic_Mandates::$methodName($objectId, $objectRef);
//   }
//   if (method_exists('CRM_Sepa_Logic_Batching', $methodName)) {
//     CRM_Sepa_Logic_Base::debug(ts('Calling SEPA Batching Logic'), $methodName, 'alert');
//     CRM_Sepa_Logic_Batching::$methodName($objectId, $objectRef);
//   }
// }


// totten's addition
function sepa_civicrm_entityTypes(&$entityTypes) {
  // add my DAO's
  $entityTypes[] = array(
      'name' => 'SepaMandate',
      'class' => 'CRM_Sepa_DAO_SEPAMandate',
      'table' => 'civicrm_sepa_mandate',
  );
  $entityTypes[] = array(
      'name' => 'SepaCreditor',
      'class' => 'CRM_Sepa_DAO_SEPACreditor',
      'table' => 'civicrm_sepa_creditor',
  );
  $entityTypes[] = array(
      'name' => 'SepaTransactionGroup',
      'class' => 'CRM_Sepa_BAO_SEPATransactionGroup',
      'table' => 'civicrm_sepa_txgroup',
  );
  $entityTypes[] = array(
      'name' => 'SepaSddFile',
      'class' => 'CRM_Sepa_DAO_SEPASddFile',
      'table' => 'civicrm_sepa_file',
  );
  $entityTypes[] = array(
      'name' => 'SepaContributionGroup',
      'class' => 'CRM_Sepa_DAO_SEPAContributionGroup',
      'table' => 'civicrm_sepa_contribution_txgroup',
  );
}
