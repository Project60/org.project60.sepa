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

use \Civi\ActionProvider\Parameter\ParameterBagInterface;
use \Civi\ActionProvider\Parameter\Specification;
use \Civi\ActionProvider\Parameter\SpecificationBag;

use CRM_Sepa_ExtensionUtil as E;

class FindMandate extends CreateRecurringMandate {

  /**
   * Returns the specification of the configuration options for the actual action.
   *
   * @return SpecificationBag specs
   */
  public function getConfigurationSpecification() {
    $mandate_types = [
        'RCUR' => E::ts("Recurring"),
        'OOFF' => E::ts("One-Off"),
    ];
    $pick_types = [
        'id desc'   => E::ts("Newest (by ID)"),
        'id asc'    => E::ts("Oldest (by ID)"),
        'date desc' => E::ts("Newest (by date)"),
        'date asc'  => E::ts("Oldest (by date)"),
    ];

    return new SpecificationBag([
        new Specification('creditor_id','Integer', E::ts('Creditor'), false, null, null, $this->getCreditors(), true),
        new Specification('type',       'String', E::ts('Mandate Type'), false, null, null, $mandate_types, false),
        new Specification('active',     'Boolean', E::ts('Still Active'), false, null, null, $pick_types, false),
        new Specification('pick',       'String', E::ts('Pick (if multiple results)'), true, null, null, $pick_types, false),
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
        new Specification('contact_id', 'Integer', E::ts('Contact ID'), false),
        new Specification('iban',       'String',  E::ts('IBAN'), false),
        new Specification('reference',  'String',  E::ts('Mandate Reference'), false),
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
      new Specification('id',        'Integer', E::ts('Mandate ID'), false, null, null, null, false),
      new Specification('reference', 'String',  E::ts('Mandate Reference'), false, null, null, null, false),
      new Specification('type',      'String',  E::ts('Mandate Type'), false, null, null, null, false),
      new Specification('iban',      'String',  E::ts('IBAN'), false, null, null, null, false),
      new Specification('bic',       'String',  E::ts('BIC'), false, null, null, null, false),
      new Specification('contact_id','Integer', E::ts('Contact ID'), false, null, null, null, false),
      new Specification('status',    'String',  E::ts('Status'), false, null, null, null, false),
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
  protected function doAction(ParameterBagInterface $parameters, ParameterBagInterface $output)
  {
    // compile search query
    $mandate_search = [];
    if (!empty($this->configuration->getParameter('creditor_id'))) {
      $mandate_search['creditor_id'] = ['IN' => $this->configuration->getParameter('creditor_id')];
    }
    if (!empty($this->configuration->getParameter('type'))) {
      $mandate_search['type'] = $this->configuration->getParameter('type');
    }
    if (!empty($this->configuration->getParameter('active'))) {
      $mandate_search['status'] = ['IN' => ['FRST', 'RCUR', 'OOFF', 'INIT']];
    }
    if (!empty($parameters->getParameter('contact_id'))) {
      $mandate_search['contact_id'] = $parameters->getParameter('contact_id');
    }
    if (!empty($parameters->getParameter('iban'))) {
      $mandate_search['iban'] = $parameters->getParameter('iban');
    }
    if (!empty($parameters->getParameter('reference'))) {
      $mandate_search['reference'] = $parameters->getParameter('reference');
    }

    // add order
    $mandate_search['option.sort']  = $this->configuration->getParameter('pick');
    $mandate_search['option.limit'] = 1;

    // search mandate
    $result = \civicrm_api3('SepaMandate', 'get', $mandate_search);
    if ($result['count']) {
      $mandate = reset($result['values']);
      $output->setParameter('id', $mandate['id']);
      $output->setParameter('reference', $mandate['reference']);
      $output->setParameter('type', $mandate['type']);
      $output->setParameter('status', $mandate['status']);
      $output->setParameter('iban', $mandate['iban']);
      $output->setParameter('contact_id', $mandate['contact_id']);
    }
  }
}