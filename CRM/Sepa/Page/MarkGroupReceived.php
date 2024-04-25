<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2024 SYSTOPIA                            |
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

/**
 * Mark a sepa group as 'received' via a runner
 * @see https://github.com/Project60/org.project60.sepa/issues/655
 *
 * @package CiviCRM_SEPA
 *
 */

require_once 'CRM/Core/Page.php';

use CRM_Sepa_ExtensionUtil as E;

class CRM_Sepa_Page_MarkGroupReceived extends CRM_Core_Page {

  function run() {
    CRM_Utils_System::setTitle(E::ts('Mark SEPA group received'));

    // get the group ID
    $group_id = (int) $_REQUEST['group_id'] ?? 0;
    if (!$group_id) {
      throw new CRM_Core_Exception(E::ts("No group_id given!"));
    }

    // get the group
    $group = civicrm_api3('SepaTransactionGroup', 'getsingle', ['id' => $group_id]);
    $this->assign('txgroup', $group);

    // check whether this is a group created by a test creditor
    $creditor = civicrm_api3('SepaCreditor', 'getsingle', ['id'=> $group['sdd_creditor_id']]);
    $is_test_creditor = isset($creditor['category']) && ($creditor['category'] == "TEST");
    if ($is_test_creditor) {
      throw new Exception(E::ts("Cannot mark TEST groups as received."));
    }

    // run the 'mark received' process
    $async_batch = CRM_Sepa_Logic_Settings::getGenericSetting('sdd_async_batching');
    if ($async_batch) {
      // execute through runner:
      $target_contribution_status = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');
      $target_group_status = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Received');
      CRM_Sepa_Logic_Queue_Close::launchCloseRunner([$group_id], $target_group_status, $target_contribution_status);

    } else {
      // execute via API
      try {
        civicrm_api3('SepaAlternativeBatching', 'received', ['txgroup_id' => $group_id]);
      } catch (Exception $exception) {
        $error_message = $exception->getMessage();
        CRM_Core_Session::setStatus(E::ts("Couldn't close SDD group #%1.<br/>Error was: %2",
                                          [1 => $group_id, 2 => $error_message]));
      }
    }

    // go back to the dashboard
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/sepa/dashboard', 'status=closed'));
  }
}
