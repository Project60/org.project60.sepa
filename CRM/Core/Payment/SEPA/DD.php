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

  protected function supportsBackOffice() {
    return TRUE;
  }

  /* Note: This doesn't seem to actually work in CiviCRM 4.6...
   * (We still need to set the template variable explicitly in the buildForm hook.) */
  protected function supportsFutureRecurStartDate() {
    return TRUE;
  }

  public function getPaymentTypeLabel() {
    return 'SEPA Mandate';
  }

  /**
   * Get array of fields that should be displayed on the payment form.
   *
   * @return array
   */
  public function getPaymentFormFields() {
    return array_merge(
      isset($GLOBALS['sepa_context']['back_office']) /* Only display the checkbox on back-office forms. (For online contribution pages, the initial mandate status is decided by the creditor setting instead.) */
        ? array('sepa_active')
        : array(),
      array(
        'bank_iban',
        'bank_bic',
      )
    );
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
    return array(
      'sepa_active' => array(
        'htmlType' => 'checkbox',
        'name' => 'sepa_active',
        'title' => ts('Active mandate'),
        'cc_field' => true,
        'is_required' => false,
      ),
      'bank_iban' => array(
        'htmlType' => 'text',
        'name' => 'bank_iban',
        'title' => ts('IBAN'),
        'cc_field' => true,
        'attributes' => array('size' => 34, 'maxlength' => 34, /* 'autocomplete' => 'off' */ ),
        'is_required' => true,
      ),
      'bank_bic' => array(
        'htmlType' => 'text',
        'name' => 'bank_bic',
        'title' => ts('BIC'),
        'cc_field' => true,
        'attributes' => array('size' => 11, 'maxlength' => 11, /* 'autocomplete' => 'off' */ ),
        'is_required' => false,
      ),
    );
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
    $creditor = civicrm_api3('SepaCreditor', 'getsingle', array(
      'payment_processor_id' => $params['payment_processor_id'],
      'return' => array('id', 'mandate_active'),
    ));

    $apiParams = array (
        "iban"=> $params["bank_iban"],
        "bic" => $params["bank_bic"],
        );
    $apiParams['creditor_id'] = $creditor['id'];
    // set the contract entity for this mandate
    if (CRM_Utils_Array::value('is_recur', $params) &&
        $params['contributionRecurID']
       ) {
      $apiParams['type'] = 'RCUR';
      $apiParams["entity_table"]="civicrm_contribution_recur";
      $apiParams["entity_id"]= $params['contributionRecurID'];
    } elseif (CRM_Utils_Array::value('selectMembership', $params))   {
      print_r($params);
      die ("TODO manage memberships in SEPA. It's supposed to be with with a recurring membership.");
      // TODO: link mandate to membership
    } else {
      // Probably a one-off contribution.
      $apiParams['type'] = 'OOFF';
      $apiParams['entity_table'] = 'civicrm_contribution';
      // Note: for one-off contributions,
      // the contribution record is created only *after* invoking doDirectPayment() --
      // so we don't have an entity ID here yet...

      /* When creating Contributions through the back-office form, a Start Date can be entered;
       * and for OOFF contributions, CiviCRM automatically passes it as `receive_date` here.
       * However, for some reason it isn't passed in the same way to the actual Contribution create --
       * thus we need to save it here, so we can later set it for the Contribution explicitly. */
      $GLOBALS['sepa_context']['receive_date'] = $params['receive_date'];
    }

    if (!isset($GLOBALS['sepa_context']['back_office'])) {
      $mandateActive = $creditor['mandate_active']; /* Online => use PP default. */
    } else {
      $mandateActive = CRM_Utils_Array::value('sepa_active', $params); /* Back-office => selected in form. */
    }
    if ($mandateActive) {
      $apiParams['status'] = CRM_Utils_Array::value('is_recur', $params) ? 'FRST' : 'OOFF';
    } else {
      $apiParams['status'] = 'INIT';
    }

    $apiParams["creation_date"]= date("YmdHis");

    if (isset($apiParams['entity_id'])) {
      CRM_Sepa_Logic_Mandates::createMandate($apiParams);
    } else {
      // If we don't yet have an entity to attach the mandate to, we need to postpone the mandate creation.
      $GLOBALS['sepa_context']['mandateParams'] = $apiParams;
    }
    return $params;
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
}
