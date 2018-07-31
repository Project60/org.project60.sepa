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

  public function buildQuickForm() {

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

    // add bank account field

    // add iban field

    // add bic field

    // add type field
    $this->add(
        'select',
        'type',
        E::ts('Mandate Type'),
        $this->getTypeList(),
        TRUE,
        array('class' => 'crm-select2')
    );

    // add amount field

    // add currency field

    // add 'replaces' fields

    // add OOFF fields
    // add collection date

    // add RCUR fields
    // add start date
    // add collection day
    // add interval
    // add end_date
    // show next collection




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
   * @return list of eligible creditors
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
        'OOFF' => E::ts("One-Off Collection (OOFF)"),
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
}
