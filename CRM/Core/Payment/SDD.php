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


  function buildForm(&$form) {
    // we don't need the default stuff:
    $form->_paymentFields = array();

    $form->add( 'text', 
                'bank_iban', 
                ts('IBAN'), 
                array('size' => 34, 'maxlength' => 34,), 
                TRUE);

    $form->add( 'text', 
                'bank_bic', 
                ts('BIC'), 
                array('size' => 11, 'maxlength' => 11), 
                TRUE);

    $form->add( 'text', 
                'cycle_day', 
                ts('day of month'), 
                array('size' => 2, 'value' => 1), 
                FALSE);

    $form->addDate('start_date', 
                ts('start date'), 
                TRUE, 
                array());

    $rcur_notice_days = (int) CRM_Sepa_Logic_Settings::getSetting("batching.RCUR.notice", $this->_creditorId);
    $ooff_notice_days = (int) CRM_Sepa_Logic_Settings::getSetting("batching.OOFF.notice", $this->_creditorId);
    $form->assign('earliest_rcur_date', date('m/d/Y', strtotime("now + $rcur_notice_days days")));
    $form->assign('earliest_ooff_date', date('m/d/Y', strtotime("now + $ooff_notice_days days")));

    CRM_Core_Region::instance('billing-block')->add(
      array('template' => 'CRM/Core/Payment/SEPA/SDD.tpl', 'weight' => -1));
  }


  /**
   * This function checks to see if we have the right config values
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
  function doDirectPayment(&$params) {
    $test_mode = ($this->_mode == 'test');
    $params['creditor_id'] = $this->_creditorId;

    // copy frequency_interval unit
    $params['frequency_interval'] = $params['frequency'];

    // see if the contribution type is there
    if (empty($params['contributionTypeID'])) {
      // if the type is not passed, look it up:
      $look_up = array('name'=>$params['contributionType_name']);
      $default = null;
      $financial_type = CRM_Financial_BAO_FinancialType::retrieve($look_up, $default);
      $params['contributionTypeID'] = $financial_type->id;
    }

    // get the contribution via 'invoiceID'
    $params['contact_id'] = $this->getForm()->getVar('_contactID');

    if (empty($params['is_recur'])) {
      return $this->_createOOFFmandate($params);
    } else {
      $params['contribution_id'] = $params['contributionID'];
      $params['contribution_recur_id'] = $params['contributionRecurID'];
      return $this->_createRCURmandate($params);
    }
  }


  function _createOOFFmandate(&$params) {
    // fix contribution
    $contribution = civicrm_api3('Contribution', 'create', array(
        'total_amount' => $params['amount'],
        'contact_id' => $params['contact_id'],
        'invoice_id' => $params['invoiceID'],
        'financial_type_id' => $params['contributionTypeID'],
        'payment_instrument_id' => CRM_Core_OptionGroup::getValue('payment_instrument', 'OOFF', 'name'),
        'contribution_status_id' => CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name'),
        'currency' => 'EUR',
        'receive_date' => date('YmdHis', strtotime($params['start_date'])),
        //'is_test' => ($this->_mode == 'test')?1:0,
    ));

    // create matching mandate
    $mandate = civicrm_api3('SepaMandate', 'create', array(
        'contact_id'                => $params['contact_id'],
        //'source'                    => $_REQUEST['source'],
        'entity_table'              => 'civicrm_contribution',
        'entity_id'                 => $contribution['id'],
        'creation_date'             => date('YmdHis'),
        'validation_date'           => date('YmdHis'),
        'date'                      => date('YmdHis'),
        'iban'                      => $params['bank_iban'],
        'bic'                       => $params['bank_bic'],
        'status'                    => 'OOFF',
        'type'                      => 'OOFF',
        'creditor_id'               => $params['creditor_id'],
        'is_enabled'                => 1,
      ));
    $mandate_id = $mandate['id'];
    $params['mandate_reference'] = $mandate['values'][$mandate_id]['reference'];

    return FALSE;
  }


  function _createRCURmandate(&$params) {

   // delete created contribution, if any => will be generated by batching
    if (!empty($params['contribution_id'])) {
      civicrm_api3('Contribution', 'delete', array('id'=>$params['contribution_id']));
    }

    // fix recurring contribution
    $contribution = civicrm_api3('ContributionRecur', 'create', array(
        'id' => $params['contribution_recur_id'],
        'amount' => $params['amount'],
        'contact_id' => $params['contact_id'],
        'financial_type_id' => $params['contributionTypeID'],
        'payment_instrument_id' => CRM_Core_OptionGroup::getValue('payment_instrument', 'RCUR', 'name'),
        'contribution_status_id' => CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name'),
        'currency' => 'EUR',
        'start_date' => date('YmdHis', strtotime($params['start_date'])),
        'create_date' => date('YmdHis'),
        'modified_date' => date('YmdHis'),
        'frequency_unit' => $params['frequency_unit'],
        'frequency_interval' => $params['frequency'],
        'cycle_day' => $params['cycle_day'],
        'is_email_receipt' => 0,
    ));

    // create matching mandate
    $mandate = civicrm_api3('SepaMandate', 'create', array(
        'contact_id'                => $params['contact_id'],
        'entity_table'              => 'civicrm_contribution_recur',
        'entity_id'                 => $params['contribution_recur_id'],
        'creation_date'             => date('YmdHis'),
        'validation_date'           => date('YmdHis'),
        'date'                      => date('YmdHis'),
        'iban'                      => $params['bank_iban'],
        'bic'                       => $params['bank_bic'],
        'status'                    => 'FRST',
        'type'                      => 'RCUR',
        'creditor_id'               => $params['creditor_id'],
        'is_enabled'                => 1,
      ));
    $mandate_id = $mandate['id'];
    $params['mandate_reference'] = $mandate['values'][$mandate_id]['reference'];

    return array(true);
  }


  function &error($errorCode = NULL, $errorMessage = NULL) {
    $e = CRM_Core_Error::singleton();
    if ($errorCode) {
      $e->push($errorCode, 0, NULL, $errorMessage);
    }
  }

}
