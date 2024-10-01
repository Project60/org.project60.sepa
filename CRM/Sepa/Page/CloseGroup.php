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

use CRM_Sepa_ExtensionUtil as E;

class CRM_Sepa_Page_CloseGroup extends CRM_Core_Page {

  function run() {
    CRM_Utils_System::setTitle(E::ts('Close SEPA Group'));
    $group_id = (int) CRM_Utils_Request::retrieve('group_id', 'Integer');
    $status = CRM_Utils_Request::retrieve('status', 'String');
    if (isset($group_id)) {
      if (isset($status) && ('missed' === $status || 'invalid' === $status || 'closed' === $status)) {
        $this->assign('status', $status);
      }
      else {
        $status = '';
      }

      $group_id = (int) CRM_Utils_Request::retrieve('group_id', 'Integer');
      $this->assign('txgid', $group_id);

      // LOAD/CREATE THE TXFILE
      try {
        $group = \Civi\Api4\SepaTransactionGroup::get(TRUE)
          ->addWhere('id', '=', $group_id)
          ->execute()
          ->single();

        $this->assign('txgroup', $group);

        // check whether this is a group created by a test creditor
        $creditor = civicrm_api('SepaCreditor', 'getsingle', [
          'version' => 3,
          'id' => $group['sdd_creditor_id'],
        ]);
        if (isset($creditor['is_error']) && $creditor['is_error']) {
          CRM_Core_Session::setStatus(
            E::ts('Cannot load creditor.') . '<br />'
            . E::ts('Error was: %1', [1 => $creditor['error_message']]),
            E::ts('Error'),
            'error'
          );
        }
        else {
          // check for test group
          $isTestGroup = isset($creditor['category']) && ('TEST' === $creditor['category']);
          $this->assign('is_test_group', $isTestGroup);

          // check if this is allowed
          $no_draftxml = CRM_Sepa_Logic_Settings::getGenericSetting('sdd_no_draft_xml');
          $this->assign('allow_xml', !$no_draftxml);

          if ('' === $status) {
            // first adjust group's collection date if requested
            $adjust = CRM_Utils_Request::retrieve('adjust', 'String');
            if (!empty($adjust)) {
              $result = CRM_Sepa_BAO_SEPATransactionGroup::adjustCollectionDate($group_id, $adjust);
              if (is_string($result)) {
                // that's an error -> stop here!
                // TODO: Do not use die().
                die($result);
              }
              else {
                // that went well, so result should be the update group data
                $group = $result;
              }
            }

            // delete old txfile
            if (!empty($group['sdd_file_id'])) {
              $result = civicrm_api('SepaSddFile', 'delete', [
                'id' => $group['sdd_file_id'],
                'version' => 3,
              ]);
              if (isset($result['is_error']) && $result['is_error']) {
                CRM_Core_Session::setStatus(
                  E::ts('Cannot delete file #%1', [1 => $group['sdd_file_id']]) . '<br />'
                  . E::ts('Error was: %1', [1 => $result['error_message']]),
                  E::ts('Error'),
                  'error'
                );
              }
            }

            $this->createDownloadLink($group_id);
          }

          if ('closed' === $status && !$isTestGroup) {
            // CLOSE THE GROUP:
            $async_batch = CRM_Sepa_Logic_Settings::getGenericSetting('sdd_async_batching');
            if ($async_batch) {
              // call the closing runner
              $skip_closed = CRM_Sepa_Logic_Settings::getGenericSetting('sdd_skip_closed');
              if ($skip_closed) {
                $target_contribution_status = (int) CRM_Core_PseudoConstant::getKey(
                  'CRM_Contribute_BAO_Contribution',
                  'contribution_status_id',
                  'Completed'
                );
                $target_group_status = (int) CRM_Core_PseudoConstant::getKey(
                  'CRM_Batch_BAO_Batch',
                  'status_id',
                  'Received'
                );
              }
              else {
                $target_contribution_status = CRM_Sepa_Logic_Settings::contributionInProgressStatusId();
                $target_group_status = (int) CRM_Core_PseudoConstant::getKey(
                  'CRM_Batch_BAO_Batch',
                  'status_id',
                  'Closed'
                );
              }
              // this call doesn't return (redirect to runner)
              CRM_Sepa_Logic_Queue_Close::launchCloseRunner([$group_id], $target_group_status, $target_contribution_status);
            }

            $result = civicrm_api('SepaAlternativeBatching', 'close', [
              'version' => 3,
              'txgroup_id' => $group_id,
            ]);
            if ($result['is_error']) {
              CRM_Core_Session::setStatus(
                E::ts('Cannot close group #%1', [1 => $group_id]) . '<br />'
                . E::ts('Error was: %1', [1 => $result['error_message']]),
                E::ts('Error'),
                'error'
              );
            }
            $this->createDownloadLink($group_id);
          }
        }
      }
      catch (Exception $exception) {
        CRM_Core_Session::setStatus(
          E::ts('Cannot load group #%1', [1 => $group_id]) . '<br />'
          . E::ts('Error was: %1', [1 => $group['error_message']]),
          E::ts('Error'),
          'error'
        );
      }
    }

    parent::run();
  }

  /**
   * generate an XML download link and assign to the template
   */
  protected function createDownloadLink($group_id) {
    $xmlfile = civicrm_api(
      'SepaAlternativeBatching',
      'createxml',
      ['txgroup_id' => $group_id, 'override' => TRUE, 'version' => 3]
    );
    if (isset($xmlfile['is_error']) && $xmlfile['is_error']) {
      CRM_Core_Session::setStatus(
        E::ts('Cannot load for group #%1', [1 => $group_id]) . '<br />'
        . E::ts('Error was: %1', [1 => $xmlfile['error_message']]),
        E::ts('Error'),
        'error'
      );
    }
    else {
      $file_id = $xmlfile['id'];
      $this->assign('file_link', CRM_Utils_System::url('civicrm/sepa/xml', "id=$file_id"));
      $this->assign('file_name', $xmlfile['filename']);
    }
  }

}
