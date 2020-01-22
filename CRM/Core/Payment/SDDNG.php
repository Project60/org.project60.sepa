<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2018 SYSTOPIA                            |
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

use CRM_Sepa_ExtensionUtil as E;

/**
 * SEPA_Direct_Debit payment processor
 *
 * This is a refactored version of CRM_Core_Payment_SDD, see SEPA-498
 *
 * @package CiviCRM_SEPA
 */

class CRM_Core_Payment_SDDNG extends CRM_Core_Payment {

  /** Caches a mandate-in-the-making */
  protected static $_pending_mandate = NULL;

  /** Caches the creditor involved */
  protected $_creditor = NULL;


  /**
   * Override CRM_Core_Payment function
   */
  public function getPaymentTypeName() {
    return 'direct_debit_ng';
  }

  /**
   * Override CRM_Core_Payment function
   */
  public function getPaymentTypeLabel() {
    return E::ts('Direct Debit');
  }

  /**
   * Should the first payment date be configurable when setting up back office recurring payments.
   * In the case of Authorize.net this is an option
   * @return bool
   */
  protected function supportsFutureRecurStartDate() {
    return FALSE;
  }

  /**
   * Can recurring contributions be set against pledges.
   *
   * In practice all processors that use the baseIPN function to finish transactions or
   * call the completetransaction api support this by looking up previous contributions in the
   * series and, if there is a prior contribution against a pledge, and the pledge is not complete,
   * adding the new payment to the pledge.
   *
   * However, only enabling for processors it has been tested against.
   *
   * @return bool
   */
  protected function supportsRecurContributionsForPledges() {
    return TRUE;
  }


  /**
   * Submit a payment using Advanced Integration Method.
   *
   * @param array $params
   *   Assoc array of input parameters for this transaction.
   *
   * @return array
   *   the result in a nice formatted array (or an error object)
   *
   * @throws \Civi\Payment\Exception\PaymentProcessorException
   */
  public function doDirectPayment(&$params) {
    $original_parameters = $params;

    // extract SEPA data
    $params['iban']      = $params['bank_account_number'];
    $params['bic']       = $params['bank_identification_number'];

    // Allow further manipulation of the arguments via custom hooks ..
    CRM_Utils_Hook::alterPaymentProcessorParams($this, $original_parameters, $params);

    // verify IBAN
    $bad_iban = CRM_Sepa_Logic_Verification::verifyIBAN($params['iban']);
    if ($bad_iban) {
      throw new \Civi\Payment\Exception\PaymentProcessorException($bad_iban);
    }

    // verify BIC
    $bad_bic  = CRM_Sepa_Logic_Verification::verifyBIC($params['bic']);
    if ($bad_bic) {
      throw new \Civi\Payment\Exception\PaymentProcessorException($bad_bic);
    }

    // make sure there's not an pending mandate
    if (self::$_pending_mandate) {
      throw new \Civi\Payment\Exception\PaymentProcessorException("SDD PaymentProcessor NG: workflow broken.");
    }

    // all good? let's prime the post-hook
    self::$_pending_mandate = $params;

    return $params;
  }


  /**
   * This function checks to see if we have the right config values.
   *
   * @return string
   *   the error message if any
   */
  public function checkConfig() {
    // TODO: anything to check?
    return NULL;
  }


  /****************************************************************************
   *                           Helpers                                        *
   ****************************************************************************/

  /**
   * Get the creditor currently involved in the process
   *
   * @return array|void
   */
  protected function getCreditor() {
    if (!$this->_creditor) {
      $pp = $this->getPaymentProcessor();
      $creditor_id = $pp['user_name'];
      try {
        $this->_creditor = civicrm_api3('SepaCreditor', 'getsingle', array('id' => $creditor_id));
      } catch (Exception $ex) {
        // probably no creditor set, or creditor has been deleted - use default
        Civi::log()->debug("org.project60.sepa: creditor [{$creditor_id}] not found, SDDNG using default/any.");
        $default_creditor_id = (int) CRM_Sepa_Logic_Settings::getSetting('batching_default_creditor');
        $creditors = civicrm_api3('SepaCreditor', 'get', array('id' => $default_creditor_id));
        $this->_creditor = reset($creditors['values']);
      }
    }
    return $this->_creditor;
  }

