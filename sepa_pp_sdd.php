<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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

/**
 * buildForm Hook for payment processor
 */
function sepa_pp_buildForm ( $formName, &$form ) {
	if ($formName == "CRM_Admin_Form_PaymentProcessor") {					// PAYMENT PROCESSOR CONFIGURATION PAGE
		$pp = civicrm_api("PaymentProcessorType", "getsingle", array("id"=>$form->_ppType, "version"=>3));
		if ($pp['class_name'] == "Payment_SDD") {
			// that's ours!

			// get payment processor id
			$pp_id = $form->getVar('_id');

			// find the associated creditor(s)
			$creditor_id      = NULL;
			$test_creditor_id = NULL;

			$pp_creditor      = NULL;
			$test_pp_creditor = NULL;

			if (!empty($pp_id)) {
				$creditor_id      = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit PP',         'pp'.$pp_id);
				$test_creditor_id = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit PP Test',    'pp'.$pp_id);
			}

			// load settings from creditor
			if ($creditor_id) {
				$pp_creditor      = civicrm_api('SepaCreditor', 'getsingle', array('version'=>3, 'id'=>$creditor_id));
				$test_pp_creditor = civicrm_api('SepaCreditor', 'getsingle', array('version'=>3, 'id'=>$test_creditor_id));
				// TODO: ERROR HANDLING
			}

			$creditors = civicrm_api('SepaCreditor', 'get', array('version'=>3));
			$creditors = $creditors['values'];

			$test_creditors = civicrm_api('SepaCreditor', 'get', array('version'=>3, 'category'=>'TEST'));
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

	} elseif ($formName == "CRM_Contribute_Form_Contribution_Main") {						  // PAYMENT PROCESS MAIN PAGE
		$mendForm = CRM_Core_BAO_Setting::getItem('SEPA Direct Debit Preferences', 'pp_improve_frequency');
		if ($mendForm) {
			// inject improved form logic
			CRM_Core_Region::instance('page-body')->add(array(
			  'template' => 'CRM/Contribute/Form/ContributionMain.sepa.tpl'));
		}

	} elseif ($formName == "CRM_Contribute_Form_Contribution_Confirm") {					// PAYMENT PROCESS CONFIRMATION PAGE
		// only for our SDD payment processors:
		$pp = civicrm_api("PaymentProcessor", "getsingle", array("id"=>$form->_params["payment_processor"], "version"=>3));
		if ($pp['class_name'] != "Payment_SDD") return;

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
		// only for our SDD payment processors:
		$pp = civicrm_api("PaymentProcessor", "getsingle", array("id"=>$form->_params["payment_processor"], "version"=>3));
		if ($pp['class_name'] != "Payment_SDD") return;

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
 */
function sepa_pp_postProcess( $formName, &$form ) {
	if ("CRM_Admin_Form_PaymentProcessor" == $formName) {
		$pp = civicrm_api("PaymentProcessorType", "getsingle", array("id"=>$form->_ppType, "version"=>3));
		if ($pp['class_name'] = "Payment_SDD") {
			$paymentProcessor = civicrm_api3('PaymentProcessor', 'getsingle',
				array('name' => $form->_submitValues['name'], 'is_test' => 0));

			$creditor_id = $form->_submitValues['user_name'];
			$test_creditor_id = $form->_submitValues['test_user_name'];
			$pp_id = $paymentProcessor['id'];

			// save settings
			// FIXME: we might consider saving this as a JSON object
			CRM_Core_BAO_Setting::setItem($creditor_id,             'SEPA Direct Debit PP',         'pp'.$pp_id);
			CRM_Core_BAO_Setting::setItem($test_creditor_id,        'SEPA Direct Debit PP Test',    'pp'.$pp_id);
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
function sepa_pp_install() {
	$sdd_pp = civicrm_api('PaymentProcessorType', 'getsingle', array('name'=>'sepa_direct_debit', 'version' => 3));
	if (!empty($sdd_pp['is_error'])) {
		// doesn't exist yet => create
		$payment_processor_data = array(
		    "version"                   => 3,
		    "name"                      => "SEPA_Direct_Debit",
		    "title"                     => ts("SEPA Direct Debit", array('domain' => 'org.project60.sepa')),
		    "description"               => ts("Payment processor for the 'Single European Payement Area' (SEPA).", array('domain' => 'org.project60.sepa')),
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
		$result = civicrm_api('PaymentProcessorType', 'create', $payment_processor_data);
		if (!empty($result['is_error'])) {
			// something went wrong here...
			error_log("org.project60.sepa_dd: payment processor with name 'SEPA_Direct_Debit' could not be created. Error was ".$result['error_message']);
		} else {
			error_log("org.project60.sepa_dd: created payment processor with name 'SEPA_Direct_Debit'");
		}


	} else {
		// already exists => enable if not enabled
		if (!$sdd_pp['is_active']) {
			$result = civicrm_api('PaymentProcessorType', 'create', array('id'=>$sdd_pp['id'], 'is_active'=>1, 'version' => 3));
			if (!empty($result['is_error'])) {
				// something went wrong here...
				error_log("org.project60.sepa_dd: payment processor with name 'SEPA_Direct_Debit' created.");
			}
		}
	}

	// make sure, to put back the class name for formerly disabled processors
	$sepa_pp_query = CRM_Core_DAO::executeQuery("SELECT civicrm_payment_processor.id AS id FROM civicrm_payment_processor LEFT JOIN civicrm_payment_processor_type ON civicrm_payment_processor.payment_processor_type_id = civicrm_payment_processor_type.id WHERE civicrm_payment_processor_type.class_name = 'Payment_SDD'");
	while ($sepa_pp_query->fetch()) {
		CRM_Core_DAO::executeQuery("UPDATE civicrm_payment_processor SET class_name ='Payment_SDD' WHERE id = {$sepa_pp_query->id}");
	}
}

/**
 * Will disable the SEPA payment processor
 */
function sepa_pp_disable() {
	// set all existing SDD instances to Payment_Dummy
	CRM_Core_DAO::executeQuery("UPDATE civicrm_payment_processor SET class_name ='Payment_Dummy' WHERE class_name ='Payment_SDD'");

	// get payment processor...
	$sdd_pp = civicrm_api('PaymentProcessorType', 'getsingle', array('name'=>'SEPA_Direct_Debit', 'version' => 3));
	if (empty($sdd_pp['is_error'])) {
		// ... and disable, if active. We can't delete it, because there might be donation pages linked to it.
		if ($sdd_pp['is_active']) {
			$result = civicrm_api('PaymentProcessorType', 'create', array('id'=>$sdd_pp['id'], 'is_active'=>0, 'version' => 3));
			if (!empty($result['is_error'])) {
				// something went wrong here...
				error_log("org.project60.sepa_dd: payment processor with name 'SEPA_Direct_Debit' could not be disabled. Error was ".$result['error_message']);
			}
		}
	} else {
		// huh? payment processor does not exist (any more)?
		error_log("org.project60.sepa_dd: payment processor with name 'SEPA_Direct_Debit' has gone missing.");
	}
}
