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
use CRM_Sepa_ExtensionUtil as E;

/**
 * SEPA Create Mandate form
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Sepa_Form_CreateMandate extends CRM_Core_Form {

  /** @var $contact_id the contact ID to create the mandate for */
  protected $contact_id;

  public function buildQuickForm() {
    // get the contact_id
    $this->contact_id = (int) CRM_Utils_Request::retrieve('cid', 'Positive');
    if (empty($this->contact_id)) {
      CRM_Core_Error::fatal("No contact ID (cid) given.");
    }

    // load the contact
    $contact_name = civicrm_api3('Contact', 'getvalue', array(
        'id'     => $this->contact_id,
        'return' => 'display_name'));
    CRM_Utils_System::setTitle(E::ts("Create SEPA Mandate for Contact [%1]: %2", array(
        1 => $this->contact_id, 2 => $contact_name)));

    // add contact_id field
    $this->add('hidden', 'contact_id', $this->contact_id);

    // add creditor field
    $js_vars['creditor_data'] = $this->getCreditors();
    $this->add(
      'select',
      'creditor_id',
      E::ts('Creditor'),
      $this->getCreditorList($js_vars['creditor_data']),
      TRUE,
       array('class' => 'crm-select2')
    );

    // add financial type
    $this->add(
        'select',
        'financial_type_id',
        E::ts('Financial Type'),
        $this->getFinancialTypeList(),
        TRUE,
        array('class' => 'crm-select2')
    );

    // add campaign
    $this->add(
        'select',
        'campaign_id',
        E::ts('Campaign'),
        $this->getCampaignList(),
        FALSE,
        array('class' => 'crm-select2')
    );

    // add mandate reference
    $this->add(
        'text',
        'reference',
        E::ts('Reference'),
        array('placeholder' => E::ts("not required, will be generated"), 'size' => '34')
    );

    // add source field
    $this->add(
        'text',
        'source',
        E::ts('Source'),
        array('placeholder' => E::ts("not required"), 'size' => '64')
    );

    // add bank account selector
    $this->add(
        'select',
        'bank_account_preset',
        E::ts('Account'),
        $this->getKnownBankAccounts(),
        FALSE,
        array('class' => 'crm-select2 huge')
    );

    // add iban field
    $this->add(
        'text',
        'iban',
        E::ts('IBAN'),
        array('placeholder' => E::ts("required"), 'size' => '32'),
        TRUE
    );

    // add bic field
    $this->add(
        'text',
        'bic',
        E::ts('BIC'),
        array('placeholder' => E::ts("required"), 'size' => '14'),
        TRUE
    );

    // add amount field
    $this->addMoney(
        'amount',
        ts('Amount'),
        TRUE,
        array('class' => 'tiny')
    );

    // add currency field
    $this->add(
        'select',
        'currency',
        E::ts('Currency'),
        $this->getCurrencyList(),
        TRUE,
        array('class' => 'tiny')
    );

    // add type field
//    $this->add(
//        'select',
//        'type',
//        E::ts('Mandate Type'),
//        $this->getTypeList(),
//        TRUE,
//        array('class' => 'crm-select2')
//    );


    // add 'replaces' fields

    // add OOFF fields
    // add collection date
    $this->addDate(
        'ooff_date',
        E::ts("Collection Date"),
        FALSE,
        array('formatType' => 'activityDate'));

    // add RCUR fields
    // add start date
    $this->addDate(
        'rcur_start_date',
        E::ts("Start Date"),
        FALSE,
        array('formatType' => 'activityDate'));

    // add collection day
    $this->add(
        'select',
        'cycle_day',
        E::ts('Day of Month'),
        range(1,31),
        FALSE,
        array()
    );

    // add interval
    $this->add(
        'select',
        'interval',
        E::ts('Frequency'),
        $this->getFrequencyList(),
        FALSE,
        array()
    );

    // add end_date
    $this->addDate(
        'rcur_end_date',
        E::ts("End Date"),
        FALSE,
        array('formatType' => 'activityDate'));

    // finally, add a date field just as a converter
    $this->addDate('sdd_converter', 'just for date conversion', FALSE, array('formatType' => 'activityDate'));


    // inject JS logic
    CRM_Core_Resources::singleton()->addVars('p60sdd', $js_vars);
    CRM_Core_Resources::singleton()->addScriptFile('org.project60.sepa', 'js/CreateMandate.js');

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Create'),
        'isDefault' => TRUE,
      ),
    ));

    parent::buildQuickForm();
  }


  /**
   * Set the defaults
   *
   * @return array|NULL|void
   */
  public function setDefaultValues() {
    $defaults = parent::setDefaultValues();

    // TODO: anything?

    return $defaults;
  }


  public function postProcess() {
    $values = $this->exportValues();

    CRM_Core_Error::debug_log_message("MANDATE: " . json_encode($values));

    // create a new mandate
    $type = $values['interval'] ? 'RCUR' : 'OOFF';
    $mandate_data = array(
        'type'                      => $type,
        'creation_date'             => date('YmdHis'),
        'creditor_id'               => $values['creditor_id'],
        'contact_id'                => $values['contact_id'],
        'campaign_id'               => $values['campaign_id'],
        'financial_type_id'         => $values['financial_type_id'],
        'currency'                  => $values['currency'],
        'iban'                      => $values['iban'],
        'bic'                       => $values['bic'],
        'cycle_day'                 => $values['cycle_day'],
        'amount'                    => $values['amount'],
        'frequency_interval'        => $type == 'RCUR' ? 12 / $values['interval'] : 0,
        'frequency_unit'            => 'month',
        'reference'                 => $values['reference'],
        'source'                    => $values['source'],
        'receive_date'              => $type == 'OOFF' ? CRM_Utils_Date::processDate($values['ooff_date']) : '',
        'start_date'                => $type == 'RCUR' ? CRM_Utils_Date::processDate($values['rcur_start_date']) : '',
        'end_date'                  => empty($values['rcur_end_date']) ? '' : CRM_Utils_Date::processDate($values['rcur_end_date']),
    );

    try {
      $mandate = civicrm_api3('SepaMandate', 'createfull', $mandate_data);
      $mandate = civicrm_api3('SepaMandate', 'getsingle', array(
          'id'     => $mandate['id'],
          'return' => 'reference,id,type'));

      // if we get here, everything went o.k.
      CRM_Core_Session::setStatus(E::ts("'%3' SEPA Mandate <a href=\"%2\">%1</a> created.", array(
          1 => $mandate['reference'],
          2 => CRM_Utils_System::url('civicrm/sepa/xmandate', "mid={$mandate['id']}"),
          3 => $mandate['type'])),
          E::ts("Success"),
          'info');

    } catch (Exception $ex) {
      // there was a problem: create error message
      CRM_Core_Session::setStatus(E::ts("Failed to create %1 mandate. Error was: %2", array(
          1 => $type,
          2 => $ex->getMessage())),
          E::ts("Error"),
          'error');
    }

//    $reference   = $mandate['values'][$mandate['id']]['reference'];
//    $mandate_url = CRM_Utils_System::url('civicrm/sepa/xmandate', "mid={$mandate['id']}");
//    CRM_Core_Session::setStatus(ts("'%3' SEPA Mandate <a href=\"%2\">%1</a> created.", array(1 => $reference, 2 => $mandate_url, 3 => $type, 'domain' => 'org.project60.sepa')), ts("Success", array('domain' => 'org.project60.sepa')), 'info');

    /*
     *     // first create a contribution
        $payment_instrument_id  = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'payment_instrument_id', $type);
        $contribution_status_id = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Pending');

        // check creditor
        $creditor = civicrm_api3('SepaCreditor', 'getsingle', array('id' => $_REQUEST['creditor_id']));

        $contribution_data = array(
            'version'                   => 3,
            'contact_id'                => $_REQUEST['contact_id'],
            'campaign_id'               => $_REQUEST['campaign_id'],
            'financial_type_id'         => $_REQUEST['financial_type_id'],
            'payment_instrument_id'     => $payment_instrument_id,
            'contribution_status_id'    => $contribution_status_id,
            'currency'                  => $creditor['currency'],
          );

        if ($type=='OOFF') {
          $initial_status = 'OOFF';
          $entity_table = 'civicrm_contribution';
          $contribution_data['total_amount'] = number_format($_REQUEST['total_amount'], 2, '.', '');
          $contribution_data['receive_date'] = $_REQUEST['date'];
          $contribution_data['source'] = $_REQUEST['source'];
          $contribution = civicrm_api('Contribution', 'create', $contribution_data);
        } else if ($type=='RCUR') {
          $initial_status = 'FRST';
          $entity_table = 'civicrm_contribution_recur';
          $contribution_data['amount']              = number_format($_REQUEST['total_amount'], 2, '.', '');
          $contribution_data['start_date']          = $_REQUEST['start_date'];
          $contribution_data['end_date']            = $_REQUEST['end_date'];
          $contribution_data['create_date']         = date('YmdHis');
          $contribution_data['modified_date']       = date('YmdHis');
          $contribution_data['frequency_unit']      = 'month';
          $contribution_data['frequency_interval']  = $_REQUEST['interval'];
          $contribution_data['cycle_day']           = $_REQUEST['cycle_day'];
          $contribution_data['is_email_receipt']    = 0;
          $contribution = civicrm_api('ContributionRecur', 'create', $contribution_data);
        }

        if (isset($contribution['is_error']) && $contribution['is_error']) {
          $this->processError(
            sprintf(ts("Couldn't create contribution for contact #%s", array('domain' => 'org.project60.sepa')), $_REQUEST['contact_id']),
            ts("Couldn't create contribution", array('domain' => 'org.project60.sepa')),
            $contribution['error_message'],
            $_REQUEST['contact_id']);
          return;
        }

        // next, create mandate
        $mandate_data = array(
            'version'                   => 3,
            'debug'                     => 1,
            'contact_id'                => $_REQUEST['contact_id'],
            'source'                    => $_REQUEST['source'],
            'entity_table'              => $entity_table,
            'entity_id'                 => $contribution['id'],
            'creation_date'             => date('YmdHis'),
            'validation_date'           => date('YmdHis'),
            'date'                      => date('YmdHis'),
            'iban'                      => $_REQUEST['iban'],
            'bic'                       => $_REQUEST['bic'],
            'reference'                 => $_REQUEST['reference'],
            'status'                    => $initial_status,
            'type'                      => $type,
            'creditor_id'               => $_REQUEST['creditor_id'],
            'is_enabled'                => 1,
          );
        // call the hook for mandate generation

        $mandate = civicrm_api('SepaMandate', 'create', $mandate_data);
        if (isset($mandate['is_error']) && $mandate['is_error']) {
          $this->processError(
            sprintf(ts("Couldn't create %s mandate for contact #%s", array('domain' => 'org.project60.sepa')), $type, $_REQUEST['contact_id']),
            ts("Couldn't create mandate", array('domain' => 'org.project60.sepa')),
            $mandate['error_message'],
            $_REQUEST['contact_id']);
          return;
        }

        // if we want to replace an old mandate:
        if (isset($_REQUEST['replace'])) {
          CRM_Sepa_BAO_SEPAMandate::terminateMandate($_REQUEST['replace'], $_REQUEST['replace_date'], $_REQUEST['replace_reason']);
        }

        // if we get here, everything went o.k.
        $reference   = $mandate['values'][$mandate['id']]['reference'];
        $mandate_url = CRM_Utils_System::url('civicrm/sepa/xmandate', "mid={$mandate['id']}");
        CRM_Core_Session::setStatus(ts("'%3' SEPA Mandate <a href=\"%2\">%1</a> created.", array(1 => $reference, 2 => $mandate_url, 3 => $type, 'domain' => 'org.project60.sepa')), ts("Success", array('domain' => 'org.project60.sepa')), 'info');

        if (!$this->isPopup()) {
          $contact_url = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$contribution_data['contact_id']}&selectedChild=contribute");
          CRM_Utils_System::redirect($contact_url);
        }

     */
    parent::postProcess();
  }



  // ############################# HELPER FUNCTIONS #############################

  /**
   * Get a list of all creditors, extended by
   *  - the list of cycle_days
   *  - a 'default' flag
   *  - the currency
   */
  protected function getCreditors() {
    $default_creditor_id = CRM_Sepa_Logic_Settings::defaultCreditor();

    $creditor_query = civicrm_api3('SepaCreditor', 'get', array());
    $creditors = $creditor_query['values'];

    foreach ($creditors as &$creditor) {
      // add default flag
      if ($creditor['id'] == $default_creditor_id) {
        $creditor['is_default'] = 1;
      } else {
        $creditor['is_default'] = 0;
      }

      // add cycle days
      $creditor['cycle_days'] = CRM_Sepa_Logic_Settings::getListSetting("cycledays", range(1, 28), $creditor['id']);
      $creditor['buffer_days'] = (int) CRM_Sepa_Logic_Settings::getSetting("pp_buffer_days");
      $creditor['ooff_notice'] = (int) CRM_Sepa_Logic_Settings::getSetting("batching.OOFF.notice", $creditor['id']);
      $creditor['frst_notice'] = (int) CRM_Sepa_Logic_Settings::getSetting("batching.FRST.notice", $creditor['id']);
    }

    return $creditors;
  }

  /**
   * Creates a neat dropdown list of the eligible creditors
   * @param $creditors
   * @return array of eligible creditors
   */
  protected function getCreditorList($creditors) {
    $creditor_list = array();
    foreach ($creditors as $creditor) {
      $creditor_list[$creditor['id']] = "[{$creditor['id']}] {$creditor['name']}";
    }
    return $creditor_list;
  }

