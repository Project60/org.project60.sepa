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
    return TRUE;
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
    if ($bad_iban) {
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
//    $error = array();
//    if (empty($this->_paymentProcessor['user_name'])) {
//      $error[] = ts('APILogin is not set for this payment processor');
//    }
//
//    if (empty($this->_paymentProcessor['password'])) {
//      $error[] = ts('Key is not set for this payment processor');
//    }
//
//    if (!empty($error)) {
//      return implode('<p>', $error);
//    }
//    else {
//      return NULL;
//    }
    // TODO: do anything here?
    return NULL;
  }


  /**
   * Submit an Automated Recurring Billing subscription.
   */
  public function doRecurPayment() {
//    $template = CRM_Core_Smarty::singleton();
//
//    $intervalLength = $this->_getParam('frequency_interval');
//    $intervalUnit = $this->_getParam('frequency_unit');
//    if ($intervalUnit == 'week') {
//      $intervalLength *= 7;
//      $intervalUnit = 'days';
//    }
//    elseif ($intervalUnit == 'year') {
//      $intervalLength *= 12;
//      $intervalUnit = 'months';
//    }
//    elseif ($intervalUnit == 'day') {
//      $intervalUnit = 'days';
//    }
//    elseif ($intervalUnit == 'month') {
//      $intervalUnit = 'months';
//    }
//
//    // interval cannot be less than 7 days or more than 1 year
//    if ($intervalUnit == 'days') {
//      if ($intervalLength < 7) {
//        return self::error(9001, 'Payment interval must be at least one week');
//      }
//      elseif ($intervalLength > 365) {
//        return self::error(9001, 'Payment interval may not be longer than one year');
//      }
//    }
//    elseif ($intervalUnit == 'months') {
//      if ($intervalLength < 1) {
//        return self::error(9001, 'Payment interval must be at least one week');
//      }
//      elseif ($intervalLength > 12) {
//        return self::error(9001, 'Payment interval may not be longer than one year');
//      }
//    }
//
//    $template->assign('intervalLength', $intervalLength);
//    $template->assign('intervalUnit', $intervalUnit);
//
//    $template->assign('apiLogin', $this->_getParam('apiLogin'));
//    $template->assign('paymentKey', $this->_getParam('paymentKey'));
//    $template->assign('refId', substr($this->_getParam('invoiceID'), 0, 20));
//
//    //for recurring, carry first contribution id
//    $template->assign('invoiceNumber', $this->_getParam('contributionID'));
//    $firstPaymentDate = $this->_getParam('receive_date');
//    if (!empty($firstPaymentDate)) {
//      //allow for post dated payment if set in form
//      $startDate = date_create($firstPaymentDate);
//    }
//    else {
//      $startDate = date_create();
//    }
//    /* Format start date in Mountain Time to avoid Authorize.net error E00017
//     * we do this only if the day we are setting our start time to is LESS than the current
//     * day in mountaintime (ie. the server time of the A-net server). A.net won't accept a date
//     * earlier than the current date on it's server so if we are in PST we might need to use mountain
//     * time to bring our date forward. But if we are submitting something future dated we want
//     * the date we entered to be respected
//     */
//    $minDate = date_create('now', new DateTimeZone(self::TIMEZONE));
//    if (strtotime($startDate->format('Y-m-d')) < strtotime($minDate->format('Y-m-d'))) {
//      $startDate->setTimezone(new DateTimeZone(self::TIMEZONE));
//    }
//
//    $template->assign('startDate', $startDate->format('Y-m-d'));
//
//    $installments = $this->_getParam('installments');
//
//    // for open ended subscription totalOccurrences has to be 9999
//    $installments = empty($installments) ? 9999 : $installments;
//    $template->assign('totalOccurrences', $installments);
//
//    $template->assign('amount', $this->_getParam('amount'));
//
//    $template->assign('cardNumber', $this->_getParam('credit_card_number'));
//    $exp_month = str_pad($this->_getParam('month'), 2, '0', STR_PAD_LEFT);
//    $exp_year = $this->_getParam('year');
//    $template->assign('expirationDate', $exp_year . '-' . $exp_month);
//
//    // name rather than description is used in the tpl - see http://www.authorize.net/support/ARB_guide.pdf
//    $template->assign('name', $this->_getParam('description', TRUE));
//
//    $template->assign('email', $this->_getParam('email'));
//    $template->assign('contactID', $this->_getParam('contactID'));
//    $template->assign('billingFirstName', $this->_getParam('billing_first_name'));
//    $template->assign('billingLastName', $this->_getParam('billing_last_name'));
//    $template->assign('billingAddress', $this->_getParam('street_address', TRUE));
//    $template->assign('billingCity', $this->_getParam('city', TRUE));
//    $template->assign('billingState', $this->_getParam('state_province'));
//    $template->assign('billingZip', $this->_getParam('postal_code', TRUE));
//    $template->assign('billingCountry', $this->_getParam('country'));
//
//    $arbXML = $template->fetch('CRM/Contribute/Form/Contribution/AuthorizeNetARB.tpl');
//    // submit to authorize.net
//
//    $submit = curl_init($this->_paymentProcessor['url_recur']);
//    if (!$submit) {
//      return self::error(9002, 'Could not initiate connection to payment gateway');
//    }
//    curl_setopt($submit, CURLOPT_RETURNTRANSFER, 1);
//    curl_setopt($submit, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
//    curl_setopt($submit, CURLOPT_HEADER, 1);
//    curl_setopt($submit, CURLOPT_POSTFIELDS, $arbXML);
//    curl_setopt($submit, CURLOPT_POST, 1);
//    curl_setopt($submit, CURLOPT_SSL_VERIFYPEER, CRM_Core_BAO_Setting::getItem(CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME, 'verifySSL'));
//
//    $response = curl_exec($submit);
//
//    if (!$response) {
//      return self::error(curl_errno($submit), curl_error($submit));
//    }
//
//    curl_close($submit);
//    $responseFields = $this->_ParseArbReturn($response);
//
//    if ($responseFields['resultCode'] == 'Error') {
//      return self::error($responseFields['code'], $responseFields['text']);
//    }
//
//    // update recur processor_id with subscriptionId
//    CRM_Core_DAO::setFieldValue('CRM_Contribute_DAO_ContributionRecur', $this->_getParam('contributionRecurID'),
//        'processor_id', $responseFields['subscriptionId']
//    );
//    //only impact of assigning this here is is can be used to cancel the subscription in an automated test
//    // if it isn't cancelled a duplicate transaction error occurs
//    if (!empty($responseFields['subscriptionId'])) {
//      $this->_setParam('subscriptionId', $responseFields['subscriptionId']);
//    }
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
      $this->_creditor = civicrm_api3('SepaCreditor', 'getsingle', array('id' => $creditor_id));
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
    CRM_Core_Error::debug_log_message("createPendingMandate for {$contribution_id}??");
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
    if (empty(self::$_pending_mandate['contribution_id'])) {
      return NULL;
    } else {
      return self::$_pending_mandate['contribution_id'];
    }
  }

  public static function releasePendingMandateData($contribution_id) {
    if (!self::$_pending_mandate) {
      // nothing pending, nothing to do
      return NULL;
    }

    if (empty(self::$_pending_mandate['contribution_id']) || $contribution_id != self::$_pending_mandate['contribution_id']) {
      // something's wrong here
      CRM_Core_Error::debug_log_message("SDD PP workflow error");
      return NULL;
    }

    // everything checks out: reset and return data
    $params = self::$_pending_mandate;
    self::$_pending_mandate = NULL;
    return $params;
  }


