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
 * REFACTORED, see SEPA-498
 *
 * @package CiviCRM_SEPA
 */

class CRM_Core_Payment_SDD extends CRM_Core_Payment {
  protected $_mode = NULL;
  protected $_params = array();

  static private $_singleton = NULL;

  /** params for pending mandate (after contribution is created) */
  protected static $_pending_mandate = NULL;

  /**
   * Constructor
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return void
   */
  function __construct($mode, &$paymentProcessor) {
    $this->_mode = $mode;
    $this->_paymentProcessor = $paymentProcessor;
    $this->_processorName = ts('SEPA Direct Debit', array('domain' => 'org.project60.sepa'));
    $this->_creditorId = $paymentProcessor['user_name'];
  }

  /**
   * singleton function used to manage this object
   *
   * @param string $mode the mode of operation: live or test
   *
   * @return object
   * @static
   *
   */
  static function &singleton($mode, &$paymentProcessor, &$paymentForm = NULL, $force = FALSE) {
    $processorName = $paymentProcessor['name'];
    if (CRM_Utils_Array::value($processorName, self::$_singleton) === NULL) {
      self::$_singleton[$processorName] = new CRM_Core_Payment_SDD($mode, $paymentProcessor);
    }
    return self::$_singleton[$processorName];
  }


  /**
   * This function checks to see if we have the right config values
   *
   * @return string the error message if any
   * @public
   */
  function checkConfig() {
    // TODO: check urls (creditor IDs)
    // don't check frequencies any more (SEPA-452)
    // CRM_Utils_SepaOptionGroupTools::checkRecurringFrequencyUnits(TRUE, TRUE);
    return NULL;
  }

  /**
   * perform SEPA "payment":
   *  - make sure the IBAN is correct
   *  - store parameters, so we can create the mandate in the POST hook
   *
   * @param  array  $params assoc array of input parameters for this transaction
   * @param  string $component context of this contribution
   *
   * @throws \Civi\Payment\Exception\PaymentProcessorException if IBAN or BIC don't check out
   * @return array the result in an nice formatted array (or an error object)
   */
  function doDirectPayment(&$params, $component = 'contribution') {
    $original_parameters = $params;

    // extract SEPA data
    $params['component'] = $component;
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
      throw new \Civi\Payment\Exception\PaymentProcessorException("SDD PaymentProcessor: workflow broken.");
    }

    // all good? let's prime the post-hook
    self::$_pending_mandate = $params;
//    $params['payment_status_id'] = 2;

    return $params;
  }




  /**
   * @param $contribution_id
   */
  public static function setContributionID($contribution_id) {
    if (!self::$_pending_mandate) {
      // nothing pending, nothing to do
      return;
    }

    self::$_pending_mandate['contribution_id'] = $contribution_id;
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




  /***********************************************
   *            Form-building duty               *
   ***********************************************/

  function buildForm(&$form) {

    // add rules
    $form->registerRule('sepa_iban_valid', 'callback', 'rule_valid_IBAN', 'CRM_Sepa_Logic_Verification');
    $form->registerRule('sepa_bic_valid',  'callback', 'rule_valid_BIC',  'CRM_Sepa_Logic_Verification');

    // BUFFER DAYS / TODO: MOVE TO SERVICE
    $buffer_days      = (int) CRM_Sepa_Logic_Settings::getSetting("pp_buffer_days");
    $frst_notice_days = (int) CRM_Sepa_Logic_Settings::getSetting("batching.FRST.notice", $this->_creditorId);
    $ooff_notice_days = (int) CRM_Sepa_Logic_Settings::getSetting("batching.OOFF.notice", $this->_creditorId);
    $earliest_rcur_date = strtotime("now + $frst_notice_days days + $buffer_days days");
    $earliest_ooff_date = strtotime("now + $ooff_notice_days days");

    // find the next cycle day
    $cycle_days = CRM_Sepa_Logic_Settings::getListSetting("cycledays", range(1, 28), $this->_creditorId);
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
   * Override CRM_Core_Payment function
   */
  public function getPaymentTypeName() {
    return 'direct_debit';
  }

  /**
   * Override CRM_Core_Payment function
   */
  public function getPaymentTypeLabel() {
    return 'Direct Debit';
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
  public function getPaymentFormFields() {
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
          'title' => ts('IBAN', array('domain' => 'org.project60.sepa')),
          'cc_field' => TRUE,
          'attributes' => array(
            'size' => 34,
            'maxlength' => 34,
            'autocomplete' => 'off',
          ),
          'rules' => array(
            array(
              'rule_message' => ts('This is not a correct IBAN.', array('domain' => 'org.project60.sepa')),
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
          'title' => ts('BIC', array('domain' => 'org.project60.sepa')),
          'cc_field' => TRUE,
          'attributes' => array(
            'size' => 20,
            'maxlength' => 11,
            'autocomplete' => 'off',
          ),
          'is_required' => TRUE,
          'rules' => array(
            array(
              'rule_message' => ts('This is not a correct BIC.', array('domain' => 'org.project60.sepa')),
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
          'title' => ts('Collection Day', array('domain' => 'org.project60.sepa')),
          'cc_field' => TRUE,
          'attributes' => CRM_Sepa_Logic_Settings::getListSetting("cycledays", range(1, 28), $this->_creditorId),
          'is_required' => FALSE,
        ),
        'start_date' => array(
          'htmlType' => 'text',
          'name' => 'start_date',
          'title' => ts('Start Date', array('domain' => 'org.project60.sepa')),
          'cc_field' => TRUE,
          'attributes' => array(),
          'is_required' => TRUE,
          'rules' => array(),
        ),
      );
    }
  }
}
