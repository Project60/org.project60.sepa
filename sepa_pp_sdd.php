<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 SYSTOPIA                       |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
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
 * Provides the SEPA payment processor
 *
 * @package CiviCRM_SEPA
 *
 */

define('PP_SDD_PROCESSOR_TYPE',     'SEPA_Direct_Debit');
define('PP_SDD_PROCESSOR_TYPE_NEW', 'SEPA_Direct_Debit_NG');

/**
 * buildForm Hook for payment processor
 */
function sepa_pp_buildForm ( $formName, &$form ) {
  if ($formName == "CRM_Admin_Form_PaymentProcessor" ) {					// PAYMENT PROCESSOR CONFIGURATION PAGE
    // get payment processor id
    $pp_id = $form->getVar('_id');
    $pp_type_id = $form->getVar('_paymentProcessorType');
    if ($pp_id || $pp_type_id) {
      // check if its ours (looking into pp or pp_type:
      $pp_class_name = '';
      if ($pp_id) {
        $pp = civicrm_api3("PaymentProcessor", "getsingle", array("id" => $pp_id));
        $pp_class_name = $pp['class_name'];
      } else {
        $pp_type = civicrm_api3("PaymentProcessorType", "getsingle", array("id" => $pp_type_id));
        $pp_class_name = $pp_type['class_name'];
      }

      if ($pp_class_name == "Payment_SDD" || $pp_class_name == "Payment_SDDNG") {
        // it's ours!

        // find the associated creditor(s)
        $creditor_id      = NULL;
        $test_creditor_id = NULL;

        $pp_creditor      = NULL;
        $test_pp_creditor = NULL;

        if (!empty($pp_id)) {
          $creditor_id      = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit PP', 'pp'.$pp_id);
          $test_creditor_id = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit PP', 'pp_test'.$pp_id);
        }

        // load settings from creditor
        if ($creditor_id) {
          $pp_creditor = civicrm_api3('SepaCreditor', 'getsingle', array('id' => $creditor_id));
        }
        if ($test_creditor_id) {
          $test_pp_creditor = civicrm_api3('SepaCreditor', 'getsingle', array('id'=>$test_creditor_id));
        }

        $creditors = civicrm_api3('SepaCreditor', 'get');
        $creditors = $creditors['values'];

        $test_creditors = civicrm_api3('SepaCreditor', 'get', array('category'=>'TEST'));
        if (empty($test_creditors['values'])) {
          // no test creditors? just offer the regular ones, selecting none is not good
          $test_creditors = civicrm_api3('SepaCreditor', 'get');
        }
        $test_creditors = $test_creditors['values'];

        // use settings
        if ($pp_creditor) {
          $form->assign('user_name', $creditor_id);
        }
        if ($test_pp_creditor) {
          $form->assign('test_user_name', $test_creditor_id);
        }
        $form->assign('creditors', $creditors);
        $form->assign('test_creditors', $test_creditors);

        // add new elements
        CRM_Core_Region::instance('page-body')->add(array(
            'template' => 'CRM/Admin/Form/PaymentProcessor/SDD.tpl'
        ));
      }
    }


	} elseif ($formName == "CRM_Contribute_Form_Contribution_Main") {						  // PAYMENT PROCESS MAIN PAGE
		$mendForm = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', 'pp_improve_frequency');
		if ($mendForm) {
			// inject improved form logic
			CRM_Core_Region::instance('page-body')->add(array(
			  'template' => 'CRM/Contribute/Form/ContributionMain.sepa.tpl'));
		}

	} elseif ($formName == "CRM_Contribute_Form_Contribution_Confirm") {					// PAYMENT PROCESS CONFIRMATION PAGE
		// check if the PP is ours
		$pp_id = CRM_Utils_Array::value('payment_processor', $form->_params);
		if (empty($pp_id)) {
			// there is no payment processor?
			return;
		} else {
			$pp = civicrm_api3('PaymentProcessor', 'getsingle', array('id' => $pp_id));
			if (empty($pp['class_name']) || $pp['class_name']  != 'Payment_SDD') {
				// this is not our processor
				return;
			}
		}

		// this IS our processor -> inject stuff
		CRM_Core_Region::instance('page-body')->add(array(
		  'template' => 'CRM/Contribute/Form/ContributionConfirm.sepa.tpl'));


	} elseif ($formName == "CRM_Event_Form_Registration_Confirm") {					      // EVENT REGISTRATION CONFIRMATION PAGE
		// only for our SDD payment processors:
		$pp = $form->getTemplate()->get_template_vars('paymentProcessor');
		if ($pp['class_name'] != "Payment_SDD") return;

		// FIXME: this is a gross hack, please help me if you know
		//    how to extract bank_bic and bank_iban variables properly...
		$form_data = print_r($form,true);
		$matches = array();
		if (preg_match('/\[bank_identification_number\] => (?P<bank_identification_number>[\w0-9]+)/i', $form_data, $matches)) {
			$form->assign("bank_identification_number",$matches[1]);
		}
		$matches = array();
		if (preg_match('/\[bank_account_number\] => (?P<bank_account_number>[\w0-9]+)/i', $form_data, $matches)) {
			$form->assign("bank_account_number",$matches[1]);
		}
		unset($form_data);

		CRM_Core_Region::instance('page-body')->add(array(
		  'template' => 'CRM/Event/Form/RegistrationConfirm.sepa.tpl'));


	} elseif ($formName == "CRM_Contribute_Form_Contribution_ThankYou") {					// PAYMENT PROCESS THANK YOU PAGE
		// check if the PP is ours
		$pp_id = CRM_Utils_Array::value('payment_processor', $form->_params);
		if (empty($pp_id)) {
			// there is no payment processor?
			return;
		} else {
			$pp = civicrm_api3('PaymentProcessor', 'getsingle', array('id' => $pp_id));
			if (empty($pp['class_name']) || $pp['class_name']  != 'Payment_SDD') {
				// this is not our processor
				return;
			}
		}

		// this IS ours
		$mandate_reference = $form->getTemplate()->get_template_vars('trxn_id');
		if ($mandate_reference) {
			$mandate      = civicrm_api3('SepaMandate',  'getsingle', array('reference' => $mandate_reference));
			$creditor     = civicrm_api3('SepaCreditor', 'getsingle', array('id' => $mandate['creditor_id']));
			$contribution = civicrm_api3('Contribution', 'getsingle', array('trxn_id' => $mandate_reference));
			$rcontribution = array(
				'cycle_day'              => CRM_Utils_Array::value('cycle_day', $form->_params),
				'frequency_interval'     => CRM_Utils_Array::value('frequency_interval', $form->_params),
				'frequency_unit'         => CRM_Utils_Array::value('frequency_unit', $form->_params),
				'start_date'             => CRM_Utils_Array::value('start_date', $form->_params));

			$form->assign('mandate_reference',          $mandate_reference);
			$form->assign("bank_account_number",        $mandate["iban"]);
			$form->assign("bank_identification_number", $mandate["bic"]);
			$form->assign("collection_day",             CRM_Utils_Array::value('cycle_day', $form->_params));
			$form->assign("frequency_interval",         CRM_Utils_Array::value('frequency_interval', $form->_params));
			$form->assign("frequency_unit",             CRM_Utils_Array::value('frequency_unit', $form->_params));
			$form->assign("creditor_id",                $creditor['identifier']);
			$form->assign("collection_date",            $contribution['receive_date']);
			$form->assign("cycle",                      CRM_Sepa_Logic_Batching::getCycle($rcontribution));
			$form->assign("cycle_day",                  CRM_Sepa_Logic_Batching::getCycleDay($rcontribution, $creditor['id']));
		}

		CRM_Core_Region::instance('contribution-thankyou-billing-block')->add(array(
		  'template' => 'CRM/Contribute/Form/ContributionThankYou.sepa.tpl'));


	} elseif ($formName == "CRM_Event_Form_Registration_ThankYou") {						// EVENT REGISTRATION THANK YOU PAGE
		// only for our SDD payment processors:
		$pp = $form->getTemplate()->get_template_vars('paymentProcessor');
		if ($pp['class_name'] != "Payment_SDD") return;

		$mandate_reference = $form->getTemplate()->get_template_vars('trxn_id');
		if ($mandate_reference) {
			$mandate      = civicrm_api3('SepaMandate',  'getsingle', array('reference' => $mandate_reference));
			$creditor     = civicrm_api3('SepaCreditor', 'getsingle', array('id' => $mandate['creditor_id']));
			$contribution = civicrm_api3('Contribution', 'getsingle', array('trxn_id' => $mandate_reference));
			$form->assign('mandate_reference',          $mandate_reference);
			$form->assign("bank_account_number",        $mandate["iban"]);
			$form->assign("bank_identification_number", $mandate["bic"]);
			$form->assign("creditor_id",                $creditor['identifier']);
			$form->assign("collection_date",            $contribution['receive_date']);
		}

		CRM_Core_Region::instance('page-body')->add(array(
		  'template' => 'CRM/Event/Form/RegistrationThankYou.sepa.tpl'));
	}
}

