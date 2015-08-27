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

    if (isset($params['hidden_processor'])) { /* Seems to be the best indication for an actual Online Contribution (through Contribution Page) vs. a back-office Contribution. */
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
        'is_required' => FALSE,
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
