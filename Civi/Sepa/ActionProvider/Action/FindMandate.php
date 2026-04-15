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

use Civi\ActionProvider\Parameter\ParameterBagInterface;
use Civi\ActionProvider\Parameter\Specification;
use Civi\ActionProvider\Parameter\SpecificationBag;

use Civi\Api4\SepaMandate;
use CRM_Sepa_ExtensionUtil as E;

class FindMandate extends CreateRecurringMandate {

  /**
   * Returns the specification of the configuration options for the actual action.
   *
   * @return \Civi\ActionProvider\Parameter\SpecificationBag specs
   */
  public function getConfigurationSpecification() {
    $mandate_types = [
      'RCUR' => E::ts('Recurring'),
      'OOFF' => E::ts('One-Off'),
    ];
    $pick_types = [
      'id desc'   => E::ts('Newest (by ID)'),
      'id asc'    => E::ts('Oldest (by ID)'),
      'date desc' => E::ts('Newest (by date)'),
      'date asc'  => E::ts('Oldest (by date)'),
    ];

    return new SpecificationBag([
      new Specification('creditor_id', 'Integer', E::ts('Creditor'), FALSE, NULL, NULL, $this->getCreditors(), TRUE),
      new Specification('type', 'String', E::ts('Mandate Type'), FALSE, NULL, NULL, $mandate_types, FALSE),
      new Specification('active', 'Boolean', E::ts('Still Active'), FALSE, NULL, NULL, $pick_types, FALSE),
      new Specification('pick', 'String', E::ts('Pick (if multiple results)'), TRUE, NULL, NULL, $pick_types, FALSE),
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
      new Specification('contact_id', 'Integer', E::ts('Contact ID'), FALSE),
      new Specification('account_holder', 'String', E::ts('Account Holder'), FALSE),
      new Specification('iban', 'String', E::ts('IBAN'), FALSE),
      new Specification('reference', 'String', E::ts('Mandate Reference'), FALSE),
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
      new Specification('id', 'Integer', E::ts('Mandate ID'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('reference', 'String', E::ts('Mandate Reference'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('type', 'String', E::ts('Mandate Type'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('account_holder', 'String', E::ts('Account Holder'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('iban', 'String', E::ts('IBAN'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('bic', 'String', E::ts('BIC'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('contact_id', 'Integer', E::ts('Contact ID'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('status', 'String', E::ts('Status'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('amount', 'Money', E::ts('Amount'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('annual_amount', 'Money', E::ts('Annual Amount'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('frequency', 'Integer', E::ts('Frequency'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('cycle_day', 'Integer', E::ts('Collection Day'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('creditor_id', 'Integer', E::ts('Creditor ID'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('financial_type_id', 'Integer', E::ts('Financial Type (default)'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('campaign_id', 'Integer', E::ts('Campaign (default)'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('start_date', 'Date', E::ts('Start Date'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('date', 'Date', E::ts('Signature Date'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('validation_date', 'Date', E::ts('Validation Date'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('contribution_id', 'Integer', E::ts('Contribution ID (One-off)'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('contribution_recur_id', 'Integer', E::ts('Recurring Contribution ID'), FALSE, NULL, NULL, NULL, FALSE),
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
    // compile search query
    $mandatesQuery = SepaMandate::get(TRUE);
    if (!empty($this->configuration->getParameter('creditor_id'))) {
      $mandatesQuery->addWhere('creditor_id', 'IN', $this->configuration->getParameter('creditor_id'));
    }
    if (!empty($this->configuration->getParameter('type'))) {
      $mandatesQuery->addWhere('type', '=', $this->configuration->getParameter('type'));
    }
    if (!empty($this->configuration->getParameter('active'))) {
      $mandatesQuery->addWhere('status', 'IN', ['FRST', 'RCUR', 'OOFF', 'INIT']);
    }
    if (!empty($parameters->getParameter('contact_id'))) {
      $mandatesQuery->addWhere('contact_id', '=', $parameters->getParameter('contact_id'));
    }
    if (!empty($parameters->getParameter('account_holder'))) {
      $mandatesQuery->addWhere('account_holder', '=', $parameters->getParameter('account_holder'));
    }
    if (!empty($parameters->getParameter('iban'))) {
      $mandatesQuery->addWhere('iban', '=', $parameters->getParameter('iban'));
    }
    if (!empty($parameters->getParameter('reference'))) {
      $mandatesQuery->addWhere('reference', '=', $parameters->getParameter('reference'));
    }

    // add order
    $mandatesQuery->addOrderBy($this->configuration->getParameter('pick'));
    // TODO: Shouldn't this use single()?
    $mandatesQuery->setLimit(1);

    // search mandate
    $mandate = $mandatesQuery->execute()->first();
    if (isset($mandate)) {
      $output->setParameter('id', $mandate['id']);
      $output->setParameter('reference', $mandate['reference']);
      $output->setParameter('type', $mandate['type']);
      $output->setParameter('status', $mandate['status']);
      $output->setParameter('account_holder', $mandate['account_holder']);
      $output->setParameter('iban', $mandate['iban']);
      $output->setParameter('contact_id', $mandate['contact_id']);
      $output->setParameter('creditor_id', $mandate['creditor_id']);
      $output->setParameter('date', $mandate['date']);
      $output->setParameter('validation_date', $mandate['validation_date']);

      // these output values depend on the type
      switch ($mandate['type']) {
        case 'RCUR':
          $recurring_contribution = civicrm_api3('ContributionRecur', 'getsingle', [
            'id' => $mandate['entity_id'],
          ]);
          $output->setParameter('amount', $recurring_contribution['amount']);
          $output->setParameter('cycle_day', $recurring_contribution['cycle_day']);
          $output->setParameter('financial_type_id', $recurring_contribution['financial_type_id']);
          $output->setParameter('campaign_id', $recurring_contribution['campaign_id'] ?? NULL);
          $output->setParameter('start_date', $recurring_contribution['start_date']);
          $output->setParameter('contribution_recur_id', $recurring_contribution['id']);

          // some need to be calculated
          // frequency is 'how ofter per year'
          $frequency = 0;
          if ($recurring_contribution['frequency_unit'] == 'month') {
            $frequency = 12 / $recurring_contribution['frequency_interval'];
          }
          elseif ($recurring_contribution['frequency_unit'] == 'year') {
            $frequency = 1.0 / $recurring_contribution['frequency_interval'];
          }
          elseif ($recurring_contribution['frequency_unit'] == 'week') {
            $frequency = 52.0 / $recurring_contribution['frequency_interval'];
          }
          $output->setParameter('frequency', $frequency);
          $output->setParameter('annual_amount', number_format($frequency * $recurring_contribution['amount'], 2, '.', ''));
          break;

        case 'OOFF':
          $contribution = civicrm_api3('Contribution', 'getsingle', [
            'id' => $mandate['entity_id'],
          ]);
          $output->setParameter('amount', $contribution['total_amount']);
          $output->setParameter('financial_type_id', $contribution['financial_type_id']);
          $output->setParameter('campaign_id', $contribution['campaign_id']);
          $output->setParameter('cycle_day', NULL);
          $output->setParameter('frequency', 0);
          $output->setParameter('annual_amount', NULL);
          $output->setParameter('contribution_id', $contribution['id']);
          break;

        default:
          // this shouldn't happen
          break;
      }
    }
  }

}
