<?php

require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_DashBoard extends CRM_Core_Page {

  function run() {
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
    $this->assign("show_closed_url", CRM_Utils_System::url('civicrm/sepa/dashbord', 'status=closed'));
    $this->assign("show_open_url", CRM_Utils_System::url('civicrm/sepa/dashbord', 'status=active'));
    $this->assign("batch_ooff", CRM_Utils_System::url('civicrm/sepa/dashbord', 'update=OOFF'));
    $this->assign("batch_recur", CRM_Utils_System::url('civicrm/sepa/dashbord', 'update=RCUR'));

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

    $result = civicrm_api("SepaTransactionGroup", "getdetail", array(
        "version"     => 3, 
        "sequential"  => 1, 
        "status_ids"  => implode(',', $status_list[$status]),
        ));
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Session::setStatus(sprintf(ts("Couldn't read transaction groups. Error was: '%s'"), $result['error_message']), ts('Error'), 'error');
    } else {
      $groups = array();
      foreach ($result["values"] as $id => $group) {
        // 'beautify'
        $group['latest_submission_date'] = date('Y-m-d', strtotime($group['latest_submission_date']));
        $group['collection_date'] = date('Y-m-d', strtotime($group['collection_date']));
        
        $group['status'] = $status_2_title[$group['status_id']];
        $remaining_days = (strtotime($group['latest_submission_date']) - strtotime("now")) / (60*60*24);
        if ($group['status']=='closed') {
          $group['submit'] = 'closed';
        } else {
          if ($remaining_days <= 1) {
            $group['submit'] = 'urgently';
          } elseif ($remaining_days <= 2) {
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
      CRM_Core_Session::setStatus(sprintf(ts("Unknown batcher mode '%s'. No batching triggered."), $mode), ts('Error'), 'error');
    }
  }
}
