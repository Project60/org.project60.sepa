<?php

require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_DashBoard extends CRM_Core_Page {

  function run() {
    // add button URLs
    $this->assign("status", $_REQUEST['status']);
    $this->assign("show_closed_url", CRM_Utils_System::url('civicrm/sepa/dashbord', 'status=closed'));
    $this->assign("show_open_url", CRM_Utils_System::url('civicrm/sepa/dashbord', 'status=active'));
    $this->assign("batch_ooff", CRM_Utils_System::url('civicrm/sepa/dashbord', 'update=OOFF'));
    $this->assign("batch_recur", CRM_Utils_System::url('civicrm/sepa/dashbord', 'update=RCUR'));

    if ($_REQUEST['update']) {
      $this->callBatcher($_REQUEST['update']);
    }

    CRM_Core_Resources::singleton()
    ->addScriptFile('civicrm', 'packages/backbone/underscore.js', 110, 'html-header', FALSE);

    $r = civicrm_api("SepaTransactionGroup","getdetail",array("version"=>3,"sequential"=>1,
    'options' => array(
      'sort' => 'created_date DESC',
      'limit' => 1,
      ),
    ));
    $this->assign("groups",$r["values"]);
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

    } else {
      CRM_Core_Session::setStatus(sprintf(ts("Unknown batcher mode '%s'. No batching triggered."), $mode), ts('Error'), 'error');
    }
  }
}
