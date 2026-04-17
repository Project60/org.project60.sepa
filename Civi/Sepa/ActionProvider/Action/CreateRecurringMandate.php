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

use Civi\ActionProvider\Exception\ExecutionException;
use Civi\ActionProvider\Parameter\ParameterBagInterface;
use Civi\ActionProvider\Parameter\Specification;
use Civi\ActionProvider\Parameter\SpecificationBag;

use Civi\Api4\SepaMandate;
use CRM_Sepa_ExtensionUtil as E;

class CreateRecurringMandate extends CreateOneOffMandate {

  /**
   * Returns the specification of the configuration options for the actual action.
   *
   * @return \Civi\ActionProvider\Parameter\SpecificationBag specs
   */
  public function getConfigurationSpecification() {
    return new SpecificationBag([
      new Specification('default_creditor_id', 'Integer', E::ts('Creditor (default)'), TRUE, NULL, NULL, $this->getCreditors(), FALSE),
      new Specification('default_financial_type_id', 'Integer', E::ts('Financial Type (default)'), TRUE, NULL, NULL, $this->getFinancialTypes(), FALSE),
      new Specification('default_campaign_id', 'Integer', E::ts('Campaign (default)'), FALSE, NULL, NULL, $this->getCampaigns(), FALSE),
      new Specification('default_frequency', 'Integer', E::ts('Frequency (default)'), TRUE, 12, NULL, $this->getFrequencies()),
      new Specification('default_cycle_day', 'Integer', E::ts('Collection Day (default)'), FALSE, 0, NULL, $this->getCollectionDays()),
      new Specification('buffer_days', 'Integer', E::ts('Buffer Days'), TRUE, 7),
    ]);
  }

  /**
   * Returns the specification of the parameters of the actual action.
   *
   * @return \Civi\ActionProvider\Parameter\SpecificationBag specs
   */
  public function getParameterSpecification() {
    return new SpecificationBag([
        // required fields
      new Specification('contact_id', 'Integer', E::ts('Contact ID'), TRUE),
      new Specification('account_holder', 'String', E::ts('Account Holder'), FALSE),
      new Specification('iban', 'String', E::ts('IBAN'), TRUE),
      new Specification('bic', 'String', E::ts('BIC'), FALSE),
      new Specification('reference', 'String', E::ts('Mandate Reference'), FALSE),
      new Specification('source', 'String', E::ts('Source'), FALSE),
      new Specification('amount', 'Money', E::ts('Amount'), FALSE),

        // recurring information
      new Specification('frequency', 'Integer', E::ts('Frequency'), FALSE, 12, NULL, $this->getFrequencies()),
      new Specification('cycle_day', 'Integer', E::ts('Collection Day'), FALSE, 1, NULL, $this->getCollectionDays()),

        // basic overrides
      new Specification('creditor_id', 'Integer', E::ts('Creditor (Leave empty to use default)'), FALSE, NULL, NULL, $this->getCreditors(), FALSE),
      new Specification('financial_type_id', 'Integer', E::ts('Financial Type (Leave empty to use default)'), FALSE, NULL, NULL, $this->getFinancialTypes(), FALSE),
      new Specification('campaign_id', 'Integer', E::ts('Campaign (Leave empty to use default)'), FALSE, NULL, NULL, $this->getCampaigns(), FALSE),

        // dates
      new Specification('start_date', 'Date', E::ts('Start Date'), FALSE, date('Y-m-d H:i:s')),
      new Specification('date', 'Date', E::ts('Signature Date'), FALSE, date('Y-m-d H:i:s')),
      new Specification('validation_date', 'Date', E::ts('Validation Date'), FALSE, date('Y-m-d H:i:s')),
      new Specification('creation_date', 'Date', E::ts('Creation Date'), FALSE, date('Y-m-d H:i:s')),
    ]);
  }