/**
 * postProcess Hook for payment processor
 * (old approach)
 */
function sepa_pp_postProcess( $formName, &$form ) {
  // SDD: make sure mandate is created:
  CRM_Core_Payment_SDDNGPostProcessor::createPendingMandate();

	if ("CRM_Admin_Form_PaymentProcessor" == $formName) {
    $pp_id = $form->getVar('_id');
    if ($pp_id) {
      $pp = civicrm_api3("PaymentProcessorType", "getsingle", array("id" => $pp_id));
      if ($pp['class_name'] = "Payment_SDD" || $pp['class_name'] == 'Payment_SDDNG') {
        $paymentProcessor = civicrm_api3('PaymentProcessor', 'getsingle',
            array('name' => $form->_submitValues['name'], 'is_test' => 0));

        $creditor_id = $form->_submitValues['user_name'];
        $test_creditor_id = $form->_submitValues['test_user_name'];
        $pp_id = $paymentProcessor['id'];

        // save settings
        // FIXME: we might consider saving this as a JSON object
        CRM_Core_BAO_Setting::setItem($creditor_id,      'SEPA Direct Debit PP', 'pp'.$pp_id);
        CRM_Core_BAO_Setting::setItem($test_creditor_id, 'SEPA Direct Debit PP', 'pp_test'.$pp_id);
      }
    }

	} elseif ('CRM_Contribute_Form_Contribution_Confirm' == $formName) {
		// post process the contributions created
		CRM_Core_Payment_SDD::processPartialMandates();

	} elseif ('CRM_Event_Form_Registration_Confirm' == $formName) {
		// post process the contributions created
		CRM_Core_Payment_SDD::processPartialMandates();
	}
}



