<?php

require_once 'CRM/Core/Page.php';

class CRM_Sepa_Page_DashBoard extends CRM_Core_Page {

  function run() {
    $r = civicrm_api("SepaTransactionGroup","getdetail",array("version"=>3,"sequential"=>1,
      "api.SepaTransactionGroup.getdetail"=>array("file_id"=>'$values.id'
),
  'options' => array(
      'sort' => 'created_date DESC',
      'limit' => 1,
    ),

));
    $this->assign("files",$r["values"]);
    parent::run();
  }

  function getTemplateFileName() {
    return "Sepa/Page/DashBoard.tpl";
}
}
