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


  function buildForm(&$form) {

    // add rules
    $form->registerRule('sepa_iban_valid', 'callback', 'rule_valid_IBAN', 'CRM_Sepa_Logic_Verification');
    $form->registerRule('sepa_bic_valid',  'callback', 'rule_valid_BIC',  'CRM_Sepa_Logic_Verification');    

    // apply "hack" for old payment forms
    if (version_compare(CRM_Utils_System::version(), '4.6.10', '<')) {
      $form->assign('pre4_6_10', 1);
      $this->fixOldDirectDebitForm($form);
    }

    // BUFFER DAYS
    $buffer_days      = (int) CRM_Sepa_Logic_Settings::getSetting("pp_buffer_days");
    $frst_notice_days = (int) CRM_Sepa_Logic_Settings::getSetting("batching.FRST.notice", $this->_creditorId);
    $ooff_notice_days = (int) CRM_Sepa_Logic_Settings::getSetting("batching.OOFF.notice", $this->_creditorId);
    $earliest_rcur_date = strtotime("now + $ooff_notice_days days + $buffer_days days");
    $earliest_ooff_date = strtotime("now + $ooff_notice_days days");

    // find the next cycle day
    $cycle_days = CRM_Sepa_Logic_Settings::getListSetting("cycledays", range(1, 28), $this->_creditorId);
    $earliest_cycle_day = $earliest_rcur_date;
    while (!in_array(date('d', $earliest_cycle_day), $cycle_days)) {
      $earliest_cycle_day = strtotime("+ 1 day", $earliest_cycle_day);
    }    

    $form->assign('earliest_rcur_date', date('Y-m-d', $earliest_rcur_date));
    $form->assign('earliest_ooff_date', date('Y-m-d', $earliest_ooff_date));
    $form->assign('earliest_cycle_day', date('d', $earliest_cycle_day));
    $form->assign('sepa_hide_bic', CRM_Sepa_Logic_Settings::getSetting("pp_hide_bic"));
    $form->assign('sepa_hide_billing', CRM_Sepa_Logic_Settings::getSetting("pp_hide_billing"));

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
    CRM_Utils_SepaOptionGroupTools::checkRecurringFrequencyUnits(TRUE, TRUE);
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

    // get contact ID (see SEPA-359)
    $contact_id = $this->getVar('_contactID');
    if (empty($contact_id)) {
      $form = $this->getForm();
      if (!empty($form) && is_object($form)) {
        $contact_id = $this->getForm()->getVar('_contactID');
      }
    }

    // prepare the creation of an incomplete mandate
    $params['creditor_id']   = $this->_creditorId;
    $params['contact_id']    = $contact_id;
    $params['source']        = substr($params['description'],0,64);
    $params['iban']          = $params['bank_account_number'];
    $params['bic']           = $params['bank_identification_number'];
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
      return CRM_Core_Error::createError(ts("Couldn't create SEPA mandate. Error was: ", array('domain' => 'org.project60.sepa')).$mandate['error_message']);
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
        'cycle_day'     => $params['cycle_day'],
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
          if (empty($mandate['contact_id'])) {
            // this happens when the payment gets created AFTER the doDirectPayment method
            $mandate_update['contact_id'] = $contribution_bao->contact_id;
          }
          
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
          $rcur_notice = (int) CRM_Sepa_Logic_Settings::getSetting("batching.FRST.notice", $mandate['creditor_id']);
          $now = strtotime(date('Y-m-d', strtotime("now +$rcur_notice days")));        // round to full day
          $collection_date = CRM_Sepa_Logic_Batching::getNextExecutionDate($rcontribution, $now, TRUE);

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
          if (empty($mandate['contact_id'])) {
            $mandate_update['contact_id']          = $contribution['contact_id'];
            $mandate['contact_id']                 = $contribution['contact_id'];
          }
          //NO: $mandate_update['first_contribution_id'] = $contribution['id'];
          
          // initialize according to the creditor settings
          CRM_Sepa_BAO_SEPACreditor::initialiseMandateData($mandate['creditor_id'], $mandate_update);

          // finally, write the changes to the mandate
          civicrm_api3('SepaMandate', 'create', $mandate_update);

          // ...and trigger notification 
          // FIXME: WORKAROUND, see https://github.com/Project60/org.project60.sepa/issues/296)
          CRM_Contribute_BAO_ContributionPage::recurringNotify(
            CRM_Core_Payment::RECURRING_PAYMENT_START,
            $mandate['contact_id'],
            $contribution_bao->contribution_page_id,
            $rcontribution_bao);

          // also, call the installemnt hook (this is the first installment)
          CRM_Utils_SepaCustomisationHooks::installment_created($mandate['id'], $rcontribution['id'], $contribution['id']);

        } else {
          // something went wrong, delete partial
          error_log("org.project60.sepa: deleting partial mandate " . $mandate['reference']);
          civicrm_api3('SepaMandate', 'delete', array('id' => $mandate_id));
        }
      }
    }
  }




  /***********************************************
   *            CiviCRM >= 4.6.10                *
   ***********************************************/

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

  /***********************************************
   *             CiviCRM < 4.6.10                *
   ***********************************************/

  public function fixOldDirectDebitForm(&$form) {
    // we don't need the default stuff:
    $form->_paymentFields = array();

    $form->add( 'text', 
                'bank_account_number', 
                ts('IBAN', array('domain' => 'org.project60.sepa')), 
                array('size' => 34, 'maxlength' => 34,), 
                TRUE);

    $form->add( 'text', 
                'bank_identification_number', 
                ts('BIC', array('domain' => 'org.project60.sepa')), 
                array('size' => 11, 'maxlength' => 11), 
                TRUE);

    $form->add( 'text', 
                'bank_name', 
                ts('Bank Name', array('domain' => 'org.project60.sepa')), 
                array('size' => 20, 'maxlength' => 64), 
                FALSE);

    $form->add( 'text', 
                'account_holder', 
                ts('Account Holder', array('domain' => 'org.project60.sepa')), 
                array('size' => 20, 'maxlength' => 64), 
                FALSE);

    $form->add( 'select', 
                'cycle_day', 
                ts('Collection Day', array('domain' => 'org.project60.sepa')), 
                CRM_Sepa_Logic_Settings::getListSetting("cycledays", range(1, 28), $this->_creditorId),
                FALSE);

    $form->addDate('start_date', 
                ts('Start Date', array('domain' => 'org.project60.sepa')), 
                TRUE, 
                array());

    // add rules
    $form->addRule('bank_account_number', ts('This is not a correct IBAN.', array('domain' => 'org.project60.sepa')), 'sepa_iban_valid');
    $form->addRule('bank_identification_number',  ts('This is not a correct BIC.', array('domain' => 'org.project60.sepa')),  'sepa_bic_valid');
  }  
}
