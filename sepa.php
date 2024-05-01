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

require_once 'sepa.civix.php';

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use \Symfony\Component\DependencyInjection\ContainerBuilder;

use CRM_Sepa_ExtensionUtil as E;

/**
 * Implements hook_civicrm_container()
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_container/
 */
function sepa_civicrm_container(ContainerBuilder $container) {
  if (class_exists('\Civi\Sepa\ContainerSpecs')) {
    $container->addCompilerPass(new \Civi\Sepa\ContainerSpecs(), PassConfig::TYPE_OPTIMIZE);
  }
}

function sepa_civicrm_pageRun( &$page ) {
  if (get_class($page) == "CRM_Contact_Page_View_Summary") {
    // mods for summary view
    if (CRM_Core_Permission::check('view sepa mandates')) {
      $page->assign('can_create_mandate', CRM_Core_Permission::check('create sepa mandates') ? '1' : '0');
      $page->assign('can_edit_mandate',   CRM_Core_Permission::check('edit sepa mandates') ? '1' : '0');
      CRM_Core_Region::instance('page-body')->add(array(
        'template' => 'CRM/Contact/Page/View/Summary.sepa.tpl'
      ));
    }

  } elseif (get_class($page) == "CRM_Contribute_Page_Tab") {
    // single contribuion view
    if (CRM_Core_Permission::check('view sepa mandates')) {
      $contribution_id = $page->getTemplate()->get_template_vars('id');
      if (empty($contribution_id) || !CRM_Sepa_BAO_SEPAMandate::getContributionMandateID($contribution_id)) {
        return; // not a SEPA contribution
      }

      if ($page->getTemplate()->get_template_vars('contribution_recur_id')) {
        // This is an installment of a recurring contribution.
        $contribution_recur_id = $page->getTemplate()->get_template_vars('contribution_recur_id');
        if (empty($contribution_recur_id)) return;

        $mandate = civicrm_api3('SepaMandate', 'getsingle', array(
          'entity_table' => 'civicrm_contribution_recur',
          'entity_id'    => $contribution_recur_id));
      }
      else {
        // this is a OOFF contribtion
        $mandate = civicrm_api3('SepaMandate', 'getsingle', array(
          'entity_table' => 'civicrm_contribution',
          'entity_id'    => $contribution_id));
      }

      // add txgroup information
      $txgroup_search = civicrm_api3('SepaContributionGroup', 'get', array(
        'contribution_id' => $contribution_id
      ));
      if (empty($txgroup_search['id'])) {
        $mandate['tx_group'] = ts('<i>None</i>', array('domain' => 'org.project60.sepa'));
      } else {
        $group = reset($txgroup_search['values']);
        if (empty($group['txgroup_id'])) {
          $mandate['tx_group'] = ts('<i>Error</i>', array('domain' => 'org.project60.sepa'));
        } else {
          $mandate['tx_group'] = civicrm_api3('SepaTransactionGroup', 'getvalue', array(
            'return' => 'reference',
            'id'     => $group['txgroup_id']));
        }
      }

      $page->assign('sepa', $mandate);
      $page->assign('can_edit_mandate',   CRM_Core_Permission::check('edit sepa mandates'));
      CRM_Core_Region::instance('page-body')->add(array(
        'template' => 'Sepa/Contribute/Form/ContributionView.tpl'
      ));
    }
  }

  elseif ( get_class($page) == "CRM_Contribute_Page_ContributionRecur") {
    // recurring contribution view
    if (CRM_Core_Permission::check('view sepa mandates')) {
      $recur = $page->getTemplate()->get_template_vars("recur");
      if (empty($recur['id'])) {
        return; // nothing to do here
      }

      // find mandate
      $mandate_id = CRM_Sepa_BAO_SEPAMandate::getRecurringContributionMandateID($recur['id']);
      if (empty($mandate_id)) {
        // this is not a SEPA recurring contribution
        return;
      }
      $mandate = civicrm_api3("SepaMandate","getsingle", ['id' => $mandate_id]);

      // load notes
      $mandate['notes'] = array();
      if ($mandate['type'] == 'RCUR') {
        $contribution_recur_id = (int) $mandate['entity_id'];
        $mandate_note_query = CRM_Core_DAO::executeQuery("SELECT note FROM civicrm_note WHERE entity_id = {$contribution_recur_id} AND entity_table = 'civicrm_contribution_recur' ORDER BY modified_date DESC;");
        while ($mandate_note_query->fetch()) {
          $mandate['notes'][] = $mandate_note_query->note;
        }
      }

      $page->assign("sepa", $mandate);
      $page->assign('can_edit_mandate',   CRM_Core_Permission::check('edit sepa mandates'));
      CRM_Core_Region::instance('page-body')->add(array(
        'template' => 'Sepa/Contribute/Page/ContributionRecur.tpl'
      ));
    }
  }
}

