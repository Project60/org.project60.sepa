<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2018 SYSTOPIA                            |
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
 * Form interface to generate retry collections,
 *  i.e. a new, out of turn, collection of previously failed collections
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Sepa_Form_RetryCollection extends CRM_Core_Form {
  public function buildQuickForm() {

    // add form elements
    $this->add(
      'select',
      'date_range',
      E::ts('Date Range'),
      $this->getDateRangePresets(),
      TRUE);

    $creditor_list = $this->getCreditorList();
    $this->assign('creditor_list', $creditor_list);
    $this->add(
        'select',
        'creditor_list',
        E::ts('Creditor(s)'),
        $creditor_list,
        TRUE,
        array('class' => 'crm-select2', 'multiple' => 'multiple'));

    $this->add(
        'select',
        'txgroup_list',
        E::ts('SDD Groups'),
        array(),
        TRUE,
        array('class' => 'crm-select2', 'multiple' => 'multiple'));

    $this->add(
        'select',
        'cancel_reason_list',
        E::ts('Cancel Reason'),
        array(),
        TRUE,
        array('class' => 'crm-select2', 'multiple' => 'multiple'));

    $this->add(
        'select',
        'frequency_list',
        E::ts('Frequency'),
        array(),
        TRUE,
        array('class' => 'crm-select2', 'multiple' => 'multiple'));

    $this->add(
        'text',
        'amount_min',
        E::ts('Installment Amount'),
        array('size' => 8));

    $this->add(
        'text',
        'amount_max',
        E::ts('Installment Amount'),
        array('size' => 8));


    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Generate'),
        'isDefault' => TRUE,
      ),
    ));

    // inject JS file
    CRM_Core_Resources::singleton()->addScriptFile('org.project60.sepa', 'js/RetryCollection.js');

    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();
//    $options = $this->getColorOptions();
//    CRM_Core_Session::setStatus(E::ts('You picked color "%1"', array(
//      1 => $options[$values['favorite_color']],
//    )));
    parent::postProcess();
  }

  /**
   * Get the presets for the date field
   */
  protected function getDateRangePresets() {
    return array(
        'last month'    => E::ts('This Month'),
        'last month'    => E::ts('Last Month'),
        'last 2 months' => E::ts('Last 2 Months'),
        'last 3 months' => E::ts('Last 3 Months'),
        'custom'        => E::ts('Custom Range'),
    );
  }

  /*
   * Get the list of creditors
   */
  protected function getCreditorList() {
    $creditor_list = array();
    $creditor_query = civicrm_api3('SepaCreditor', 'get', array(
      'option.limit' => 0,
      'return'       => 'name,id'));
    foreach ($creditor_query['values'] as $creditor) {
      $creditor_list[$creditor['id']] = $creditor['name'];
    }
    return $creditor_list;
  }
}