/**
 * Will install the SEPA payment processor
 */
function sepa_pp_enable() {
  // INSTALL OLD PROCESSOR
  $sdd_pp_type_ids = [];
	$sdd_pp = civicrm_api3('PaymentProcessorType', 'get', array('name' => PP_SDD_PROCESSOR_TYPE));
	if (empty($sdd_pp['id'])) {
		// doesn't exist yet => create
		$payment_processor_data = array(
		    "name"                      => "SEPA_Direct_Debit",
		    "title"                     => ts("SEPA Direct Debit"),
		    "description"               => ts("Payment processor for the 'Single European Payement Area' (SEPA)."),
		    "is_active"                 => 1,
		    "user_name_label"           => "SEPA Creditor identifier",
		    "class_name"                => "Payment_SDD",
		    "url_site_default"          => "",
		    "url_recur_default"         => "",
		    "url_site_test_default"     => "",
		    "url_recur_test_default"    => "",
		    "billing_mode"              => "1",
		    "is_recur"                  => "1",
		    "payment_type"              => CRM_Core_Payment::PAYMENT_TYPE_DIRECT_DEBIT
		);
		$result = civicrm_api3('PaymentProcessorType', 'create', $payment_processor_data);
    $sdd_pp_type_ids[$result['id']] = 'Payment_SDD';
    CRM_Core_Error::debug_log_message("org.project60.sepa_dd: created payment processor with name PP_SDD_PROCESSOR_TYPE");

	} else {
		// already exists => enable if not enabled
    $sdd_pp_type_ids[$sdd_pp['id']] = 'Payment_SDD';
		if (empty($sdd_pp['is_active'])) {
			$result = civicrm_api3('PaymentProcessorType', 'create', array(
          'id'        => $sdd_pp['id'],
          'is_active' => 1));
		}
	}

  // INSTALL NEW/NG PROCESSOR
  $sdd_pp_ng = civicrm_api3('PaymentProcessorType', 'get', array('name' => PP_SDD_PROCESSOR_TYPE_NEW));
  if (empty($sdd_pp_ng['id'])) {
    // doesn't exist yet => create
    $payment_processor_data = array(
        "name"                      => PP_SDD_PROCESSOR_TYPE_NEW,
        "title"                     => ts("SEPA Direct Debit (NEW)"),
        "description"               => ts("Refactored Payment processor for the 'Single European Payement Area' (SEPA)."),
        "is_active"                 => 1,
        "user_name_label"           => "SEPA Creditor identifier",
        "class_name"                => "Payment_SDDNG",
        "url_site_default"          => "",
        "url_recur_default"         => "",
        "url_site_test_default"     => "",
        "url_recur_test_default"    => "",
        "billing_mode"              => "1",
        "is_recur"                  => "1",
        "payment_type"              => CRM_Core_Payment::PAYMENT_TYPE_DIRECT_DEBIT
    );
    $result = civicrm_api3('PaymentProcessorType', 'create', $payment_processor_data);
    $sdd_pp_type_ids[$result['id']] = 'Payment_SDDNG';
    CRM_Core_Error::debug_log_message("org.project60.sepa_dd: created payment processor with name 'SEPA_Direct_Debit_NG'");

  } else {
    // already exists => enable if not enabled
    $sdd_pp_type_ids[$sdd_pp_ng['id']] = 'Payment_SDDNG';
    if (empty($sdd_pp_ng['is_active'])) {
      $result = civicrm_api3('PaymentProcessorType', 'create', array(
          'id'        => $sdd_pp_ng['id'],
          'is_active' => 1));
    }
  }

  // restore dummy instances
  if (!empty($sdd_pp_type_ids)) {
    $sdd_pps = civicrm_api3('PaymentProcessor', 'get', [
        'payment_processor_type_id' => ['IN' => array_keys($sdd_pp_type_ids)],
        'class_name'                => 'Payment_Dummy']);
    foreach ($sdd_pps['values'] as $sdd_pp) {
      civicrm_api3('PaymentProcessor', 'create', [
          'id'         => $sdd_pp['id'],
          'class_name' => $sdd_pp_type_ids[$sdd_pp['payment_processor_type_id']]]);
    }
  }
}

/**
 * Will disable the SEPA payment processor
 */
function sepa_pp_disable() {
	// get payment processor...
  $type_ids = [];
	$sdd_pp_types = civicrm_api3('PaymentProcessorType', 'get', array(
	    'name' => ['IN' => [PP_SDD_PROCESSOR_TYPE, PP_SDD_PROCESSOR_TYPE_NEW]]));
	foreach ($sdd_pp_types['values'] as $sdd_pp_type ) {
    if ($sdd_pp_type['is_active']) {
      $type_ids[] = $sdd_pp_type['id'];
      $result = civicrm_api3('PaymentProcessorType', 'create', array(
          'id'        => $sdd_pp_type['id'],
          'is_active' => 0));
    }
  }

  // set instances to dummy
  if (!empty($type_ids)) {
    $sdd_pps = civicrm_api3('PaymentProcessor', 'get', [
        'payment_processor_type_id' => ['IN' => $type_ids]]);
    foreach ($sdd_pps['values'] as $sdd_pp) {
      civicrm_api3('PaymentProcessor', 'create', [
          'id'         => $sdd_pp['id'],
          'class_name' => 'Payment_Dummy']);
    }
  }
}
