<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 Project60                      |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

declare(strict_types = 1);

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
require_once 'sepa.civix.php';
// phpcs:enable

use Civi\Sepa\Lock\SepaBatchLockManager;
use CRM_Sepa_ExtensionUtil as E;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Implements hook_civicrm_container().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_container/
 */
function sepa_civicrm_container(ContainerBuilder $container): void {
  if (class_exists('\Civi\Sepa\ContainerSpecs')) {
    $container->addCompilerPass(new \Civi\Sepa\ContainerSpecs(), PassConfig::TYPE_OPTIMIZE);
  }

  $container->addResource(new FileResource(__FILE__));
  $container->findDefinition('dispatcher')
    ->addMethodCall('addListener', ['civi.token.list', 'sepa_register_tokens'])
    ->setPublic(TRUE);
  $container->findDefinition('dispatcher')
    ->addMethodCall('addListener', ['civi.token.eval', 'sepa_evaluate_tokens'])
    ->setPublic(TRUE);

  $container->autowire(SepaBatchLockManager::class)->setPublic(TRUE);
}

// phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
function sepa_civicrm_pageRun(object $page): void {
  if (get_class($page) === 'CRM_Contact_Page_View_Summary') {
    // mods for summary view
    if (CRM_Core_Permission::check('view sepa mandates')) {
      $page->assign('can_create_mandate', CRM_Core_Permission::check('create sepa mandates') ? '1' : '0');
      $page->assign('can_edit_mandate', CRM_Core_Permission::check('edit sepa mandates') ? '1' : '0');
      CRM_Core_Region::instance('page-body')->add([
        'template' => 'CRM/Contact/Page/View/Summary.sepa.tpl',
      ]);
    }

  }
  elseif (get_class($page) === 'CRM_Contribute_Page_Tab') {
    // single contribuion view
    if (CRM_Core_Permission::check('view sepa mandates')) {
      $contribution_id = (int) $page::getTemplate()->getTemplateVars('id');
      if (0 === $contribution_id || !CRM_Sepa_BAO_SEPAMandate::getContributionMandateID($contribution_id)) {
        // not a SEPA contribution
        return;
      }

      if ($page::getTemplate()->getTemplateVars('contribution_recur_id')) {
        // This is an installment of a recurring contribution.
        $contribution_recur_id = $page::getTemplate()->getTemplateVars('contribution_recur_id');
        if (empty($contribution_recur_id)) {
          return;
        }

        $mandate = \Civi\Api4\SepaMandate::get(TRUE)
          ->addWhere('entity_table', '=', 'civicrm_contribution_recur')
          ->addWhere('entity_id', '=', $contribution_recur_id)
          ->execute()
          ->single();
      }
      else {
        // this is a OOFF contribtion
        $mandate = \Civi\Api4\SepaMandate::get(TRUE)
          ->addWhere('entity_table', '=', 'civicrm_contribution')
          ->addWhere('entity_id', '=', $contribution_id)
          ->execute()
          ->single();
      }

      // add txgroup information
      $txgroup_search = \Civi\Api4\SepaContributionGroup::get(TRUE)
        ->addWhere('contribution_id', '=', $contribution_id)
        ->execute()
        ->indexBy('id');
      if ($txgroup_search->count() === 0) {
        $mandate['tx_group'] = E::ts('<i>None</i>');
      }
      else {
        $group = $txgroup_search->first();
        if (empty($group['txgroup_id'])) {
          $mandate['tx_group'] = E::ts('<i>Error</i>');
        }
        else {
          $mandate['tx_group'] = \Civi\Api4\SepaTransactionGroup::get(TRUE)
            ->addSelect('reference')
            ->addWhere('id', '=', $group['txgroup_id'])
            ->execute()
            ->single()['reference'];
        }
      }

      $page->assign('sepa', $mandate);
      $page->assign('can_edit_mandate', CRM_Core_Permission::check('edit sepa mandates'));
      CRM_Core_Region::instance('page-body')->add([
        'template' => 'Sepa/Contribute/Form/ContributionView.tpl',
      ]);
    }
  }

  elseif (get_class($page) === 'CRM_Contribute_Page_ContributionRecur') {
    // recurring contribution view
    if (CRM_Core_Permission::check('view sepa mandates')) {
      $recur = $page::getTemplate()->getTemplateVars('recur');
      if (empty($recur['id'])) {
        // nothing to do here
        return;
      }

      // find mandate
      $mandate_id = CRM_Sepa_BAO_SEPAMandate::getRecurringContributionMandateID((int) $recur['id']);
      if (empty($mandate_id)) {
        // this is not a SEPA recurring contribution
        return;
      }
      $mandate = \Civi\Api4\SepaMandate::get(TRUE)
        ->addWhere('id', '=', $mandate_id)
        ->execute()
        ->single();

      // load notes
      $mandate['notes'] = [];
      if ($mandate['type'] === 'RCUR') {
        $contribution_recur_id = (int) $mandate['entity_id'];
        /** @var \CRM_Core_DAO $mandate_note_query */
        $mandate_note_query = CRM_Core_DAO::executeQuery(
          "SELECT note FROM civicrm_note
            WHERE entity_id = {$contribution_recur_id} AND entity_table = 'civicrm_contribution_recur' 
            ORDER BY modified_date DESC;"
        );
        while ($mandate_note_query->fetch()) {
          $mandate['notes'][] = $mandate_note_query->note;
        }
      }

      $page->assign('sepa', $mandate);
      $page->assign('can_edit_mandate', CRM_Core_Permission::check('edit sepa mandates'));
      CRM_Core_Region::instance('page-body')->add([
        'template' => 'Sepa/Contribute/Page/ContributionRecur.tpl',
      ]);
    }
  }
}