//  /**
//   * Get the list of eligible types
//   */
//  protected function getTypeList() {
//    // TODO: replace
//
//    // default:
//    return array(
//        'RCUR' => E::ts("Recurring Collection (RCUR)"),
//        'OOFF' => E::ts("One-Off Debit (OOFF)"),
//    );
//  }

  /**
   * Get the list of (active) financial types
   */
  protected function getFinancialTypeList() {
    $list = array();
    $query = civicrm_api3('FinancialType', 'get',array(
        'is_active'    => 1,
        'option.limit' => 0,
        'return'       => 'id,name'
    ));

    foreach ($query['values'] as $value) {
      $list[$value['id']] = $value['name'];
    }

    return $list;
  }

  /**
   * Get the list of (active) financial types
   */
  protected function getCampaignList() {
    $list = array('' => E::ts("- none -"));
    $query = civicrm_api3('Campaign', 'get',array(
        'is_active'    => 1,
        'option.limit' => 0,
        'return'       => 'id,title'
    ));

    foreach ($query['values'] as $value) {
      $list[$value['id']] = $value['title'];
    }

    return $list;
  }

  /**
   * Get a list of known bank accounts from:
   *  - successful SEPA mandates
   *  - known CiviBanking accounts
   */
  protected function getKnownBankAccounts() {
    $known_accounts = array('' => E::ts("new account"));

    // get data from SepaMandates
    $mandates = civicrm_api3('SepaMandate', 'get', array(
      'contact_id'   => $this->contact_id,
      'status'       => array('IN' => array('RCUR', 'COMPLETE', 'SENT')),
      'option.limit' => 0,
      'return'       => 'iban,bic,reference',
      'option.sort'  => 'id desc'
    ));
    CRM_Core_Error::debug_log_message("mada " . json_encode($mandates));
    foreach ($mandates['values'] as $mandate) {
      $key = "{$mandate['iban']}/{$mandate['bic']}";
      if (!isset($known_accounts[$key])) {
        $known_accounts[$key] = "{$mandate['iban']} ({$mandate['reference']})";
      }
    }

    // get data from CiviBanking (if installed)
    if (class_exists('CRM_Banking_BAO_BankAccountReference')) {
      $iban_reference_type_id = civicrm_api3('OptionValue', 'getvalue', array(
          'return'          => 'id',
          'option_group_id' => 'civicrm_banking.reference_types',
          'value'           => 'IBAN',
      ));

      if ($iban_reference_type_id) {
        $accounts = civicrm_api3('BankingAccount', 'get', array(
            'contact_id'   => $this->contact_id,
            'option.limit' => 0,
            'return'       => 'id,data_parsed',
            'sequential'   => 0,
        ));

        $account_references = civicrm_api3('BankingAccountReference', 'get', array(
            'ba_id'             => array('IN' => array_keys($accounts['values'])),
            'reference_type_id' => $iban_reference_type_id,
            'option.limit'      => 0,
            'return'            => 'reference,ba_id',
            'sequential'        => 1,
            'option.sort'       => 'id desc'
        ));

        foreach ($account_references['values'] as $account_reference) {
          $account = $accounts['values'][$account_reference['ba_id']];
          $account_data = json_decode($account['data_parsed'], TRUE);
          $bic = CRM_Utils_Array::value('BIC', $account_data, CRM_Utils_Array::value('bic', $account_data, ''));
          $key = "{$account_reference['reference']}/{$bic}";
          $account_already_in_list = FALSE;
          if ($bic) {
            $account_already_in_list = isset($known_accounts[$key]);
          } else { // no BIC? we'll have to search
            foreach ($known_accounts as $existing_key => $value) {
              if ($key == substr($existing_key, 0, strlen($key))) {
                $account_already_in_list = TRUE;
                break;
              }
            }
          }
          if (!$account_already_in_list) {
            $account_name = empty($account_data['name']) ? E::ts("CiviBanking") : "'{$account_data['name']}'";
            $known_accounts[$key] = "{$account_reference['reference']} ({$account_name})";
          }
        }
      }
    }

    return $known_accounts;
  }

  /**
   * Get available currencies
   */
  protected function getCurrencyList() {
    return CRM_Core_OptionGroup::values('currencies_enabled');
  }

  /**
   * Get allowed frequencies
   *
   * @return array list of frequency to title
   */
  protected function getFrequencyList() {
    return array(
        '0'  => E::ts("One-time only (OOFF)"),
        '12' => CRM_Utils_SepaOptionGroupTools::getFrequencyText( 1, 'month', TRUE),
        '4'  => CRM_Utils_SepaOptionGroupTools::getFrequencyText( 3, 'month', TRUE),
        '2'  => CRM_Utils_SepaOptionGroupTools::getFrequencyText( 6, 'month', TRUE),
        '1'  => CRM_Utils_SepaOptionGroupTools::getFrequencyText(12, 'month', TRUE),
    );
  }
}
