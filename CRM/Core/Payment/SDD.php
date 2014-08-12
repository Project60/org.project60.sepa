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

    // get the contribution via contactID
    $params['contact_id'] = $this->getForm()->getVar('_contactID');

    if (empty($params['is_recur'])) {
      // OOFF

      $params['financial_type_id'] = $params['contributionTypeID'];
      $params['type'] = 'OOFF';
      $params['status'] = 'OOFF';
      $params['iban'] = $params['bank_iban'];
      $params['bic']  = $params['bank_bic'];
      $params['creation_date'] = date('YmdHis');
      $contribution = civicrm_api3('SepaMandate', 'createfull', $params);

      return FALSE;
    } else {
      // RCUR

      // delete created contribution, if any => will be generated by batching
      if (!empty($params['contribution_id'])) {
        civicrm_api3('Contribution', 'delete', array('id'=>$params['contribution_id']));
      }

      $params['type'] = 'RCUR';
      $params['status'] = 'FRST';
      $params['iban'] = $params['bank_iban'];
      $params['bic']  = $params['bank_bic'];
      $params['creation_date'] = date('YmdHis');
      $params['frequency_interval'] = $params['frequency'];
      $params['financial_type_id'] = $params['contributionTypeID'];
      $params['contribution_id'] = $params['contributionID'];
      $params['contribution_recur_id'] = $params['contributionRecurID'];

      $contribution = civicrm_api3('SepaMandate', 'createfull', $params);

      return FALSE;
    }
  }

  function &error($errorCode = NULL, $errorMessage = NULL) {
    $e = CRM_Core_Error::singleton();
    if ($errorCode) {
      $e->push($errorCode, 0, NULL, $errorMessage);
    }
  }

}
