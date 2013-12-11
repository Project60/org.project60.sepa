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
    $params['trxn_id'] = "TODO GENERATE MANDATE ID";

    // create the mandate
    if ($this->_mode == 'test') {
      $params['trxn_id'] = "TEST:" . $params['trxn_id'];
    }
    $apiParams = array (
        "iban"=> $params["bank_iban"],
        "bic" => $params["bank_bic"],
        );
    $apiParams ["creditor_id"] = $GLOBALS["sepa_context"]["creditor_id"];
    // set the contract entity for this mandate
    if (CRM_Utils_Array::value('is_recur', $params) &&
        $params['contributionRecurID']
       ) {
      $apiParams["entity_table"]="civicrm_contribution_recur";
      $apiParams["entity_id"]= $params['contributionRecurID'];
    } elseif (CRM_Utils_Array::value('selectMembership', $params))   {
      print_r($params);
      die ("TODO manage memberships in SEPA. It's supposed to be with with a recurring membership.");
      // TODO: link mandate to membership
    } else {
      die ("is this a single payment? We don't do that in SEPA (yet)");
    }

    $creditor = civicrm_api3 ('SepaCreditor', 'getsingle', array ('id' => $GLOBALS["sepa_context"]["creditor_id"], 'return' => 'mandate_active'));
    if ($creditor['mandate_active']) {
      $apiParams['status'] = CRM_Utils_Array::value('is_recur', $params) ? 'FRST' : 'OOFF';
    } else {
      $apiParams['status'] = 'INIT';
    }

    $apiParams["creation_date"]= date("YmdHis");

    CRM_Sepa_Logic_Mandates::createMandate($apiParams);
    return array(true); // Need to return a non-empty array to indicate success...
  }

  
  function &error($errorCode = NULL, $errorMessage = NULL) {
    $e = CRM_Core_Error::singleton();
    if ($errorCode) {
      $e->push($errorCode, 0, NULL, $errorMessage);
    }
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

  function buildForm(&$form) {
    $form->_paymentFields = array(); //remove existing fields (bank account, branch, bla)
    //TODO input:[name="is_recur"]')[0].checked = true;

    //e.g. IBAN can have maxlength of 34 digits
    $form->_paymentFields['bank_iban'] = array(
        'htmlType' => 'text',
        'name' => 'bank_iban',
        'title' => ts('IBAN'),
        'cc_field' => TRUE,
        'attributes' => array('size' => 34, 'maxlength' => 34, /* 'autocomplete' => 'off' */ ),
        'is_required' => TRUE,
        );

    //e.g. SWIFT-BIC can have maxlength of 11 digits
    $form->_paymentFields['bank_bic'] = array(
        'htmlType' => 'text',
        'name' => 'bank_bic',
        'title' => ts('BIC'),
        'cc_field' => TRUE,
        'attributes' => array('size' => 11, 'maxlength' => 11, /* 'autocomplete' => 'off' */ ),
        'is_required' => TRUE,
        );

    foreach ($form->_paymentFields as $name => $field) {
      if (isset($field['cc_field']) &&
          $field['cc_field']
         ) {
        $form->add($field['htmlType'], $field['name'], $field['title'], $field['attributes'], $field['is_required']
            );
      }
    }

    CRM_Core_Region::instance('billing-block')->update('default', array('disabled' => TRUE));
    CRM_Core_Region::instance('billing-block')->add(array('template' => 'CRM/Sepa/Mandate.tpl',
          'weight' => -1));
  }
}
