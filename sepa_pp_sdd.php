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
	if ($formName == "CRM_Admin_Form_PaymentProcessor") {
		$pp = civicrm_api("PaymentProcessorType", "getsingle", array("id"=>$form->_ppType, "version"=>3));
		if ($pp['class_name'] = "Payment_SDD") {
			// that's ours!
			$creditors = civicrm_api('SepaCreditor', 'get', array('version'=>3));
			$form->assign('creditors', $creditors['values']);
			$form->assign('cycle_day', 1);

			// build cycle day options
			$cycle_day_options = range(0,28);
			$cycle_day_options[0] = ts('Any');
			unset($cycle_day_options[0]); // 'any' disabled for now

			// add new elements
			$form->add('select', 'cycle_day', ts('Cycle Day'), $cycle_day_options);
			$form->add('select', 'test_cycle_day', ts('Cycle Day'), $cycle_day_options);
			CRM_Core_Region::instance('page-body')->add(array(
				'template' => 'CRM/Admin/Form/PaymentProcessor/SDD.tpl'
			));
		}

	} elseif ($formName == "CRM_Contribute_Form_Contribution_Confirm") {


	} elseif ($formName == "CRM_Contribute_Form_Contribution_ThankYou") {
		$form->assign("mandate_reference",	$form->_params["mandate_reference"]);
		$form->assign("bank_iban",			$form->_params["bank_iban"]);
		$form->assign("bank_bic",			$form->_params["bank_bic"]);
		CRM_Core_Region::instance('contribution-thankyou-billing-block')->add(array(
		  'template' => 'CRM/Contribute/Form/ContributionThankYou.tpl'));
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
		}
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
		    "payment_type"              => "9002"	// TODO
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
}

/**
 * Will disable the SEPA payment processor
 */
function sepa_pp_disable() {
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