  /****************************************************************************
   *                 Contribution/Mandate Handover                            *
   ****************************************************************************/

  /**
   * @param $contribution_id
   */
  public static function processContribution($contribution_id) {
    Civi::log()->debug("org.project60.sepa: createPendingMandate for {$contribution_id}??");
    if (!self::$_pending_mandate) {
      // nothing pending, nothing to do
      return;
    }

    // store contribution ID
    self::$_pending_mandate['contribution_id'] = $contribution_id;
  }

  /**
   * Get the ID of the currently pending contribution, if any
   */
  public static function getPendingContributionID() {
    if (!empty(self::$_pending_mandate['contribution_id'])) {
      return self::$_pending_mandate['contribution_id'];
    }
    if (!empty(self::$_pending_mandate['contributionID'])) {
      return self::$_pending_mandate['contributionID'];
    }
    return NULL;
  }

  public static function releasePendingMandateData($contribution_id) {
    if (!self::$_pending_mandate) {
      // nothing pending, nothing to do
      return NULL;
    }

    $contribution_id = self::getPendingContributionID();
    if ($contribution_id != $contribution_id) {
      // something's wrong here
      Civi::log()->debug("org.project60.sepa: SDD PP workflow error");
      return NULL;
    }

    // everything checks out: reset and return data
    $params = self::$_pending_mandate;
    self::$_pending_mandate = NULL;
    return $params;
  }



  /***********************************************
   *            Form-building duty               *
   ***********************************************/

  function buildForm(&$form) {
    // add rules
    $form->registerRule('sepa_iban_valid', 'callback', 'rule_valid_IBAN', 'CRM_Sepa_Logic_Verification');
    $form->registerRule('sepa_bic_valid',  'callback', 'rule_valid_BIC',  'CRM_Sepa_Logic_Verification');

    // BUFFER DAYS / TODO: MOVE TO SERVICE
    $creditor = $this->getCreditor();
    $buffer_days      = (int) CRM_Sepa_Logic_Settings::getSetting("pp_buffer_days");
    $frst_notice_days = (int) CRM_Sepa_Logic_Settings::getSetting("batching.FRST.notice", $creditor['id']);
    $ooff_notice_days = (int) CRM_Sepa_Logic_Settings::getSetting("batching.OOFF.notice", $creditor['id']);
    $earliest_rcur_date = strtotime("now + $frst_notice_days days + $buffer_days days");
    $earliest_ooff_date = strtotime("now + $ooff_notice_days days");

    // find the next cycle day
    $cycle_days = CRM_Sepa_Logic_Settings::getListSetting("cycledays", range(1, 28), $creditor['id']);
    $earliest_cycle_day = $earliest_rcur_date;
    while (!in_array(date('j', $earliest_cycle_day), $cycle_days)) {
      $earliest_cycle_day = strtotime("+ 1 day", $earliest_cycle_day);
    }

    $form->assign('earliest_rcur_date', date('Y-m-d', $earliest_rcur_date));
    $form->assign('earliest_ooff_date', date('Y-m-d', $earliest_ooff_date));
    $form->assign('earliest_cycle_day', date('j', $earliest_cycle_day));
    $form->assign('sepa_hide_bic', CRM_Sepa_Logic_Settings::getSetting("pp_hide_bic"));
    $form->assign('sepa_hide_billing', CRM_Sepa_Logic_Settings::getSetting("pp_hide_billing"));
    $form->assign('bic_extension_installed', CRM_Sepa_Logic_Settings::isLittleBicExtensionAccessible());

    CRM_Core_Region::instance('billing-block')->add(
        array('template' => 'CRM/Core/Payment/SEPA/SDD.tpl', 'weight' => -1));
  }

