<?php

require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_SepaMandate extends CRM_Core_Page {

  function run() {
    $r = civicrm_api("SepaSddFile","get",array("version"=>3,"sequential"=>1,"status_id"=>1,"api.SepaTransactionGroup.get"=>array("sepa_file_id"=>'$values.id')));
    $this->assign("files",$r["values"]);
    parent::run();
  }

  function getTemplateFileName() {
    return "Sepa/Page/DashBoard.tpl";
}
}