//
//    protected $_mode = NULL;
//  protected $_params = array();
//
//  static private $_singleton = NULL;
//
//  /** params for pending mandate (after contribution is created) */
//  protected static $_pending_mandate = NULL;

//  /**
//   * Constructor
//   *
//   * @param string $mode the mode of operation: live or test
//   *
//   * @return void
//   */
//  function __construct($mode, &$paymentProcessor) {
//    $this->_mode = $mode;
//    $this->_paymentProcessor = $paymentProcessor;
////    $this->_processorName = E::ts('SEPA Direct Debit NG');
////    $this->_creditorId = $paymentProcessor['user_name'];
//  }
//
//  /**
//   * singleton function used to manage this object
//   *
//   * @param string $mode the mode of operation: live or test
//   *
//   * @return object
//   * @static
//   *
//   */
//  static function &singleton($mode, &$paymentProcessor, &$paymentForm = NULL, $force = FALSE) {
//    $processorName = $paymentProcessor['name'];
//    if (CRM_Utils_Array::value($processorName, self::$_singleton) === NULL) {
//      self::$_singleton[$processorName] = new CRM_Core_Payment_SDD($mode, $paymentProcessor);
//    }
//    return self::$_singleton[$processorName];
//  }


//  /**
//   * This function checks to see if we have the right config values
//   *
//   * @return string the error message if any
//   * @public
//   */
//  function checkConfig() {
//    // TODO: check urls (creditor IDs)
//    // don't check frequencies any more (SEPA-452)
//    // CRM_Utils_SepaOptionGroupTools::checkRecurringFrequencyUnits(TRUE, TRUE);
//    return NULL;
//  }
//
//  /**
//   * perform SEPA "payment":
//   *  - make sure the IBAN is correct
//   *  - store parameters, so we can create the mandate in the POST hook
//   *
//   * @param  array  $params assoc array of input parameters for this transaction
//   * @param  string $component context of this contribution
//   *
//   * @throws \Civi\Payment\Exception\PaymentProcessorException if IBAN or BIC don't check out
//   * @return array the result in an nice formatted array (or an error object)
//   */
//  function doDirectPayment(&$params, $component = 'contribution') {
//    $original_parameters = $params;
//
//    // extract SEPA data
//    $params['component'] = $component;
//    $params['iban']      = $params['bank_account_number'];
//    $params['bic']       = $params['bank_identification_number'];
//
//    // Allow further manipulation of the arguments via custom hooks ..
//    CRM_Utils_Hook::alterPaymentProcessorParams($this, $original_parameters, $params);
//
//    // verify IBAN
//    $bad_iban = CRM_Sepa_Logic_Verification::verifyIBAN($params['iban']);
//    if ($bad_iban) {
//      throw new \Civi\Payment\Exception\PaymentProcessorException($bad_iban);
//    }
//
//    // verify BIC
//    $bad_bic  = CRM_Sepa_Logic_Verification::verifyBIC($params['bic']);
//    if ($bad_iban) {
//      throw new \Civi\Payment\Exception\PaymentProcessorException($bad_bic);
//    }
//
//    // make sure there's not an pending mandate
//    if (self::$_pending_mandate) {
//      throw new \Civi\Payment\Exception\PaymentProcessorException("SDD PaymentProcessor: workflow broken.");
//    }
//
//    // all good? let's prime the post-hook
//    self::$_pending_mandate = $params;
////    $params['payment_status_id'] = 2;
//
//    return $params;
//  }




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
