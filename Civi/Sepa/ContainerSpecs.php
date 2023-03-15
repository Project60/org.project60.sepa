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

namespace Civi\Sepa;

use CRM_Sepa_ExtensionUtil as E;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ContainerSpecs implements CompilerPassInterface {

  /**
   * Register SEPA Actions
   */
  public function process(ContainerBuilder $container) {
    if ($container->hasDefinition('action_provider')) {
      $typeFactoryDefinition = $container->getDefinition('action_provider');
      $typeFactoryDefinition->addMethodCall('addAction', [
        'SepaMandateOOFF',
        'Civi\Sepa\ActionProvider\Action\CreateOneOffMandate',
        E::ts('Create SEPA Mandate (One-Off)'),
        [
          \Civi\ActionProvider\Action\AbstractAction::SINGLE_CONTACT_ACTION_TAG,
        ]
      ]);
      $typeFactoryDefinition->addMethodCall('addAction', [
        'SepaMandateRCUR',
        'Civi\Sepa\ActionProvider\Action\CreateRecurringMandate',
        E::ts('Create SEPA Mandate (Recurring)'),
        [
          \Civi\ActionProvider\Action\AbstractAction::SINGLE_CONTACT_ACTION_TAG,
        ]
      ]);
      $typeFactoryDefinition->addMethodCall('addAction', [
        'FindMandate',
        'Civi\Sepa\ActionProvider\Action\FindMandate',
        E::ts('Find SEPA Mandate'),
        [
          \Civi\ActionProvider\Action\AbstractAction::DATA_RETRIEVAL_TAG,
        ]
      ]);
    }
    if ($container->hasDefinition('data_processor_factory')) {
      $dataProcessorFactoryDefinition = $container->getDefinition('data_processor_factory');
      $dataProcessorFactoryDefinition->addMethodCall('addDataSource', [
        'sepa_mandate', 'Civi\Sepa\DataProcessor\Source\SepaMandate', E::ts('SEPA Mandate')]);
      $dataProcessorFactoryDefinition->addMethodCall('addDataSource', [
        'sepa_creditor', 'Civi\Sepa\DataProcessor\Source\SepaCreditor', E::ts('SEPA Creditor')]);
      $dataProcessorFactoryDefinition->addMethodCall('addDataSource', [
        'sepa_transaction_group', 'Civi\Sepa\DataProcessor\Source\SepaTransactionGroup', E::ts('SEPA Transaction Group')]);
      $dataProcessorFactoryDefinition->addMethodCall('addDataSource', [
        'sepa_sdd_file', 'Civi\Sepa\DataProcessor\Source\SepaSddFile', E::ts('SEPA SDD File')]);
      $dataProcessorFactoryDefinition->addMethodCall('addDataSource', [
        'sepa_contribution_group', 'Civi\Sepa\DataProcessor\Source\SepaContributionGroup', E::ts('SEPA Contribution Group')]);
      $dataProcessorFactoryDefinition->addMethodCall('addDataSource', [
        'sepa_mandate_link', 'Civi\Sepa\DataProcessor\Source\SepaMandateLink', E::ts('SEPA Mandate Link')]);
    }
  }
}
