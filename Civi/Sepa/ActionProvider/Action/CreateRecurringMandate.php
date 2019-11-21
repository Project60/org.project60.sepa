<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2019 SYSTOPIA                            |
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

namespace Civi\Sepa\ActionProvider\Action;

use \Civi\ActionProvider\Action\AbstractAction;
use \Civi\ActionProvider\Parameter\ParameterBagInterface;
use \Civi\ActionProvider\Parameter\Specification;
use \Civi\ActionProvider\Parameter\SpecificationBag;

use CRM_Sepa_ExtensionUtil as E;

class CreateRecurringMandate extends CreateOneOffMandate {

  /**
   * Returns the specification of the configuration options for the actual action.
   *
   * @return SpecificationBag specs
   */
  public function getConfigurationSpecification() {
    return new SpecificationBag([
        new Specification('default_creditor_id',       'Integer', E::ts('Creditor (default)'), true, null, null, $this->getCreditors(), false),
        new Specification('default_financial_type_id', 'Integer', E::ts('Financial Type (default)'), true, null, null, $this->getFinancialTypes(), false),
        new Specification('default_campaign_id',       'Integer', E::ts('Campaign (default)'), false, null, null, $this->getCampaigns(), false),
        new Specification('default_frequency',         'Integer', E::ts('Frequency (default)'), true, 12, null, $this->getFrequencies()),
        new Specification('default_cycle_day',         'Integer', E::ts('Collection Day (default)'), true, 1, null, $this->getCollectionDays()),
        new Specification('buffer_days',               'Integer', E::ts('Buffer Days'), true, 7),
    ]);
  }

  /**
   * Returns the specification of the parameters of the actual action.
   *
   * @return SpecificationBag specs
   */
  public function getParameterSpecification() {
    return new SpecificationBag([
        // required fields
        new Specification('contact_id', 'Integer', E::ts('Contact ID'), true),
        new Specification('iban',       'String',  E::ts('IBAN'), true),
        new Specification('bic',        'String',  E::ts('BIC'), true),
        new Specification('reference',  'String',  E::ts('Mandate Reference'), false),
        new Specification('amount',     'Money',   E::ts('Amount'), false),

        // recurring information
        new Specification('frequency',  'Integer', E::ts('Frequency'),      false, 12, null, $this->getFrequencies()),
        new Specification('cycle_day',  'Integer', E::ts('Collection Day'), false, 1,  null, $this->getCollectionDays()),

        // basic overrides
        new Specification('creditor_id',       'Integer', E::ts('Creditor (default)'), false, null, null, $this->getCreditors(), false),
        new Specification('financial_type_id', 'Integer', E::ts('Financial Type (default)'), false, null, null, $this->getFinancialTypes(), false),
        new Specification('campaign_id',       'Integer', E::ts('Campaign (default)'), false, null, null, $this->getCampaigns(), false),

        // dates
        new Specification('start_date',      'Date', E::ts('Start Date'), false, date('Y-m-d H:i:s')),
        new Specification('date',            'Date', E::ts('Signature Date'),  false, date('Y-m-d H:i:s')),
        new Specification('validation_date', 'Date', E::ts('Validation Date'), false, date('Y-m-d H:i:s')),
    ]);
  }

  /**
   * Returns the specification of the output parameters of this action.
   *
   * This function could be overridden by child classes.
   *
   * @return SpecificationBag specs
   */
  public function getOutputSpecification() {
    return new SpecificationBag([
      new Specification('mandate_id',        'Integer', E::ts('Mandate ID'), false, null, null, null, false),
      new Specification('mandate_reference', 'String',  E::ts('Mandate Reference'), false, null, null, null, false),
    ]);
  }

  /**
   * Run the action
   *
   * @param ParameterBagInterface $parameters
   *   The parameters to this action.
   * @param ParameterBagInterface $output
   * 	 The parameters this action can send back
   * @return void
   */
  protected function doAction(ParameterBagInterface $parameters, ParameterBagInterface $output) {
    $mandate_data = ['type' => 'RCUR'];
    // add basic fields
    foreach (['contact_id', 'iban', 'bic', 'reference', 'amount', 'start_date', 'date', 'validation_date'] as $parameter_name) {
      $value = $parameters->getParameter($parameter_name);
      if (!empty($value)) {
        $mandate_data[$parameter_name] = $value;
      }
    }

    // add override fields
    foreach (['creditor_id', 'financial_type_id', 'campaign_id', 'cycle_day', 'frequency'] as $parameter_name) {
      $value = $parameters->getParameter($parameter_name);
      if (empty($value)) {
        $value = $this->configuration->getParameter("default_{$parameter_name}");
      }
      $mandate_data[$parameter_name] = $value;
    }

    // sort out frequency
    $mandate_data['frequency_interval'] = 12 / $mandate_data['frequency'];
    $mandate_data['frequency_unit'] = 'month';
    unset($mandate_data['frequency']);

    // verify/adjust start date
    $buffer_days = (int) $this->configuration->getParameter('buffer_days');
    $earliest_start_date = strtotime("+ {$buffer_days} days");
    $current_start_date = strtotime($mandate_data['start_date']);
    if ($current_start_date < $earliest_start_date) {
      $mandate_data['start_date'] = date('YmdHis', $earliest_start_date);
    }

    // create mandate
    $mandate = \civicrm_api3('SepaMandate', 'createfull', $mandate_data);
    $mandate = \civicrm_api3('SepaMandate', 'getsingle', ['id' => $mandate['id'], 'return' => 'id,reference']);
    $output->setParameter('mandate_id', $mandate['id']);
    $output->setParameter('mandate_reference', $mandate['reference']);
  }

  /**
   * Get list of frequencies
   */
  protected function getFrequencies() {
    return [
        1  => E::ts("annually"),
        2  => E::ts("semi-annually"),
        4  => E::ts("quarterly"),
        6  => E::ts("bi-monthly"),
        12 => E::ts("monthly"),
    ];
  }

  /**
   * Get list of collection days
   */
  protected function getCollectionDays() {
    $list = range(1,28);
    return array_combine($list, $list);
  }
}