  /**
   * Returns the specification of the output parameters of this action.
   *
   * This function could be overridden by child classes.
   *
   * @return \Civi\ActionProvider\Parameter\SpecificationBag specs
   */
  public function getOutputSpecification() {
    return new SpecificationBag([
      new Specification('mandate_id', 'Integer', E::ts('Mandate ID'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('mandate_reference', 'String', E::ts('Mandate Reference'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('recurring_contribution_id', 'Integer', E::ts('Recurring Contribution ID'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('error', 'String', E::ts('Error Message (if creation failed)'), FALSE, NULL, NULL, NULL, FALSE),
    ]);
  }

  /**
   * Run the action
   *
   * @param \Civi\ActionProvider\Parameter\ParameterBagInterface $parameters
   *   The parameters to this action.
   * @param \Civi\ActionProvider\Parameter\ParameterBagInterface $output
   *      The parameters this action can send back
   * @return void
   */
  protected function doAction(ParameterBagInterface $parameters, ParameterBagInterface $output) {
    $mandate_data = ['type' => 'RCUR'];
    // add basic fields
    foreach (['contact_id', 'account_holder', 'iban', 'bic', 'reference', 'amount', 'start_date', 'date', 'validation_date', 'source', 'creation_date'] as $parameter_name) {
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

    // Check whether BIC is required, depending on the creditor setting.
    $creditor = civicrm_api3('SepaCreditor', 'getsingle', ['id' => $mandate_data['creditor_id']]);
    if (!empty($creditor['uses_bic']) && empty($mandate_data['bic'])) {
      throw new ExecutionException('BIC is required');
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

    // if not set, calculate the closest cycle day
    if (empty($mandate_data['cycle_day'])) {
      $mandate_data['cycle_day'] = $this->calculateSoonestCycleDay($mandate_data);
    }
    // use default creation date
    if (!$mandate_data['creation_date']) {
      $creationDate = new \DateTime();
      $mandate_data['creation_date'] = $creationDate->format('Y-m-d H:i:s');
    }

    // create mandate
    try {
      $mandate = \civicrm_api3('SepaMandate', 'createfull', $mandate_data);
      $mandate = SepaMandate::get(TRUE)
        ->addSelect('id', 'reference', 'entity_id')
        ->addWhere('id', '=', $mandate['id'])
        ->execute()
        ->single();
      $output->setParameter('mandate_id', $mandate['id']);
      $output->setParameter('recurring_contribution_id', $mandate['entity_id']);
      $output->setParameter('mandate_reference', $mandate['reference']);
    }
    catch (\Exception $ex) {
      $output->setParameter('mandate_id', '');
      $output->setParameter('recurring_contribution_id', '');
      $output->setParameter('mandate_reference', '');
      $output->setParameter('error', $ex->getMessage());
    }
  }

  /**
   * Get list of frequencies
   */
  protected function getFrequencies() {
    return [
      1  => E::ts('annually'),
      2  => E::ts('semi-annually'),
      4  => E::ts('quarterly'),
      6  => E::ts('bi-monthly'),
      12 => E::ts('monthly'),
    ];
  }

  /**
   * Get list of collection days
   */
  protected function getCollectionDays() {
    $list = range(0, 28);
    $options = array_combine($list, $list);
    $options[0] = E::ts('as soon as possible');
    return $options;
  }

  /**
   * Select the cycle day from the given creditor,
   *  that allows for the soonest collection given the buffer time
   *
   * @param array $mandate_data
   *      all data known about the mandate
   *
   */
  protected function calculateSoonestCycleDay($mandate_data) {
    // get creditor ID
    $creditor_id = (int) $mandate_data['creditor_id'];
    if (!$creditor_id) {
      $default_creditor = \CRM_Sepa_Logic_Settings::defaultCreditor();
      if ($default_creditor) {
        $creditor_id = $default_creditor->id;
      }
      else {
        \Civi::log()->notice('CreateRecurringMandate action: No creditor, and no default creditor set! Using cycle day 1');
        return 1;
      }
    }

    // get start date
    $date = strtotime($mandate_data['start_date'] ?? date('Y-m-d'));

    // get cycle days
    $cycle_days = \CRM_Sepa_Logic_Settings::getListSetting('cycledays', range(1, 28), $creditor_id);

    // iterate through the days until we hit a cycle day
    for ($i = 0; $i < 31; $i++) {
      if (in_array(date('j', $date), $cycle_days)) {
        // we found our cycle_day!
        return date('j', $date);
      }
      else {
        // no? try the next one...
        $date = strtotime('+ 1 day', $date);
      }
    }

    // no hit? that shouldn't happen...
    return 1;
  }

}
