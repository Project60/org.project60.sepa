<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit CUSTOMISATION EXTENSION |
| Copyright (C) 2013-2015 SYSTOPIA                       |
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

require_once 'sepacustom.civix.php';

/**
 * This hook is called by the alternativeBatching:
 *  you can set a custom collection date for a rcurring contribution.
 *  For example you can use this hook when you mandate is connected to a yearly membership from January to December.
 *  And when a new member signs up in October. You want to collect that money in october and the membership will end on 31st of December.
 *  So the next collection is in January.
 *
 * @param string $next_collection_date  the calculated collection date (format: "YYYY-MM-DD").
 * @param array  $data array with data (such as mandate_id, mandate_entity_id for contribution recur id).
 */
function sepacustom_civicrm_alter_next_collection_date(&$next_collection_date, $data) {
  // Check if this rcontribution is part of a membership.
  if (!isset($data['mandate_entity_id']) || !isset($data['mandate_creditor_id'])) {
    return;
  }
  $contribution_recur_id = $data['mandate_entity_id'];
  $creditor_id = $data['mandate_creditor_id'];

  // Fetch the possible cycle days.
  $cycle_days = \CRM_Sepa_Logic_Settings::getListSetting("cycledays", range(1, 28), $creditor_id);
  if (!is_array($cycle_days) || !count($cycle_days)) {
    return;
  }
  asort($cycle_days);
  $notice_days = \CRM_Sepa_Logic_Settings::getSetting('batching_RCUR_notice', $creditor_id);
  $now = new \DateTime();
  $now->modify('+'.$notice_days.' days');

  // Check whether this is the first contribution or not.
  $sqlContributionCount = "SELECT count(*) FROM civicrm_contribution WHERE contribution_recur_id = %1";
  $sqlContributionCountParams[1] = array($contribution_recur_id, 'Integer');
  $contributionCount = CRM_Core_DAO::singleValueQuery($sqlContributionCount, $sqlContributionCountParams);
  if ($contributionCount > 0) {
    // Only alter the collection date when this is not the first contribution.
    try {
      $membership = civicrm_api3('Membership', 'getsingle', ['contribution_recur_id' => $contribution_recur_id]);
      $membershipEndDate = new \DateTime($membership['end_date']);
      $membershipEndDate->modify('+1 days');
      if ($membershipEndDate < $now) {
        $membershipEndDate = $now;
      }
      // Move the first collection date
      while(!in_array($membershipEndDate->format('j'), $cycle_days)) {
        $membershipEndDate->modify('+1 day');
      }
      $next_collection_date = $membershipEndDate->format('Y-m-d');
    } catch (CiviCRM_API3_Exception $e) {
      // No membership found.
      // Do not alter the date.
    }
  }
}

/**
 * This hook lets you modify the parameters of a to-be-created mandate.
 *
 * As an example, we use this pattern to generate our custom mandate reference:
 *   P60-00C00000099D20150115N1
 *                            \__ counter to allow multiple mandates per contact and date
 *                   \_______\___ date
 *          \_______\____________ contact ID
 *       \_\_____________________ inteval, 00=OOFF, 04=quarterly, 02=monthly, etc.
 *   \__\________________________ identifier string
 */
function sepacustom_civicrm_create_mandate(&$mandate_parameters) {

  if (isset($mandate_parameters['reference']) && !empty($mandate_parameters['reference']))
    return;   // user defined mandate

  // load contribution
  if ($mandate_parameters['entity_table']=='civicrm_contribution') {
    $contribution = civicrm_api('Contribution', 'getsingle', array('version' => 3, 'id' => $mandate_parameters['entity_id']));
    $interval = '00';   // one-time
  } else if ($mandate_parameters['entity_table']=='civicrm_contribution_recur') {
    $contribution = civicrm_api('ContributionRecur', 'getsingle', array('version' => 3, 'id' => $mandate_parameters['entity_id']));
    if ($contribution['frequency_unit']=='month') {
      $interval = sprintf('%02d', 12/$contribution['frequency_interval']);
    } else if ($contribution['frequency_unit']=='year') {
      $interval = '01';
    } else {
      // error:
      $interval = '99';
    }
  } else {
    die("unsupported mandate");
  }

  $reference  = 'P60-';
  $reference .= $interval;
  $reference .= sprintf('C%08d', $contribution['contact_id']);
  $reference .= 'D';          // separator
  $reference .= date('Ymd');
  $reference .= 'N';          // separator
  $reference .= '%d';         // for numbers

  // try to find one that's not used yet...
  for ($n=0; $n < 10; $n++) {
    $reference_candidate = sprintf($reference, $n);
    // check if it exists
    try {
      $mandate = \Civi\Api4\SepaMandate::get(TRUE)
        ->addWhere('reference', '=', $reference_candidate)
        ->execute()
        ->single();
    }
    catch (\CRM_Core_Exception $exception) {
      // does not exist! take it!
      $mandate_parameters['reference'] = $reference_candidate;
      return;
    }
  }

  // if we get here, there are no more IDs
  die('No mandates IDs left for this id/date/type.');
}