  /**
   * Override custom PI validation
   *  to make billing information NOT mandatory (see SEPA-372)
   *
   * @author N. Bochan
   */
  public function validatePaymentInstrument($values, &$errors) {
    // first: call parent's implementation
    parent::validatePaymentInstrument($values, $errors);

    // if this feature is not active, we do nothing:
    $pp_hide_billing = CRM_Sepa_Logic_Settings::getSetting("pp_hide_billing");
    if (empty($pp_hide_billing)) return;

    // now: by removing all the errors on the billing fields, we
    //   effectively render the billing block "not mandatory"
    if (isset($errors)) {
      foreach ($errors as $fieldname => $error_message) {
        if (substr($fieldname, 0, 8) == 'billing_') {
          unset($errors[$fieldname]);
        }
      }
    }
  }

  /**
   * Override CRM_Core_Payment function
   */
  public function _getPaymentFormFields() {
    if (version_compare(CRM_Utils_System::version(), '4.6.10', '<')) {
      return parent::getPaymentFormFields();
    } else {
      return array(
          'cycle_day',
          'start_date',
          'account_holder',
          'bank_account_number',
          'bank_identification_number',
          'bank_name',
      );
    }
  }

  /**
   * Return an array of all the details about the fields potentially required for payment fields.
   *
   * Only those determined by getPaymentFormFields will actually be assigned to the form
   *
   * @return array
   *   field metadata
   */
  public function getPaymentFormFieldsMetadata() {
    if (version_compare(CRM_Utils_System::version(), '4.6.10', '<')) {
      return parent::getPaymentFormFieldsMetadata();
    } else {
      $creditor = $this->getCreditor();
      return array(
          'account_holder' => array(
              'htmlType' => 'text',
              'name' => 'account_holder',
              'title' => ts('Account Holder', array('domain' => 'org.project60.sepa')),
              'cc_field' => TRUE,
              'attributes' => array(
                  'size' => 20,
                  'maxlength' => 34,
                  'autocomplete' => 'on',
              ),
              'is_required' => FALSE,
          ),
          //e.g. IBAN can have maxlength of 34 digits
          'bank_account_number' => array(
              'htmlType' => 'text',
              'name' => 'bank_account_number',
              'default' => 'DE91100000000123456789',
              'title' => E::ts('IBAN'),
              'cc_field' => TRUE,
              'attributes' => array(
                  'size' => 34,
                  'maxlength' => 34,
                  'autocomplete' => 'off',
              ),
              'rules' => array(
                  array(
                      'rule_message' => E::ts('This is not a correct IBAN.'),
                      'rule_name' => 'sepa_iban_valid',
                      'rule_parameters' => NULL,
                  ),
              ),
              'is_required' => TRUE,
          ),
          //e.g. SWIFT-BIC can have maxlength of 11 digits
          'bank_identification_number' => array(
              'htmlType' => 'text',
              'name' => 'bank_identification_number',
              'title' => E::ts('BIC'),
              'cc_field' => TRUE,
              'attributes' => array(
                  'size' => 20,
                  'maxlength' => 11,
                  'autocomplete' => 'off',
              ),
              'is_required' => TRUE,
              'rules' => array(
                  array(
                      'rule_message' => E::ts('This is not a correct BIC.'),
                      'rule_name' => 'sepa_bic_valid',
                      'rule_parameters' => NULL,
                  ),
              ),
          ),
          'bank_name' => array(
              'htmlType' => 'text',
              'name' => 'bank_name',
              'title' => ts('Bank Name', array('domain' => 'org.project60.sepa')),
              'cc_field' => TRUE,
              'attributes' => array(
                  'size' => 34,
                  'maxlength' => 64,
                  'autocomplete' => 'off',
              ),
              'is_required' => FALSE,
          ),
          'cycle_day' => array(
              'htmlType' => 'select',
              'name' => 'cycle_day',
              'title' => E::ts('Collection Day'),
              'cc_field' => TRUE,
              'attributes' => CRM_Sepa_Logic_Settings::getListSetting("cycledays", range(1, 28), $creditor['id']),
              'is_required' => FALSE,
          ),
          'start_date' => array(
              'htmlType' => 'text',
              'name' => 'start_date',
              'title' => E::ts('Start Date'),
              'cc_field' => TRUE,
              'attributes' => array(),
              'is_required' => TRUE,
              'rules' => array(),
          ),
      );
    }
  }
}
