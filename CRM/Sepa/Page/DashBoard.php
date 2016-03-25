<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 SYSTOPIA                       |
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

require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_DashBoard extends CRM_Core_Page {

  function run() {
    CRM_Utils_System::setTitle(ts('CiviSEPA Dashboard', array('domain' => 'org.project60.sepa')));
    // get requested group status
    if (isset($_REQUEST['status'])) {
      if ($_REQUEST['status'] != 'open' && $_REQUEST['status'] != 'closed') {
        $status = 'open';
      } else {
        $status = $_REQUEST['status'];
      }
    } else {
      $status = 'open';
    }

    // add button URLs
    $this->assign("status", $status);
    $this->assign("show_closed_url", CRM_Utils_System::url('civicrm/sepa/dashboard', 'status=closed'));
    $this->assign("show_open_url", CRM_Utils_System::url('civicrm/sepa/dashboard', 'status=active'));
    $this->assign("batch_ooff", CRM_Utils_System::url('civicrm/sepa/dashboard', 'update=OOFF'));
    $this->assign("batch_recur", CRM_Utils_System::url('civicrm/sepa/dashboard', 'update=RCUR'));

    // check permissions
    $this->assign('can_delete', CRM_Core_Permission::check('administer CiviCRM'));

    if (isset($_REQUEST['update'])) {
      $this->callBatcher($_REQUEST['update']);
    }

    // generate status value list
    $status_2_title = array();
    $status_list = array(
      'open' => array(  
            CRM_Core_OptionGroup::getValue('batch_status', 'Open', 'name'),
            CRM_Core_OptionGroup::getValue('batch_status', 'Reopened', 'name')), 
      'closed' => array(
            CRM_Core_OptionGroup::getValue('batch_status', 'Closed', 'name'),
            CRM_Core_OptionGroup::getValue('batch_status', 'Exported', 'name'), 
            CRM_Core_OptionGroup::getValue('batch_status', 'Received', 'name')));
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
    $this->assign('closed_status_id', CRM_Core_OptionGroup::getValue('batch_status', 'Closed', 'name'));

    // now read the details
    $result = civicrm_api("SepaTransactionGroup", "getdetail", array(
        "version"       => 3, 
        "sequential"    => 1, 
        "status_ids"    => implode(',', $status_list[$status]),
        "order_by"      => (($status=='open')?'latest_submission_date':'file.created_date'),
        ));
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Session::setStatus(sprintf(ts("Couldn't read transaction groups. Error was: '%s'", array('domain' => 'org.project60.sepa')), $result['error_message']), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
    } else {
      $groups = array();
      foreach ($result["values"] as $id => $group) {
        // 'beautify'
        $group['latest_submission_date'] = date('Y-m-d', strtotime($group['latest_submission_date']));
        $group['collection_date'] = date('Y-m-d', strtotime($group['collection_date']));
        $group['status'] = $status_2_title[$group['status_id']];
        $group['status_label'] = $status2label[$group['status_id']];
        $remaining_days = (strtotime($group['latest_submission_date']) - strtotime("now")) / (60*60*24);
        if ($group['status']=='closed') {
          $group['submit'] = 'closed';
        } else {
          if ($remaining_days <= -1) {
            $group['submit'] = 'missed';
          } elseif ($remaining_days <= 1) {
            $group['submit'] = 'urgently';
          } elseif ($remaining_days <= 6) {
            $group['submit'] = 'soon';
          } else {
            $group['submit'] = 'later';
          }
        }

        array_push($groups, $group);
      }
      $this->assign("groups", $groups);
    }

    parent::run();
  }

  function getTemplateFileName() {
    return "CRM/Sepa/Page/DashBoard.tpl";
  }

  function callBatcher($mode) {
    if ($mode=="OOFF") {
      $parameters = array(
            "version"           => 3,
            "type"              => $mode,
          );
      $result = civicrm_api("SepaAlternativeBatching", "update", $parameters);

    } elseif ($mode=="RCUR") {
      // perform for FRST _and_ RCUR
      $parameters = array(
            "version"           => 3,
            "type"              => 'FRST',
          );
      $result = civicrm_api("SepaAlternativeBatching", "update", $parameters);
      $parameters = array(
            "version"           => 3,
            "type"              => 'RCUR',
          );
      $result = civicrm_api("SepaAlternativeBatching", "update", $parameters);

    } else {
      CRM_Core_Session::setStatus(sprintf(ts("Unknown batcher mode '%s'. No batching triggered.", array('domain' => 'org.project60.sepa')), $mode), ts('Error', array('domain' => 'org.project60.sepa')), 'error');
    }
  }
}
