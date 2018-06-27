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
    CRM_Utils_System::setTitle(E::ts("Retry Collection of Failed DDs"));

    $js_vars = array();

    // add form elements
    $this->add(
      'select',
      'date_range',
      E::ts('Date Range'),
      $this->getDateRangePresets(),
      TRUE);

    $creditor_list = $this->getCreditorList();
    $js_vars['creditor_list'] = $creditor_list;
    $this->add(
        'select',
        'creditor_list',
        E::ts('Creditor(s)'),
        $creditor_list,
        FALSE,
        array('class' => 'crm-select2', 'multiple' => 'multiple'));

    $txgroup_list = $this->getGroupList();
    $js_vars['txgroup_list'] = $txgroup_list;
    $this->add(
        'select',
        'txgroup_list',
        E::ts('SDD Groups'),
        $txgroup_list,
        TRUE,
        array('class' => 'crm-select2', 'multiple' => 'multiple'));

    $this->add(
        'select',
        'cancel_reason_list',
        E::ts('Cancel Reason'),
        array(),
        FALSE,
        array('class' => 'crm-select2', 'multiple' => 'multiple'));

    $frequency_list = $this->getFrequencyList();
    $js_vars['frequencies'] = $frequency_list;
    $this->add(
        'select',
        'frequencies',
        E::ts('Frequency'),
        $frequency_list,
        FALSE,
        array('class' => 'crm-select2', 'multiple' => 'multiple'));

    $this->add(
        'text',
        'amount_min',
        E::ts('Installment Amount'),
        array('size' => 6, 'style' => 'text-align:center;'));

    $this->add(
        'text',
        'amount_max',
        E::ts('Installment Amount'),
        array('size' => 6, 'style' => 'text-align:center;'));

    $this->addDate(
        'collection_date',
        E::ts('Collection Date'),
        TRUE,
        array('formatType' => 'activityDate'));


    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Generate'),
        'isDefault' => TRUE,
      ),
    ));

    // inject JS file
    CRM_Core_Resources::singleton()->addVars('p60sdd', $js_vars);
    CRM_Core_Resources::singleton()->addScriptFile('org.project60.sepa', 'js/RetryCollection.js');

    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();

    // format some values
    $values['collection_date'] = CRM_Utils_Date::processDate($values['collection_date'], null, null, 'YmdHis');

    // process from-to dates
    if ($values['date_range'] != 'custom') {
      list($values['date_from'], $values['date_to']) = explode('-', $values['date_range']);
    }

    // generate the new group
    CRM_Sepa_Logic_Retry::createRetryGroup($values);

    // go to dashboard
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/sepa/dashboard', 'status=active'));

    parent::postProcess();
  }

  /**
   * Get the presets for the date field
   */
  protected function getDateRangePresets() {
    $presets = array();
    // add "this month"
    $presets[date('Ym01000000') . '-now'] = E::ts('This Month');

    // add "last week"
    $from = date('YmdHis', strtotime('now - 7 days'));
    $presets["{$from}-now"] = E::ts('Last 7 Days');

    // add "last 2 weeks"
    $from = date('YmdHis', strtotime('now - 14 days'));
    $presets["{$from}-now"] = E::ts('Last 14 Days');

    // add "last 30 days"
    $from = date('YmdHis', strtotime(date('YmdHis') . ' - 30 days'));
    $presets["{$from}-now"] = E::ts('Last 30 Days');

    // add "last 60 days"
    $from = date('YmdHis', strtotime(date('YmdHis') . ' - 60 days'));
    $presets["{$from}-now"] = E::ts('Last 60 Days');

    // add "last 90 days"
    $from = date('YmdHis', strtotime(date('YmdHis') . ' - 90 days'));
    $presets["{$from}-now"] = E::ts('Last 90 Days');

    // add "last month"
    $from = date('YmdHis', strtotime(date('Y-m-01') . ' - 1 month'));
    $to   = date('YmdHis', strtotime(date('Y-m-01') . ' - 1 second'));
    $presets["{$from}-{$to}"] = E::ts('Last Calendar Month');

    // add last two months
    $from = date('YmdHis', strtotime(date('Y-m-01') . ' - 2 month'));
    $to   = date('YmdHis', strtotime(date('Y-m-01') . ' - 1 second'));
    $presets["{$from}-{$to}"] = E::ts('Last Two Calendar Months');

    // finally: add custom option
    // TODO: implement: $presets['custom'] = E::ts('Custom Range');
    return $presets;
  }

  /*
   * Get the list of creitors
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

  /*
   * Get the list of creditors
   */
  protected function getGroupList() {
    $txgroup_list = array();
    $txgroup_query = civicrm_api3('SepaTransactionGroup', 'get', array(
        'option.limit' => 0,
        'type'         => array('IN' => array('RCUR', 'FRST')),
        'return'       => 'reference,id'));
    foreach ($txgroup_query['values'] as $txgroup) {
      $txgroup_list[$txgroup['id']] = $txgroup['reference'];
    }
    return $txgroup_list;
  }

  /*
   * Get the list of creditors
   */
  protected function getFrequencyList() {
    return array(
        "1" => E::ts("annually"),
        "2" => E::ts("semi-annually"),
//        "3" => E::ts("3-monthly"),
        "4" => E::ts("quarterly"),
//        "6" => E::ts("bi-monthly"),
        "12" => E::ts("monthly"),
    );
  }
}
