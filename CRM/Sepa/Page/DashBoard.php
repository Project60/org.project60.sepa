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
 * sepa dash board / group manager
 *
 * @package CiviCRM_SEPA
 *
 */

use Civi\Sepa\Lock\SepaBatchLockManager;
use CRM_Sepa_ExtensionUtil as E;

class CRM_Sepa_Page_DashBoard extends CRM_Core_Page {

  /** cache for getFormatFilename function */
  protected $_creditorID2format = array();

  function run() {
    CRM_Utils_System::setTitle(ts('CiviSEPA Dashboard', array('domain' => 'org.project60.sepa')));
    // get requested group status
    $status = CRM_Utils_Request::retrieve('status', 'String');
    if ('open' !== $status && 'closed' !== $status) {
      $status = 'open';
    }

    // add button URLs
    $this->assign("status", $status);
    $this->assign("show_closed_url", CRM_Utils_System::url('civicrm/sepa/dashboard', 'status=closed'));
    $this->assign("show_open_url", CRM_Utils_System::url('civicrm/sepa/dashboard', 'status=active'));
    $this->assign("batch_ooff", CRM_Utils_System::url('civicrm/sepa/dashboard', 'update=OOFF'));
    $this->assign("batch_recur", CRM_Utils_System::url('civicrm/sepa/dashboard', 'update=RCUR'));
    $this->assign("batch_retry", CRM_Utils_System::url('civicrm/sepa/retry', 'reset=1'));

    // check permissions
    $this->assign('can_delete', CRM_Core_Permission::check('delete sepa groups'));
    $this->assign('can_batch',  CRM_Core_Permission::check('batch sepa groups'));
    $this->assign(
      'financialacls',
      \CRM_Extension_System::singleton()
        ->getManager()
        ->getStatus('financialacls') === \CRM_Extension_Manager::STATUS_INSTALLED
    );

    if (isset($_REQUEST['update'])) {
      $this->callBatcher($_REQUEST['update']);
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/sepa/dashboard', 'status=active'));
    }

    // generate status value list
    $status_2_title = array();
    $status_list = array(
      'open' => array(
            CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Open'),
            CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Reopened')),
      'closed' => array(
            CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Closed'),
            CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Exported'),
            CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Received')));
    foreach ($status_list as $title => $values) {
      foreach ($values as $value) {
        if (empty($value)) {    // delete empty values (i.e. batch_status doesn't exist)
          unset($status_list[$title][array_search($value, $status_list[$title])]);
        } else {
          $status_2_title[$value] = $title;
        }
      }
    }
    // generate group value list
    $status2label = array();
    $status_values = array();
    $status_group_selector = array('name'=>'batch_status');
    CRM_Core_OptionValue::getValues($status_group_selector, $status_values);
    foreach ($status_values as $status_value) {
      $status2label[$status_value['value']] = $status_value['label'];
    }
    $this->assign('closed_status_id', CRM_Core_PseudoConstant::getKey('CRM_Batch_BAO_Batch', 'status_id', 'Closed'));

    // now read the details
    try {
      // API4 SepaTransactionGroup.get checks Financial ACLs for corresponding contributions.
      $sepaTransactionGroups = \Civi\Api4\SepaTransactionGroup::get(TRUE)
        ->selectRowCount()
        ->addSelect(
          'id',
          'reference',
          'sdd_file_id',
          'type',
          'collection_date',
          'latest_submission_date',
          'status_id',
          'sdd_creditor_id',
          'sepa_sdd_file.created_date',
          'SUM(contribution.total_amount) AS total',
          'COUNT(*) AS nb_contrib',
          'contribution.currency',
          'sepa_sdd_file.reference'
        )
        ->addJoin(
          'Contribution AS contribution',
          'LEFT',
          'SepaContributionGroup'
        )
        ->addJoin(
          'SepaSddFile AS sepa_sdd_file',
          'LEFT',
          ['sdd_file_id', '=', 'sepa_sdd_file.id']
        )
        ->addWhere('status_id', 'IN', $status_list[$status])
        ->addOrderBy('open' === $status ? 'latest_submission_date' : 'sepa_sdd_file.created_date')
        ->addGroupBy('id')
        ->addGroupBy('reference')
        ->addGroupBy('sdd_file_id')
        ->addGroupBy('type')
        ->addGroupBy('collection_date')
        ->addGroupBy('latest_submission_date')
        ->addGroupBy('status_id')
        ->addGroupBy('sdd_creditor_id')
        ->addGroupBy('sepa_sdd_file.created_date')
        ->addGroupBy('contribution.currency')
        ->addGroupBy('sepa_sdd_file.reference')
        ->execute();
      $groups = [];
      $now = date('Y-m-d');
      foreach ($sepaTransactionGroups as $group) {
        $group['latest_submission_date'] = date('Y-m-d', strtotime($group['latest_submission_date']));
        $group['collection_date'] = date('Y-m-d', strtotime($group['collection_date']));
        $group['collection_date_in_future'] = ($group['collection_date'] > $now) ? 1 : 0;
        $group['status'] = $status_2_title[$group['status_id']];
        $group['currency'] = $group['contribution.currency'];
        $group['file_id'] = $group['sdd_file_id'];
        $group['file_created_date'] = $group['sepa_sdd_file.created_date'];
        $group['creditor_id'] = $group['sdd_creditor_id'];
        $group['file'] = $group['sepa_sdd_file.reference'];
        $group['file'] = $this->getFormatFilename($group);
        $group['status_label'] = $status2label[$group['status_id']];
        $remaining_days = (strtotime($group['latest_submission_date']) - strtotime("now")) / (60 * 60 * 24);
        if ('closed' === $group['status']) {
          $group['submit'] = 'closed';
        }
        elseif ('OOFF' === $group['type']) {
          $group['submit'] = 'soon';
        }
        else {
          if ($remaining_days <= -1) {
            $group['submit'] = 'missed';
          }
          elseif ($remaining_days <= 1) {
            $group['submit'] = 'urgently';
          }
          elseif ($remaining_days <= 6) {
            $group['submit'] = 'soon';
          }
          else {
            $group['submit'] = 'later';
          }
        }

        $group['transaction_message'] = CRM_Sepa_BAO_SEPATransactionGroup::getCustomGroupTransactionMessage($group['id']);
        $group['transaction_note'] = CRM_Sepa_BAO_SEPATransactionGroup::getNote($group['id']);

        $groups[] = $group;
      }
      $this->assign('groups', $groups);
    }
    catch (CRM_Core_Exception $exception) {
      CRM_Core_Session::setStatus(
        E::ts(
          "Couldn't read transaction groups. Error was: %1",
          [1 => $exception->getMessage()]
        ),
        E::ts('Error'),
        'error'
      );
    }

    parent::run();
  }

  /**
   * call the batching API
   */
  function callBatcher(string $mode): void {
    if (!SepaBatchLockManager::getInstance()->acquire(0)) {
      CRM_Core_Session::setStatus(E::ts('Cannot run update, another update is in progress!'), '', 'error');

      return;
    }

    $async_batching = CRM_Sepa_Logic_Settings::getGenericSetting('sdd_async_batching');
    if ($async_batching) {
      // use the runner rather that the API (this doesn't return)
      CRM_Sepa_Logic_Queue_Update::launchUpdateRunner($mode);
    }

    if ($mode=="OOFF") {
      $result = civicrm_api3("SepaAlternativeBatching", "update", array('type' => $mode));

    } elseif ($mode=="RCUR") {
      // perform for FRST _and_ RCUR
      $result = civicrm_api3("SepaAlternativeBatching", "update", array('type' => 'FRST'));
      $result = civicrm_api3("SepaAlternativeBatching", "update", array('type' => 'RCUR'));

    } else {
      CRM_Core_Session::setStatus(sprintf(E::ts("Unknown batcher mode '%s'. No batching triggered."), $mode), E::ts('Error'), 'error');
    }
  }

  /**
   * Generate the right link wrt the correct file format
   *
   * @param $group_data array the group data
   * @return string new (full) suggested file name
   * @throws Exception
   */
  protected function getFormatFilename($group_data) {
    if (empty($group_data['file'])) {
      return '';
    }

    // get the format
    if (!empty($group_data['creditor_id'] && !isset($this->_creditorID2format[$group_data['creditor_id']]))) {
      $format = CRM_Sepa_Logic_Format::getFormatForCreditor($group_data['creditor_id']);
      $this->_creditorID2format[$group_data['creditor_id']] = $format;
    }
    $format = $this->_creditorID2format[$group_data['creditor_id']];

    if ($format) {
      return $format->getFilename($group_data['file']);
    } else {
      return $group_data['file'] . '.xml';
    }
  }
}
