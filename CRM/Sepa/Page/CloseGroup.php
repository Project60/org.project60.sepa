<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2018 SYSTOPIA                       |
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
 * Close a sepa group
 *
 * @package CiviCRM_SEPA
 *
 */

require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_CloseGroup extends CRM_Core_Page {

  function run() {
    CRM_Utils_System::setTitle(ts('Close SEPA Group', array('domain' => 'org.project60.sepa')));
    if (isset($_REQUEST['group_id'])) {
        if (isset($_REQUEST['status']) && ($_REQUEST['status'] == "missed" || $_REQUEST['status'] == "invalid" || $_REQUEST['status'] == "closed")) {
          $this->assign('status', $_REQUEST['status']);
        }else{
          $_REQUEST['status'] = "";
        }

        $group_id = (int) $_REQUEST['group_id'];
        $this->assign('txgid', $group_id);

        // LOAD/CREATE THE TXFILE
        $group = civicrm_api('SepaTransactionGroup', 'getsingle', array('version'=>3, 'id'=>$group_id));
        if (isset($group['is_error']) && $group['is_error']) {
          CRM_Core_Session::setStatus("Cannot load group #$group_id.<br/>Error was: ".$group['error_message'], ts('Error', array('domain' => 'org.project60.sepa')), 'error');
        } else {
          $this->assign('txgroup', $group);

          // check whether this is a group created by a test creditor
          $creditor = civicrm_api('SepaCreditor', 'getsingle', array('version'=>3, 'id'=>$group['sdd_creditor_id']));
          if (isset($creditor['is_error']) && $creditor['is_error']) {
            CRM_Core_Session::setStatus("Cannot load creditor.<br/>Error was: ".$creditor['error_message'], ts('Error', array('domain' => 'org.project60.sepa')), 'error');
          }else{
              // check for test group
              $isTestGroup = isset($creditor['category']) && ($creditor['category'] == "TEST");
              $this->assign('is_test_group', $isTestGroup);

              // check if this is allowed
              $no_draftxml = CRM_Sepa_Logic_Settings::getGenericSetting('sdd_no_draft_xml');
              $this->assign('allow_xml', !$no_draftxml);

              if ($_REQUEST['status'] == "") {
                // first adjust group's collection date if requested
                if (!empty($_REQUEST['adjust'])) {
                  $result = CRM_Sepa_BAO_SEPATransactionGroup::adjustCollectionDate($group_id, $_REQUEST['adjust']);
                  if (is_string($result)) {
                    // that's an error -> stop here!
                    die($result);
                  } else {
                    // that went well, so result should be the update group data
                    $group = $result;
                  }
                }


                // delete old txfile
                if (!empty($group['sdd_file_id'])) {
                  $result = civicrm_api('SepaSddFile', 'delete', array('id'=>$group['sdd_file_id'], 'version'=>3));
                  if (isset($result['is_error']) && $result['is_error']) {
                    CRM_Core_Session::setStatus("Cannot delete file #".$group['sdd_file_id'].".<br/>Error was: ".$result['error_message'], ts('Error', array('domain' => 'org.project60.sepa')), 'error');
                  }
                }

                $this->createDownloadLink($group_id);
              }

              if ($_REQUEST['status'] == "closed" && !$isTestGroup) {
                // CLOSE THE GROUP:
                $async_batch = CRM_Sepa_Logic_Settings::getGenericSetting('sdd_async_batching');
                if ($async_batch) {
                  // call the closing runner
                  $skip_closed = CRM_Sepa_Logic_Settings::getGenericSetting('sdd_skip_closed');
                  if ($skip_closed) {
                    $target_contribution_status = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');
                    $target_group_status = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Received');
                  } else {
                    $target_contribution_status =  CRM_Sepa_Logic_Settings::contributionInProgressStatusId();
                    $target_group_status = (int) CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Closed');
                  }
                  // this call doesn't return (redirect to runner)
                  CRM_Sepa_Logic_Queue_Close::launchCloseRunner(array($group_id), $target_group_status, $target_contribution_status);
                }

                $result = civicrm_api('SepaAlternativeBatching', 'close', array('version'=>3, 'txgroup_id'=>$group_id));
                if ($result['is_error']) {
                  CRM_Core_Session::setStatus("Cannot close group #$group_id.<br/>Error was: ".$result['error_message'], ts('Error', array('domain' => 'org.project60.sepa')), 'error');
                }
                $this->createDownloadLink($group_id);
              }
          }

        }
    }

    parent::run();
  }

  /**
   * generate an XML download link and assign to the template
   */
  protected function createDownloadLink($group_id) {
    $xmlfile = civicrm_api('SepaAlternativeBatching', 'createxml', array('txgroup_id'=>$group_id, 'override'=>True, 'version'=>3));
    if (isset($xmlfile['is_error']) && $xmlfile['is_error']) {
      CRM_Core_Session::setStatus("Cannot load for group #".$group_id.".<br/>Error was: ".$xmlfile['error_message'], ts('Error', array('domain' => 'org.project60.sepa')), 'error');
    }else{
      $file_id = $xmlfile['id'];
      $this->assign('file_link', CRM_Utils_System::url('civicrm/sepa/xml', "id=$file_id"));
      $this->assign('file_name', $xmlfile['filename']);
    }
  }
}
