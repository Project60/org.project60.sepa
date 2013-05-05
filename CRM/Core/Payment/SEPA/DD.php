<?php
class CRM_Core_Payment_SEPA_DD extends CRM_Core_Payment {
  protected $_mode = NULL;
  protected $_params = array();

  static private $_singleton = NULL;

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
    $this->_processorName = ts('SEPA Direct Debit');
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
      self::$_singleton[$processorName] = new CRM_Core_Payment_SEPA_DD($mode, $paymentProcessor);
    }
    return self::$_singleton[$processorName];
  }

  /**
   * Submit a payment using Advanced Integration Method
   *
   * @param  array $params assoc array of input parameters for this transaction
   *
   * @return array the result in a nice formatted array (or an error object)
   * @public
   */
  function doDirectPayment(&$params) {
die ("It's never used, but needs to be declared as it's an abstract method");
  }

  function &error($errorCode = NULL, $errorMessage = NULL) {
    $e = CRM_Core_Error::singleton();
    if ($errorCode) {
      $e->push($errorCode, 0, NULL, $errorMessage);
    }
    else {
      $e->push(9001, 0, NULL, 'Unknown System Error.');
    }
    return $e;
  }

  /**
   * This function checks to see if we have the right config values
   *
   * @return string the error message if any
   * @public
   */
  function checkConfig() {
    $errors = '';

    if (empty($this->_paymentProcessor['user_name'])) {
      $errors .= '<p>' . ts('Creditor ID is not set in the Administer CiviCRM &raquo; Payment Processor.') . '</p>';
    }

    return strlen($errors) ? $errors : NULL;

  }

   /* @param array $params  name value pair of contribution data
   * This creates the mandate, should it be where the pdf mandate is generated and mailed to the supporter?
   * TODO: Write this
   * @return void
   * @access public
   *
   */
  function doTransferCheckout(&$params, $component) {
/*
Array
(
    [qfKey] => b3a74c5ed68e3a20cd9bcedda3eda8b9_1422
    [hidden_processor] => 1
    [email-5] => 43@tttp.eu
    [payment_processor] => 4
    [priceSetId] => 3
    [price_2] => 0
    [price_3] => 12
    [is_recur] => 1
    [frequency_interval] => 1
    [frequency_unit] => month
    [selectProduct] => no_thanks
    [options_1] => White
    [MAX_FILE_SIZE] => 2097152
    [ip_address] => 127.0.0.1
    [amount] => 12
    [currencyID] => USD
    [payment_action] => Sale
    [is_pay_later] => 0
    [invoiceID] => 28004c12ea3401affd0050fef45868d2
    [is_quick_config] => 1
    [description] => Online Contribution: Test
    [accountingCode] => 
    [payment_processor_id] => 4
    [contributionType_name] => Donation
    [contributionType_accounting_code] => 4200
    [contributionPageID] => 1
    [contactID] => 202
    [contributionID] => 98
    [contributionTypeID] => 1
    [item_name] => Online Contribution: Help Support CiviCRM!
    [receive_date] => 20130505102733
    [contributionRecurID] => 2

)
do transfer contribute
print_r($params);
*/
    $component = strtolower($component);

    if (CRM_Utils_Array::value('is_recur', $params) &&
      $params['contributionRecurID']
    ) {
// TODO link the mandate to the recurring contrib
    }
//single debit? TODO: verify how membership is transmited

    $params['trxn_id'] = "TODO GENERATE MANDATE ID";
    if ($this->_mode == 'test') {
      $params['trxn_id'] = "TEST:".$params['trxn_id'];
    }
//TODO
//civicrm_api ("SepaMandate","create", array ("version"=>3...);
  }

}

