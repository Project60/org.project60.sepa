<?php

/* should we move to a method in the logic class? not so keen visually on a CRM_Contribute_Form_Contribution_Confirm_validate method
This hook has two aims:
- if there is an iban that is set, validate it
- set the context (it's a sepa payment) to be used in the pre hook
*/

function sepa_civicrm_validateForm ( $formName, &$fields, &$files, &$form, &$errors ){
  $tag = str_replace('_', '', $formName);
  if (stream_resolve_include_path('CRM/Sepa/Hooks/'.$tag.'.php')) {
    $className = 'CRM_Sepa_Hooks_' . $tag;
    if (class_exists($className)) {
      if (method_exists($className, 'validateForm')) {
        CRM_Sepa_Logic_Base::debug(ts('Calling SEPA Hook '), $className . '::validateForm', 'alert');
        $className::validateForm($form);
      }
    }
  }

  if ("CRM_Admin_Form_PaymentProcessor" == $formName) {
    if (civicrm_api3('PaymentProcessorType', 'getvalue', array('id' => $fields['payment_processor_type_id'], 'return' => 'name')) == 'sepa_dd') {
      $errors += CRM_Sepa_BAO_SEPAMandate::validate_account($fields['creditor_iban'], $fields['creditor_bic'], null);
    }
    return;
  }

  /* Forms that initiate a PP transaction. */
  if ("CRM_Contribute_Form_Contribution_Confirm" == $formName || /* On-line Contribution Page. (PP invoked here if a confirmation page is used.) */
      "CRM_Contribute_Form_Contribution_Main" == $formName || /* On-line Contribution Page. (PP invoked here if no confirmation page is used.) */
      "CRM_Contribute_Form_Contribution" == $formName && empty($form->_values) /* New back-office Contribution. */
  ) {
    // check whether this is a SDD contribution, in which case we need to build
    // the context for the mandate logic to pickup up some values
    if (isset($fields['payment_processor_id'])) {
      $paymentProcessorId = $fields['payment_processor_id']; /* Back-office Contribution form sets this one... */
    } elseif (isset($fields['payment_processor'])) {
      $paymentProcessorId = $fields['payment_processor']; /* Online Contribution Page sets that one... */
    } else {
      return; /* "Normal" back-office Contribution not using a PP. */
    }
    $pp= civicrm_api("PaymentProcessor","getsingle"
      ,array("version"=>3,"id"=>$paymentProcessorId));
    if("Payment_SEPA_DD" != $pp["class_name"])
      return;

    $type = $fields['is_recur'] ? 'FRST' : 'OOFF';
    $GLOBALS["sepa_context"]["payment_instrument_id"] = CRM_Core_OptionGroup::getValue('payment_instrument', $type, 'name');
    //CRM_Core_Session::setStatus('Set payment instrument in context to ' . $cred['payment_instrument_id'], '', 'info');

    $creditor = civicrm_api3('SepaCreditor', 'getsingle', array('payment_processor_id' => $paymentProcessorId, 'return' => 'iban'));

    /* Work around upstream issue CRM-16285 by performing the check here. */
    if (!empty($fields['is_recur'])) {
      if ($fields['frequency_interval'] <= 0) {
        $errors['frequency_interval'] = ts('Please enter a number for how often you want to make this recurring contribution (EXAMPLE: Every 3 months).');
      }
      if ($fields['frequency_unit'] == '0') {
        $errors['frequency_unit'] = ts('Please select a period (e.g. months, years ...) for how often you want to make this recurring contribution (EXAMPLE: Every 3 MONTHS).');
      }
    }

  /* Other forms that might have an IBAN/BIC input we need to verify. */
  } elseif ("CRM_Contribute_Form_Contribution" == $formName) { /* Back-office Contribution edit form. */
    if (!CRM_Sepa_Logic_Base::isSDD($fields)) {
      return;
    }
    if (isset($form->_values['contribution_recur_id'])) { /* Installment of a recurring contribution => no account data here. */
      return;
    }
    $result = civicrm_api3('SepaMandate', 'getsingle', array(
      'entity_table' => 'civicrm_contribution',
      'entity_id' => $form->_values['contribution_id'],
      'return' => 'creditor_id',
      'api.SepaCreditor.getsingle' => array(
        'id' => '$value.creditor_id',
        'return' => 'iban',
      ),
    ));
    $creditor = $result['api.SepaCreditor.getsingle'];
  } elseif ("CRM_Contribute_Form_UpdateSubscription" == $formName) { /* Contribution Recur record edit. */
    if ($form->_paymentProcessor['payment_processor_type'] != 'sepa_dd') {
      return;
    }
    $creditor = civicrm_api3('SepaCreditor', 'getsingle', array('payment_processor_id' => $form->_paymentProcessor['id'], 'return' => 'iban'));
  }

  /* Perform IBAN/BIC check for any forms that carry these fields. */
  if (array_key_exists('bank_iban', $fields)) {
    assert($creditor, 'Failed to determine SEPA Creditor for IBAN/BIC check');
    $errors += CRM_Sepa_BAO_SEPAMandate::validate_account($fields['bank_iban'], $fields['bank_bic'], $creditor['iban']);
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
//  CRM_Sepa_Logic_Base::debug('pre-'.$objectName.'-'.$op);
  $methodName = implode('_', $parts);

  if (method_exists('CRM_Sepa_Logic_Mandates', $methodName)) {
    CRM_Sepa_Logic_Base::debug(ts('Calling SEPA Mandate Logic'), $methodName, 'alert');
    CRM_Sepa_Logic_Mandates::$methodName($id, $params);
  } else {
  }
}

/**
 * This hook makes it possible to implement POST hooks by definine the appropriate method in a logic class
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
//  CRM_Sepa_Logic_Base::debug('post-'.$objectName.'-'.$op);
  $methodName = implode('_', $parts);
  if (method_exists('CRM_Sepa_Logic_Mandates', $methodName)) {
    CRM_Sepa_Logic_Base::debug(ts('Calling SEPA Mandate Logic'), $methodName, 'alert');
    CRM_Sepa_Logic_Mandates::$methodName($objectId, $objectRef);
  }
}


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



// example implementation for Xavier's customization hooks for WMFR

/* disabled for the moment
function sepa_civicrm_create_mandate(&$mandate_parameters) {
  $reference = "WMFR-" . date("Y");
  if ($mandate_parameters) {
    $reference .="-" . $mandate_parameters["entity_id"];
  } else {
    $reference .= "-RAND" . sprintf("%08d", rand(0, 999999));
  }
  $mandate_parameters['reference'] = $reference;
}

function sepa_civicrm_mend_rcontrib($rcontribId, &$rcontrib) {
  $rcontrib->cycle_day = 8;
}
*/