function sepa_civicrm_buildForm ( $formName, &$form ) {
  // restrict payment instrument use if necessary
  CRM_Sepa_Logic_PaymentInstruments::restrictPaymentInstrumentsInForm($formName, $form);
}

/**
 * Implementation of hook_civicrm_config
 */
function sepa_civicrm_config(&$config) {
  _sepa_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_install
 */
function sepa_civicrm_install() {
  $config = CRM_Core_Config::singleton();
  //create the tables
  $sqlfile = dirname(__FILE__) . '/sql/sepa.sql';
  CRM_Utils_File::sourceSQLFile($config->dsn, $sqlfile, NULL, false);

  return _sepa_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_enable
 */
function sepa_civicrm_enable() {
  return _sepa_civix_civicrm_enable();
}

function sepa_civicrm_summaryActions( &$actions, $contactID ) {
  // add "create SEPA mandate action"
  if (CRM_Core_Permission::check('create sepa mandates')) {
    $actions['sepa_contribution'] = array(
        'title'           => E::ts("Record SEPA Mandate"),
        'weight'          => 5,
        'ref'             => 'new-sepa-contribution',
        'key'             => 'sepa_contribution',
        'component'       => 'CiviContribute',
        'href'            => CRM_Utils_System::url('civicrm/sepa/createmandate', "reset=1&cid={$contactID}"),
        'permissions'     => array('access CiviContribute', 'edit contributions')
      );
  }
}

/**
 *  Support SEPA mandates in merge operations
 */
function sepa_civicrm_merge ( $type, &$data, $mainId = NULL, $otherId = NULL, $tables = NULL ) {
   switch ($type) {
    case 'relTables':
      // Offer user to merge SEPA Mandates
      $data['rel_table_sepamandate'] = array(
          'title'  => ts('SEPA Mandates', array('domain' => 'org.project60.sepa')),
          'tables' => array('civicrm_sdd_mandate'),
          'url'    => CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid=$cid&selectedChild=contribute'),  // '$cid' will be automatically replaced
      );
    break;

    case 'cidRefs':
      // this is the only field that needs to be modified
        $data['civicrm_sdd_mandate'] = array('contact_id');
    break;
  }
}

/**
 * Implements hook_civicrm_apiWrappers to prevent people from deleting
 *   contributions connected to SDD mandates.
 */
function sepa_civicrm_apiWrappers(&$wrappers, $apiRequest) {
  // add a wrapper for Contact.getlist (used e.g. for AJAX lookups)
  if ($apiRequest['entity'] == 'Contribution' && $apiRequest['action'] == 'delete') {
    $wrappers[] = new CRM_Sepa_Logic_ContributionProtector();
  }
}

/**
 * Implements hook_civicrm_links to prevent people from deleting
 *   contributions connected to SDD mandates.
 */
function sepa_civicrm_links($op, $objectName, $objectId, &$links, &$mask, &$values) {
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
function sepa_civicrm_pre($op, $objectName, $id, &$params) {
  // adjust next collection date
  if ($objectName == 'ContributionRecur' || $objectName == 'SepaMandate') {
    if ($op == 'create' || $op == 'edit') {
      if ($objectName == 'SepaMandate') {
        CRM_Sepa_Logic_NextCollectionDate::processMandatePreEdit($op, $objectName, $id, $params);
      } else {
        CRM_Sepa_Logic_NextCollectionDate::processRecurPreEdit($op, $objectName, $id, $params);
      }
    }
  }

  /**
   * prevent the user to delete a (recurring) contribution when there's a mandate attached.
   * this is only a last resort, most UI actions leading to this should've been disabled
   */
  if ($op=='delete' && ($objectName=='Contribution' || $objectName=='ContributionRecur')) {
    if ($objectName=='Contribution') {
      $error = CRM_Sepa_Logic_ContributionProtector::isProtected($id, 'civicrm_contribution');
    } else {
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
function sepa_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  if ($objectName == 'ContributionRecur' || $objectName == 'SepaMandate') {
    if ($op == 'create' || $op == 'edit') {
      if ($objectName == 'SepaMandate') {
        CRM_Sepa_Logic_NextCollectionDate::processMandatePostEdit($op, $objectName, $objectId, $objectRef);
      } else {
        CRM_Sepa_Logic_NextCollectionDate::processRecurPostEdit($op, $objectName, $objectId, $objectRef);
      }
    }
  }
}

// totten's addition
function sepa_civicrm_entityTypes(&$entityTypes) {
  // add my DAO's
  $entityTypes[] = array(
      'name' => 'SepaMandate',
      'class' => 'CRM_Sepa_DAO_SEPAMandate',
      'table' => 'civicrm_sdd_mandate',
  );
  $entityTypes[] = array(
      'name' => 'SepaCreditor',
      'class' => 'CRM_Sepa_DAO_SEPACreditor',
      'table' => 'civicrm_sdd_creditor',
  );
  $entityTypes[] = array(
      'name' => 'SepaTransactionGroup',
      'class' => 'CRM_Sepa_DAO_SEPATransactionGroup',
      'table' => 'civicrm_sdd_txgroup',
  );
  $entityTypes[] = array(
      'name' => 'SepaSddFile',
      'class' => 'CRM_Sepa_DAO_SEPASddFile',
      'table' => 'civicrm_sdd_file',
  );
  $entityTypes[] = array(
      'name' => 'SepaContributionGroup',
      'class' => 'CRM_Sepa_DAO_SEPAContributionGroup',
      'table' => 'civicrm_sdd_contribution_txgroup',
  );
  $entityTypes[] = array(
      'name' => 'SepaMandateLink',
      'class' => 'CRM_Sepa_DAO_SepaMandateLink',
      'table' => 'civicrm_sdd_entity_mandate',
  );
}

/**
* Implementation of hook_civicrm_config
*/

/**
* Implementation of hook_civicrm_navigationMenu
*/
function sepa_civicrm_navigationMenu(&$menu) {
  //add menu entry for SEPA Dashboard to Contributions menu
  $sepa_dashboard_url = 'civicrm/sepa';

  _sepa_civix_insert_navigation_menu($menu,'Contributions',array(
    'label' => ts('CiviSEPA Dashboard',array('domain' => 'org.project60.sepa')),
    'name' => 'CiviSEPA Dashboard',
    'url' => $sepa_dashboard_url,
    'permission' => 'view sepa groups',
    'operator' => NULL,
    'separator' => 2,
    'active' => 1
  ));

  //add menu entry for SEPA settings to Administer>CiviContribute menu
  $sepa_settings_url = 'civicrm/admin/setting/sepa';

  _sepa_civix_insert_navigation_menu($menu, 'Administer/CiviContribute',array (
        'label' => ts('CiviSEPA Settings',array('domain' => 'org.project60.sepa')),
        'name' => 'CiviSEPA Settings',
        'url' => $sepa_settings_url,
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'separator' => 2,
        'active' => 1
  ));

  _sepa_civix_navigationMenu($menu);
}

/**
 * Define SEPA permissions
 */
function sepa_civicrm_permission(&$permissions) {
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
function sepa_civicrm_alterAPIPermissions($entity, $action, &$params, &$permissions) {
  // TODO: add more
  $permissions['sepa_alternative_batching']['received'] = array('batch sepa groups');
  $permissions['sepa_logic']['received'] = array('batch sepa groups');
  $permissions['sepa_transaction_group']['toaccgroup'] = array('batch sepa groups');
  $permissions['sepa_mandate']['get'] = array('view sepa mandates');
}

/**
 * CiviCRM validateForm hook
 *
 * make sure, people don't create (broken) payment with SDD payment instrument, but w/o mandates
 */
function sepa_civicrm_validateForm( $formName, &$fields, &$files, &$form, &$errors ) {
  if ($formName == 'CRM_Contribute_Form_Contribution') {
    // we'll just focus on the payment_instrument_id
    if (empty($fields['payment_instrument_id'])) return;

    // find the contribution id
    $contribution_id = $form->getVar('_id');
    if (empty($contribution_id)) return;

    // if this contribution has no mandate, it should not have the classic sepa PIs
    $mandate_id = CRM_Sepa_BAO_SEPAMandate::getContributionMandateID($contribution_id);
    if (!$mandate_id) {
      $payment_instruments = CRM_Sepa_Logic_PaymentInstruments::getClassicSepaPaymentInstruments();
      if (isset($payment_instruments[$fields['payment_instrument_id']])) {
        $errors['payment_instrument_id'] = E::ts("This contribution has no mandate and cannot simply be changed to a SEPA payment instrument.");
      }
    } else {
      // TODO: restrict to the PIs the creditor allows?
    }
  }
}

/**
 * Insert "Last Mandate" tokens
 */
function sepa_civicrm_tokens(&$tokens) {
  $prefix = ts("Most Recent SEPA Mandate", array('domain' => 'org.project60.sepa'));
  $prefix = str_replace(' ', '_', $prefix); // spaces break newletters, see https://github.com/Project60/org.project60.sepa/issues/419
  $tokens[$prefix] = array(
    "$prefix.reference"               => ts('Reference', array('domain' => 'org.project60.sepa')),
    "$prefix.source"                  => ts('Source', array('domain' => 'org.project60.sepa')),
    "$prefix.type"                    => ts('Type', array('domain' => 'org.project60.sepa')),
    "$prefix.status"                  => ts('Status', array('domain' => 'org.project60.sepa')),
    "$prefix.date"                    => ts('Signature Date (raw)', array('domain' => 'org.project60.sepa')),
    "$prefix.date_text"               => ts('Signature Date', array('domain' => 'org.project60.sepa')),
    "$prefix.account_holder"          => ts('Account Holder', array('domain' => 'org.project60.sepa')),
    "$prefix.iban"                    => ts('IBAN', array('domain' => 'org.project60.sepa')),
    "$prefix.iban_anonymised"         => ts('IBAN (anonymised)', array('domain' => 'org.project60.sepa')),
    "$prefix.bic"                     => ts('BIC', array('domain' => 'org.project60.sepa')),
    "$prefix.amount"                  => ts('Amount (raw)', array('domain' => 'org.project60.sepa')),
    "$prefix.amount_text"             => ts('Amount', array('domain' => 'org.project60.sepa')),
    "$prefix.currency"                => ts('Currency', array('domain' => 'org.project60.sepa')),
    "$prefix.first_collection"        => ts('First Collection Date (raw)', array('domain' => 'org.project60.sepa')),
    "$prefix.first_collection_text"   => ts('First Collection Date', array('domain' => 'org.project60.sepa')),
    "$prefix.cycle_day"               => ts('Cycle Day', array('domain' => 'org.project60.sepa')),
    "$prefix.frequency_interval"      => ts('Interval Multiplier', array('domain' => 'org.project60.sepa')),
    "$prefix.frequency_unit"          => ts('Interval Unit', array('domain' => 'org.project60.sepa')),
    "$prefix.frequency"               => ts('Interval', array('domain' => 'org.project60.sepa')),
  );
}

/**
 * Fill "Last Mandate" tokens
 */
function sepa_civicrm_tokenValues(&$values, $cids, $job = null, $tokens = array(), $context = null) {
  // this could be only one ID, or (potentially) even a comma separated list of IDs
  if (!is_array($cids)) {
    $contained_ids = explode(',', $cids);  // also works on scalars (int)
    $cids = array(); // make $cids into an array
    foreach ($contained_ids as $cid_string) {
      $cid = (int) $cid_string;
      if ($cid) {
        $cids[] = $cid;
      }
    }
  }

  // make sure there are cids, because otherwise we'd generate invalid SQL (see https://github.com/Project60/org.project60.sepa/issues/399)
  if (empty($cids) || !is_array($cids)) return;

  try { // make sure this doesn't cause any troubles
    $prefix = ts("Most Recent SEPA Mandate", array('domain' => 'org.project60.sepa'));
    $prefix = str_replace(' ', '_', $prefix); // spaces break newletters, see https://github.com/Project60/org.project60.sepa/issues/419

    // No work needed if none of the tokens is used
    if (!in_array($prefix, array_keys($tokens))) return;

    // FIND most recent SEPA Mandates (per contact)
    $contact_id_list = implode(',', $cids);
    $query = "SELECT contact_id, MAX(id) AS mandate_id FROM civicrm_sdd_mandate WHERE contact_id IN ($contact_id_list) GROUP BY contact_id;";
    $result = CRM_Core_DAO::executeQuery($query);
    while ($result->fetch()) {
      // load the mandate
      $mandate = civicrm_api3('SepaMandate', 'getsingle', array('id' => $result->mandate_id));

      // make sure there is an array
      if (!isset($values[$result->contact_id])) {
        $values[$result->contact_id] = array();
      }

      // copy the mandate values
      $values[$result->contact_id]["$prefix.reference"]       = $mandate['reference'];
      $values[$result->contact_id]["$prefix.source"]          = $mandate['source'];
      $values[$result->contact_id]["$prefix.type"]            = $mandate['type'];
      $values[$result->contact_id]["$prefix.status"]          = $mandate['status'];
      $values[$result->contact_id]["$prefix.date"]            = $mandate['date'];
      $values[$result->contact_id]["$prefix.account_holder"]  = $mandate['account_holder'];
      $values[$result->contact_id]["$prefix.iban"]            = $mandate['iban'];
      $values[$result->contact_id]["$prefix.iban_anonymised"] = CRM_Sepa_Logic_Verification::anonymiseIBAN($mandate['iban']);
      $values[$result->contact_id]["$prefix.bic"]             = $mandate['bic'];

      // load and copy the contribution information
      if ($mandate['entity_table'] == 'civicrm_contribution') {
        $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $mandate['entity_id']));
        $values[$result->contact_id]["$prefix.amount"]           = $contribution['total_amount'];
        $values[$result->contact_id]["$prefix.currency"]         = $contribution['currency'];
        $values[$result->contact_id]["$prefix.amount_text"]      = CRM_Utils_Money::format($contribution['total_amount'], $contribution['currency']);
        $values[$result->contact_id]["$prefix.first_collection"] = $contribution['receive_date'];

      } elseif ($mandate['entity_table'] == 'civicrm_contribution_recur') {
        $rcontribution = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $mandate['entity_id']));
        $values[$result->contact_id]["$prefix.amount"]             = $rcontribution['amount'];
        $values[$result->contact_id]["$prefix.currency"]           = $rcontribution['currency'];
        $values[$result->contact_id]["$prefix.amount_text"]        = CRM_Utils_Money::format($rcontribution['amount'], $rcontribution['currency']);
        $values[$result->contact_id]["$prefix.cycle_day"]          = $rcontribution['cycle_day'];
        $values[$result->contact_id]["$prefix.frequency_interval"] = $rcontribution['frequency_interval'];
        $values[$result->contact_id]["$prefix.frequency_unit"]     = $rcontribution['frequency_unit'];
        $values[$result->contact_id]["$prefix.frequency"]          = CRM_Utils_SepaOptionGroupTools::getFrequencyText($rcontribution['frequency_interval'], $rcontribution['frequency_unit'], true);

        // first collection date
        if (empty($mandate['first_contribution_id'])) {
          // calculate
          $calculator = new CRM_Sepa_Logic_NextCollectionDate($mandate['creditor_id']);
          $values[$result->contact_id]["$prefix.first_collection"] = $calculator->calculateNextCollectionDate($mandate['entity_id']);

        } else {
          // use date of first contribution
          $fcontribution = civicrm_api3('Contribution', 'getsingle', array('id' => $mandate['first_contribution_id']));
          $values[$result->contact_id]["$prefix.first_collection"] = $fcontribution['receive_date'];
        }
      }

      // format dates
      if (!empty($values[$result->contact_id]["$prefix.first_collection"])) {
        $values[$result->contact_id]["$prefix.first_collection_text"] = CRM_Utils_Date::customFormat($values[$result->contact_id]["$prefix.first_collection"]);
      }
      if (!empty($values[$result->contact_id]["$prefix.date"])) {
        $values[$result->contact_id]["$prefix.date_text"] = CRM_Utils_Date::customFormat($values[$result->contact_id]["$prefix.date"]);
      }
    }

  } catch (Exception $e) {
    // probably just a minor issue, see SEPA-461
  }
}

/**
 * Implements hook_civicrm_tabset()
 *
 * Will inject the SepaMandate tab
 */
function sepa_civicrm_tabset($tabsetName, &$tabs, $context) {
  if ($tabsetName == 'civicrm/contact/view' &&
    (empty($context['contact_id']) || CRM_Core_Permission::check('view sepa mandates'))
  ) {
    $tabs[] = [
      'id'     => 'sepa',
      'url'    => CRM_Utils_System::url('civicrm/sepa/tab', "reset=1&snippet=1&force=1&cid={$context['contact_id']}"),
      'title'  => E::ts('SEPA Mandates'),
      'count'  => CRM_Sepa_Page_MandateTab::getMandateCount($context['contact_id']),
      'icon'   => 'crm-i fa-bank',
      'weight' => 20,
    ];
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
