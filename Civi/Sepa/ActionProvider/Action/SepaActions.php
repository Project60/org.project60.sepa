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

use Civi\ActionProvider\Action\AbstractAction;
use CRM_Sepa_ExtensionUtil as E;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SepaActions implements CompilerPassInterface {

  /**
   * Register this one action: XcmGetOrCreate
   */
  public function process(ContainerBuilder $container) {
    if (!$container->hasDefinition('action_provider')) {
      return;
    }
    $typeFactoryDefinition = $container->getDefinition('action_provider');
    $typeFactoryDefinition->addMethodCall('addAction', ['SepaMandateOOFF', 'Civi\Sepa\ActionProvider\Action\CreateOneOffMandate', E::ts('Create SEPA Mandate (One-Off)'), [
        AbstractAction::SINGLE_CONTACT_ACTION_TAG,
    ]]);
    $typeFactoryDefinition->addMethodCall('addAction', ['SepaMandateRCUR', 'Civi\Sepa\ActionProvider\Action\CreateRecurringMandate', E::ts('Create SEPA Mandate (Recurring)'), [
        AbstractAction::SINGLE_CONTACT_ACTION_TAG,
    ]]);
  }
}