function sepa_civicrm_buildForm(string $formName, object $form): void {
  // restrict payment instrument use if necessary
  CRM_Sepa_Logic_PaymentInstruments::restrictPaymentInstrumentsInForm($formName, $form);
}

/**
 * Implements hook_civicrm_config().
 */
function sepa_civicrm_config(CRM_Core_Config $config): void {
  _sepa_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 */
function sepa_civicrm_install(): void {
  $config = CRM_Core_Config::singleton();
  //create the tables
  $sqlfile = dirname(__FILE__) . '/sql/sepa.sql';
  CRM_Utils_File::sourceSQLFile($config->dsn, $sqlfile, NULL, FALSE);

  _sepa_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 */
function sepa_civicrm_enable(): void {
  _sepa_civix_civicrm_enable();
}

function sepa_civicrm_summaryActions(array &$actions, $contactID): void {
  // add "create SEPA mandate action"
  if (CRM_Core_Permission::check('create sepa mandates')) {
    $actions['sepa_contribution'] = [
      'title' => E::ts('Record SEPA Mandate'),
      'weight' => 5,
      'ref' => 'new-sepa-contribution',
      'key' => 'sepa_contribution',
      'component' => 'CiviContribute',
      'href' => CRM_Utils_System::url('civicrm/sepa/createmandate', "reset=1&cid={$contactID}"),
      'permissions' => ['access CiviContribute', 'edit contributions'],
    ];
  }
}

/**
 *  Support SEPA mandates in merge operations
 */
function sepa_civicrm_merge(string $type, array &$data, $mainId = NULL, $otherId = NULL, $tables = NULL): void {
  switch ($type) {
    case 'relTables':
      // Offer user to merge SEPA Mandates
      $data['rel_table_sepamandate'] = [
        'title' => E::ts('SEPA Mandates'),
        'tables' => ['civicrm_sdd_mandate'],
        // '$cid' will be automatically replaced
        'url' => CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid=$cid&selectedChild=contribute'),
      ];
      break;

    case 'cidRefs':
      // this is the only field that needs to be modified
      $data['civicrm_sdd_mandate'] = ['contact_id'];
      break;
  }
}

/**
 * Prevent people from deleting contributions connected to SDD mandates.
 *
 * Implements hook_civicrm_apiWrappers().
 */
function sepa_civicrm_apiWrappers(array &$wrappers, $apiRequest): void {
  // add a wrapper for Contact.getlist (used e.g. for AJAX lookups)
  if ($apiRequest['entity'] == 'Contribution' && $apiRequest['action'] == 'delete') {
    $wrappers[] = new CRM_Sepa_Logic_ContributionProtector();
  }
}

/**
 * Prevent people from deleting contributions connected to SDD mandates.
 *
 * Implements hook_civicrm_links().
 */
function sepa_civicrm_links(
  string $op,
  string $objectName,
  int|string $objectId,
  array &$links,
  &$mask,
  &$values
): void {
  if ($op == 'contribution.selector.row' && $objectName == 'Contribution') {
    $links_copy = $links;
    foreach ($links_copy as $index => $link) {
      if ($link['bit'] == CRM_Core_Action::DELETE) {
        $protected = CRM_Sepa_Logic_ContributionProtector::isProtected($objectId, 'civicrm_contribution');
        if ($protected) {
          // this is a protected contribution -> remove "DELETE" link
          unset($links[$index]);
        }
      }
    }
  }
}

/**
 * CiviCRM PRE event:
 *  1) make sure the next collection date
 *     is adjusted according to the change
 *  2) prevent users from deleting contributions/recurring contributions
 *     if they are part of a mandate
 */
function sepa_civicrm_pre(string $op, string $objectName, ?int $id, array $params): void {
  // adjust next collection date
  if ($objectName == 'ContributionRecur' || $objectName == 'SepaMandate') {
    if ($op == 'create' || $op == 'edit') {
      if ($objectName == 'SepaMandate') {
        CRM_Sepa_Logic_NextCollectionDate::processMandatePreEdit($op, $objectName, $id, $params);
      }
      else {
        CRM_Sepa_Logic_NextCollectionDate::processRecurPreEdit($op, $objectName, $id, $params);
      }
    }
  }

  /**
   * prevent the user to delete a (recurring) contribution when there's a mandate attached.
   * this is only a last resort, most UI actions leading to this should've been disabled
   */
  if ($op == 'delete' && ($objectName == 'Contribution' || $objectName == 'ContributionRecur')) {
    if ($objectName == 'Contribution') {
      $error = CRM_Sepa_Logic_ContributionProtector::isProtected($id, 'civicrm_contribution');
    }
    else {
      $error = CRM_Sepa_Logic_ContributionProtector::isProtected($id, 'civicrm_contribution_recur');
    }

    if ($error) {
      // Unfortunately, there is no other option at this point.
      //   Ideally, this would've been caught by the API, this is just a last resort
      throw new CRM_Core_Exception($error);
    }
  }
}

/**
 * CiviCRM POST event: make sure the next collection date
 *   is adjusted according to the change
 */
function sepa_civicrm_post($op, $objectName, $objectId, &$objectRef): void {
  if ($objectName == 'ContributionRecur' || $objectName == 'SepaMandate') {
    if ($op == 'create' || $op == 'edit') {
      if ($objectName == 'SepaMandate') {
        CRM_Sepa_Logic_NextCollectionDate::processMandatePostEdit($op, $objectName, $objectId, $objectRef);
      }
      else {
        CRM_Sepa_Logic_NextCollectionDate::processRecurPostEdit($op, $objectName, $objectId, $objectRef);
      }
    }
  }
}

/**
 * totten's addition
 */
function sepa_civicrm_entityTypes(&$entityTypes): void {
  // add my DAO's
  $entityTypes[] = [
    'name' => 'SepaMandate',
    'class' => 'CRM_Sepa_DAO_SEPAMandate',
    'table' => 'civicrm_sdd_mandate',
  ];
  $entityTypes[] = [
    'name' => 'SepaCreditor',
    'class' => 'CRM_Sepa_DAO_SEPACreditor',
    'table' => 'civicrm_sdd_creditor',
  ];
  $entityTypes[] = [
    'name' => 'SepaTransactionGroup',
    'class' => 'CRM_Sepa_DAO_SEPATransactionGroup',
    'table' => 'civicrm_sdd_txgroup',
  ];
  $entityTypes[] = [
    'name' => 'SepaSddFile',
    'class' => 'CRM_Sepa_DAO_SEPASddFile',
    'table' => 'civicrm_sdd_file',
  ];
  $entityTypes[] = [
    'name' => 'SepaContributionGroup',
    'class' => 'CRM_Sepa_DAO_SEPAContributionGroup',
    'table' => 'civicrm_sdd_contribution_txgroup',
  ];
  $entityTypes[] = [
    'name' => 'SepaMandateLink',
    'class' => 'CRM_Sepa_DAO_SepaMandateLink',
    'table' => 'civicrm_sdd_entity_mandate',
  ];
}

/**
 * Implements hook_civicrm_config().
 */

/**
 * Implements hook_civicrm_navigationMenu().
 */
function sepa_civicrm_navigationMenu(&$menu): void {
  //add menu entry for SEPA Dashboard to Contributions menu
  $sepa_dashboard_url = 'civicrm/sepa';

  _sepa_civix_insert_navigation_menu($menu, 'Contributions', [
    'label' => E::ts('CiviSEPA Dashboard'),
    'name' => 'CiviSEPA Dashboard',
    'url' => $sepa_dashboard_url,
    'permission' => 'view sepa groups',
    'operator' => NULL,
    'separator' => 2,
    'active' => 1,
  ]);

  //add menu entry for SEPA settings to Administer>CiviContribute menu
  $sepa_settings_url = 'civicrm/admin/setting/sepa';

  _sepa_civix_insert_navigation_menu($menu, 'Administer/CiviContribute', [
    'label' => E::ts('CiviSEPA Settings'),
    'name' => 'CiviSEPA Settings',
    'url' => $sepa_settings_url,
    'permission' => 'administer CiviCRM',
    'operator' => NULL,
    'separator' => 2,
    'active' => 1,
  ]);

  _sepa_civix_navigationMenu($menu);
}

/**
 * Define SEPA permissions
 */
function sepa_civicrm_permission(&$permissions): void {
  $prefix = E::ts('CiviSEPA') . ': ';

  // Mandate permissions.
  $permissions['create sepa mandates'] = [
    'label' => $prefix . E::ts('Create SEPA mandates'),
    'description' => E::ts('Allows creating SEPA Direct Debit mandates.'),
  ];
  $permissions['view sepa mandates'] = [
    'label' => $prefix . E::ts('View SEPA mandates'),
    'description' => E::ts('Allows viewing SEPA Direct Debit mandates'),
  ];
  $permissions['edit sepa mandates'] = [
    'label' => $prefix . E::ts('Edit SEPA mandates'),
    'description' => E::ts('Allows editing SEPA Direct Debit mandates.'),
  ];
  $permissions['delete sepa mandates'] = [
    'label' => $prefix . E::ts('Delete SEPA mandates'),
    'description' => E::ts('Allows deleting SEPA Direct Debit mandates'),
  ];

  // Transaction group permissions.
  $permissions['view sepa groups'] = [
    'label' => $prefix . E::ts('View SEPA transaction groups'),
    'description' => E::ts('Allows viewing groups of SEPA transactions to be sent to the bank.'),
  ];
  $permissions['batch sepa groups'] = [
    'label' => $prefix . E::ts('Batch SEPA transaction groups'),
    'description' => E::ts('Allows generating groups of SEPA transactions to be sent to the bank.'),
  ];
  $permissions['delete sepa groups'] = [
    'label' => $prefix . E::ts('Delete SEPA transaction groups'),
    'description' => E::ts('Allows deleting groups of SEPA transactions to be sent to the bank.'),
  ];
}

/**
 * Set permission to the API calls
 */
function sepa_civicrm_alterAPIPermissions($entity, $action, &$params, &$permissions): void {
  // TODO: add more
  $permissions['sepa_alternative_batching']['received'] = ['batch sepa groups'];
  $permissions['sepa_logic']['received'] = ['batch sepa groups'];
  $permissions['sepa_transaction_group']['toaccgroup'] = ['batch sepa groups'];
  $permissions['sepa_mandate']['get'] = ['view sepa mandates'];
}

/**
 * CiviCRM validateForm hook
 *
 * make sure, people don't create (broken) payment with SDD payment instrument, but w/o mandates
 */
function sepa_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors): void {
  if ($formName == 'CRM_Contribute_Form_Contribution') {
    // we'll just focus on the payment_instrument_id
    if (empty($fields['payment_instrument_id'])) {
      return;
    }

    // find the contribution id
    $contribution_id = $form->getVar('_id');
    if (empty($contribution_id)) {
      return;
    }

    // if this contribution has no mandate, it should not have the classic sepa PIs
    $mandate_id = CRM_Sepa_BAO_SEPAMandate::getContributionMandateID($contribution_id);
    if (!$mandate_id) {
      $payment_instruments = CRM_Sepa_Logic_PaymentInstruments::getClassicSepaPaymentInstruments();
      if (isset($payment_instruments[$fields['payment_instrument_id']])) {
        $errors['payment_instrument_id'] = E::ts(
          'This contribution has no mandate and cannot simply be changed to a SEPA payment instrument.'
        );
      }
    }
    // @todo else: restrict to the PIs the creditor allows?
  }
}

/**
 * Insert "Last Mandate" tokens
 * (Deprecated hook)
 */
function sepa_civicrm_tokens(&$tokens): void {
  $prefix = E::ts('Most Recent SEPA Mandate');
  // spaces break newletters, see https://github.com/Project60/org.project60.sepa/issues/419
  $prefix = str_replace(' ', '_', $prefix);

  $tokenList = CRM_Utils_SepaTokensDeprecated::getTokenList();
  foreach ($tokenList as $token => $tokenDescription) {
    $tokens[$prefix]["$prefix.$token"] = $tokenDescription;
  }
}

function sepa_get_cids_as_array($cids): array {
  if (is_array($cids)) {
    return $cids;
  }

  // also works on scalars (int)
  $contained_ids = explode(',', $cids);
  // make $cids into an array
  $cids = [];
  foreach ($contained_ids as $cid_string) {
    $cid = (int) $cid_string;
    if ($cid) {
      $cids[] = $cid;
    }
  }

  return $cids;
}

/**
 * Fill "Last Mandate" tokens
 * (Deprecated hook)
 */
function sepa_civicrm_tokenValues(&$values, $cids, $job = NULL, $tokens = [], $context = NULL): void {
  $cids = sepa_get_cids_as_array($cids);

  $prefix = E::ts('Most Recent SEPA Mandate');
  // spaces break newletters, see https://github.com/Project60/org.project60.sepa/issues/419
  $prefix = str_replace(' ', '_', $prefix);

  // No work needed if none of the tokens is used
  if (!in_array($prefix, array_keys($tokens))) {
    return;
  }

  foreach ($cids as $cid) {
    CRM_Utils_SepaTokensDeprecated::fillLastMandateTokenValues($cid, $prefix, $values);
  }
}

function sepa_register_tokens(\Civi\Token\Event\TokenRegisterEvent $e): void {
  $prefix = 'Most_Recent_SEPA_Mandate';

  $tokenList = CRM_Utils_SepaTokens::getTokenList();
  foreach ($tokenList as $token => $tokenDescription) {
    $e->entity($prefix)->register($token, $tokenDescription);
  }
}

function sepa_evaluate_tokens(\Civi\Token\Event\TokenValueEvent $e): void {
  $prefix = 'Most_Recent_SEPA_Mandate';

  foreach ($e->getRows() as $tokenRow) {
    // @phpstan-ignore-next-line False positive caused by CiviCRM core PHPDoc bug in TokenValueEvent::getRows().
    if (!empty($tokenRow->context['contactId'])) {
      $tokenRow->format('text/html');
      CRM_Utils_SepaTokens::fillLastMandateTokenValues($tokenRow->context['contactId'], $prefix, $tokenRow);
    }
  }
}

/**
 * Will inject the SepaMandate tab.
 *
 * Implements hook_civicrm_tabset().
 */
function sepa_civicrm_tabset($tabsetName, &$tabs, $context): void {
  if ($tabsetName == 'civicrm/contact/view'
    && (empty($context['contact_id'])
      || CRM_Core_Permission::check(
        'view sepa mandates'
      ))
  ) {
    $tabs[] = [
      'id' => 'sepa',
      'url' => CRM_Utils_System::url('civicrm/sepa/tab', "reset=1&snippet=1&force=1&cid={$context['contact_id']}"),
      'title' => E::ts('SEPA Mandates'),
      'count' => CRM_Sepa_Page_MandateTab::getMandateCount($context['contact_id']),
      'icon' => 'crm-i fa-bank',
      'weight' => 20,
    ];
  }
}

function sepa_civicrm_xmlMenu(&$files): void {
  foreach (glob(__DIR__ . '/xml/Menu/*.xml') as $file) {
    $files[] = $file;
  }
}

// /**
//  * Implements hook_civicrm_entityTypes().
//  *
//  * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
//  */
// function sepa_civicrm_entityTypes(&$entityTypes) {
//   _sepa_civix_civicrm_entityTypes($entityTypes);
// }
