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

    // look up status value
    if ($status=='open') {
      $status_id = CRM_Core_OptionGroup::getValue('contribution_status', 'Pending', 'name');
    } else {
      $status_id = CRM_Core_OptionGroup::getValue('contribution_status', 'Completed', 'name');
    }

    $result = civicrm_api("SepaTransactionGroup", "getdetail", array(
        "version"     => 3, 
        "sequential"  => 1, 
        "status_id"   => $status_id,
        ));
    if (isset($result['is_error']) && $result['is_error']) {
      CRM_Core_Session::setStatus(sprintf(ts("Couldn't read transaction groups. Error was: '%s'"), $result['error_message']), ts('Error'), 'error');
    } else {
      $groups = array();
      $status_list = array(1 => 'closed', 2=>'open');
      foreach ($result["values"] as $id => $group) {
        // 'beautify'
        $group['latest_submission_date'] = date('Y-m-d', strtotime($group['latest_submission_date']));
        $group['collection_date'] = date('Y-m-d', strtotime($group['collection_date']));
        $group['status'] = $status_list[$group['status_id']];
        $remaining_days = (strtotime($group['latest_submission_date']) - strtotime("now")) / (60*60*24);
        if ($group['status']=='closed') {
          $group['submit'] = 'closed';
        } else {
          if ($remaining_days < 3) {
            $group['submit'] = 'urgently';
          } else {
            $group['submit'] = 'soon';
          }
        }

        array_push($groups, $group);
      }
      $this->assign("groups", $groups);
    }


    // CRM_Core_Resources::singleton()
    // ->addScriptFile('civicrm', 'packages/backbone/underscore.js', 110, 'html-header', FALSE);

    // $r = civicrm_api("SepaTransactionGroup","getdetail",array("version"=>3,"sequential"=>1,
    // 'options' => array(
    //   'sort' => 'created_date DESC',
    //   'limit' => 1,
    //   ),
    // ));
    // $this->assign("groups",$r["values"]);
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