/**
 * This hook lets defer the collection date according to your banks preferences.
 * Most banks will only accept collection days that comply with their 'bank days'
 *
 * In this implementation, we only prevent the collection day to be on weekend,
 * but -depending on your bank- you might want to include national holidays as well.
 */
function sepacustom_civicrm_defer_collection_date(&$collection_date, $creditor_id) {
  // Don't collect on the week end
  $day_of_week = date('N', strtotime($collection_date));
  if ($day_of_week > 5) {
    // this is a weekend -> skip to Monday
    $defer_days = 8 - $day_of_week;
    $collection_date = date('Y-m-d', strtotime("+$defer_days day", strtotime($collection_date)));
  }
}


/**
 * This hook lets you customize the collection message.
 *
 * You can simply put a string here, but most likely you would want to base
 * the message on the type of payment and/or the creditor.
 */
function sepacustom_civicrm_modify_txmessage(&$txmessage, $info, $creditor) {
    $txmessage = "This is a customized message.";
}


/**
 * This hook lets you customize the EndToEndId used when submitting
 *  a collection file to the bank
 *
 * The variable end2endID already contains a uniqe ID (contribution ID),
 * but you can add a custom prefix or suffix.
 *
 * If you want to create your own ID you have to make sure it's really unique for
 * each transactions, otherwise it'll be rejected by the bank.
 * It will also have to create the SAME ID every time it's called for the same transaction.
 */
function sepacustom_civicrm_modify_endtoendid(&$end2endID, $contribution, $creditor) {
  $end2endID = "PREFIX{$end2endID}SUFFIX";
}

/**
 * This hook is called by the batching algorithm:
 *  whenever a new installment has been created for a given RCUR mandate
 *  this hook is called so you can modify the resulting contribution,
 *  e.g. connect it to a membership, or copy custom fields
 *
 * be aware the newly created contribution is still 'Pending', it might NOT be
 * issued to the bank.
 *
 * @param array  $mandate_id             the CiviSEPA mandate entity ID
 * @param array  $contribution_recur_id  the recurring contribution connected to the mandate
 * @param array  $contribution_id        the newly created contribution
 *
 * @access public
 */
function sepacustom_civicrm_installment_created($mandate_id, $contribution_recur_id, $contribution_id) {
  // example: assign to membership if contact has (exactly) one...
  try {
    $contribution = civicrm_api3('Contribution', 'getsingle', [
        'id'     => $contribution_id,
        'return' => 'financial_type_id,contact_id']);
    if ($contribution['financial_type_id'] == 2) {
      // this is a membership fee (in a default system...)
      $membership_id = civicrm_api3('Membership', 'getvalue', [
          'contact_id' => $contribution['contact_id'],
          'status_id'  => ['IN' => [1, 2, 3]], // current member (in a default system)
          'return'     => 'id']);

      // if we get here, both exist and we can connect them
      civicrm_api3('MembershipPayment', 'create', [
          'membership_id'   => $membership_id,
          'contribution_id' => $contribution_id]);
    }
  } catch (Exception $ex) {
    // not a big deal, most likely there was not a single membership found in the getvalue call
  }
}








/**
 * Implementation of hook_civicrm_config
 */
function sepacustom_civicrm_config(&$config) {
  _sepacustom_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function sepacustom_civicrm_xmlMenu(&$files) {
  _sepacustom_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function sepacustom_civicrm_install() {
  return _sepacustom_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function sepacustom_civicrm_uninstall() {
  return _sepacustom_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function sepacustom_civicrm_enable() {
  return _sepacustom_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function sepacustom_civicrm_disable() {
  return _sepacustom_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function sepacustom_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _sepacustom_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function sepacustom_civicrm_managed(&$entities) {
  return _sepacustom_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_caseTypes
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 */
function sepacustom_civicrm_caseTypes(&$caseTypes) {
  _sepacustom_civix_civicrm_caseTypes($caseTypes);
}
