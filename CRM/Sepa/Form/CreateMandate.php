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

    // add creditor field
    $creditors = $this->getCreditors();
    $this->assign('sdd_creditors', json_encode($creditors));
    $this->add(
      'select',
      'creditor_id',
      E::ts('Creditor'),
      $this->getCreditorList($creditors),
      TRUE,
       array('class' => 'crm-select2')
    );

    // add contact field
    // TODO

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
    $this->add(
        'select',
        'type',
        E::ts('Mandate Type'),
        $this->getTypeList(),
        TRUE,
        array('class' => 'crm-select2')
    );


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


    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Create'),
        'isDefault' => TRUE,
      ),
    ));

    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();


//    CRM_Core_Session::setStatus(E::ts('You picked color "%1"', array(
//      1 => $options[$values['favorite_color']],
//    )));
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

  /**
   * Get the list of eligible types
   */
  protected function getTypeList() {
    // TODO: replace

    // default:
    return array(
        'OOFF' => E::ts("One-Off Debit (OOFF)"),
        'RCUR' => E::ts("Recurring Collection (RCUR)"));

  }

  /**
   * Get the list of (active) financial types
   */
  protected function getFinancialTypeList() {
    $list = array();
    $query = civicrm_api3('FinancialType', 'get',array(
        'is_active' => 1,
        'return'    => 'id,name'
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
        'is_active' => 1,
        'return'    => 'id,title'
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
        '12' => CRM_Utils_SepaOptionGroupTools::getFrequencyText(1, 'month', TRUE),
        '4' => CRM_Utils_SepaOptionGroupTools::getFrequencyText(3, 'month', TRUE),
        '2' => CRM_Utils_SepaOptionGroupTools::getFrequencyText(6, 'month', TRUE),
        '1' => CRM_Utils_SepaOptionGroupTools::getFrequencyText(12, 'month', TRUE)
    );
  }
}
