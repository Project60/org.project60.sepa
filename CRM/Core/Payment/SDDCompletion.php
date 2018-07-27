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
 * SEPA_Direct_Debit payment processor
 *
 * This API Wrapper extends the Contribution.completetransaction
 *  API call triggered after a successful contribution
 *
 * @package CiviCRM_SEPA
 */

class CRM_Core_Payment_SDDCompletion implements API_Wrapper {
  /**
   * Nothing to do here, we'll execute _after_ the original call
   */
  public function fromApiInput($apiRequest) {
    return $apiRequest;
  }

  /**
   * alter the result before returning it to the caller.
   */
  public function toApiOutput($apiRequest, $result) {
    // before returning this, we'll do our wrap-up as well:
    if (!empty($apiRequest['params']['id'])) {
      self::createPendingMandate($apiRequest['params']['id']);
    }
    // nothing to do here
    return $result;
  }

  /**
   * Create a pending mandate for a contribution created by a SDD payment processor
   *
   * @todo: recurring contributions
   * @todo: react on component
   * @todo: remove financial transactions
   *
   * @param $contribution_id integer   the freshly created contribution
   */
  public static function createPendingMandate($contribution_id) {
    // get pending mandate data
    $params = CRM_Core_Payment_SDD::releasePendingMandateData($contribution_id);
    if (!$params) {
      // nothing pending for us...
      return;
    }

    CRM_Core_Error::debug_log_message("createPendingMandate STEP 1");

    // CREATE OOFF CONTRIBUTION
    // load contribution
    $contribution = civicrm_api3('Contribution', 'getsingle', array(
        'id'     => $contribution_id,
        'return' => 'contact_id,campaign_id,currency'));

    // load payment processor
    $payment_processor = civicrm_api3('PaymentProcessor', 'getsingle', array(
        'id'     => $params['payment_processor_id'],
        'return' => 'user_name'));

    // load creditor
    if (!empty($creditor['user_name'])) {
      $creditor = civicrm_api3('SepaCreditor', 'get', array(
          'id'     => $payment_processor['user_name'],
          'return' => 'id,currency',
      ));
    } else {
      // this shouldn't happen - just a fallback
      $creditor = array(
          'id'       => 0,
          'currency' => 'EUR'
      );
    }

    CRM_Core_Error::debug_log_message("createPendingMandate STEP 2");

    // create mandate
    $mandate = civicrm_api3('SepaMandate', 'create', array(
        'creditor_id'     => $creditor['id'],
        'type'            => 'OOFF',
        'iban'            => $params['iban'],
        'bic'             => $params['bic'],
        'status'          => 'OOFF',
        'entity_table'    => 'civicrm_contribution',
        'entity_id'       => $contribution_id,
        'contact_id'      => $contribution['contact_id'],
        'campaign_id'     => CRM_Utils_Array::value('campaign_id', $contribution),
        'currency'        => $creditor['currency'],
        'date'            => date('YmdHis'),
        'creation_date'   => date('YmdHis'),
        'validation_date' => date('YmdHis'),
    ));

    // reset contribution to 'Pending'
    $ooff_payment = (int)CRM_Sepa_Logic_PaymentInstruments::getSddPaymentInstrumentID('OOFF');
    self::resetContribution($contribution_id, $ooff_payment);

    CRM_Core_Error::debug_log_message("createPendingMandate STEP 3");

  }

  /**
   * @param $contribution_id
   */
  public static function resetContribution($contribution_id, $payment_instrument_id) {
    // update contribution... this can be tricky
    $status_pending = (int)CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');
    try {
      civicrm_api3('Contribution', 'create', array(
          'skipRecentView'         => 1, // avoid overhead
          'id'                     => $contribution_id,
          'contribution_status_id' => $status_pending,
          'payment_instrument_id'  => $payment_instrument_id,
      ));
    } catch (Exception $ex) {
      // that's not good... but we can't leave it like this...
      CRM_Core_Error::debug_log_message("SDD reset contribution via API failed, using SQL...");
      CRM_Core_DAO::executeQuery("UPDATE civicrm_contribution SET contribution_status_id = %1, payment_instrument_id = %2 WHERE id = %3;", array(
          1 => array($status_pending,        'Integer'),
          2 => array($payment_instrument_id, 'Integer'),
          1 => array($contribution_id,       'Integer')));
    }

    CRM_Core_Error::debug_log_message("RESET 1");

    // delete all finacial transactions
    CRM_Core_DAO::executeQuery("
      DELETE FROM civicrm_financial_trxn
      WHERE id IN (SELECT etx.financial_trxn_id 
                   FROM civicrm_entity_financial_trxn etx 
                   WHERE etx.entity_id = {$contribution_id}
                     AND etx.entity_table = 'civicrm_contribution');");

    CRM_Core_Error::debug_log_message("RESET 2");
  }
}
