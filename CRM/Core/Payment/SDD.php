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
 * SEPA_Direct_Debit payment processor
 *
 * @package CiviCRM_SEPA
 */

class CRM_Core_Payment_SDD extends CRM_Core_Payment {

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
      self::$_singleton[$processorName] = new CRM_Core_Payment_SDD($mode, $paymentProcessor);
    }
    return self::$_singleton[$processorName];
  }


  function buildForm(&$form) {
    // we don't need the default stuff:
    $form->_paymentFields = array();

    $form->add( 'text', 
                'bank_iban', 
                ts('IBAN'), 
                array('size' => 34, 'maxlength' => 34, /* 'autocomplete' => 'off' */ ), 
                TRUE);
    //error_log(print_r($form->_paymentFields, true));

    $form->add( 'text', 
                'bank_bic', 
                ts('BIC'), 
                array('size' => 11, 'maxlength' => 11, /* 'autocomplete' => 'off' */ ), 
                TRUE);

    // TODO: add (hidden) cycle_day, frequency and start_date

    //CRM_Core_Region::instance('billing-block')->update('default', array('disabled' => TRUE));

    // TODO: create new template
    CRM_Core_Region::instance('billing-block')->add(
      array('template' => 'CRM/Sepa/Mandate.tpl', 'weight' => -1));
  }


  /**
   * This function checks to see if we have the right config values
   *
   * @param  string $mode the mode we are operating in (live or test)
   *
   * @return string the error message if any
   * @public
   */
  function checkConfig() {
    // TODO: check urls (creditor IDs)
    return NULL;
  }



  /**
   * This function collects all the information from a web/api form and invokes
   * the relevant payment processor specific functions to perform the transaction
   *
   * @param  array $params assoc array of input parameters for this transaction
   *
   * @return array the result in an nice formatted array (or an error object)
   */
  // EXAMPLE ARRAY
  // [qfKey] => f85d583b0d6c76b40af41946f022c487_9963
  // [entryURL] => http://localhost:8888/migration/civicrm/contribute/transact?reset=1&amp;id=2&amp;action=preview
  // [hidden_processor] => 1
  // [bank_iban] => DEXXXXXXXXX0
  // [bank_bic] => AABAFI22
  // [email-5] => schuttenberg@systopia.de
  // [payment_processor] => 10
  // [priceSetId] => 4
  // [price_5] => 50
  // [is_recur] => 1
  // [frequency_interval] => 1
  // [frequency_unit] => month
  // [selectProduct] => 
  // [MAX_FILE_SIZE] => 33554432
  // [ip_address] => 127.0.0.1
  // [amount] => 50
  // [currencyID] => EUR
  // [payment_action] => Sale
  // [is_pay_later] => 0
  // [invoiceID] => 5ab7e63d0e975ea9d25df745612698f5
  // [is_quick_config] => 1
  // [description] => Online-Zuwendung: Test2
  // [accountingCode] => 
  // [payment_processor_id] => 10
  // [email] => srb@systopia.de
  // [contributionType_name] => Abo
  // [contributionType_accounting_code] => 4300
  // [contributionPageID] => 2
  // [contactID] => 2
  // [contributionID] => 10705
  // [contributionTypeID] => 4
  // [contributionRecurID] => 277
  //
  //
  // OR:
  // [qfKey] => f85d583b0d6c76b40af41946f022c487_6725
  // [entryURL] => http://localhost:8888/migration/civicrm/contribute/transact?reset=1&amp;id=2&amp;action=preview
  // [hidden_processor] => 1
  // [bank_iban] => Dxxx00
  // [bank_bic] => Axxx2
  // [email-5] => schuttenberg@systopia.de
  // [payment_processor] => 10
  // [priceSetId] => 4
  // [price_5] => 50
  // [frequency_interval] => 1
  // [frequency_unit] => month
  // [selectProduct] => 
  // [MAX_FILE_SIZE] => 33554432
  // [ip_address] => 127.0.0.1
  // [amount] => 50
  // [currencyID] => EUR
  // [payment_action] => Sale
  // [is_pay_later] => 0
  // [invoiceID] => 90ba21ded5ea70bb3cc8d68a25655d89
  // [is_quick_config] => 1
  // [description] => Online-Zuwendung: Test2
  // [accountingCode] => 
  // [payment_processor_id] => 10
  // [email] => schuttenberg@systopia.de
  // [contributionType_name] => Abo
  // [contributionType_accounting_code] => 4300
  // [contributionPageID] => 2
function doDirectPayment(&$params) {
    error_log(print_r($params, true));

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
    }

    $creditor = civicrm_api3 ('SepaCreditor', 'getsingle', array ('id' => $GLOBALS["sepa_context"]["creditor_id"], 'return' => 'mandate_active'));
    if ($creditor['mandate_active']) {
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
    return array(true); // Need to return a non-empty array to indicate success...
  }

  


  function &error($errorCode = NULL, $errorMessage = NULL) {
    $e = CRM_Core_Error::singleton();
    if ($errorCode) {
      $e->push($errorCode, 0, NULL, $errorMessage);
    }
  }

}
