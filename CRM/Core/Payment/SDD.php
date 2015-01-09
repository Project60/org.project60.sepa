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
    // check if we're in test mode
    if ($this->_mode == 'test') {
      $test = ' Test';
    } else {
      $test = '';
    }

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

    $form->add( 'select', 
                'cycle_day', 
                ts('Collection Day'), 
                CRM_Sepa_Logic_Settings::getListSetting("cycledays", range(1, 28), $this->_creditorId),
                FALSE);

    $form->addDate('start_date', 
                ts('start date'), 
                TRUE, 
                array());

    $form->registerRule('sepa_iban_valid', 'callback', 'rule_valid_IBAN', 'CRM_Sepa_Logic_Verification');
    $form->registerRule('sepa_bic_valid',  'callback', 'rule_valid_BIC',  'CRM_Sepa_Logic_Verification');
    $form->addRule('bank_iban', ts('This is not a correct IBAN.'), 'sepa_iban_valid');
    $form->addRule('bank_bic',  ts('This is not a correct BIC.'),  'sepa_bic_valid');


    $rcur_notice_days = (int) CRM_Sepa_Logic_Settings::getSetting("batching.RCUR.notice", $this->_creditorId);
    $ooff_notice_days = (int) CRM_Sepa_Logic_Settings::getSetting("batching.OOFF.notice", $this->_creditorId);
    $timestamp_rcur = strtotime("now + $rcur_notice_days days");
    $timestamp_ooff = strtotime("now + $ooff_notice_days days");
    $earliest_rcur_date = array(date('Y', $timestamp_rcur), date('m', $timestamp_rcur), date('d', $timestamp_rcur));
    $earliest_ooff_date = array(date('Y', $timestamp_ooff), date('m', $timestamp_ooff), date('d', $timestamp_ooff));
    $form->assign('earliest_rcur_date', $earliest_rcur_date);
    $form->assign('earliest_ooff_date', $earliest_ooff_date);

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
   * This function collects all the information and 
   * "simulates" a payment processor by creating an incomplete mandate,
   * that will later be connected with the results of the rest of the
   * payment process
   *
   * @param  array $params assoc array of input parameters for this transaction
   *
   * @return array the result in an nice formatted array (or an error object)
   */
  function doDirectPayment(&$params) {
    $original_parameters = $params;

    // prepare the creation of an incomplete mandate
    $params['creditor_id']   = $this->_creditorId;
    $params['contact_id']    = $this->getForm()->getVar('_contactID');
    $params['source']        = $params['description'];
    $params['iban']          = $params['bank_iban'];
    $params['bic']           = $params['bank_bic'];
    $params['creation_date'] = date('YmdHis');
    $params['status']        = 'PARTIAL';

    if (empty($params['is_recur'])) {
      $params['type']          = 'OOFF';
      $params['entity_table']  = 'civicrm_contribution';
    } else {
      $params['type']          = 'RCUR';
      $params['entity_table']  = 'civicrm_contribution_recur';
    }

    // we don't have the associated entity id yet
    // so we set MAX_INT as a dummy value
    // remark: setting this to 0/NULL does not work
    // due to complications with the api
    $params['entity_id'] = pow(2,32)-1;

    // Allow further manipulation of the arguments via custom hooks ..
    CRM_Utils_Hook::alterPaymentProcessorParams($this, $original_parameters, $params);

    // create the mandate
    $params['version'] = 3;
    $mandate = civicrm_api('SepaMandate', 'create', $params);
    if (!empty($mandate['is_error'])) {
      return CRM_Core_Error::createError(ts("Couldn't create SEPA mandate. Error was: ").$mandate['error_message']);
    }
    $params['trxn_id'] = $mandate['values'][$mandate['id']]['reference'];
    $params['sepa_start_date'] = empty($params['start_date'])?date('YmdHis'):date('YmdHis', strtotime($params['start_date']));

    // update the contribution, if existing (RCUR case)
    if (!empty($params['contributionID'])) {
      $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $params['contributionID'])); 
      civicrm_api3('Contribution', 'create', array(
        'id'           => $params['contributionID'], 
        'contact_id'   => $contribution['contact_id'], // resubmit, leaving it out causes errors sometimes
        'receive_date' => $params['sepa_start_date'],
        'trxn_id'      => $params['trxn_id']));
    }

    if (!empty($params['contributionRecurID'])) {
      civicrm_api3('ContributionRecur', 'create', array(
        'id'            => $params['contributionRecurID'], 
        'start_date'    => $params['sepa_start_date'],
        'trxn_id'       => $params['trxn_id']));
    }

    return $params;
  }


  /**
   * This is the counterpart to the doDirectPayment method. This method creates
   * partial mandates, where the subsequent payment processess produces a payment.
   *
   * This function here should be called after the payment process was completed.
   * It will process all the PARTIAL mandates and connect them with created contributions.
   */ 
  public static function processPartialMandates() {
    // load all the PARTIAL mandates
    $partial_mandates = civicrm_api3('SepaMandate', 'get', array('version'=>3, 'status'=>'PARTIAL', 'option.limit'=>9999));
    foreach ($partial_mandates['values'] as $mandate_id => $mandate) {
      if ($mandate['type']=='OOFF') {
        // in the OOFF case, we need to find the contribution, and connect it
        $contribution = civicrm_api('Contribution', 'getsingle', array('version'=>3, 'trxn_id' => $mandate['reference']));
        if (empty($contribution['is_error'])) {
          // check collection date
          $ooff_notice = (int) CRM_Sepa_Logic_Settings::getSetting("batching.OOFF.notice", $mandate['creditor_id']);
          $first_collection_date = strtotime("+$ooff_notice days");
          $collection_date = strtotime($contribution['receive_date']);
          if ($collection_date < $first_collection_date) {
            // adjust collection date to the earliest possible one
            $collection_date = $first_collection_date;
          }

          // FOUND! Update the contribution... 
          $contribution_bao = new CRM_Contribute_BAO_Contribution();
          $contribution_bao->get('id', $contribution['id']);
          $contribution_bao->is_pay_later = 0;
          $contribution_bao->receive_date = date('YmdHis', $collection_date);
          $contribution_bao->contribution_status_id = (int) CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name');
          $contribution_bao->payment_instrument_id = (int) CRM_Core_OptionGroup::getValue('payment_instrument', 'OOFF', 'name');
          $contribution_bao->save();

          // ...and connect it to the mandate
          $mandate_update = array();
          $mandate_update['id']        = $mandate['id'];
          $mandate_update['entity_id'] = $contribution['id'];
          $mandate_update['type']      = $mandate['type'];
          
          // initialize according to the creditor settings
          CRM_Sepa_BAO_SEPACreditor::initialiseMandateData($mandate['creditor_id'], $mandate_update);

          // finally, write the changes to the mandate
          civicrm_api3('SepaMandate', 'create', $mandate_update);

        } else {
          // if NOT FOUND or error, delete the partial mandate
          civicrm_api3('SepaMandate', 'delete', array('id' => $mandate_id));
        }


      } elseif ($mandate['type']=='RCUR') {
        // in the RCUR case, we also need to find the contribution, and connect it
        
        // load the contribution AND the associated recurring contribution
        $contribution =  civicrm_api('Contribution', 'getsingle', array('version'=>3, 'trxn_id' => $mandate['reference']));
        $rcontribution = civicrm_api('ContributionRecur', 'getsingle', array('version'=>3, 'trxn_id' => $mandate['reference']));
        if (empty($contribution['is_error']) && empty($rcontribution['is_error'])) {
          // we need to set the receive date to the correct collection date, otherwise it will be created again (w/o)
          $rcur_notice = (int) CRM_Sepa_Logic_Settings::getSetting("batching.RCUR.notice", $mandate['creditor_id']);
          $now = strtotime(date('Y-m-d', strtotime("now +$rcur_notice days")));        // round to full day
          $collection_date = CRM_Sepa_Logic_Batching::getNextExecutionDate($rcontribution, $now);

          // fix contribution
          $contribution_bao = new CRM_Contribute_BAO_Contribution();
          $contribution_bao->get('id', $contribution['id']);
          $contribution_bao->is_pay_later = 0;
          $contribution_bao->contribution_status_id = (int) CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name');
          $contribution_bao->payment_instrument_id = (int) CRM_Core_OptionGroup::getValue('payment_instrument', 'FRST', 'name');
          $contribution_bao->receive_date = date('YmdHis', strtotime($collection_date));
          $contribution_bao->save();

          // fix recurring contribution
          $rcontribution_bao = new CRM_Contribute_BAO_ContributionRecur();
          $rcontribution_bao->get('id', $rcontribution['id']);
          $rcontribution_bao->start_date = date('YmdHis', strtotime($rcontribution_bao->start_date));
          $rcontribution_bao->create_date = date('YmdHis', strtotime($rcontribution_bao->create_date));
          $rcontribution_bao->modified_date = date('YmdHis', strtotime($rcontribution_bao->modified_date));
          $rcontribution_bao->contribution_status_id = (int) CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name');
          $rcontribution_bao->payment_instrument_id = (int) CRM_Core_OptionGroup::getValue('payment_instrument', 'FRST', 'name');
          $rcontribution_bao->save();

          // ...and connect it to the mandate
          $mandate_update = array();
          $mandate_update['id']                    = $mandate['id'];
          $mandate_update['entity_id']             = $rcontribution['id'];
          $mandate_update['type']                  = $mandate['type'];
          //NO: $mandate_update['first_contribution_id'] = $contribution['id'];
          
          // initialize according to the creditor settings
          CRM_Sepa_BAO_SEPACreditor::initialiseMandateData($mandate['creditor_id'], $mandate_update);

          // finally, write the changes to the mandate
          civicrm_api3('SepaMandate', 'create', $mandate_update);

        } else {
          // something went wrong, delete partial
          error_log("org.project60.sepa: deleting partial mandate " . $mandate['reference']);
          civicrm_api3('SepaMandate', 'delete', array('id' => $mandate_id));
        }
      }
    }
  }
}
