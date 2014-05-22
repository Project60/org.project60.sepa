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
 * Provides the SYSTOPIA SEPA payment processor
 *
 * @package CiviCRM_SEPA
 *
 */

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